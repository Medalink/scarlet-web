<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A combination of the signals that make two characters comparable (docs/DESIGN.md, Rulesets).
 *
 * @property int $id
 * @property string $key
 * @property int|null $preset
 * @property bool|null $hardcore
 * @property string $war_mode
 * @property bool|null $xp_off
 * @property int|null $game_mode
 */
class Ruleset extends Model
{
    public const string WAR_MODE_UNKNOWN = 'unknown';

    public const string WAR_MODE_ON = 'on';

    public const string WAR_MODE_OFF = 'off';

    /** The slug of a ruleset none of whose signals the client reported. */
    public const string UNKNOWN_SLUG = 'unknown';

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['hardcore' => 'boolean', 'xp_off' => 'boolean'];
    }

    /** @param  array{preset: int|null, hardcore: bool|null, war_mode: string, xp_off: bool|null, game_mode: int|null}  $signals */
    public static function keyFor(array $signals): string
    {
        return implode('|', array_map(fn ($value): string => match (true) {
            $value === null => '-',
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        }, [$signals['preset'], $signals['hardcore'], $signals['war_mode'], $signals['xp_off'], $signals['game_mode']]));
    }

    /**
     * The path segment of a ruleset: every signal that was reported has a part, so two rulesets never share one.
     * `p1-std-pve-xp-g1` is preset 1, not hardcore, war mode off, experience on, game mode 1.
     */
    public function slug(): string
    {
        $parts = [
            $this->preset === null ? null : 'p'.$this->preset,
            match ($this->hardcore) {
                true => 'hc',
                false => 'std',
                default => null,
            },
            match ($this->war_mode) {
                self::WAR_MODE_ON => 'pvp',
                self::WAR_MODE_OFF => 'pve',
                default => null,
            },
            match ($this->xp_off) {
                true => 'noxp',
                false => 'xp',
                default => null,
            },
            $this->game_mode === null ? null : 'g'.$this->game_mode,
        ];
        $parts = array_values(array_filter($parts));

        return $parts === [] ? self::UNKNOWN_SLUG : implode('-', $parts);
    }

    /** The label of a ruleset none of whose signals were recorded. */
    public const string UNRECORDED_LABEL = 'Rules not recorded';

    /**
     * The name a page shows: "Hardcore, PvP, Experience off". The recording carries the preset and the game mode as
     * bare numbers and nothing that names them, so they stay out of the label; the slug still tells them apart.
     */
    public function label(): string
    {
        $parts = [
            match ($this->hardcore) {
                true => 'Hardcore',
                false => 'Standard',
                default => null,
            },
            match ($this->war_mode) {
                self::WAR_MODE_ON => 'PvP',
                self::WAR_MODE_OFF => 'PvE',
                default => null,
            },
            match ($this->xp_off) {
                true => 'Experience off',
                false => 'Experience on',
                default => null,
            },
        ];
        $parts = array_values(array_filter($parts));

        return $parts === [] ? self::UNRECORDED_LABEL : implode(', ', $parts);
    }
}
