<?php

namespace App\View\Pages;

use App\Support\RespecKind;
use App\View\Format;
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
            'groups' => array_map(fn (array $group): array => self::group($group, $group['group'] === $talents['active'], count($groups) > 1), $groups),
            'order' => self::order($order),
            'respecs' => self::respecs($respecs, count($groups) > 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    private static function group(array $group, bool $active, bool $several): array
    {
        /** @var list<array{id: int, name: string|null, spent: int}> $tabs */
        $tabs = $group['tabs'];
        $top = max(1, ...array_column($tabs, 'spent'));
        $known = array_flip(array_column($tabs, 'id'));
        $byTab = [];
        /** @var list<array{node: int, rank: int, tab: int|null, name: string|null, spell: int|null}> $nodes */
        $nodes = $group['nodes'];
        foreach ($nodes as $node) {
            $byTab[$node['tab'] !== null && isset($known[$node['tab']]) ? $node['tab'] : 0][] = [
                'cells' => [$node['name'] ?? ($node['spell'] === null ? 'Node '.$node['node'] : 'Spell '.$node['spell']), (string) $node['rank']],
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
            'sections' => $sections,
        ];
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

            return ['cells' => [$level, $row['point'] === null ? '' : (string) $row['point'], $row['name'] ?? 'Node '.$row['node'], (string) $row['rank'], $row['exact'] ? 'Seen in game' : 'Between sessions']];
        }, $order);

        return [
            'columns' => [['label' => 'Level', 'numeric' => true], ['label' => 'Point', 'numeric' => true], ['label' => 'Talent'], ['label' => 'Rank', 'numeric' => true], ['label' => 'Recorded']],
            'rows' => $rows,
            'total' => $rows === [] ? 'No point was taken after the first time the build was read.' : Format::count(count($rows), 'point').' taken after the first time the build was read.',
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
                $cells = [Carbon::parse((string) $row['at'])->format('j M Y H:i'), (string) ($row['level'] ?? ''), (RespecKind::tryFrom((string) $row['kind'])?->label() ?? (string) $row['kind']), Format::number($row['moved']), $row['cost'] === null ? '' : Format::moneyText($row['cost'])];
                if ($several) {
                    array_splice($cells, 2, 0, [(string) $row['group']]);
                }

                return ['cells' => $cells];
            }, $respecs),
        ];
    }
}
