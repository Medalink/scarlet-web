<?php

namespace App\Console\Commands;

use App\Actions\Rollups\RefillSearchScores;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('search:scores')]
#[Description('Replaces the numbers search ranks creatures, items, quests and zones by with the ones the rollups hold now')]
class RefillSearchScoresCommand extends Command
{
    public function handle(RefillSearchScores $scores): int
    {
        foreach ($scores->handle() as $kind => $rows) {
            $this->line($kind.': '.$rows);
        }

        return self::SUCCESS;
    }
}
