<?php

namespace App\Actions\Public\Character;

use App\Actions\Rollups\RulesetSummaries;
use App\Support\LootSourceKind;
use App\Support\LootVia;
use App\Support\NameKind;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SessionRecords
{
    public const string XP_HOUR = 'xp_h';

    public const string GOLD_HOUR = 'gold_h';

    public const string KILLS_HOUR = 'kills_h';

    public const string GATHERS_HOUR = 'gathers_h';

    public const string XP = 'xp';

    public const string KILLS = 'kills';

    public const string GATHERS = 'gathers';

    public const string ITEM = 'item';

    public const string QUESTS = 'quests';

    public const string LOOT = 'loot';

    public const string PLAYED = 'played';

    public const string BALANCE = 'balance';

    /** Played seconds a session needs before its rates and totals count as a record. */
    public const int MIN_PLAYED = 900;

    /**
     * What the character did most in one session, as the Records of the app's Character Overview, in its order. A session
     * counts from `MIN_PLAYED` seconds of play. A record is left out when no session holds a value above zero for it, or
     * when the sharing group it is read from was never sent. `gold_h` and `balance` are in the answer only with
     * `$economy`. The app's "Most gold looted" is not here: the site stores the money a session earned, not what was
     * looted.
     *
     * @return list<array{key: string, value: int|float, at: string, from: int|null, to: int|null, item: string|null}>
     */
    public function handle(int $characterId, bool $economy): array
    {
        $sessions = DB::table('sessions as s')->where('s.character_id', $characterId)->where('s.counted', true)->orderBy('s.start_at')
            ->selectRaw('s.session_start, s.start_at, s.end_at, s.played_ms, s.level_start, s.level_end, s.xp_total, s.kills, s.quests_done, s.copper_earned, s.copper_spent, s.money_end')->get();
        $looted = $this->looted($characterId);
        $gathered = DB::table('loot_opens')->where('character_id', $characterId)->where('counted', true)->where('source_kind', LootSourceKind::Object->value)
            ->groupBy('session_start')->selectRaw('session_start, count(*) as gathers')->pluck('gathers', 'session_start');

        $best = [];
        $balance = null;
        foreach ($sessions as $s) {
            $level = [$s->level_start === null ? null : (int) $s->level_start, $s->level_end === null ? null : (int) $s->level_end];
            $own = fn (string $key, int|float $value, ?string $item = null): array => ['key' => $key, 'value' => $value, 'at' => Carbon::parse((string) $s->start_at)->utc()->toIso8601ZuluString(), 'from' => $level[0], 'to' => $level[1], 'item' => $item];
            if ($economy && $s->money_end !== null && ($balance === null || (int) $s->money_end > $balance['value'])) {
                $balance = $own(self::BALANCE, (int) $s->money_end);
            }
            if ((int) $s->played_ms < self::MIN_PLAYED * 1000) {
                continue;
            }
            $seconds = (int) $s->played_ms / 1000;
            $hour = fn (int|float $count): float => $count * 3600 / $seconds;
            $gathers = (int) ($gathered[$s->session_start] ?? 0);
            $top = $looted[$s->session_start]['top'] ?? null;
            $net = $s->copper_earned === null ? 0 : (int) $s->copper_earned - (int) $s->copper_spent;
            $values = [
                self::XP_HOUR => $s->xp_total !== null && ($level[0] ?? 0) < RulesetSummaries::TARGET_LEVEL ? $hour((int) $s->xp_total) : 0,
                self::GOLD_HOUR => $economy ? $hour($net) : 0,
                self::KILLS_HOUR => $hour((int) $s->kills),
                self::GATHERS_HOUR => $hour($gathers),
                self::XP => (int) $s->xp_total,
                self::KILLS => (int) $s->kills,
                self::GATHERS => $gathers,
                self::ITEM => $top === null ? 0 : $top['quantity'],
                self::QUESTS => (int) $s->quests_done,
                self::LOOT => $looted[$s->session_start]['total'] ?? 0,
                self::PLAYED => $seconds,
            ];
            foreach ($values as $key => $value) {
                if ($value > 0 && $value > ($best[$key]['value'] ?? 0)) {
                    $best[$key] = $own($key, $value, $key === self::ITEM ? ($top['name'] ?? null) : null);
                }
            }
        }
        if ($balance !== null && $balance['value'] > 0) {
            $best[self::BALANCE] = $balance;
        }
        $order = [self::XP_HOUR, self::GOLD_HOUR, self::KILLS_HOUR, self::GATHERS_HOUR, self::XP, self::KILLS, self::GATHERS, self::ITEM, self::QUESTS, self::LOOT, self::PLAYED, self::BALANCE];

        return array_values(array_filter(array_map(fn (string $key): ?array => $best[$key] ?? null, $order)));
    }

    /** @return array<int, array{total: int, top: array{quantity: int, name: string}}> the items received in each session and the one received most */
    private function looted(int $characterId): array
    {
        $rows = DB::table('loot_drops as d')->where('d.character_id', $characterId)->where('d.counted', true)->where('d.via', LootVia::Receipt->value)->whereNotNull('d.item_id')
            ->groupBy('d.session_start', 'd.item_id')
            ->selectRaw('d.session_start, d.item_id, sum(d.quantity)::bigint as quantity, (select n.name from names n where n.kind = ? and n.id = d.item_id order by n.votes desc, n.name limit 1) as name', [NameKind::Item->value])
            ->orderBy('d.session_start')->orderByDesc('quantity')->orderBy('d.item_id')->get();

        $by = [];
        foreach ($rows as $row) {
            $by[$row->session_start]['total'] = ($by[$row->session_start]['total'] ?? 0) + (int) $row->quantity;
            $by[$row->session_start]['top'] ??= ['quantity' => (int) $row->quantity, 'name' => $row->name === null ? 'Item '.$row->item_id : (string) $row->name];
        }

        return $by;
    }
}
