<?php

namespace App\Actions\Public;

use App\Support\NameKind;
use Illuminate\Support\Facades\DB;

class SpellRecord
{
    public function __construct(private readonly ResolveNames $names, private readonly RecipeFacts $recipes) {}

    /**
     * What the spell page and `/public/spells/{id}` show: the facts the client gave for the spell and, when the
     * spell is a recipe, what it makes and needs. Null when nothing is known by this id.
     *
     * @return array<string, mixed>|null
     */
    public function handle(int $id): ?array
    {
        $name = $this->names->handle(NameKind::Spell, [$id])[$id] ?? null;
        $spell = DB::table('spells')->where('spell_id', $id)->first(['icon_id', 'cast_ms', 'min_range', 'max_range', 'costs', 'passive', 'description']);
        $recipe = $this->recipes->handle([$id])[$id] ?? null;
        if ($name === null && $spell === null && $recipe === null) {
            return null;
        }

        return [
            'id' => $id,
            'name' => $name ?? 'Spell '.$id,
            'facts' => $spell === null ? null : (array) $spell,
            'recipe' => $recipe,
        ];
    }
}
