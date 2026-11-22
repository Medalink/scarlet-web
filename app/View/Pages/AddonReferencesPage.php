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

    /** @var array<string, string> the amounts of a fishing claim, by the name the page gives each */
    private const array FISHING = ['casts' => 'Casts', 'landed' => 'Catches', 'items' => 'Fish'];

    /**
     * One module per addon and kind, a pair of rows per account total it reads: the addon's own figures and the sum
     * of Scarlet's rows and the counted claims of the characters that carry it. A figure more than 2.5% above the addon's
     * is listed in notes.
     *
     * @param  list<array{origin: string, store: string, kind: string, from: int, to: int, amounts: array<string, int>, sum: array{casts: int, landed: int, items: int}}>  $references
     * @return list<array{heading: string, table: array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}, notes: list<string>}>
     */
    public static function modules(array $references): array
    {
        $groups = [];
        foreach ($references as $reference) {
            if (AddonKind::tryFrom($reference['kind']) === AddonKind::Fishing) {
                $groups[$reference['origin']][] = $reference;
            }
        }

        $modules = [];
        foreach ($groups as $origin => $list) {
            $name = AddonOrigin::tryFrom((string) $origin)?->label() ?? (string) $origin;
            $rows = $notes = [];
            foreach ($list as $reference) {
                $since = self::day($reference['from']);
                $asOf = self::day($reference['to']);
                $rows[] = ['cells' => [$name, $since, $asOf, ...self::figures($reference['amounts'])]];
                $rows[] = ['class' => 'me', 'cells' => ['Scarlet and counted addons', $since, $asOf, ...self::figures($reference['sum'])]];
                foreach (self::FISHING as $metric => $label) {
                    $claimed = $reference['amounts'][$metric] ?? null;
                    if ($claimed !== null && $reference['sum'][$metric] > $claimed * self::TOLERANCE) {
                        $above = $claimed > 0 ? ', '.Format::percent($reference['sum'][$metric] / $claimed - 1, 1).' above' : '';
                        $notes[] = $label.': '.Format::number($reference['sum'][$metric]).' against '.Format::number($claimed).' in '.$name.$above.'.';
                    }
                }
            }
            $modules[] = [
                'heading' => $name.' lifetime fishing',
                'table' => [
                    'columns' => [['label' => 'Source'], ['label' => 'Since'], ['label' => 'As of'], ...array_map(fn (string $label): array => ['label' => $label, 'numeric' => true], array_values(self::FISHING))],
                    'rows' => $rows,
                ],
                'notes' => $notes,
            ];
        }

        return $modules;
    }

    /**
     * @param  array<string, int>  $amounts
     * @return list<string>
     */
    private static function figures(array $amounts): array
    {
        return array_map(fn (string $metric): string => isset($amounts[$metric]) ? Format::number($amounts[$metric]) : '', array_keys(self::FISHING));
    }

    private static function day(int $epoch): string
    {
        return Carbon::createFromTimestampUTC($epoch)->format('j M Y');
    }
}
