<?php

namespace App\Console\Commands;

use App\Actions\Ingest\QueueOutdatedSessions;
use App\Actions\Ingest\QueueRedecode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ingest:redecode {--outdated : Queue only the next batch of sessions an older decoder wrote}')]
#[Description('Decodes again every stored session, so rows written by an older decoder follow the current one')]
class RedecodeSessionsCommand extends Command
{
    public function handle(QueueRedecode $redecode, QueueOutdatedSessions $outdated): int
    {
        if ($this->option('outdated')) {
            $this->info($outdated->handle().' outdated sessions queued.');

            return self::SUCCESS;
        }

        $this->info($redecode->handle().' sessions queued.');

        return self::SUCCESS;
    }
}
