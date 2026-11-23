<?php

namespace App\Console\Commands;

use App\Actions\Feats\PruneFeatEvents;
use App\Actions\Feats\RebuildFeatShares;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('feats:shares {--hours= : Recorded hours a character needs to be counted}')]
#[Description('Rebuilds feat_shares, the share of counted characters at each tier of each feat or higher, and deletes feat_events older than the days kept')]
class RebuildFeatSharesCommand extends Command
{
    public function handle(RebuildFeatShares $rebuild, PruneFeatEvents $prune): int
    {
        $hours = $this->option('hours');
        $this->line('wrote '.$rebuild->handle($hours === null ? RebuildFeatShares::MIN_HOURS : (int) $hours).' rows');
        $this->line('deleted '.$prune->handle().' events');

        return self::SUCCESS;
    }
}
