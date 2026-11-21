<?php

namespace App\View\Pages;

use App\Actions\Public\Character\DeathLog;
use App\Actions\Public\Character\SkillRanks;
use App\Support\ItemQuality;
use App\Support\Profession;
use App\View\Charts;
use App\View\Format;
use App\View\ItemTypes;

/** The sections of the character page that read one fact table each: zones, deaths, items and skills. */
final class CharacterSections
{
    /** Characters that completed a level before the ruleset's median time in it is shown. */
    private const int MEDIAN_FROM = 2;

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
     * One cell per level from 1 to the character's level, and every logged death as a table row.
     *
     * @param  array{levels: array<int, int>, rows: list<array<string, mixed>>}  $log
     * @param  array{total: int, zones: list<array<string, mixed>>}  $deaths
     * @return array<string, mixed>
     */
    public static function deaths(array $log, array $deaths, int $level): array
    {
        $top = max($level, $log['levels'] === [] ? 0 : max(array_keys($log['levels'])));
        $strip = [];
        for ($n = 1; $n <= $top; $n++) {
            $count = $log['levels'][$n] ?? 0;
            $strip[] = ['level' => $n, 'count' => $count, 'say' => $count === 0 ? 'Level '.$n.': no deaths' : 'Level '.$n.': '.Format::count($count, 'death')];
        }
        $rows = array_map(fn (array $row): array => ['cells' => [
            $row['level'] === null ? '' : (string) $row['level'],
            $row['zone_id'] !== null && $row['zone'] !== null ? ['text' => (string) $row['zone'], 'href' => '/zones/'.$row['zone_id']] : (string) ($row['zone'] ?? ''),
            $row['killer'] ?? 'Killer not recorded',
            $row['damage'] === null ? '' : Format::number($row['damage']),
        ]], $log['rows']);
        $zones = array_map(fn (array $row): string => (string) ($row['zone'] ?? 'Zone '.$row['zone_id']).' '.$row['deaths'], $deaths['zones']);
        $shown = count($log['rows']);

        return [
            'strip' => $deaths['total'] === 0 ? [] : $strip,
            'columns' => [['label' => 'Level', 'numeric' => true], ['label' => 'Zone'], ['label' => 'Killer'], ['label' => 'Killing blow', 'numeric' => true]],
            'rows' => $rows,
            'total' => ($deaths['total'] === 0 ? 'No deaths recorded.' : Format::count($deaths['total'], 'death').' recorded.')
                .($shown < $deaths['total'] ? ' The latest '.DeathLog::SHOWN.' are listed.' : '')
                .($zones === [] ? '' : ' Most in '.implode(', ', $zones).'.'),
        ];
    }

    /**
     * @param  array{qualities: array<int, int>, best: list<array<string, mixed>>}  $items
     * @return array<string, mixed>|null
     */
    public static function items(array $items): ?array
    {
        $found = array_sum($items['qualities']);
        if ($found === 0) {
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
        $top = max(1, (int) max([0, ...array_column($items['best'], 'item_level')]));

        return [
            'segments' => $segments,
            'key' => $key,
            'say' => Format::number($found).' items looted: '.implode(', ', array_column($segments, 'say')).'.',
            'total' => Format::number($found).' items looted.',
            'best' => array_map(fn (array $row): array => [
                'label' => (string) $row['name'], 'href' => '/items/'.$row['item_id'], 'quality' => ItemTypes::quality($row['quality']),
                'value' => ($row['quantity'] > 1 ? '×'.Format::number($row['quantity']).', ' : '').($row['item_level'] === null ? ItemQuality::from($row['quality'])->label() : 'item level '.$row['item_level']),
                'share' => ($row['item_level'] ?? 0) / $top * 100, 'class' => ItemQuality::from($row['quality'])->fill(),
            ], $items['best']),
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
     * @return list<array<string, mixed>>
     */
    public static function skills(array $skills): array
    {
        usort($skills, fn (array $a, array $b): int => [Profession::tryFrom($a['skill']) === null, $b['rank'], $a['skill']] <=> [Profession::tryFrom($b['skill']) === null, $a['rank'], $b['skill']]);

        return array_map(fn (array $row): array => [
            'label' => $row['skill'], 'value' => Format::number($row['rank']), 'share' => $row['rank'] / max(1, $row['cap'] ?: SkillRanks::CAP) * 100,
        ], $skills);
    }
}
