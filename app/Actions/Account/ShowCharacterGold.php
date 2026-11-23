<?php

namespace App\Actions\Account;

use App\Support\FactTable;
use Illuminate\Support\Facades\DB;

class ShowCharacterGold
{
    /**
     * What each character earned and spent on money lines, apart from the copper moved between your own characters, and
     * the balance at the end of every session, oldest first.
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
        $balances = DB::table('sessions')->whereIn('character_id', $characterIds)->whereNotNull('money_end')->orderBy('end_at')->orderBy('id')
            ->get(['character_id', 'end_at', 'money_end'])
            ->map(fn (object $row): array => ['character_id' => (int) $row->character_id, 'at' => (string) $row->end_at, 'balance' => (int) $row->money_end])->all();

        return ['totals' => $totals, 'balances' => array_values($balances)];
    }
}
