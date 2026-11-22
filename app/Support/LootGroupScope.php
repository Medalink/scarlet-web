<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

/**
 * Which windows the loot rules and group size page reads (docs/LOOT.md, Loot rules and group size): the windows read for
 * loot on creatures, or on the one source the page is narrowed to, and never personal loot, which is not pooled with any
 * other rule.
 */
final class LootGroupScope
{
    /**
     * The rules that say what the game did: not personal loot, and not a rule no line has named yet.
     *
     * @return list<string>
     */
    public static function knownRules(): array
    {
        $rules = [];
        foreach (LootRule::cases() as $rule) {
            if ($rule !== LootRule::Personal && $rule !== LootRule::Unknown) {
                $rules[] = $rule->value;
            }
        }

        return $rules;
    }

    /** `$personal` keeps the windows of personal loot, for a figure that is never pooled across rules. */
    public static function of(Builder $query, ?LootSourceRef $source, string $prefix = '', bool $personal = false): Builder
    {
        LootRead::Loot->within($query, $prefix.'read');
        $source?->within($query, $prefix.'source_kind', $prefix.'source_id');
        $query->when($source === null, fn (Builder $query): Builder => $query->where($prefix.'source_kind', LootSourceKind::Creature->value));

        return $personal ? $query : $query->where($prefix.'loot_rule', '<>', LootRule::Personal->value);
    }

    /** A group size as the pages say it: solo is 1, whether the decoder wrote 0 or 1. */
    public static function size(mixed $size): ?int
    {
        return $size === null ? null : max(1, (int) $size);
    }
}
