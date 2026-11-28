<?php

namespace App\View\Pages;

use App\Support\Feat;
use App\Support\FeatCatalog;
use App\Support\FeatText;
use App\View\Format;
use App\View\Sc\Crest;

/** The data of /feats (design/final/feats-web.html) from the answer of App\Actions\Feats\ShowFeatsHub and the query of the page. */
final class FeatsPage
{
    public const string PATH = '/feats';

    /**
     * @param  array<string, mixed>  $hub
     * @param  array{scope?: string|null, as?: string|null, tier?: string|null, family?: string|null, q?: string|null, feat?: string|null}  $query
     * @return array<string, mixed>
     */
    public static function data(array $hub, array $query): array
    {
        /** @var list<array<string, mixed>> $feats */
        $feats = $hub['feats'];
        $character = $hub['character'];
        $keep = $character !== null;
        $shown = array_values(array_filter($feats, fn (array $feat): bool => self::passes($feat, $query, $keep)));
        $picked = self::picked($hub, $query['feat'] ?? null);

        return [
            'signedIn' => $hub['names'] !== [] || ($hub['signedIn'] ?? false),
            'mode' => $hub['mode'],
            'names' => $hub['names'],
            'as' => $character['name'] ?? null,
            'title' => Format::number($hub['catalog']).' feats, five tiers each',
            'rarest' => self::rarest($hub['rarest']),
            'head' => $character === null ? null : self::head($character),
            'closest' => array_map(self::closestCard(...), $hub['closest']),
            'account' => AccountFeatWall::row($hub['account'] ?? null, fn (array $feat): bool => self::passes($feat, $query, $keep)),
            'who' => $character === null ? 'Every feat' : 'Every feat '.self::first($character['name']).' can hold',
            'tierChips' => $keep ? self::tierChips($query) : [],
            'familyChips' => self::familyChips($feats, $query, $keep),
            'search' => $query['q'] ?? '',
            'family' => $query['family'] ?? null,
            'hidden' => self::hidden($query),
            'wall' => self::wall($shown, $query, $picked['key'] ?? null),
            'count' => count($shown),
            'aside' => $picked === null ? null : self::aside($picked, $character === null ? null : self::first($character['name']), $character !== null),
            'note' => $hub['mode'] === 'mine' ? 'Shares are hidden on Only you.' : null,
        ];
    }

    public static function first(string $name): string
    {
        return explode(' ', Format::characterName($name))[0];
    }

    /**
     * The address of the page with `$over` laid over `$query`; a null or empty value leaves a parameter out.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $over
     */
    public static function url(array $query, array $over = []): string
    {
        $merged = array_filter([...$query, ...$over], fn (mixed $value): bool => $value !== null && $value !== '');

        return self::PATH.($merged === [] ? '' : '?'.http_build_query($merged));
    }

    /** A share as the page says it: "64%", "<1%" under one percent. */
    public static function percent(?float $share): string
    {
        if ($share === null) {
            return '';
        }

        return $share > 0 && $share < 1 ? '<1%' : round($share).'%';
    }

    /**
     * @param  array<string, mixed>  $feat
     * @param  array<string, mixed>  $query
     */
    private static function passes(array $feat, array $query, bool $tiers): bool
    {
        $tier = $query['tier'] ?? null;
        $family = $query['family'] ?? null;
        $text = mb_strtolower(trim((string) ($query['q'] ?? '')));

        return ($family === null || $family === '' || $feat['family'] === $family)
            && (! $tiers || $tier === null || $tier === '' || (int) $feat['tier'] === (int) $tier)
            && ($text === '' || str_contains(mb_strtolower((string) $feat['name']), $text));
    }

    /**
     * @param  array<string, mixed>  $hub
     * @return array<string, mixed>|null the feat the panel at the right shows: the one asked for, else the closest or the first
     */
    private static function picked(array $hub, ?string $key): ?array
    {
        /** @var list<array<string, mixed>> $feats */
        $feats = $hub['feats'];
        $by = array_column($feats, null, 'key');
        $default = $hub['closest'][0]['key'] ?? ($by['slayer']['key'] ?? ($feats[0]['key'] ?? null));

        $wanted = $key ?? '';

        return $by[$wanted] ?? ($default === null ? null : $by[$default]);
    }

    /**
     * @param  array<string, mixed>  $character
     * @return array<string, mixed>
     */
    private static function head(array $character): array
    {
        $parts = [Format::number($character['general']).' general'];
        if ($character['class_feats'] > 0) {
            $parts[] = Format::number($character['class_feats']).' for the class and its trees';
        }
        if ($character['role_feats'] > 0) {
            $parts[] = Format::number($character['role_feats']).' for '.mb_strtolower(implode(' and ', $character['roles']));
        }
        $class = Format::className($character['class']);
        $tiles = [];
        for ($tier = 5; $tier >= 1; $tier--) {
            $tiles[] = ['tier' => $tier, 'name' => Crest::TIER_NAMES[$tier], 'count' => $character['tiers'][$tier - 1]];
        }

        return [
            'name' => Format::characterName($character['name']),
            'first' => self::first($character['name']),
            'held' => $character['held'],
            'total' => $character['total'],
            'ring' => $character['total'] === 0 ? 0 : $character['held'] / $character['total'],
            'line' => 'A '.$class.' can hold '.Format::number($character['total']).': '.implode(', ', $parts).'.',
            'tiles' => $tiles,
            'score' => $character['score'],
            'max' => self::scoreMax(),
            'place' => $character['place'] === null ? null : Format::ordinal($character['place']).' of '.Format::number((int) $character['of']),
        ];
    }

