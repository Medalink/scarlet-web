<?php

namespace App\Actions\Public\Character;

use App\Actions\Public\ResolvePlayerName;
use App\Support\KillerKind;
use App\Support\NameKind;
use App\Support\PlayerNameShape;
use Illuminate\Support\Facades\DB;

class DeathLog
{
    /** Deaths the log lists, newest first. */
    public const int SHOWN = 400;

    /** The newest deaths whose hits the log lists: at 20 hits a death, 2,000 hit rows at most. */
    public const int WITH_HITS = 100;

    public function __construct(private readonly ResolvePlayerName $players) {}

    /**
     * `killer` and a hit's `source` are a creature's name,
     * a player's name only when ResolvePlayerName may show it, `ResolvePlayerName::UNKNOWN` for any other player, or
     * null when there is none. A guild is never read. `npc_id` is the killer's creature id when the site has a page
     * for it. `hits` are the recorded hits of the death, oldest first, for the newest `WITH_HITS` deaths and empty for
     * the rest; `recorded` is how many hits the game's recap held in all (null when the line did not say).
     *
     * @return array{rows: list<array{level: int|null, zone_id: int|null, zone: string|null, killer: string|null, damage: int|null, environmental_type: string|null, npc_id: int|null, recorded: int|null, hits: list<array<string, mixed>>}>}
     */
    public function handle(int $characterId, bool $owner): array
    {
        $rows = DB::table('deaths')->where('character_id', $characterId)->orderByDesc('at')->limit(self::SHOWN)
            ->selectRaw('at, session_start, level, zone_id, (select z.name from zones z where z.id = deaths.zone_id) as zone, killer_kind, killer, damage, environmental_type, killer_npc_id, hits')->get();
        $hits = $this->hits($characterId, ($rows->get(self::WITH_HITS - 1) ?? $rows->last())?->at);
        $pseudonyms = [];
        foreach ($rows as $row) {
            if ($row->killer !== null && ($row->killer_kind === KillerKind::Player->value || PlayerNameShape::isPseudonym((string) $row->killer))) {
                $pseudonyms[] = (string) $row->killer;
            }
        }
        $spellIds = [];
        foreach ($hits as $group) {
            foreach ($group as $hit) {
                $spellIds[] = $hit['spell_id'];
                if ($hit['source'] !== null && PlayerNameShape::isPseudonym($hit['source'])) {
                    $pseudonyms[] = $hit['source'];
                }
            }
        }
        $names = $this->players->handle($characterId, $owner, $pseudonyms);
        $spells = $this->named(NameKind::Spell, $spellIds);
        $npcs = $this->named(NameKind::Npc, array_values($rows->map(fn (object $row): ?int => $row->killer_npc_id === null ? null : (int) $row->killer_npc_id)->all()));

        return [
            'rows' => array_values($rows->map(fn (object $row): array => [
                'level' => $row->level === null ? null : (int) $row->level,
                'zone_id' => $row->zone_id === null ? null : (int) $row->zone_id,
                'zone' => $row->zone,
                'killer' => $this->killer($row->killer, $row->killer_kind, $names),
                'damage' => $row->damage === null ? null : (int) $row->damage,
                'environmental_type' => $row->environmental_type,
                'npc_id' => $row->killer_npc_id !== null && isset($npcs[(int) $row->killer_npc_id]) ? (int) $row->killer_npc_id : null,
                'recorded' => $row->hits === null ? null : (int) $row->hits,
                'hits' => array_map(fn (array $hit): array => [
                    'pos' => $hit['pos'],
                    'seconds_before' => $hit['seconds_before'],
                    'source' => $hit['source'] === null ? null : $this->killer($hit['source'], null, $names),
                    'spell' => $hit['spell_id'] === null ? null : ($spells[$hit['spell_id']] ?? null),
                    'event' => $hit['event'],
                    'environmental_type' => $hit['environmental_type'],
                    'amount' => $hit['amount'],
                    'absorbed' => $hit['absorbed'],
                    'resisted' => $hit['resisted'],
                    'blocked' => $hit['blocked'],
                ], $hits[$row->session_start.'|'.$row->at] ?? []),
            ])->all()),
        ];
    }

    /**
     * The hits of the listed deaths, oldest hit first, by `session_start|at` of the death they belong to.
     *
     * @return array<string, list<array{pos: int, seconds_before: float|null, source: string|null, spell_id: int|null, event: string|null, environmental_type: string|null, amount: int|null, absorbed: int, resisted: int, blocked: int}>>
     */
    private function hits(int $characterId, ?string $since): array
    {
        if ($since === null) {
            return [];
        }
        $grouped = [];
        foreach (DB::table('death_hits')->where('character_id', $characterId)->where('at', '>=', $since)->orderBy('at')->orderByDesc('pos')
            ->get(['at', 'session_start', 'pos', 'before_ms', 'source', 'spell_id', 'event', 'environmental_type', 'amount', 'absorbed', 'resisted', 'blocked']) as $hit) {
            $grouped[$hit->session_start.'|'.$hit->at][] = [
                'pos' => (int) $hit->pos,
                'seconds_before' => $hit->before_ms === null ? null : round((int) $hit->before_ms / 1000, 1),
                'source' => $hit->source === null ? null : (string) $hit->source,
                'spell_id' => $hit->spell_id === null ? null : (int) $hit->spell_id,
                'event' => $hit->event,
                'environmental_type' => $hit->environmental_type,
                'amount' => $hit->amount === null ? null : (int) $hit->amount,
                'absorbed' => (int) $hit->absorbed,
                'resisted' => (int) $hit->resisted,
                'blocked' => (int) $hit->blocked,
            ];
        }

        return $grouped;
    }

    /**
     * @param  list<int|null>  $ids
     * @return array<int, string> the most voted name of each id
     */
    private function named(NameKind $kind, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn (?int $id): bool => $id !== null && $id > 0)));
        if ($ids === []) {
            return [];
        }
        $names = [];
        foreach (DB::table('names')->where('kind', $kind->value)->whereIn('id', $ids)->orderBy('votes')->orderByDesc('name')->get(['id', 'name']) as $row) {
            $names[(int) $row->id] = (string) $row->name;
        }

        return $names;
    }

    /** @param  array<string, string>  $names */
    private function killer(?string $killer, ?string $kind, array $names): ?string
    {
        if ($killer === null) {
            return null;
        }
        if ($kind === KillerKind::Player->value || PlayerNameShape::isPseudonym($killer)) {
            return $names[$killer] ?? ResolvePlayerName::UNKNOWN;
        }

        return PlayerNameShape::matches($killer) ? ResolvePlayerName::UNKNOWN : $killer;
    }
}
