<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

/** One source the loot pages are narrowed to, as the `source` parameter writes it: its kind letter and id, `c255851`. */
final readonly class LootSourceRef
{
    public function __construct(
        public LootSourceKind $kind,
        public int $id,
    ) {}

    /** The reference a `source` parameter holds, or null for any other text. */
    public static function parse(?string $text): ?self
    {
        if ($text === null || preg_match('/^([cg])('.RecordId::pattern().')$/', $text, $match) !== 1) {
            return null;
        }

        return new self(LootSourceKind::from($match[1]), (int) $match[2]);
    }

    public function key(): string
    {
        return $this->kind->value.$this->id;
    }

    public function page(): ?string
    {
        return $this->kind->page($this->id);
    }

    /** The rows of an aggregate or fact table that belong to this source. */
    public function within(Builder $query, string $kind = 'source_kind', string $id = 'source_id'): Builder
    {
        return $query->where($kind, $this->kind->value)->where($id, $this->id);
    }

    /** The kind of the `names` dictionary that names this source. */
    public function nameKind(): NameKind
    {
        return $this->kind === LootSourceKind::Creature ? NameKind::Npc : NameKind::Object;
    }
}
