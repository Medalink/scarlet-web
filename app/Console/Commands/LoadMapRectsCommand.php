<?php

namespace App\Console\Commands;

use App\Actions\Maps\LoadMapRects;
use App\Actions\Maps\PlaceWorldPoints;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('maps:load-rects {path? : The table to load, docs/map-rects.tsv by default}')]
#[Description('Loads the zone map rectangles on the stitched world into the map_rects table, then places the points recorded on them')]
class LoadMapRectsCommand extends Command
{
    public function handle(LoadMapRects $action, PlaceWorldPoints $place): int
    {
        $path = $this->argument('path');

        try {
            $count = $action->handle(is_string($path) ? $path : base_path('docs/map-rects.tsv'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$count} map rectangles in the table.");
        foreach ($place->handle() as $table => $rows) {
            $this->info("{$table}: {$rows} rows placed.");
        }

        return self::SUCCESS;
    }
}
