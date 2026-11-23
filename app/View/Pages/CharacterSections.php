<?php

namespace App\View\Pages;

use App\Actions\Public\Character\SkillRanks;
use App\Support\EnvironmentalDamage;
use App\Support\HitEvent;
use App\Support\ItemOrigin;
use App\Support\ItemQuality;
use App\Support\MovementMode;
use App\Support\Profession;
use App\View\Charts;
use App\View\Format;
use App\View\ItemTypes;

/** The sections of the character page that read one fact table each: zones, deaths, items and skills. */
final class CharacterSections
{
    /** Characters that completed a level before the ruleset's median time in it is shown. */
    private const int MEDIAN_FROM = 2;

    private const int DEATH_ZONES = 3;

    /**
     * @param  array{zones: list<array<string, mixed>>, others: int, other_seconds: float}  $hours
     * @return array{rows: list<array<string, mixed>>, others: string|null}
     */
    public static function zones(array $hours): array
    {
        $top = $hours['zones'] === [] ? 1.0 : max(1.0, (float) $hours['zones'][0]['seconds']);

        return [
            'rows' => array_map(fn (array $zone): array => [
                'label' => (string) $zone['name'], 'value' => Format::duration($zone['seconds']), 'share' => $zone['seconds'] / $top * 100,
                'href' => $zone['linked'] ? '/zones/'.$zone['id'] : null,
            ], $hours['zones']),
            'others' => $hours['others'] === 0 ? null : Format::count($hours['others'], 'other zone').', '.Format::duration($hours['other_seconds']),
        ];
    }

    /**
     * @param  array{rows: list<array<string, mixed>>}  $log
     * @param  array{total: int, zones: list<array<string, mixed>>}  $deaths
     * @return array<string, mixed>
     */
    public static function deaths(array $log, array $deaths, int $level): array
    {
        $rows = array_map(fn (array $row): array => ['cells' => [
            $row['level'] === null ? '' : (string) $row['level'],
            $row['zone_id'] !== null && $row['zone'] !== null ? ['text' => (string) $row['zone'], 'href' => '/zones/'.$row['zone_id']] : (string) ($row['zone'] ?? ''),
            self::killer($row),
            $row['damage'] === null ? '' : Format::number($row['damage']),
        ], 'detail' => self::recap($row)], $log['rows']);
        $zones = array_map(fn (array $row): string => (string) ($row['zone'] ?? 'Zone '.$row['zone_id']).' ('.Format::number($row['deaths']).')', array_slice($deaths['zones'], 0, self::DEATH_ZONES));
        $shown = count($log['rows']);

        return [
            ...self::deathMarks($log['rows'], $level),
            'columns' => [['label' => 'Level', 'numeric' => true], ['label' => 'Zone'], ['label' => 'Killer'], ['label' => 'Killing blow', 'numeric' => true]],
            'rows' => $rows,
            'all' => $shown < $deaths['total']
                ? 'Latest '.$shown.' of '.Format::number($deaths['total']).' deaths'
                : ($deaths['total'] === 1 ? '1 death' : 'All '.Format::count($deaths['total'], 'death')),
            'total' => ($deaths['total'] === 0 ? 'No deaths recorded.' : Format::count($deaths['total'], 'death').' recorded.')
                .($zones === [] ? '' : ' Most in '.implode(', ', $zones).'.'),
        ];
    }

