<?php

namespace App\Actions\Ingest;

use App\Support\DecodedSession;
use App\Support\FactTable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class UpsertReferenceFacts
{
    private const array RECIPE_COLUMNS = ['output_item_id', 'min_quantity', 'max_quantity', 'optional_slots', 'type'];

    private const array SPELL_COLUMNS = ['icon_id', 'cast_ms', 'min_range', 'max_range', 'costs', 'passive', 'description'];

    private const int CHUNK = 1000;

    /**
     * Write the recipes and spells a session read. They are the same for every uploader, so a row only replaces
     * the stored one when its line was read later; a spell keeps a known value the later row has none for.
     * Runs inside the session's transaction: the stored rows are locked while they are compared.
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
        $known = $this->seen('recipes', 'recipe_spell_id', array_column($rows, 'recipe_spell_id'));
        $write = [];
        $reagents = [];
        $replaced = [];
        foreach ($rows as $row) {
            $id = (int) $row['recipe_spell_id'];
            if (isset($known[$id]) && $known[$id] > (int) $row['at']) {
                continue;
            }
            $write[] = ['recipe_spell_id' => $id, 'seen_at' => FactTable::instant((int) $row['at']), 'updated_at' => now()]
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
        $ids = array_column($rows, 'spell_id');
        $stored = DB::table('spells')->whereIn('spell_id', $ids)->lockForUpdate()->get()->keyBy('spell_id');
        $write = [];
        foreach ($rows as $row) {
            $id = (int) $row['spell_id'];
            $old = $stored->get($id);
            $oldAt = $old?->seen_at === null ? null : CarbonImmutable::parse($old->seen_at)->getTimestampMs();
            $newer = $oldAt === null || (int) $row['at'] >= $oldAt;
            $merged = ['spell_id' => $id, 'seen_at' => FactTable::instant($newer ? (int) $row['at'] : $oldAt), 'updated_at' => now()];
            foreach (self::SPELL_COLUMNS as $column) {
                $stays = $old === null ? null : $old->{$column};
                $merged[$column] = $newer ? ($row[$column] ?? $stays) : ($stays ?? $row[$column]);
            }
            $write[] = $merged;
        }

        DB::table('spells')->upsert($write, ['spell_id'], ['seen_at', 'updated_at', ...self::SPELL_COLUMNS]);
    }

    /**
     * The newest read of each stored id, in epoch milliseconds; the rows are locked.
     *
     * @param  list<mixed>  $ids
     * @return array<int, int>
     */
    private function seen(string $table, string $key, array $ids): array
    {
        $seen = [];
        foreach (DB::table($table)->whereIn($key, $ids)->whereNotNull('seen_at')->lockForUpdate()->get([$key, 'seen_at']) as $row) {
            $seen[(int) $row->{$key}] = CarbonImmutable::parse($row->seen_at)->getTimestampMs();
        }

        return $seen;
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
