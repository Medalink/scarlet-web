<?php

namespace App\View\Pages;

use App\Support\Profession;

/** The links between the pages of the professions, and on a player's own pages the camps page beside them. */
final class Trades
{
    public const string CAMPS = 'camps';

    /**
     * @param  string  $current  a profession's slug, or `CAMPS`
     * @param  string  $query  the ruleset and server of the page, `?ruleset=...` or empty, carried to every link
     * @return list<array{label: string, href: string, current: bool}>
     */
    public static function of(string $current, string $query, bool $own): array
    {
        $prefix = $own ? '/account' : '';
        $trades = array_map(
            fn (Profession $profession): array => ['label' => $profession->value, 'href' => $prefix.'/professions/'.$profession->slug().$query, 'current' => $profession->slug() === $current],
            Profession::withPages(),
        );
        if ($own) {
            $trades[] = ['label' => 'Camps', 'href' => '/account/camps'.$query, 'current' => $current === self::CAMPS];
        }

        return $trades;
    }
}
