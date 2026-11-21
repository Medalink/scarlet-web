<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A combination of the signals that make two characters comparable (docs/DESIGN.md, Rulesets).
 *
 * @property int $id
 * @property string $key
 */
class Ruleset extends Model
{
    public const string WAR_MODE_UNKNOWN = 'unknown';

    public $timestamps = false;

    protected $guarded = [];

    /** @param  array{preset: int|null, hardcore: bool|null, war_mode: string, xp_off: bool|null, game_mode: int|null}  $signals */
    public static function keyFor(array $signals): string
    {
        return implode('|', array_map(fn ($value): string => match (true) {
            $value === null => '-',
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        }, [$signals['preset'], $signals['hardcore'], $signals['war_mode'], $signals['xp_off'], $signals['game_mode']]));
    }
}
