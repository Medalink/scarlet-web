<?php

namespace App\Support;

/**
 * The shared reference tables whose rows every uploader writes and every viewer reads (docs/ROWS.md): the value
 * is the kind a vote row carries. A field is voted on by the installs that read it (`App\Actions\Ingest\VoteSharedRows`),
 * so no one install sets what a page serves.
 */
enum SharedKind: string
{
    case Item = 'item';
    case Zone = 'zone';
    case Spell = 'spell';
    case Recipe = 'recipe';

    public const string INT = 'int';

    public const string TEXT = 'text';

    public const string FLAG = 'flag';

    public const string REAGENTS = 'reagents';

    public function table(): string
    {
        return match ($this) {
            self::Item => 'items',
            self::Zone => 'zones',
            self::Spell => 'spells',
            self::Recipe => 'recipes',
        };
    }

    public function key(): string
    {
        return match ($this) {
            self::Item => 'item_id',
            self::Zone => 'id',
            self::Spell => 'spell_id',
            self::Recipe => 'recipe_spell_id',
        };
    }

    /** Whether a displaced row is kept in the row's `previous` column. */
    public function keepsPrevious(): bool
    {
        return $this === self::Item || $this === self::Zone;
    }

    /** Whether the table has a `seen_at` column, the server time of the decode that last changed the row. */
    public function stampsSeen(): bool
    {
        return $this === self::Spell || $this === self::Recipe;
    }

    /**
     * Every field of the kind that is voted on and its type. A field the table has no column for (the reagents of a
     * recipe, which live in `recipe_reagents`) is voted on all the same.
     *
     * @return array<string, string>
     */
    public function fields(): array
    {
        return match ($this) {
            self::Item => [
                'class_id' => self::INT, 'subclass_id' => self::INT, 'equip_loc' => self::TEXT, 'item_level' => self::INT,
                'required_level' => self::INT, 'sell_price' => self::INT, 'bind_type' => self::INT, 'stack' => self::INT,
                'quality' => self::INT, 'tooltip' => self::TEXT,
            ],
            self::Zone => ['name' => self::TEXT, 'instance_type' => self::TEXT, 'instance_id' => self::INT],
            self::Spell => [
                'icon_id' => self::INT, 'cast_ms' => self::INT, 'min_range' => self::INT, 'max_range' => self::INT,
                'costs' => self::TEXT, 'passive' => self::FLAG, 'description' => self::TEXT,
            ],
            self::Recipe => [
                'output_item_id' => self::INT, 'min_quantity' => self::INT, 'max_quantity' => self::INT,
                'optional_slots' => self::INT, 'type' => self::INT, 'reagents' => self::REAGENTS,
            ],
        };
    }

    /**
     * One value in the form a vote and a stored column are compared in, or null for no value: an absent field, and a
     * text that is empty, carry no vote.
     */
    public static function normalize(string $type, mixed $value): int|string|bool|null
    {
        return match (true) {
            $value === null => null,
            $type === self::INT => is_numeric($value) ? (int) $value : null,
            $type === self::FLAG => is_bool($value) ? $value : ($value === 't' || $value === '1' || $value === 1 ? true : ($value === 'f' || $value === '0' || $value === 0 ? false : null)),
            default => is_scalar($value) && (string) $value !== '' ? (string) $value : null,
        };
    }
}
