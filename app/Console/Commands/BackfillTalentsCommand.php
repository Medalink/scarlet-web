<?php

namespace App\Console\Commands;

use App\Actions\Ingest\QueueTalentBackfill;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('talents:backfill')]
#[Description('Decodes again every session that has a build part, filling the talent tables from parts uploaded earlier')]
class BackfillTalentsCommand extends Command
{
    public function handle(QueueTalentBackfill $backfill): int
    {
        $this->info($backfill->handle().' sessions queued.');

        return self::SUCCESS;
    }
}
