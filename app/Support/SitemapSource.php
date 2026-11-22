<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;

/** The rows the sitemap files list: one query per kind, read both for a count and for a page. */
class SitemapSource
{
    /** The number of rows of a kind, with the same filters a page lists. */
    public function count(SearchKind $kind): int
    {
        return (int) $this->base($kind)->distinct()->count('id');
    }

    /**
     * One file's worth of a kind, page one first. Empty once the pages run out.
     *
     * @return list<array{path: string, modified: string|null}>
     */
    public function page(SearchKind $kind, int $page, int $perPage): array
    {
        $rows = $this->base($kind)->orderBy('id')->forPage($page, $perPage)->get(match ($kind) {
            SearchKind::Characters => ['id', 'name', 'last_seen_at'],
            default => ['id'],
        });

        return array_values(array_map(fn (stdClass $row): array => match ($kind) {
            SearchKind::Characters => [
                'path' => SearchKind::Characters->path().rawurlencode((string) $row->name),
                'modified' => $row->last_seen_at === null ? null : Carbon::parse((string) $row->last_seen_at)->utc()->format('Y-m-d\TH:i:sP'),
            ],
            default => ['path' => $kind->path().$row->id, 'modified' => null],
        }, $rows->all()));
    }

    /** The rows of a kind: public characters, the zones table, or the names dictionary of the kind (for objects, those the search finds). */
    private function base(SearchKind $kind): Builder
    {
        return match ($kind) {
            SearchKind::Characters => DB::table('characters')->where('is_public', true),
            SearchKind::Zones => DB::table('zones'),
            SearchKind::Creatures => DB::table('names')->where('kind', NameKind::Npc->value)->distinct(),
            SearchKind::Items => DB::table('names')->where('kind', NameKind::Item->value)->distinct(),
            SearchKind::Objects => DB::table('names')->where('kind', NameKind::Object->value)->distinct()
                ->whereExists(fn (Builder $scored) => $scored->selectRaw('1')->from('search_scores as s')->where('s.kind', SearchKind::Objects->value)->whereColumn('s.id', 'names.id')),
            SearchKind::Quests => DB::table('names')->where('kind', NameKind::Quest->value)->distinct(),
        };
    }
}
