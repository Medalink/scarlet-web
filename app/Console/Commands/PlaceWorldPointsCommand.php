<?php

namespace App\Console\Commands;

use App\Actions\Maps\PlaceWorldPoints;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('maps:place-points')]
#[Description('Fills the world position of recorded points whose zone map now has a rectangle, then refreshes the world heat')]
class PlaceWorldPointsCommand extends Command
{
    public function handle(PlaceWorldPoints $action): int
    {
        foreach ($action->handle() as $table => $rows) {
            $this->info("{$table}: {$rows} rows placed.");
        }

        return self::SUCCESS;
    }
}
