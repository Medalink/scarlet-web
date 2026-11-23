<?php

namespace App\Actions\Rollups;

use App\Support\LootRule;
use App\Support\LootSourceRef;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class MidnightWindows
{
    private const int SECONDS = 300;

    public function __construct(private readonly RollupRead $read) {}

    /**
     * The days whose midnight with the day before has a free-for-all loot window of the source within 300 s of it, on
     * either side, by `LootSourceRef::key()`, each as the later day (`YYYY-MM-DD`). The daily aggregates carry no time of
     * day, so a corpse that one character read just before midnight with no key and another just after with a key is two
     * days to them; `LootArm::overDays` takes these days as one. The windows are those of the characters and the windows a
     * member sent, read from the facts for the sources given (every source when null) and kept for the read-fresh seconds.
     *
     * @param  list<LootSourceRef>|null  $sources
     * @return array<string, list<string>>
     */
    public function handle(?array $sources = null): array
    {
        if ($sources === []) {
            return [];
        }
        $keys = $sources === null ? null : array_values(array_unique(array_map(fn (LootSourceRef $source): string => $source->key(), $sources)));
        if ($keys !== null) {
            sort($keys);
        }

        /** @var array<string, list<string>> */
        return $this->read->kept('midnight-windows', [$keys], fn (): array => $this->read($sources));
    }

    /**
     * @param  list<LootSourceRef>|null  $sources
     * @return array<string, list<string>>
     */
    private function read(?array $sources): array
    {
        $own = DB::table('loot_opens')->where('counted', true)->where('has_items', true)->whereRaw('first_read is not false')->whereNotNull('source_id')
            ->where('read', 'loot')->where('loot_rule', LootRule::FreeForAll->value)->selectRaw('source_kind, source_id, at as read_at');
        $peer = DB::table('peer_opens')->where('counted', true)->where('has_items', true)->whereRaw('first_read is not false')->whereNotNull('source_id')
            ->whereNotNull('corpse_key')->where('source_kind', 'c')->where('loot_rule', LootRule::FreeForAll->value)
            ->selectRaw('source_kind, source_id, at - make_interval(secs => coalesce(late_s, 0)) as read_at');
        if ($sources !== null) {
            LootSourceRef::anyOf($own, $sources);
            LootSourceRef::anyOf($peer, $sources);
        }
        $rows = $this->read->rows(DB::query()->fromSub($own->unionAll($peer), 'w')
            ->selectRaw("source_kind, source_id, to_char((read_at + interval '300 seconds') at time zone 'UTC', 'YYYY-MM-DD') as day")
            ->whereRaw('extract(epoch from read_at)::bigint % 86400 < ? or extract(epoch from read_at)::bigint % 86400 >= ?', [self::SECONDS, 86400 - self::SECONDS])
            ->groupBy('source_kind', 'source_id')->groupByRaw("to_char((read_at + interval '300 seconds') at time zone 'UTC', 'YYYY-MM-DD')")
            ->orderBy('source_kind')->orderBy('source_id')->orderBy('day'));

        $by = [];
        foreach ($rows as $row) {
            $by[$row['source_kind'].$row['source_id']][] = (string) $row['day'];
        }

        return $by;
    }
}
