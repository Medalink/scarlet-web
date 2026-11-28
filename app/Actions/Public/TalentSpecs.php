<?php

namespace App\Actions\Public;

use App\Support\LatestTalentBuilds;
use App\Support\NameKind;
use App\Support\PageScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TalentSpecs
{
    /**
     * The specs characters play, each with the characters that play it: a character plays every spec it has a counted
     * build of, by the latest of them, the number the spec page counts for All levels. Most played first.
     *
     * @return list<array{class: string, class_slug: string, tab_id: int, name: string, slug: string, characters: int}>
     */
    public function handle(PageScope $scope): array
    {
        $rows = DB::query()->fromSub(LatestTalentBuilds::query($scope), 'l')->join('characters as c', 'c.id', '=', 'l.character_id')
            ->whereNotNull('c.class')
            ->groupBy('c.class', 'l.spec_tab_id')->selectRaw('c.class, l.spec_tab_id, count(*) as characters')
            ->orderByDesc('characters')->orderBy('c.class')->orderBy('l.spec_tab_id')->get();

        $names = [];
        foreach (DB::table('names')->where('kind', NameKind::TalentTab->value)->whereIn('id', $rows->pluck('spec_tab_id')->all())->orderBy('votes')->get(['id', 'name']) as $name) {
            $names[(int) $name->id] = (string) $name->name;
        }

        return array_values($rows->map(function (\stdClass $row) use ($names): array {
            $tab = (int) $row->spec_tab_id;
            $name = $names[$tab] ?? 'Tab '.$tab;

            return ['class' => (string) $row->class, 'class_slug' => strtolower((string) $row->class), 'tab_id' => $tab, 'name' => $name, 'slug' => Str::slug($name), 'characters' => (int) $row->characters];
        })->all());
    }
}
