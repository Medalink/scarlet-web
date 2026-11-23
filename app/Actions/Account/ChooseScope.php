<?php

namespace App\Actions\Account;

use App\Models\Account;
use App\View\Pages\ScopeCompare;
use Closure;

class ChooseScope
{
    public function __construct(private readonly OwnCharacterNames $names, private readonly ChooseComparedCharacter $compared) {}

    /**
     * What a hub needs for the scope switch and the You vs everyone panel of the signed-in viewer.
     *
     * @param  string  $choice  everyone, compare or mine
     * @param  array<string, string>  $query  the parameters the switch links keep
     * @param  array<mixed>  $everyone  the page's figures for everyone
     * @param  Closure(int): (array<mixed>|null)|null  $figuresOf  the page's figures for one character, when it compares figures
     * @param  list<string>  $skip  figure labels that mean nothing for one character
     * @return array{choice: string, names: list<string>, as: string|null, chosen: array{id: int, name: string}|null, query: array<string, string>, versus: array{name: string, rows: list<array{0: string, 1: string, 2: string}>}|null}
     */
    public function handle(?Account $account, string $choice, ?string $as, bool $own, array $query, array $everyone = [], ?Closure $figuresOf = null, array $skip = []): array
    {
        $names = $account === null ? [] : $this->names->handle($account);
        $chosen = $account === null || $choice !== 'compare' ? null : $this->compared->handle($account, $as);
        $yours = $chosen === null || $figuresOf === null ? null : $figuresOf($chosen['id']);

        return [
            'choice' => $own && $choice !== 'mine' ? 'mine' : $choice,
            'names' => $names,
            'as' => $chosen['name'] ?? null,
            'chosen' => $chosen,
            'query' => $query,
            'versus' => $chosen === null || $yours === null ? null : ScopeCompare::figures($everyone, $yours, $chosen['name'], $skip),
        ];
    }
}
