<?php

namespace App\Console\Commands;

use App\Actions\Ingest\MarkSessionJoins;
use App\Actions\Ingest\RebuildCharacterLevels;
use App\Actions\Ingest\SummarizeCharacter;
use App\Actions\Rollups\RefreshAggregates;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('levels:rebuild')]
#[Description('Marks how every session follows the one before it, rebuilds every character\'s level rows and summary from its stored rows, then refreshes the aggregates over the days that changed')]
class RebuildLevelsCommand extends Command
{
    public function handle(MarkSessionJoins $joins, RebuildCharacterLevels $levels, SummarizeCharacter $summaries, RefreshAggregates $refresh): int
    {
        $from = null;
        $until = null;
        foreach (DB::table('characters')->orderBy('id')->pluck('id') as $id) {
            $extent = DB::transaction(function () use ($joins, $levels, $summaries, $id): ?array {
                $joins->handle((int) $id);
                $summaries->handle((int) $id);

                return $levels->handle((int) $id);
            });
            if ($extent === null) {
                continue;
            }
            $from = min($from ?? $extent['from'], $extent['from']);
            $until = max($until ?? $extent['until'], $extent['until']);
            $this->info("character {$id}: levels rebuilt.");
        }
        if ($from !== null && $until !== null) {
            $refresh->handle($from, $until);
            $this->info('aggregates refreshed.');
        }

        return self::SUCCESS;
    }
}
