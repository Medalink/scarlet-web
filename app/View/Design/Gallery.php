<?php

namespace App\View\Design;

use App\View\Charts;
use App\View\Design\ClassPages\ClassGallery;
use App\View\Format;
use App\View\Pages\Filters;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The component gallery: the comps rebuilt from PHP arrays through the layout and the components. Every name and
 * number is invented, the same invented data as docs/comps/build.mjs. It is registered only in the local
 * environment and is how the pages are checked against their comps.
 */
final class Gallery
{
    public const BASE = '/design';

    /** Gallery page → the comp it rebuilds. */
    public const PAGES = [
        'stats' => 'stats.html',
        'world' => 'world.html',
        'rulesets' => 'rulesets.html',
        'leaderboards' => 'leaderboards.html',
        'search' => 'search.html',
        'creature' => 'creature.html',
        'item' => 'item.html',
        'zone' => 'zone.html',
        'quest' => 'quest.html',
        'character' => 'character.html',
        'account' => 'account.html',
        'login' => 'login.html',
        'link' => 'link.html',
        'developers' => 'developers.html',
        'class' => 'class.html',
        'class-rogue' => 'the Rogue mock',
        'class-shaman' => 'the Shaman mock',
    ];

    /** Gallery page → its view. */
    public const VIEWS = [
        'stats' => 'design.stats',
        'world' => 'design.world',
        'rulesets' => 'design.rulesets',
        'leaderboards' => 'design.leaderboards',
        'search' => 'design.search',
        'creature' => 'design.creature',
        'item' => 'design.item',
        'zone' => 'design.zone',
        'quest' => 'design.quest',
        'character' => 'design.character',
        'account' => 'design.account',
        'login' => 'design.login',
        'link' => 'design.link',
        'developers' => 'design.developers',
        'class' => 'design.class',
        'class-rogue' => 'design.class',
        'class-shaman' => 'design.class',
    ];

    /** Where a gallery page lives. */
    public static function url(string $page): string
    {
        return self::BASE.'/'.$page;
    }

    /**
     * The data a gallery page renders from.
     *
     * @return array<string, mixed>
     */
    public static function data(string $page): array
    {
        return match ($page) {
            'stats' => StatsPage::data(),
            'world' => WorldPage::data(),
            'rulesets', 'leaderboards', 'search' => ListPages::data($page),
            'creature', 'item', 'zone', 'quest' => ResultPages::data($page),
            'character' => CharacterPage::data(),
            'account', 'login', 'link' => AccountPages::data($page),
            'developers' => DevelopersPage::data(),
            'class', 'class-rogue', 'class-shaman' => ClassGallery::data($page),
            default => [],
        };
    }

    /**
     * The ruleset, server and class filters most pages share.
     *
     * @return array<string, mixed>
     */
    public static function filters(): array
    {
        $on = fn (array $labels): array => array_map(fn (string $label, int $i): array => ['label' => $label, 'href' => '#', 'on' => $i === 0] + (str_contains($label, 'not out') ? ['disabled' => true] : []), $labels, array_keys($labels));

        return [
            'rulesets' => $on(['All', 'Normal', 'PvP', 'Roleplay', 'Roleplay PvP', Filters::HARDCORE_NOT_OUT]),
            'servers' => $on(['PvE and PvP', 'PvE', 'PvP']),
            'classes' => ['Every class', 'Warrior', 'Mage'],
        ];
    }

    /**
     * The day heatmap, which no comp uses on its own: 19 weeks of invented play time up to 20 Nov 2026.
     *
     * @return array<string, mixed>
     */
    public static function dayHeat(): array
    {
        $r = new Rng(21);
        $start = new DateTimeImmutable('2026-07-13', new DateTimeZone('UTC'));
        $today = new DateTimeImmutable('2026-11-20', new DateTimeZone('UTC'));
        $days = $say = $months = [];
        $was = null;
        for ($i = 0; $i < 133; $i++) {
            $day = $start->modify('+'.$i.' days');
            if ($i % 7 === 0) {
                $month = (int) $day->format('n');
                $months[] = $month === $was ? '' : $day->format('M');
                $was = $month;
            }
            $played = $day > $today ? 0 : (int) (max(0, $r->next() - 0.35) * 24000);
            $sessions = max(1, intdiv($played, 5400));
            $level = match (true) {
                $played <= 0 => 0,
                $played < 3600 => 1,
                $played < 7200 => 2,
                $played < 14400 => 3,
                default => 4,
            };
            $days[] = [$level, $day > $today ? 1 : 0];
            $say[] = Format::line($day->format('D j M'), $played > 0 ? ' · '.Format::duration($played).' in '.Format::number($sessions).($sessions === 1 ? ' session' : ' sessions') : ' · not played');
        }

        return ['spec' => Charts::days($days, $months, $say, 'days-out'), 'line' => $say[131]];
    }

    /**
     * A share bar for each value, scaled so the largest is 100.
     *
     * @param  list<int|float>  $values
     * @return list<float>
     */
    public static function shares(array $values): array
    {
        if ($values === []) {
            return [];
        }
        $top = max($values);

        return array_map(fn (int|float $value): float => $top > 0 ? $value / $top * 100 : 0.0, $values);
    }
}
