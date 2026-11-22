<?php

namespace App\View\Pages;

use App\Actions\Public\Character\TalentFacts;
use App\Support\RespecKind;
use App\View\Format;
use App\View\TalentTree;
use Illuminate\Support\Carbon;

/** The talent modules of the character page, from the answer of App\Actions\Public\Character\Talents. */
final class CharacterTalents
{
    /**
     * @param  array<string, mixed>|null  $talents
     * @return array<string, mixed>|null
     */
    public static function data(?array $talents): ?array
    {
        if ($talents === null) {
            return null;
        }
        /** @var list<array<string, mixed>> $groups */
        $groups = $talents['groups'];
        /** @var list<array<string, mixed>> $order */
        $order = $talents['order'];
        /** @var list<array<string, mixed>> $respecs */
        $respecs = $talents['respecs'];

        return [
            'groups' => array_map(fn (array $group): array => self::group($group, $group['group'] === $talents['active'], count($groups) > 1, $talents['names']['spell']), $groups),
            'order' => self::order($order),
            'respecs' => self::respecs($respecs, count($groups) > 1),
            'facts' => self::facts($talents['facts'], $respecs, $talents['names']['spell']),
        ];
    }

    /** @return string|array{text: string, href: string} a spell's name linked to its page when the talent has a spell */
    private static function spellCell(string $name, ?int $spell): string|array
    {
        return $spell === null ? $name : ['text' => $name, 'href' => '/spells/'.$spell];
    }

    /**
     * @param  array<string, mixed>  $facts
     * @param  list<array<string, mixed>>  $respecs
     * @param  array<int, string>  $spells
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private static function facts(array $facts, array $respecs, array $spells): array
    {
        /** @var array{same: int, total: int, level: int}|null $sharing */
        $sharing = $facts['sharing'];
        /** @var array{node: int, spell: int|null, taken: int, total: int}|null $least */
        $least = $facts['least'];
        /** @var array{level: int|null, first_read: bool}|null $deep */
        $deep = $facts['first_deep'];
        /** @var array{points: int, longest_s: float, median_s: float}|null $unspent */
        $unspent = $facts['unspent'];
        /** @var array{refunded: int, picks: int} $takenBack */
        $takenBack = $facts['taken_back'];
        /** @var list<array{group: int, seconds: float, sessions: int}> $groupTime */
        $groupTime = $facts['group_time'];
        $costs = array_filter(array_column($respecs, 'cost'), fn (mixed $cost): bool => $cost !== null);
        $rows = [];
        if ($sharing !== null && $sharing['total'] > 0) {
            $rows[] = ['Characters with this exact build at level '.$sharing['level'], Format::number($sharing['same']), 'of '.Format::count($sharing['total'], 'other character').' at that level'];
        }
        $rows[] = ['Respecs', Format::number(count($respecs)), 'recorded'];
        if ($costs !== []) {
            $rows[] = ['Gold spent on respecs', Format::moneyText((int) array_sum($costs)), 'from '.Format::count(count($costs), 'respec').' with a recorded cost'];
        }
        if ($least !== null) {
            $rows[] = ['Least picked talent taken', ($least['spell'] === null ? null : ($spells[$least['spell']] ?? null)) ?? 'Talent '.$least['node'], 'held by '.Format::number($least['taken']).' of '.Format::count($least['total'], 'other character').' of the same class and spec'];
        }
        if ($deep !== null) {
            $rows[] = ['First build with '.TalentFacts::DEEP_POINTS.' points in one tree', $deep['level'] === null ? '' : 'Level '.$deep['level'], $deep['first_read'] ? 'already held when the build was first read' : 'when that build was read'];
        }
        if ($unspent !== null) {
            $rows[] = ['Longest a point sat unspent', Format::duration($unspent['longest_s']), 'of '.Format::count($unspent['points'], 'point').' spent after a level-up'];
            $rows[] = ['Median wait to spend a point', Format::duration($unspent['median_s']), 'of '.Format::count($unspent['points'], 'point')];
        }
        $rows[] = ['Picks refunded within '.TalentFacts::TAKEN_BACK_MINUTES.' minutes', Format::number($takenBack['refunded']), 'of '.Format::count($takenBack['picks'], 'pick').' seen'];
        foreach ($groupTime as $time) {
            $rows[] = ['Play time in talent group '.$time['group'], Format::duration($time['seconds']), 'across '.Format::count($time['sessions'], 'session').' with it active'];
        }

