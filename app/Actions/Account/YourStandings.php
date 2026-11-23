<?php

namespace App\Actions\Account;

use App\Actions\Public\Character\Standings;
use App\Models\Account;
use App\Models\Character;
use App\Support\Board;

class YourStandings
{
    public function __construct(private readonly Standings $standings) {}

    /**
     * The places of the account's characters on the boards: the one character `$only` names, or all of them, the most
     * played first. Without `$board`, every board the character is on; with it, that board alone.
     *
     * @return list<array{name: string, standings: list<array{board: string, title: string, place: int, total: int, value: int|float}>}>
     */
    public function handle(Account $account, ?int $only = null, ?Board $board = null): array
    {
        $characters = $account->characters()->when($only !== null, fn ($query) => $query->whereKey($only))->orderByDesc('played_seconds')->orderBy('name_key')->get();
        $groups = [];
        foreach ($characters as $character) {
            /** @var Character $character */
            $rows = $this->standings->handle($character);
            $rows = $board === null ? $rows : array_values(array_filter($rows, fn (array $row): bool => $row['board'] === $board->value));
            if ($rows !== []) {
                $groups[] = ['name' => (string) $character->name, 'standings' => $rows];
            }
        }

        return $groups;
    }
}
