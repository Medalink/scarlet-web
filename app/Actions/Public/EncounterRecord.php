<?php

namespace App\Actions\Public;

use App\Support\NameKind;

class EncounterRecord
{
    public function __construct(private readonly BossDrops $drops, private readonly ResolveNames $names) {}

    /**
     * What the encounter page and `/public/encounters/{id}` show: the items the encounter dropped, each drop counted
     * once per uploader group. Null when no drop of it is recorded.
     *
     * @return array<string, mixed>|null
     */
    public function handle(int $id): ?array
    {
        $drops = $this->drops->handle(null, $id, 100);
        if ($drops === []) {
            return null;
        }
        $items = $this->names->handle(NameKind::Item, array_column($drops, 'item_id'));

        return [
            'id' => $id,
            'drops' => array_map(fn (array $drop): array => [...$drop, 'item' => $items[$drop['item_id']] ?? 'Item '.$drop['item_id']], $drops),
        ];
    }
}