        return [
            'columns' => [['label' => 'Fact'], ['label' => 'Value', 'numeric' => true], ['label' => 'Based on']],
            'rows' => array_map(fn (array $row): array => ['cells' => $row], $rows),
        ];
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  array<int, string>  $spells
     * @return array<string, mixed>
     */
    private static function group(array $group, bool $active, bool $several, array $spells): array
    {
        /** @var list<array{id: int, name: string|null, spent: int}> $tabs */
        $tabs = $group['tabs'];
        $top = max(1, ...array_column($tabs, 'spent'));
        $known = array_flip(array_column($tabs, 'id'));
        $byTab = [];
        /** @var list<array{node: int, entry: int, rank: int, tab: int|null, name: string|null, spell: int|null}> $nodes */
        $nodes = $group['nodes'];
        foreach ($nodes as $node) {
            $byTab[$node['tab'] !== null && isset($known[$node['tab']]) ? $node['tab'] : 0][] = [
                'cells' => [self::spellCell($node['name'] ?? ($node['spell'] === null ? 'Talent '.$node['node'] : 'Spell '.$node['spell']), $node['spell']), (string) $node['rank']],
            ];
        }
        $sections = [];
        foreach ($tabs as $tab) {
            if (isset($byTab[$tab['id']])) {
                $sections[] = ['heading' => $tab['name'] ?? 'Tab '.$tab['id'], 'rows' => $byTab[$tab['id']]];
            }
        }
        if (isset($byTab[0])) {
            $sections[] = ['heading' => $sections === [] ? null : 'Other talents', 'rows' => $byTab[0]];
        }

        return [
            'label' => $several ? 'Group '.$group['group'].($active ? ', active' : '') : null,
            'summary' => Format::count($group['points'], 'point').' spent'.($group['level'] === null ? '' : ' at level '.$group['level']).', read '.Carbon::parse((string) $group['at'])->format('j M Y').'.',
            'tabs' => array_map(fn (array $tab): array => [
                'label' => $tab['name'] ?? 'Tab '.$tab['id'], 'value' => Format::number($tab['spent']), 'share' => $tab['spent'] / $top * 100,
                'class' => $tab['id'] === $group['spec_tab_id'] ? null : 'dim',
            ], array_values(array_filter($tabs, fn (array $tab): bool => $tab['spent'] > 0))),
            'columns' => [['label' => 'Talent'], ['label' => 'Rank', 'numeric' => true]],
            'sections' => $group['layout'] === null ? $sections : [],
            'tree' => $group['layout'] === null ? null : self::tree($group['layout'], $nodes, $tabs, $spells),
        ];
    }

    /**
     * @param  array{nodes: array<int, array{id: int, max: int|null, entries: list<array{entry: int, spell: int|null}>, tab: int|null, x: int, y: int, edges: list<array{to: int, type: string}>}>}  $layout
     * @param  list<array{node: int, entry: int, rank: int}>  $held
     * @param  list<array{id: int, name: string|null}>  $tabs
     * @param  array<int, string>  $spells
     * @return array<string, mixed>
     */
    private static function tree(array $layout, array $held, array $tabs, array $spells): array
    {
        $ranks = [];
        foreach ($held as $node) {
            $ranks[$node['node']] = $node;
        }
        $marks = [];
        foreach ($layout['nodes'] as $id => $node) {
            $rank = $ranks[$id]['rank'] ?? 0;
            $entry = null;
            foreach ($node['entries'] as $candidate) {
                $entry ??= isset($ranks[$id]) && $candidate['entry'] === $ranks[$id]['entry'] ? $candidate : null;
            }
            $names = array_map(fn (array $candidate): string => ($candidate['spell'] === null ? null : ($spells[$candidate['spell']] ?? null)) ?? 'Talent '.$candidate['entry'], $entry === null ? $node['entries'] : [$entry]);
            $name = implode(' or ', $names) ?: 'Talent '.$id;
            $max = $node['max'] ?? 0;
            $marks[$id] = [
                'title' => $rank === 0 ? $name.', not taken' : $name.', rank '.$rank.($max > 0 ? ' of '.$max : ''),
                'text' => $rank > 0 ? (string) $rank : '',
                'class' => $rank === 0 ? 'off' : ($max > 0 && $rank >= $max ? 'full' : 'part'),
                'on' => $rank > 0,
            ];
        }
        $tabNames = [];
        foreach ($tabs as $tab) {
            if ($tab['name'] !== null) {
                $tabNames[$tab['id']] = $tab['name'];
            }
        }

        return TalentTree::geometry($layout, $marks, $tabNames);
    }

    /**
     * @param  list<array<string, mixed>>  $order
     * @return array<string, mixed>
     */
    private static function order(array $order): array
    {
        $rows = array_map(function (array $row): array {
            $level = $row['exact'] || $row['from'] === null || $row['from'] === $row['level']
                ? (string) ($row['level'] ?? '')
                : $row['from'].' to '.$row['level'];

            return ['cells' => [$level, $row['point'] === null ? '' : (string) $row['point'], $row['name'] ?? 'Talent '.$row['node'], (string) $row['rank'], $row['exact'] ? 'Seen in game' : 'Between sessions']];
        }, $order);

        return [
            'columns' => [['label' => 'Level', 'numeric' => true], ['label' => 'Point', 'numeric' => true], ['label' => 'Talent'], ['label' => 'Rank', 'numeric' => true], ['label' => 'Recorded']],
            'rows' => $rows,
            'total' => $rows === [] ? 'No points spent after the build was first read.' : Format::count(count($rows), 'point').' spent after the build was first read.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $respecs
     * @return array<string, mixed>
     */
    private static function respecs(array $respecs, bool $several): array
    {
        $columns = [['label' => 'When'], ['label' => 'Level', 'numeric' => true], ['label' => 'What'], ['label' => 'Points', 'numeric' => true], ['label' => 'Cost', 'numeric' => true]];
        if ($several) {
            array_splice($columns, 2, 0, [['label' => 'Group', 'numeric' => true]]);
        }

        return [
            'columns' => $columns,
            'rows' => array_map(function (array $row) use ($several): array {
                $at = Carbon::parse((string) $row['at'])->utc();
                $cells = [['instant' => $at->toIso8601ZuluString(), 'format' => 'j M Y H:i', 'text' => $at->format('j M Y H:i')], (string) ($row['level'] ?? ''), (RespecKind::tryFrom((string) $row['kind'])?->label() ?? (string) $row['kind']), Format::number($row['moved']), $row['cost'] === null ? '' : Format::moneyText($row['cost'])];
                if ($several) {
                    array_splice($cells, 2, 0, [(string) $row['group']]);
                }

                return ['cells' => $cells];
            }, $respecs),
        ];
    }
}
