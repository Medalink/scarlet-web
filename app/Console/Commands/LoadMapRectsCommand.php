<?php

namespace App\Console\Commands;

use App\Actions\Maps\LoadMapRects;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('maps:load-rects {path? : The table to load, docs/map-rects.tsv by default}')]
#[Description('Loads the zone map rectangles on the stitched world into the map_rects table')]
class LoadMapRectsCommand extends Command
{
    public function handle(LoadMapRects $action): int
    {
        $path = $this->argument('path');

        try {
            $count = $action->handle(is_string($path) ? $path : base_path('docs/map-rects.tsv'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$count} map rectangles in the table.");

        return self::SUCCESS;
    }
}