    /** @param  array<string, mixed>  $row */
    private static function killerText(array $row): string
    {
        if ($row['killer'] !== null) {
            return Format::killerName($row['killer']);
        }
        $cause = EnvironmentalDamage::tryFrom((string) $row['environmental_type']);

        return $cause === null ? 'Killer not recorded' : $cause->label();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return string|array<string, mixed>
     */
    private static function killer(array $row): string|array
    {
        $text = self::killerText($row);

        return $row['killer'] !== null && $row['npc_id'] !== null ? ['text' => $text, 'href' => '/creatures/'.$row['npc_id']] : $text;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{summary: string, columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}|null
     */
    private static function recap(array $row): ?array
    {
        $hits = $row['hits'];
        if ($hits === []) {
            return null;
        }
        $span = $hits[0]['seconds_before'];
        $held = $row['recorded'] !== null && $row['recorded'] > count($hits) ? ' of '.Format::number($row['recorded']) : '';

        return [
            'summary' => Format::count(count($hits), 'hit').$held.($span === null || count($hits) === 1 ? '' : ' over '.Format::number($span, 1).' s'),
            'columns' => [['label' => 'Before death', 'numeric' => true], ['label' => 'Hit by'], ['label' => 'With'], ['label' => 'Damage', 'numeric' => true], ['label' => 'Mitigated']],
            'rows' => array_values(array_map(fn (array $hit): array => ['cells' => [
                $hit['seconds_before'] === null ? '' : Format::number($hit['seconds_before'], 1).' s',
                $hit['source'] === null ? '' : Format::killerName($hit['source']),
                self::hitWith($hit),
                $hit['amount'] === null ? '' : Format::number($hit['amount']),
                implode(', ', array_filter([
                    $hit['absorbed'] > 0 ? Format::number($hit['absorbed']).' absorbed' : null,
                    $hit['resisted'] > 0 ? Format::number($hit['resisted']).' resisted' : null,
                    $hit['blocked'] > 0 ? Format::number($hit['blocked']).' blocked' : null,
                ])),
            ]], $hits)),
        ];
    }

    /** @param  array<string, mixed>  $hit */
    private static function hitWith(array $hit): string
    {
        $cause = EnvironmentalDamage::tryFrom((string) $hit['environmental_type']);
        if ($cause !== null) {
            return $cause->label();
        }

        return $hit['spell'] ?? HitEvent::tryFrom((string) $hit['event'])?->label() ?? '';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{spec: array<string, mixed>|null, key: list<array<string, mixed>>}
     */
    private static function deathMarks(array $rows, int $level): array
    {
        $dated = array_values(array_filter(array_reverse($rows), fn (array $row): bool => $row['level'] !== null));
        if ($dated === []) {
            return ['spec' => null, 'key' => []];
        }
        usort($dated, fn (array $a, array $b): int => $a['level'] <=> $b['level']);
        $blows = array_values(array_filter(array_map(fn (array $row): int => (int) $row['damage'], $dated), fn (int $damage): bool => $damage > 0));
        $peak = $blows === [] ? 0 : max($blows);
        $least = $blows === [] ? 0 : min($blows);
        $heights = $data = $say = [];
        $biggest = 0;
        foreach ($dated as $i => $row) {
            $height = $heights[$row['level']] ?? 0;
            $heights[$row['level']] = $height + 1;
            $damage = (int) $row['damage'];
            $data[] = [$row['level'], $height, $damage];
            $biggest = $damage > (int) $dated[$biggest]['damage'] ? $i : $biggest;
            $say[] = Format::line(
                'Level '.$row['level'].($row['zone'] === null ? '' : ', '.$row['zone']),
                ': '.self::killerText($row).($damage > 0 ? ', '.Format::number($damage).' damage' : ''),
            );
        }
        $top = max($level, ...array_map(fn (array $row): int => (int) $row['level'], $dated));

        return [
            'spec' => Charts::dots(['w' => 1100, 'x0' => 0, 'x1' => $top + 1, 'rows' => max($heights), 'peak' => $peak, 'xt' => Charts::ticks(Axis::spread(1, $top))], $data, $say, $biggest),
            'key' => $peak > $least ? [
                ['label' => 'Killing blow '.Format::number($least), 'class' => 'k-dot', 'style' => 'width:0.45rem;height:0.45rem'],
                ['label' => Format::number($peak).' damage', 'class' => 'k-dot', 'style' => 'width:0.95rem;height:0.95rem'],
            ] : [],
        ];
    }

    /**
     * The looted items by quality, then the best items received from any source except crafting, then what the log let the
     * page attribute.
     *
     * @param  array{qualities: array<int, int>, best: list<array<string, mixed>>}  $items
     * @return array<string, mixed>|null
     */
    public static function items(array $items): ?array
    {
        $found = array_sum($items['qualities']);
        if ($found === 0 && $items['best'] === []) {
            return null;
        }
        $segments = $key = [];
        foreach (ItemQuality::cases() as $quality) {
            $count = $items['qualities'][$quality->value] ?? 0;
            if ($count === 0) {
                continue;
            }
            $segments[] = ['class' => $quality->fill(), 'count' => $count, 'say' => $quality->label().' '.Format::number($count)];
            $key[] = ['label' => $quality->label().' '.Format::number($count), 'class' => $quality->fill()];
        }

        return [
            'looted' => $found === 0 ? null : [
                'segments' => $segments,
                'key' => $key,
                'say' => Format::count($found, 'item').' looted: '.implode(', ', array_column($segments, 'say')).'.',
            ],
            'best' => array_map(fn (array $row): array => [
                'label' => (string) $row['name'], 'href' => '/items/'.$row['item_id'], 'quality' => ItemTypes::quality($row['quality']),
                'level' => $row['item_level'] === null ? '–' : (string) $row['item_level'],
                'value' => ($row['quantity'] > 1 ? '×'.Format::number($row['quantity']).', ' : '').ItemQuality::from($row['quality'])->label().', '.ItemOrigin::from($row['origin'])->label(),
            ], $items['best']),
        ];
    }

    /**
     * The yards a character's movement stretches report, by mode, most first, as one bar with a key. Distance in world
     * widths (the Wayfarer feat, the most-distance board) is the path's length, not this.
     *
     * @param  list<array{mode: string, yards: int}>  $modes  the answer of Records
     * @return array{segments: list<array<string, mixed>>, key: list<array<string, mixed>>, say: string}|null
     */
    public static function distance(array $modes, string $since = ''): ?array
    {
        $segments = $key = [];
        foreach ($modes as $mode) {
            $kind = MovementMode::tryFrom((string) $mode['mode']);
            if ($kind === null || $mode['yards'] < 1) {
                continue;
            }
            $segments[] = ['class' => $kind->fill(), 'count' => $mode['yards'], 'say' => $kind->label().' '.Format::number($mode['yards']).' yd'];
            $key[] = ['label' => $kind->label().' '.Format::number($mode['yards']).' yd', 'class' => $kind->fill()];
        }
        if ($segments === []) {
            return null;
        }

        return [
            'segments' => $segments, 'key' => $key,
            'say' => 'Distance by travel mode'.$since.': '.implode(', ', array_column($segments, 'say')).'.',
        ];
    }

    /**
     * A bar for the hours the character spent in each level it has left, with a tick at the ruleset's median wherever at
     * least two characters completed the level. The median of fewer than two is the character itself.
     *
     * @param  array{character: list<array{level: int, seconds: int}>, ruleset: list<array<string, mixed>>}  $times
     * @return array{spec: array<string, mixed>, key: list<array<string, mixed>>}|null
     */
    public static function levelTimes(array $times): ?array
    {
        $own = $times['character'];
        if (count($own) < 2) {
            return null;
        }
        $medians = [];
        foreach ($times['ruleset'] as $row) {
            if ((int) $row['completed'] >= self::MEDIAN_FROM) {
                $medians[(int) $row['level']] = (float) $row['median'];
            }
        }
        $data = $say = [];
        $ticks = false;
        foreach ($own as $row) {
            $median = $medians[$row['level']] ?? null;
            $ticks = $ticks || $median !== null;
            $data[] = [$row['level'], $row['seconds'] / 3600, null, null, $median === null ? null : $median / 3600];
            $say[] = Format::line('Level '.$row['level'], ': '.Format::duration($row['seconds']).($median === null ? '' : ', median '.Format::duration($median)));
        }
        $axis = Axis::nice(max([0.0, ...array_column($data, 1), ...array_filter(array_column($data, 4), fn (mixed $hours): bool => $hours !== null)]));
        $first = $data[0][0];
        $end = $data[count($data) - 1][0];

        return [
            'spec' => Charts::bars(['w' => 1100, 'h' => 240, 'x0' => $first - 1, 'x1' => $end + 1, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks'], 'h'), 'xt' => Charts::ticks(Axis::spread($first, $end))], $data, 't', $say),
            'key' => [['label' => 'Time in level', 'style' => 'background:var(--fg)'], ...($ticks ? [['label' => 'Ruleset median', 'class' => 'k-line', 'style' => 'background:var(--accent)']] : [])],
        ];
    }

    /**
     * Professions first, then the weapon and armour skills, each longest rank first.
     *
     * @param  list<array{skill: string, rank: int, cap: int}>  $skills
     * @param  array<string, string>  $classes  the bar class of each skill the skills chart draws a line for
     * @return list<array<string, mixed>>
     */
    public static function skills(array $skills, array $classes = []): array
    {
        usort($skills, fn (array $a, array $b): int => [Profession::tryFrom($a['skill']) === null, $b['rank'], $a['skill']] <=> [Profession::tryFrom($b['skill']) === null, $a['rank'], $b['skill']]);

        return array_map(fn (array $row): array => [
            'label' => $row['skill'], 'class' => $classes[$row['skill']] ?? null, 'value' => Format::number($row['rank']), 'share' => $row['rank'] / max(1, $row['cap'] ?: SkillRanks::CAP) * 100,
        ], $skills);
    }
}
