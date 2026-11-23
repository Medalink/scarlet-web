<?php

namespace App\View\Pages;

/** The `Account` row of the feat wall: the account crests the account has earned or begun, earned first, from the answer of App\Actions\Feats\ShowAccountFeats. */
final class AccountFeatWall
{
    /**
     * @param  array{held: int, total: int, cards: list<array<string, mixed>>}|null  $account
     * @param  (callable(array<string, mixed>): bool)|null  $keep  which crests the page's filters leave in
     * @return array{name: string, family: string, held: int, total: int, feats: list<array<string, mixed>>}|null null when nothing is earned or begun
     */
    public static function row(?array $account, ?callable $keep = null): ?array
    {
        if ($account === null) {
            return null;
        }
        $cards = array_values(array_filter($account['cards'], fn (array $card): bool => $card['value'] > 0));
        usort($cards, fn (array $a, array $b): int => ($b['tier'] > 0) <=> ($a['tier'] > 0));
        $feats = [];
        foreach ($cards as $card) {
            $feat = [
                'key' => $card['key'], 'name' => $card['name'], 'glyph' => $card['icon'], 'family' => $card['family'], 'scope' => 'account',
                'tier' => $card['tier'], 'progress' => $card['progress'], 'spell' => null, 'href' => '/feats/'.$card['key'],
            ];
            if ($keep === null || $keep($feat)) {
                $feats[] = $feat;
            }
        }

        return $feats === [] ? null : ['name' => 'Account', 'family' => 'account', 'held' => $account['held'], 'total' => $account['total'], 'feats' => $feats];
    }
}
