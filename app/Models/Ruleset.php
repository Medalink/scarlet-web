<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A ruleset of WoW Forever, or the one for characters whose kind is not known yet (docs/DESIGN.md, Rulesets). The
 * rows from before a ruleset was its kind are kept, each with `merged_into` naming the row that took its sessions.
 *
 * @property int $id
 * @property string $key
 * @property int|null $preset
 * @property bool|null $hardcore
 * @property string $war_mode
 * @property bool|null $xp_off
 * @property int|null $game_mode
 * @property int|null $mode_record
 * @property string|null $kind
 * @property int|null $merged_into
 */
class Ruleset extends Model
{
    public const string WAR_MODE_UNKNOWN = 'unknown';

    public const string WAR_MODE_ON = 'on';

    public const string WAR_MODE_OFF = 'off';

    /** The slug of a row from before kinds none of whose signals the client reported. */
    public const string UNKNOWN_SLUG = 'unknown';

    /** The key and slug of the row for characters whose kind is not known yet. */
    public const string UNIDENTIFIED_KEY = 'unidentified';

    /** The label of the ruleset of characters whose kind is not known yet. */
    public const string UNIDENTIFIED_LABEL = 'Not identified';

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['hardcore' => 'boolean', 'xp_off' => 'boolean'];
    }

    /** The key of the row for a kind, or for no kind. Rows from before kinds are keyed by their signals instead. */
    public static function keyFor(?RulesetKind $kind): string
    {
        return $kind === null ? self::UNIDENTIFIED_KEY : $kind->value;
    }

    /** @return list<string> the keys of the rows that are rulesets: one for each kind and the unidentified one */
    public static function kindKeys(): array
    {
        return [...array_map(fn (RulesetKind $kind): string => $kind->value, RulesetKind::cases()), self::UNIDENTIFIED_KEY];
    }

    /** False for a row from before a ruleset was its kind: such a row has no sessions once merged. */
    public function isKindRow(): bool
    {
        return in_array($this->key, self::kindKeys(), true);
    }

    /**
     * The path segment of a ruleset: its kind (`normal`, `roleplay-pvp`) or `unidentified`. A row from before kinds keeps
     * the slug it had, built from every signal it recorded (`p1-std-pve-xp-g1-r15-kn`), so the address of each old
     * ruleset can still be matched and sent to the ruleset that took it over.
     */
    public function slug(): string
    {
        return $this->isKindRow() ? $this->key : $this->slugOf(withKind: true);
    }

    /**
     * The slug of a row from before kinds without its kind: what an address from before the kind was part of a
     * ruleset's identity says.
     */
    public function slugWithoutKind(): string
    {
        return $this->slugOf(withKind: false);
    }

    private function slugOf(bool $withKind): string
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
            $withKind ? $this->storedKind()?->slugToken() : null,
        ];
        $parts = array_values(array_filter($parts));

        return $parts === [] ? self::UNKNOWN_SLUG : implode('-', $parts);
    }

    /** The ruleset of WoW Forever this row is, or null while neither a signal nor its characters name it. */
    public function kind(): ?RulesetKind
    {
        return RulesetKind::of($this->hardcore, $this->mode_record) ?? $this->storedKind();
    }

    /** The kind the characters filed under this row have (`rulesets.kind`). */
    private function storedKind(): ?RulesetKind
    {
        return $this->kind === null ? null : RulesetKind::tryFrom($this->kind);
    }

    /** The name a page shows: the ruleset's name, or "Not identified" while its kind is not known. */
    public function label(): string
    {
        return $this->kind()?->label() ?? self::UNIDENTIFIED_LABEL;
    }
}
