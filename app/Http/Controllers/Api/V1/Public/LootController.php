<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Public\ObjectRecord;
use App\Actions\Rollups\LootItems;
use App\Actions\Rollups\LootSources;
use App\Http\Requests\Api\PublicRequest;
use App\Support\Cursor;
use App\Support\GameObjects;
use App\Support\LootSourceKind;
use App\Support\LootView;
use Illuminate\Http\JsonResponse;

/** What creatures, objects and containers gave, the items that dropped and one object, every rate with its `estimate`. */
class LootController extends PublicController
{
    public function sources(PublicRequest $request, LootSources $sources): JsonResponse
    {
        $view = $request->lootView();
        $offset = $request->offset();
        $limit = $request->limit();
        $page = $sources->handle($request->lootFilter($view), $offset, $limit);
        $kind = $view->sourceKind() ?? LootSourceKind::Other;
        $rows = array_map(fn (array $row): array => [
            'source_kind' => $kind->value,
            'source_id' => $row['source_id'],
            'name' => $row['name'],
            'path' => $kind->page($row['source_id']),
            'windows' => $row['windows'],
            'items' => $row['items'],
            'characters' => $row['characters'],
            'level_min' => $row['level_min'],
            'level_max' => $row['level_max'],
            'zone' => $row['zone'],
            'top' => $row['top'],
        ], $page['rows']);

        return $this->respondPage($rows, $page['more'] ? Cursor::encode($offset + $limit) : null, ['view' => $view->value]);
    }

    public function items(PublicRequest $request, LootItems $items): JsonResponse
    {
        $offset = $request->offset();
        $limit = $request->limit();
        $page = $items->handle($request->lootFilter(LootView::Items), $offset, $limit);
        $rows = array_map(function (array $row): array {
            $best = $row['best'];

            return [
                'item_id' => $row['item_id'],
                'name' => $row['name'],
                'quality' => $row['quality'],
                'path' => '/items/'.$row['item_id'],
                'dropped' => $row['dropped'],
                'sources' => $row['sources'],
                'best' => $best === null ? null : [...$best, 'path' => LootSourceKind::tryFrom($best['source_kind'])?->page($best['source_id'])],
                'zone' => $row['zone'],
            ];
        }, $page['rows']);

        return $this->respondPage($rows, $page['more'] ? Cursor::encode($offset + $limit) : null, ['view' => LootView::Items->value]);
    }

    public function object(PublicRequest $request, ObjectRecord $record, int $id): JsonResponse
    {
        $answer = $id === GameObjects::FISHING_BOBBER ? null : $record->handle($id, $request->lootRule());
        if ($answer === null) {
            $this->missing('The object');
        }
        unset($answer['map_zones']);

        return $this->respond($answer);
    }
}
