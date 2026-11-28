<?php

namespace App\Actions\Public;

use App\View\ItemTypes;
use Illuminate\Support\Facades\DB;

class KeepPoles
{
    /**
     * A gathering answer for the web page: its `poles` without the items the dictionary knows are not fishing poles.
     * The public API keeps the answer whole.
     *
     * @param  array<string, mixed>|null  $answer
     * @return array<string, mixed>|null
     */
    public function handle(?array $answer): ?array
    {
        if ($answer === null || ! is_array($answer['poles'] ?? null) || $answer['poles'] === []) {
            return $answer;
        }
        $poles = array_values($answer['poles']);
        $others = DB::table('items')->whereIn('item_id', array_map(fn (array $row): int => (int) $row['item_id'], $poles))->whereNotNull('class_id')
            ->where(fn ($query) => $query->where('class_id', '<>', ItemTypes::WEAPON)->orWhere('subclass_id', '<>', ItemTypes::FISHING_POLE))
            ->pluck('item_id')->map(fn (mixed $id): int => (int) $id)->all();
        $answer['poles'] = array_values(array_filter($poles, fn (array $row): bool => ! in_array((int) $row['item_id'], $others, true)));

        return $answer;
    }
}
