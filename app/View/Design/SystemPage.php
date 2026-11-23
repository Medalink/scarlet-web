<?php

namespace App\View\Design;

use App\Actions\Public\Character\CharacterRates;
use App\Actions\Rollups\RebuildCharacterRates;
use App\Models\Character;
use Illuminate\Support\Facades\DB;

/** The invented data of /design/system: one of everything, from the numbers of src/system.html in the design. */
final class SystemPage
{
    /**
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        return ['heat' => self::heat(5), 'days' => self::days(), 'area' => self::area(), 'recap' => SystemRecap::recap(), 'rates' => self::rates()];
    }

    /**
     * The compare strips of a character of the largest band of this database, read the way a character page reads them but drawn from any group size; null on an empty database.
     *
     * @return array<string, mixed>|null
     */
    private static function rates(): ?array
    {
        $row = DB::table('character_rates_bracket')->where('metric', RebuildCharacterRates::KILLS)->orderByDesc('n')->first();
        if ($row === null) {
            return null;
        }
        $characters = Character::query()->where('class', $row->class)->whereBetween('level', [$row->bracket, $row->bracket + 9])->get();
        foreach ($characters as $character) {
            $rates = app(CharacterRates::class)->handle($character, 1);
            if ($rates !== null) {
                return $rates;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function days(): array
    {
        $rows = [];
        for ($i = 0; $i < 30; $i++) {
            $rows[] = ['Day '.($i + 1), [round(0.4 + (($i * 7) % 5) * 0.5, 1), round((($i * 3) % 4) * 0.4, 1), round((($i * 5) % 3) * 0.3, 1)]];
        }

        return ['series' => [['Sable', '--s-1'], ['Odile', '--s-2'], ['Other', '--s-0']], 'rows' => $rows, 'unit' => 'h', 'every' => 7];
    }

    /**
     * @return array<string, mixed>
     */
    private static function area(): array
    {
        $points = [];
        for ($i = 0; $i < 30; $i++) {
            $points[] = 20 + $i * 3 + (($i * 11) % 7) * 2;
        }

        return ['points' => $points, 'xlabels' => [[0, 'Day 1'], [14, 'Day 15'], [29, 'Day 30']], 'unit' => 'g', 'name' => 'Gold held'];
    }

    /**
     * Seven days by 24 hours of evening-weighted play, as the design's heat grid draws it.
     *
     * @return list<int>
     */
    private static function heat(int $seed): array
    {
        $state = $seed;
        $next = function () use (&$state): float {
            $state = ($state * 1664525 + 1013904223) & 0xFFFFFFFF;

            return $state / 4294967296;
        };
        $cells = [];
        for ($d = 0; $d < 7; $d++) {
            for ($h = 0; $h < 24; $h++) {
                $evening = exp(-((($h - 20.5) / 2.6) ** 2));
                $weekend = $d > 4 ? exp(-((($h - 14) / 4) ** 2)) * 0.8 : 0;
                $x = ($evening + $weekend) * (0.55 + $next() * 0.75) * ($d === 2 ? 0.5 : 1);
                $cells[] = match (true) {
                    $x < 0.12 => 0,
                    $x < 0.3 => 1,
                    $x < 0.5 => 2,
                    $x < 0.72 => 3,
                    $x < 0.95 => 4,
                    default => 5,
                };
            }
        }

        return $cells;
    }
}
