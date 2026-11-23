<?php

namespace App\Actions\Account;

use App\Support\FactTable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShowCharacterGold
{
    /** Days of balances the chart covers, ending on the last day with one. */
    public const int DAYS = 30;

    /**
     * What each character earned and spent on money lines, apart from the copper moved between your own characters, and
     * the balance at the end of each session of the last days, oldest first.
     *
     * @param  list<int>  $characterIds
     * @return array{totals: array<int, array{earned: int, spent: int, transfer: int}>, balances: list<array{character_id: int, at: string, balance: int}>}
     */
    public function handle(array $characterIds): array
    {
        $totals = [];
        $rows = DB::table(FactTable::MoneyChanges->value)->whereIn('character_id', $characterIds)->where('counted', true)->where('src', FactTable::MONEY_LINE)
            ->groupBy('character_id')->selectRaw('character_id, sum(earned) as earned, sum(spent) as spent, sum(transfer) as transfer')->get();
        foreach ($rows as $row) {
            $totals[(int) $row->character_id] = ['earned' => (int) $row->earned, 'spent' => (int) $row->spent, 'transfer' => (int) $row->transfer];
        }

        return ['totals' => $totals, 'balances' => $this->balances($characterIds)];
    }

    /**
     * The balance after each session of the last `DAYS` days with a balance, oldest first, led by the balance each character held before them.
     *
     * @param  list<int>  $characterIds
     * @return list<array{character_id: int, at: string, balance: int}>
     */
    private function balances(array $characterIds): array
    {
        $sessions = fn () => DB::table('sessions')->whereIn('character_id', $characterIds)->whereNotNull('money_end');
        $last = $sessions()->max('end_at');
        if ($last === null) {
            return [];
        }
        $from = Carbon::parse((string) $last, 'UTC')->startOfDay()->subDays(self::DAYS - 1);
        $before = $sessions()->where('end_at', '<', $from)->orderBy('character_id')->orderByDesc('end_at')->orderByDesc('id')
            ->selectRaw('distinct on (character_id) character_id, end_at, money_end')->get();
        $within = $sessions()->where('end_at', '>=', $from)->orderBy('end_at')->orderBy('id')->get(['character_id', 'end_at', 'money_end']);

        return array_values($before->sortBy('end_at')->concat($within)
            ->map(fn (object $row): array => ['character_id' => (int) $row->character_id, 'at' => (string) $row->end_at, 'balance' => (int) $row->money_end])->all());
    }
}
