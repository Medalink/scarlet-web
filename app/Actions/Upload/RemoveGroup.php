<?php

namespace App\Actions\Upload;

use App\Actions\Feats\ForgetClassStandings;
use App\Actions\Feats\QueueClassStandings;
use App\Actions\Ingest\AddonClaimSiblings;
use App\Actions\Ingest\DeleteFactRows;
use App\Actions\Ingest\InvalidateReconciles;
use App\Jobs\ReconcileCharacterClaims;
use App\Jobs\RefreshRollups;
use App\Models\Character;
use App\Models\Install;
use App\Models\Part;
use App\Support\DecompressionLimit;
use App\Support\FactTable;
use App\Support\GroupRefusals;
use App\Support\RefreshWindow;
use App\Support\SessionRows;
use App\Support\SharingGroups;
use Illuminate\Support\Facades\DB;

class RemoveGroup
{
    public function __construct(
        private readonly SharingGroups $groups,
        private readonly GroupRefusals $refusals,
        private readonly DeleteFactRows $deleteRows,
        private readonly AddonClaimSiblings $siblings,
        private readonly InvalidateReconciles $reconciles,
        private readonly ForgetClassStandings $standings,
        private readonly QueueClassStandings $queueStandings,
    ) {}

    /**
     * Remove one sharing group from the server for every character of the install's owner: its parts and
     * decoded rows go, the sessions forget the group and null its counters (and the columns other tables keep for it,
     * FactTable::groupColumns), the rollups are refreshed over the time
     * the characters played, and the group is refused until the app puts it back (PUT /upload/groups/{key}). The addon
     * claims of the characters and of the characters linked to them are marked as not reconciled in the same transaction
     * and reconciled again, since the rows they were checked against may be gone. The characters' stored class
     * standings and bands go in the same transaction and are stored again from the rows that are left.
     *
     * @return array{group: string, refused: bool, parts_removed: int, rows_removed: int}
     */
    public function handle(Install $install, string $group): array
    {
        $this->groups->requireSwitchable($group);

        /** @var list<int> $characterIds */
        $characterIds = Character::query()->ownedBy($install)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $window = RefreshWindow::ofCharacters($characterIds);

        [$parts, $rows] = DB::transaction(function () use ($install, $group, $characterIds): array {
            DecompressionLimit::lift();
            // Wait for a running decode of these characters first; both take the row lock before the fact rows.
            Character::query()->whereIn('id', $characterIds)->lockedInOrder()->pluck('id');
            $this->reconciles->handle($characterIds);
            $parts = Part::query()->whereIn('character_id', $characterIds)->where('group_key', $group)->delete();
            $rows = $this->deleteRows->handle($characterIds, $group);
            $this->standings->handle($characterIds);
            $this->forgetInSessions($characterIds, $group);
            $this->refusals->add($install, $group);

            return [$parts, $rows];
        });

        if ($window !== null) {
            RefreshRollups::dispatch($window['from'], $window['until']);
        }
        foreach ($characterIds as $characterId) {
            $this->queueStandings->handle($characterId);
            if ($this->siblings->handle($characterId) !== []) {
                ReconcileCharacterClaims::dispatch($characterId);
            }
        }

        return ['group' => $group, 'refused' => true, 'parts_removed' => $parts, 'rows_removed' => $rows];
    }

    /** @param  list<int>  $characterIds */
    private function forgetInSessions(array $characterIds, string $group): void
    {
        DB::update(
            'update sessions set groups = groups - ?::text where character_id = any(?::bigint[])',
            [$group, '{'.implode(',', $characterIds).'}'],
        );
        $counters = SessionRows::GROUP_COUNTERS[$group] ?? [];
        if ($counters !== []) {
            DB::table('sessions')->whereIn('character_id', $characterIds)->update(array_fill_keys($counters, null));
        }
        foreach (FactTable::cases() as $table) {
            $columns = $table->groupColumns()[$group] ?? [];
            if ($columns !== []) {
                DB::table($table->value)->whereIn('character_id', $characterIds)->update(array_fill_keys($columns, null));
            }
        }
    }
}