    public static function scoreMax(): int
    {
        return count(array_filter(Feat::cases(), fn (Feat $feat): bool => $feat->scores())) * count(Feat::TIERS);
    }

    /**
     * @param  array<string, mixed>|null  $rarest
     * @return array{text: string, href: string}|null
     */
    private static function rarest(?array $rarest): ?array
    {
        if ($rarest === null) {
            return null;
        }

        return ['text' => $rarest['name'].', '.Crest::TIER_NAMES[$rarest['tier']].', by '.Format::characterName($rarest['character']).'. '.self::percent($rarest['share']).' of characters hold it.', 'href' => '/feats/'.$rarest['key']];
    }

    /**
     * @param  array<string, mixed>  $feat
     * @return array<string, mixed>
     */
    public static function closestCard(array $feat): array
    {
        $kind = Feat::from($feat['key']);

        return [
            'key' => $feat['key'], 'name' => $feat['name'], 'glyph' => $feat['glyph'], 'family' => $feat['family'], 'tier' => $feat['tier'], 'progress' => $feat['progress'], 'spell' => $feat['spell'],
            'href' => '/feats/'.$feat['key'],
            'left' => FeatText::amount($kind, (int) $feat['left']).' '.FeatText::noun($kind, (int) $feat['left']).' to '.Crest::TIER_NAMES[$feat['tier'] + 1],
            'counted' => $feat['counted'],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array{label: string, href: string, on: bool}>
     */
    private static function tierChips(array $query): array
    {
        $current = $query['tier'] ?? null;
        $chips = [['All tiers', null], ['Not earned', '0']];
        foreach (array_slice(Crest::TIER_NAMES, 1, null, true) as $tier => $name) {
            $chips[] = [$name, (string) $tier];
        }

        return array_map(fn (array $chip): array => ['label' => $chip[0], 'href' => self::url($query, ['tier' => $chip[1]]).'#wall', 'on' => ($current ?? null) === $chip[1] || ($chip[1] === null && $current === '')], $chips);
    }

    /**
     * @param  list<array<string, mixed>>  $feats
     * @param  array<string, mixed>  $query
     * @return list<array{label: string, href: string, on: bool}>
     */
    private static function familyChips(array $feats, array $query, bool $keep): array
    {
        $current = $query['family'] ?? null;
        $chips = [['label' => 'All '.count($feats), 'href' => self::url($query, ['family' => null]).'#wall', 'on' => $current === null || $current === '']];
        foreach (FeatCatalog::FAMILIES as $family) {
            $n = count(array_filter($feats, fn (array $feat): bool => $feat['family'] === $family));
            if ($n > 0) {
                $chips[] = ['label' => ucfirst($family).' '.$n, 'href' => self::url($query, ['family' => $family]).'#wall', 'on' => $current === $family];
            }
        }

        return $chips;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, string> the parameters the search form keeps
     */
    private static function hidden(array $query): array
    {
        return array_filter(['scope' => $query['scope'] ?? null, 'as' => $query['as'] ?? null, 'tier' => $query['tier'] ?? null, 'family' => $query['family'] ?? null], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  list<array<string, mixed>>  $feats
     * @param  array<string, mixed>  $query
     * @return list<array{name: string, family: string, feats: list<array<string, mixed>>}>
     */
    private static function wall(array $feats, array $query, ?string $picked): array
    {
        $groups = [];
        foreach ($feats as $feat) {
            $groups[(string) $feat['collection']]['family'] = (string) $feat['family'];
            $groups[(string) $feat['collection']]['feats'][] = [
                'key' => $feat['key'], 'name' => $feat['name'], 'glyph' => $feat['glyph'], 'family' => $feat['family'], 'tier' => $feat['tier'], 'progress' => $feat['progress'], 'spell' => $feat['spell'],
                'share' => self::percent($feat['share']), 'on' => $feat['key'] === $picked, 'href' => self::url($query, ['feat' => $feat['key']]).'#feat',
            ];
        }
        $wall = [];
        foreach ($groups as $name => $group) {
            $wall[] = ['name' => $name, 'family' => $group['family'], 'feats' => $group['feats']];
        }

        return $wall;
    }

    /**
     * @param  array<string, mixed>  $feat
     * @return array<string, mixed>
     */
    private static function aside(array $feat, ?string $who, bool $owned): array
    {
        $kind = Feat::from($feat['key']);

        return [
            'key' => $feat['key'], 'name' => $feat['name'], 'glyph' => $feat['glyph'], 'family' => $feat['family'], 'tier' => $feat['tier'], 'progress' => $feat['progress'], 'spell' => $feat['spell'],
            'count' => $owned ? $feat['counted'] : 'Counts '.$feat['many'].'.',
            'to' => $owned ? $feat['to_next'] : null,
            'href' => '/feats/'.$feat['key'],
            'ladder' => FeatLadder::rows($kind, $feat['thresholds'], $feat['shares'] ?? null, $owned ? $feat['tier'] : null, $owned && $who !== null ? $who.', '.$feat['counted'] : null),
            'shares' => ($feat['shares'] ?? null) !== null,
        ];
    }
}
