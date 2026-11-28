<?php

namespace App\Actions\Account;

use App\Models\Account;
use App\Support\SharingGroups;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShowStorage
{
    public function __construct(private readonly SharingGroups $groups) {}

    /**
     * What the server holds for the account's characters, from `parts` grouped once by character and sharing group.
     * `bytes` is the size of the part's lines before compression.
     *
     * @return array{
     *     totals: array{parts: int, bytes: int, lines: int, characters: int, sessions: int, installs: int, revoked: int},
     *     characters: list<array{id: int, name: string, class: string|null, sessions: int, parts: int, bytes: int, lines: int, last: Carbon|null}>,
     *     groups: list<array{key: string, label: string, parts: int, bytes: int}>
     * }
     */
    public function handle(Account $account): array
    {
        $characters = $account->characters()->select(['id', 'name', 'class', 'sessions_decoded'])->orderBy('name')->get();
        $installs = $account->installs()->select(['id', 'name', 'revoked_at', 'created_at'])->orderBy('created_at')->get();

        $rows = DB::table('parts')
            ->whereIn('character_id', $characters->pluck('id')->all())
            ->groupBy('character_id', 'group_key')
            ->selectRaw('character_id, group_key, count(*) as parts, sum(raw_bytes) as bytes, sum(lines) as lines, max(created_at) as last')
            ->get();

        $byCharacter = [];
        $byGroup = [];
        foreach ($rows as $row) {
            $own = $byCharacter[$row->character_id] ?? ['parts' => 0, 'bytes' => 0, 'lines' => 0, 'last' => null];
            $own['parts'] += (int) $row->parts;
            $own['bytes'] += (int) $row->bytes;
            $own['lines'] += (int) $row->lines;
            $own['last'] = $own['last'] === null || $row->last > $own['last'] ? $row->last : $own['last'];
            $byCharacter[$row->character_id] = $own;
            $group = $byGroup[$row->group_key] ?? ['parts' => 0, 'bytes' => 0];
            $byGroup[$row->group_key] = ['parts' => $group['parts'] + (int) $row->parts, 'bytes' => $group['bytes'] + (int) $row->bytes];
        }

        $labels = $this->groups->labels();
        $list = [];
        foreach ($characters as $character) {
            $own = $byCharacter[$character->id] ?? ['parts' => 0, 'bytes' => 0, 'lines' => 0, 'last' => null];
            $list[] = [
                'id' => $character->id,
                'name' => $character->name,
                'class' => $character->class,
                'sessions' => (int) $character->sessions_decoded,
                'parts' => $own['parts'],
                'bytes' => $own['bytes'],
                'lines' => $own['lines'],
                'last' => $own['last'] === null ? null : Carbon::parse($own['last'])->utc(),
            ];
        }
        $grouped = [];
        foreach ($byGroup as $key => $group) {
            $grouped[] = ['key' => (string) $key, 'label' => $labels[$key] ?? (string) $key, 'parts' => $group['parts'], 'bytes' => $group['bytes']];
        }
        usort($grouped, fn (array $a, array $b): int => $b['bytes'] <=> $a['bytes']);

        return [
            'totals' => [
                'parts' => array_sum(array_column($list, 'parts')),
                'bytes' => array_sum(array_column($list, 'bytes')),
                'lines' => array_sum(array_column($list, 'lines')),
                'characters' => count($list),
                'sessions' => array_sum(array_column($list, 'sessions')),
                'installs' => $installs->count(),
                'revoked' => $installs->filter(fn ($install): bool => $install->isRevoked())->count(),
            ],
            'characters' => $list,
            'groups' => $grouped,
        ];
    }
}
