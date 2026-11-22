<?php

namespace App\Console\Commands;

use App\Support\FeatMeasure;
use App\Support\FeatRules;
use App\Support\NameKind;
use App\Support\PlayerClass;
use App\Support\Role;
use App\Support\TalentTree;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** The spell names each cast rule looks for and the ones the names table does not hold, so a spell the server renamed shows up. */
#[Signature('feats:spells {--missing : List only the cast rules with a spell name the names table does not hold}')]
#[Description('Lists, per cast rule, its spell names and the ones no spell of the names table carries')]
class FeatSpellsCommand extends Command
{
    public function handle(): int
    {
        $names = DB::table('names')->where('kind', NameKind::Spell->value)->distinct()->pluck('name')->all();
        $rows = [];
        $castRules = 0;
        $silent = 0;
        foreach (FeatRules::RULES as $feat => [$scope, $measure, $patterns]) {
            if ($measure !== FeatMeasure::Casts) {
                continue;
            }
            $castRules++;
            $unknown = array_values(array_filter($patterns, fn (string $pattern): bool => ! $this->named($pattern, $names)));
            if (count($unknown) === count($patterns)) {
                $silent++;
            }
            if ($unknown !== [] || ! $this->option('missing')) {
                $rows[] = [$feat, $this->scope($scope), implode('; ', $patterns), implode('; ', $unknown)];
            }
        }

        $this->table(['feat', 'scope', 'spells', 'not in the names table'], $rows);
        $this->components->info("$silent of $castRules cast rules match no spell name in the names table.");

        return self::SUCCESS;
    }

    /** @param  array<array-key, mixed>  $names */
    private function named(string $pattern, array $names): bool
    {
        foreach ($names as $name) {
            if (Str::is($pattern, (string) $name, true)) {
                return true;
            }
        }

        return false;
    }

    private function scope(PlayerClass|TalentTree|Role $scope): string
    {
        return match (true) {
            $scope instanceof PlayerClass => $scope->name,
            $scope instanceof TalentTree => $scope->class()->name.' / '.$scope->names()[0],
            $scope instanceof Role => 'Role: '.$scope->name,
        };
    }
}
