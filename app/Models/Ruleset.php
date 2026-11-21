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
 * @property int|null $mode_record
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

    /**
     * The identity of a combination of signals. A session with no game mode record has the five-part key it always
     * had, so the rulesets stored before the record was read keep their rows.
     *
     * @param  array{preset: int|null, hardcore: bool|null, war_mode: string, xp_off: bool|null, game_mode: int|null, mode_record: int|null}  $signals
     */
    public static function keyFor(array $signals): string
    {
        $key = implode('|', array_map(fn ($value): string => match (true) {
            $value === null => '-',
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        }, [$signals['preset'], $signals['hardcore'], $signals['war_mode'], $signals['xp_off'], $signals['game_mode']]));

        return $signals['mode_record'] === null ? $key : $key.'|r'.$signals['mode_record'];
    }

    /**
     * The path segment of a ruleset: every signal that was reported has a part, so two rulesets never share one.
     * `p1-std-pve-xp-g1` is preset 1, not hardcore, war mode off, experience on, game mode 1. The slug is an
     * identity, not a name: it keeps the form it had, so the address of every ruleset keeps working.
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
            $this->mode_record === null ? null : 'r'.$this->mode_record,
        ];
        $parts = array_values(array_filter($parts));

        return $parts === [] ? self::UNKNOWN_SLUG : implode('-', $parts);
    }

    /** The label of a ruleset that no checked signal names yet. */
    public const string UNIDENTIFIED_LABEL = 'Not identified';

    /** The ruleset of WoW Forever this row is, or null while no checked signal names it. */
    public function kind(): ?RulesetKind
    {
        return RulesetKind::of($this->hardcore, $this->mode_record);
    }

    /** The name a page shows: the ruleset's name, or "Not identified" for a row no checked signal names. */
    public function label(): string
    {
        return $this->kind()?->label() ?? self::UNIDENTIFIED_LABEL;
    }
}
