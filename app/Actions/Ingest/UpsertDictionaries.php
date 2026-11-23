<?php

namespace App\Actions\Ingest;

use App\Support\DecodedSession;
use App\Support\SharedKind;
use Illuminate\Support\Facades\DB;

class UpsertDictionaries
{
    private const int CHUNK = 5000;

    public function __construct(private readonly VoteSharedRows $shared) {}

    /**
     * Write what the session told about names, items, zones and the players it met. A name's votes are one per
     * install behind the character, so no uploader's session count decides a rank; the fields of an item or a zone
     * are voted on the same way (`VoteSharedRows`).
     *
     * @param  bool  $countVotes  false when the session's names were counted by an earlier decode
     */
    public function handle(int $characterId, DecodedSession $decoded, bool $countVotes): void
    {
        $this->names($characterId, $decoded->names, $countVotes);
        $this->shared->handle($characterId, SharedKind::Item, $decoded->items);
        $this->shared->handle($characterId, SharedKind::Zone, array_map(fn (array $row): array => ['name' => mb_substr((string) ($row['name'] ?? ''), 0, 128)] + $row, $decoded->zones));
        $this->pseudonyms($characterId, $decoded->pseudonyms);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function names(int $characterId, array $rows, bool $countVotes): void
    {
        $unique = [];
        foreach ($rows as $row) {
            $name = mb_substr((string) $row['name'], 0, 255);
            $unique[$row['kind'].'/'.$row['id'].'/'.$name] = ['kind' => $row['kind'], 'id' => $row['id'], 'name' => $name];
        }

        $chunks = array_chunk($unique, self::CHUNK, preserve_keys: true);
        if (! $countVotes) {
            foreach ($chunks as $chunk) {
                DB::table('names')->insertOrIgnore(array_map(fn (array $row): array => $row + ['votes' => 1], $chunk));
            }

            return;
        }

        $casted = $this->castVotes($characterId, $unique);
        foreach ($chunks as $chunk) {
            $candidates = [];
            foreach ($chunk as $key => $row) {
                $candidates[] = $row + ['votes' => $casted[$key] ?? 0];
            }
            DB::table('names')->upsert($candidates, ['kind', 'id', 'name'], ['votes' => DB::raw('names.votes + excluded.votes')]);
        }
    }

    /**
     * The votes this decode casts per name row, keyed `kind/id/name`: at most one per install of the character,
     * and per install at most the daily budget. The rows written carry `$window` as `created_at`, and the count
     * reads back only what landed, so a decode of another character of the same install that wrote the same
     * (name, install) first casts the vote, not this one.
     *
     * @param  array<string, array<string, int|string>>  $unique
     * @return array<string, int>
     */
    private function castVotes(int $characterId, array $unique): array
    {
        $installs = DB::table('install_character')->where('character_id', $characterId)->pluck('install_id');
        if ($installs->isEmpty()) {
            return [];
        }

        $budget = (int) config('scarlet.ingest.name_votes_per_install_day');
        $used = DB::table('name_votes')->whereIn('install_id', $installs)->where('created_at', '>=', today())
            ->selectRaw('install_id, count(*) as votes')->groupBy('install_id')->pluck('votes', 'install_id');
        $remaining = [];
        foreach ($installs as $install) {
            $remaining[$install] = max(0, $budget - (int) ($used[$install] ?? 0));
        }

        $window = now();
        $candidates = [];
        foreach ($unique as $row) {
            foreach ($installs as $install) {
                if ($remaining[$install] === 0) {
                    continue;
                }
                $remaining[$install]--;
                $candidates[] = ['kind' => $row['kind'], 'id' => $row['id'], 'name' => $row['name'], 'install_id' => $install, 'created_at' => $window];
            }
        }
        foreach (array_chunk($candidates, self::CHUNK) as $chunk) {
            DB::table('name_votes')->insertOrIgnore($chunk);
        }

        $casted = [];
        $written = DB::table('name_votes')->whereIn('install_id', $installs)->where('created_at', $window)
            ->groupBy('kind', 'id', 'name')->get(['kind', 'id', 'name', DB::raw('count(*) as votes')]);
        foreach ($written as $row) {
            $casted[$row->kind.'/'.$row->id.'/'.$row->name] = (int) $row->votes;
        }

        return $casted;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function pseudonyms(int $characterId, array $rows): void
    {
        $unique = [];
        foreach ($rows as $row) {
            $unique[$row['pseudonym']] = [
                'character_id' => $characterId,
                'pseudonym' => $row['pseudonym'],
                'name' => mb_substr((string) $row['name'], 0, 64),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk(array_values($unique), 1000) as $chunk) {
            DB::table('pseudonyms')->upsert($chunk, ['character_id', 'pseudonym'], ['name', 'updated_at']);
        }
    }
}
