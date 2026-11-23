<?php

namespace App\Console\Commands;

use App\Support\ClientSpells;
use App\Support\FeatMeasure;
use App\Support\FeatProof;
use App\Support\FeatProofCheck;
use App\Support\FeatRules;
use App\Support\PlayerClass;
use App\Support\Role;
use App\Support\TalentTree;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** The proof map of the class, talent tree and role feats against the client's spell tables (docs/FEAT-PROOF.md). */
#[Signature("feats:proof {tables : Folder of the client's tables as CSV} {--write : Rewrite the generated part of docs/FEAT-PROOF.md}")]
#[Description('Proves every class, talent tree and role feat can reach its legend on the spell tables of the client')]
class FeatProofCommand extends Command
{
    private const string START = '<!-- feats:proof start -->';

    private const string END = '<!-- feats:proof end -->';

    private const string HEADER = '| Feat | For | Counts | Spells on this client | Legend | Fastest possible, hours | Result |';

    private const string SEPARATOR = '| --- | --- | --- | --- | --- | --- | --- |';

    public function handle(): int
    {
        $tables = (string) $this->argument('tables');
        if (! str_starts_with($tables, '/')) {
            $tables = base_path($tables);
        }
        $missing = ClientSpells::missingFile($tables);
        if ($missing !== null) {
            $this->components->error('The client tables are missing '.$missing.'.');

            return self::FAILURE;
        }

        $checks = (new FeatProof(new ClientSpells($tables)))->run();
        foreach ($checks as $check) {
            if ($check->reason !== null) {
                $this->line($check->feat->label().' ('.$this->scope($check).'): '.$check->reason);
            }
        }
        $this->components->info($this->summary($checks));

        if ($this->option('write') && $this->write($this->markdown($checks)) === self::FAILURE) {
            return self::FAILURE;
        }

        return $this->failed($checks) === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param  list<FeatProofCheck>  $checks */
    private function summary(array $checks): string
    {
        $castRules = count(array_filter(FeatRules::RULES, fn (array $rule): bool => $rule[1] === FeatMeasure::Casts));
        $classChecks = count(array_filter($checks, fn (FeatProofCheck $check): bool => $check->measure === FeatMeasure::Casts));

        return sprintf('%d feats, %d cast rules, %d class checks, %d failed.', count(FeatRules::RULES), $castRules, $classChecks, $this->failed($checks));
    }

    /** @param  list<FeatProofCheck>  $checks */
    private function failed(array $checks): int
    {
        return count(array_filter($checks, fn (FeatProofCheck $check): bool => $check->reason !== null));
    }

    private function scope(FeatProofCheck $check): string
    {
        $scope = $check->scope;

        return match (true) {
            $scope instanceof PlayerClass => $scope->name,
            $scope instanceof TalentTree => $scope->class()->name.', '.$scope->names()[0],
            $scope instanceof Role => $scope->name.', '.$check->holder->name,
        };
    }

    /**
     * @param  list<FeatProofCheck>  $checks
     */
    private function markdown(array $checks): string
    {
        $lines = [$this->summary($checks), ''];
        foreach (PlayerClass::cases() as $class) {
            $lines[] = '## '.$class->name;
            $lines[] = '';
            $lines[] = $this->markdownTable($this->held($checks, $class));
            $lines[] = '';
        }
        foreach (Role::cases() as $role) {
            $lines[] = '## '.$role->name;
            $lines[] = '';
            $lines[] = $this->roleTable($role, $checks);
            $lines[] = '';
        }

        return implode("\n", array_slice($lines, 0, -1));
    }

    /**
     * @param  list<FeatProofCheck>  $checks
     * @return list<FeatProofCheck> the class's class and talent tree checks, in rule order
     */
    private function held(array $checks, PlayerClass $class): array
    {
        return array_values(array_filter($checks, fn (FeatProofCheck $check): bool => match (true) {
            $check->scope instanceof PlayerClass => $check->scope === $class,
            $check->scope instanceof TalentTree => $check->scope->class() === $class,
            default => false,
        }));
    }

    /**
     * @param  list<FeatProofCheck>  $checks
     */
    private function markdownTable(array $checks): string
    {
        $lines = [self::HEADER, self::SEPARATOR];
        foreach ($checks as $check) {
            $lines[] = $this->row($check, $this->scope($check));
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<FeatProofCheck>  $checks
     */
    private function roleTable(Role $role, array $checks): string
    {
        $lines = [self::HEADER, self::SEPARATOR];
        foreach ($checks as $check) {
            if ($check->scope === $role) {
                $lines[] = $this->row($check, $check->holder->name);
            }
        }

        return implode("\n", $lines);
    }

    private function row(FeatProofCheck $check, string $for): string
    {
        $patterns = $check->patterns;
        $counts = $check->measure === FeatMeasure::Casts
            ? 'casts of '.implode(', ', $patterns)
            : $this->sentence($check->measure);

        return '| '.implode(' | ', [
            $check->feat->label(),
            $for,
            $counts,
            $this->spells($check),
            number_format($check->feat->thresholds()[4]),
            $check->hours === null ? '-' : number_format($check->hours, 1),
            $check->reason ?? 'in reach',
        ]).' |';
    }

    private function spells(FeatProofCheck $check): string
    {
        $parts = [];
        foreach ($check->spells as $spell) {
            $bits = [];
            if ($spell->level !== 0) {
                $bits[] = 'level '.$spell->level;
            }
            if ($spell->cooldown !== 0.0) {
                $bits[] = $this->seconds($spell->cooldown).'s cooldown';
            }
            $parts[] = $bits === [] ? $spell->name : $spell->name.' ('.implode(', ', $bits).')';
        }

        return $parts === [] ? '-' : implode(', ', $parts);
    }

    private function seconds(float $cooldown): string
    {
        return $cooldown == round($cooldown) ? (string) round($cooldown) : (string) $cooldown;
    }

    private function sentence(FeatMeasure $measure): string
    {
        return match ($measure) {
            FeatMeasure::Hours => 'played time of the sessions that logged in with this spec; no ceiling',
            FeatMeasure::DungeonRuns, FeatMeasure::DungeonBosses, FeatMeasure::RaidBosses, FeatMeasure::DeathlessRuns => 'the same count as the general feat, over the sessions played in this role; repeatable without limit',
            FeatMeasure::Damage, FeatMeasure::Healing, FeatMeasure::DamageTaken => 'the client\'s damage meter totals (`DM` lines) summed over the sessions played in this role; no ceiling',
            FeatMeasure::BestDamage, FeatMeasure::BestHealing, FeatMeasure::BestDamageTaken => 'the largest single-fight total of the client\'s damage meter; the legend is an estimate of a long raid boss fight at level 60, not measured on this server',
            default => '',
        };
    }

    private function write(string $markdown): int
    {
        $path = base_path('docs/FEAT-PROOF.md');
        $doc = is_file($path) ? (string) file_get_contents($path) : "# Feat proof map\n\n".self::START."\n".self::END."\n";
        $start = strpos($doc, self::START);
        $end = strpos($doc, self::END);
        if ($start === false || $end === false || $end < $start) {
            $this->components->error('docs/FEAT-PROOF.md lacks the feats:proof markers.');

            return self::FAILURE;
        }
        file_put_contents($path, substr($doc, 0, $start + strlen(self::START))."\n".$markdown."\n".substr($doc, $end));

        return self::SUCCESS;
    }
}
