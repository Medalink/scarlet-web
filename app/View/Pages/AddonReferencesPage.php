<?php

namespace App\View\Pages;

use App\Support\AddonKind;
use App\Support\AddonOrigin;
use App\View\Format;
use Illuminate\Support\Carbon;

/** The owner's view of the account-scope addon claims (docs/DESIGN.md, Addon counts, rule 7), formatted from App\Actions\Account\ShowAccountReferences. */
final class AddonReferencesPage
{
    private const float TOLERANCE = 1.025;

    /** @var array<string, array{title: string, figures: array<string, string>}> the kinds the page shows, each with its heading ending and the amounts it names */
    private const array KINDS = [
        AddonKind::Fishing->value => ['title' => 'lifetime fishing', 'figures' => ['casts' => 'Casts', 'landed' => 'Catches', 'items' => 'Fish']],
        AddonKind::Herb->value => ['title' => 'lifetime herb gathers', 'figures' => ['attempts' => 'Attempts']],
        AddonKind::Ore->value => ['title' => 'lifetime ore gathers', 'figures' => ['attempts' => 'Attempts']],
    ];

    /**
     * One module per addon and kind, a pair of rows per account total it reads: the addon's own figures and the sum
     * of Scarlet's rows and the counted claims of the characters that carry it. A figure more than 2.5% above the addon's
     * is listed in notes.
     *
     * @param  list<array{origin: string, store: string, kind: string, from: int, to: int, amounts: array<string, int>, sum: array<string, int>}>  $references
     * @return list<array{heading: string, table: array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}, notes: list<string>}>
     */
    public static function modules(array $references): array
    {
        $modules = [];
        foreach (self::KINDS as $kind => $shape) {
            $groups = [];
            foreach ($references as $reference) {
                if ($reference['kind'] === $kind) {
                    $groups[$reference['origin']][] = $reference;
                }
            }
            foreach ($groups as $origin => $list) {
                $modules[] = self::module($shape, (string) $origin, $list);
            }
        }

        return $modules;
    }

    /**
     * @param  array{title: string, figures: array<string, string>}  $shape
     * @param  list<array{origin: string, store: string, kind: string, from: int, to: int, amounts: array<string, int>, sum: array<string, int>}>  $list
     * @return array{heading: string, table: array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}, notes: list<string>}
     */
    private static function module(array $shape, string $origin, array $list): array
    {
        $name = AddonOrigin::tryFrom($origin)?->label() ?? $origin;
        $rows = $notes = [];
        foreach ($list as $reference) {
            $since = self::day($reference['from']);
            $asOf = self::day($reference['to']);
            $rows[] = ['cells' => [$name, $since, $asOf, ...self::figures($reference['amounts'], $shape['figures'])]];
            $rows[] = ['class' => 'me', 'cells' => ['Scarlet and counted addons', $since, $asOf, ...self::figures($reference['sum'], $shape['figures'])]];
            foreach ($shape['figures'] as $metric => $label) {
                $claimed = $reference['amounts'][$metric] ?? null;
                $total = $reference['sum'][$metric] ?? 0;
                if ($claimed !== null && $total > $claimed * self::TOLERANCE) {
                    $above = $claimed > 0 ? ', '.Format::percent($total / $claimed - 1, 1).' above' : '';
                    $notes[] = $label.': '.Format::number($total).' against '.Format::number($claimed).' in '.$name.$above.'.';
                }
            }
        }

        return [
            'heading' => $name.' '.$shape['title'],
            'table' => [
                'columns' => [['label' => 'Source'], ['label' => 'Since'], ['label' => 'As of'], ...array_map(fn (string $label): array => ['label' => $label, 'numeric' => true], array_values($shape['figures']))],
                'rows' => $rows,
            ],
            'notes' => $notes,
        ];
    }

    /**
     * @param  array<string, int>  $amounts
     * @param  array<string, string>  $figures
     * @return list<string>
     */
    private static function figures(array $amounts, array $figures): array
    {
        return array_map(fn (string $metric): string => isset($amounts[$metric]) ? Format::number($amounts[$metric]) : '', array_keys($figures));
    }

    private static function day(int $epoch): string
    {
        return Carbon::createFromTimestampUTC($epoch)->format('j M Y');
    }
}
