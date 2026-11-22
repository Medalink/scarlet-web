<?php

namespace App\Console\Commands;

use App\Actions\Ingest\AuditAddonClaims;
use App\Models\Character;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('addons:audit {character? : Name-Surname; every character when left out}')]
#[Description('Checks the stored addon claims, prints what each character holds per kind and exits 1 on a violation')]
class AddonsAuditCommand extends Command
{
    public function handle(AuditAddonClaims $audit): int
    {
        $name = $this->argument('character');
        $character = null;
        if (is_string($name)) {
            $character = Character::query()->where('name_key', Character::keyFor($name))->first();
            if ($character === null) {
                $this->error("No character {$name}.");

                return self::FAILURE;
            }
        }

        $report = $audit->handle($character);

        $this->table(
            ['Character', 'Scope', 'Kind', 'Claims', 'Amounts'],
            array_map(fn (array $total): array => [
                $total['character'],
                $total['scope'],
                $total['kind'],
                $total['claims'],
                $this->amounts($total['amounts']),
            ], $report->totals),
        );
        foreach ($report->explain as $table) {
            $this->line("{$table['character']} {$table['kind']}");
            $this->table(
                ['Status', 'Claims', 'Claimed', 'Counted', 'Rule'],
                array_map(fn (array $status): array => [$status['status'], $status['claims'], $this->amounts($status['claimed']), $this->amounts($status['counted']), $status['rule']], $table['statuses']),
            );
            if ($table['scarlet'] !== null && $table['total'] !== null) {
                $this->line('Scarlet '.$this->amounts($table['scarlet']).'  Total '.$this->amounts($table['total']));
            }
        }
        foreach ($report->notes as $note) {
            $this->line("note: {$note}");
        }
        foreach ($report->violations as $violation) {
            $this->error("violation: {$violation}");
        }
        $this->line($report->passes() ? 'Addon audit passed.' : count($report->violations).' violation(s).');

        return $report->passes() ? self::SUCCESS : self::FAILURE;
    }

    /** @param  array<string, int|float>  $amounts */
    private function amounts(array $amounts): string
    {
        $order = array_flip(['casts', 'landed', 'items', 'unknown']);
        uksort($amounts, fn (string $a, string $b): int => [$order[$a] ?? count($order), $a] <=> [$order[$b] ?? count($order), $b]);

        return implode(' ', array_map(fn (string $metric, int|float $sum): string => "{$metric}={$sum}", array_keys($amounts), $amounts));
    }
}
