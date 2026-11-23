<?php

namespace Database\Seeders\Population;

use App\Models\Install;
use App\Support\Database\BinaryValue;
use Database\Factories\InstallFactory;
use Illuminate\Support\Facades\DB;

/**
 * Who the population is and which installs carry it. The plan is built from fixed keys; the only thing read from the
 * database is the names of characters that are not the population's, which it never reuses.
 */
final class Roster
{
    private static ?PopulationPlan $plan = null;

    public static function plan(): PopulationPlan
    {
        return self::$plan ??= new PopulationPlan(
            (int) config('scarlet.seed.characters_per_class'),
            self::foreignNames(),
        );
    }

    /** The label of the install at a place of the plan, which names its key. */
    public static function label(int $place): string
    {
        return PopulationPlan::accountInstall($place) ? 'account-'.$place : 'shared-'.$place;
    }

    public static function install(int $place): ?Install
    {
        return Install::query()
            ->whereRaw('public_key = ?', [BinaryValue::bind(InstallFactory::keyFor(self::label($place)))])
            ->first();
    }

    /** @return list<string> */
    private static function foreignNames(): array
    {
        $own = [];

        for ($place = 0; $place < PopulationPlan::places((int) config('scarlet.seed.characters_per_class')); $place++) {
            $install = self::install($place);
            if ($install !== null) {
                $own[] = $install->id;
            }
        }

        return DB::table('characters')
            ->where(fn ($query) => $query->whereNull('first_install_id')->orWhereNotIn('first_install_id', $own))
            ->pluck('name_key')
            ->map(fn ($key): string => (string) $key)
            ->all();
    }
}
