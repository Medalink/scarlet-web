<?php

namespace App\Actions\Public;

use App\Support\NameKind;
use Illuminate\Support\Facades\DB;

class ResolveNames
{
    /**
     * The shown name of each id: the one most uploads agree on.
     *
     * @param  list<int|null>  $ids
     * @return array<int, string>
     */
    public function handle(NameKind $kind, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn (mixed $id): bool => $id !== null)));
        $names = [];
        foreach (DB::table('names')->where('kind', $kind->value)->whereIn('id', $ids)->orderBy('votes')->orderByDesc('name')->get(['id', 'name']) as $row) {
            $names[(int) $row->id] = (string) $row->name;
        }

        return $names;
    }
}
