<?php

namespace App\Actions\Ingest;

use App\Support\DecodedSession;
use Illuminate\Support\Facades\DB;

class UpsertReferenceFacts
{
    private const array RECIPE_COLUMNS = ['output_item_id', 'min_quantity', 'max_quantity', 'optional_slots', 'type'];

    private const array SPELL_COLUMNS = ['icon_id', 'cast_ms', 'min_range', 'max_range', 'costs', 'passive', 'description'];

    private const int CHUNK = 1000;

    /**
     * Write the recipes and spells a session read. They are the same for every uploader, so the newest decode to
     * reach the server wins: `seen_at` is the server time of this decode, never the line's `at`, and a spell keeps
     * a stored value the new row has none for.
     */
    public function handle(DecodedSession $decoded): void
    {
        foreach (array_chunk($decoded->recipes, self::CHUNK) as $chunk) {
            $this->recipes($chunk);
        }
        foreach (array_chunk($decoded->spells, self::CHUNK) as $chunk) {
            $this->spells($chunk);
        }
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function recipes(array $rows): void
    {
        $write = [];
        $replaced = [];
        $reagents = [];
        foreach ($rows as $row) {
            $id = (int) $row['recipe_spell_id'];
            $write[] = ['recipe_spell_id' => $id, 'seen_at' => now(), 'updated_at' => now()]
                + array_intersect_key($row, array_flip(self::RECIPE_COLUMNS));
            $replaced[] = $id;
            foreach ($this->reagents((string) ($row['reagents'] ?? '')) as $item => $quantity) {
                $reagents[] = ['recipe_spell_id' => $id, 'item_id' => $item, 'quantity' => $quantity];
            }
        }
        if ($write === []) {
            return;
        }

        DB::table('recipes')->upsert($write, ['recipe_spell_id'], ['seen_at', 'updated_at', ...self::RECIPE_COLUMNS]);
        DB::table('recipe_reagents')->whereIn('recipe_spell_id', $replaced)->delete();
        foreach (array_chunk($reagents, self::CHUNK) as $part) {
            DB::table('recipe_reagents')->insert($part);
        }
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function spells(array $rows): void
    {
        $stored = DB::table('spells')->whereIn('spell_id', array_column($rows, 'spell_id'))->lockForUpdate()->get()->keyBy('spell_id');
        $write = [];
        foreach ($rows as $row) {
            $old = $stored->get((int) $row['spell_id']);
            $merged = ['spell_id' => (int) $row['spell_id'], 'seen_at' => now(), 'updated_at' => now()];
            foreach (self::SPELL_COLUMNS as $column) {
                $merged[$column] = $row[$column] ?? $old?->{$column};
            }
            $write[] = $merged;
        }

        DB::table('spells')->upsert($write, ['spell_id'], ['seen_at', 'updated_at', ...self::SPELL_COLUMNS]);
    }

    /**
     * @return array<int, int> quantity by item id, for `itemID:count` joined by commas
     */
    private function reagents(string $list): array
    {
        $needs = [];
        foreach (explode(',', $list) as $entry) {
            [$item, $count] = [...explode(':', $entry, 2), '1'];
            if (! ctype_digit($item) || ! ctype_digit($count) || (int) $count < 1) {
                continue;
            }
            $needs[(int) $item] = ($needs[(int) $item] ?? 0) + (int) $count;
        }

        return $needs;
    }
}
