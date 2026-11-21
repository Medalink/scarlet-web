<?php

namespace App\Actions\Ingest;

use App\Support\DecodedSession;
use Illuminate\Support\Facades\DB;

class UpsertDictionaries
{
    private const int CHUNK = 5000;

    /** The `items` columns a session may fill; the tooltip is last. */
    private const array ITEM_COLUMNS = ['class_id', 'subclass_id', 'equip_loc', 'item_level', 'required_level', 'sell_price', 'bind_type', 'stack', 'quality', 'tooltip'];

    /**
     * Write what the session told about names, items, zones and the players it met.
     *
     * @param  bool  $countVotes  false when the session's names were counted by an earlier decode
     */
    public function handle(int $characterId, DecodedSession $decoded, bool $countVotes): void
    {
        $this->names($decoded->names, $countVotes);
        $this->items($decoded->items);
        $this->zones($decoded->zones);
        $this->pseudonyms($characterId, $decoded->pseudonyms);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function names(array $rows, bool $countVotes): void
    {
        $unique = [];
        foreach ($rows as $row) {
            $name = mb_substr((string) $row['name'], 0, 255);
            $unique[$row['kind'].'/'.$row['id'].'/'.$name] = ['kind' => $row['kind'], 'id' => $row['id'], 'name' => $name, 'votes' => 1];
        }

        foreach (array_chunk(array_values($unique), self::CHUNK) as $chunk) {
            if (! $countVotes) {
                DB::table('names')->insertOrIgnore($chunk);

                continue;
            }
            DB::table('names')->upsert($chunk, ['kind', 'id', 'name'], ['votes' => DB::raw('names.votes + 1')]);
        }
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function items(array $rows): void
    {
        $unique = [];
        foreach ($rows as $row) {
            $item = ['item_id' => $row['item_id'], 'updated_at' => now()];
            foreach (self::ITEM_COLUMNS as $column) {
                $item[$column] = $row[$column] ?? null;
            }
            $unique[$row['item_id']] = $item;
        }

        foreach (array_chunk(array_values($unique), 1000) as $chunk) {
            // A session without the line leaves the known value alone.
            DB::table('items')->upsert($chunk, ['item_id'], [
                'class_id' => DB::raw('coalesce(excluded.class_id, items.class_id)'),
                'subclass_id' => DB::raw('coalesce(excluded.subclass_id, items.subclass_id)'),
                'equip_loc' => DB::raw('coalesce(excluded.equip_loc, items.equip_loc)'),
                'item_level' => DB::raw('coalesce(excluded.item_level, items.item_level)'),
                'required_level' => DB::raw('coalesce(excluded.required_level, items.required_level)'),
                'sell_price' => DB::raw('coalesce(excluded.sell_price, items.sell_price)'),
                'bind_type' => DB::raw('coalesce(excluded.bind_type, items.bind_type)'),
                'stack' => DB::raw('coalesce(excluded.stack, items.stack)'),
                'quality' => DB::raw('coalesce(excluded.quality, items.quality)'),
                'tooltip' => DB::raw('coalesce(excluded.tooltip, items.tooltip)'),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function zones(array $rows): void
    {
        $unique = [];
        foreach ($rows as $row) {
            $unique[$row['id']] ??= [
                'id' => $row['id'],
                'name' => mb_substr((string) ($row['name'] ?? ''), 0, 128),
                'instance_type' => $row['instance_type'] ?? null,
                'instance_id' => $row['instance_id'] ?? null,
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk(array_values($unique), 1000) as $chunk) {
            DB::table('zones')->upsert($chunk, ['id'], [
                'name' => DB::raw("case when excluded.name <> '' then excluded.name else zones.name end"),
                'instance_type' => DB::raw('coalesce(excluded.instance_type, zones.instance_type)'),
                'instance_id' => DB::raw('coalesce(excluded.instance_id, zones.instance_id)'),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function pseudonyms(int $characterId, array $rows): void
    {
        $unique = [];
        foreach ($rows as $row) {
            $unique[$row['pseudonym']] = [
                'character_id' => $characterId,
                'pseudonym' => $row['pseudonym'],
                'name' => mb_substr((string) $row['name'], 0, 64),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk(array_values($unique), 1000) as $chunk) {
            DB::table('pseudonyms')->upsert($chunk, ['character_id', 'pseudonym'], ['name', 'updated_at']);
        }
    }
}
