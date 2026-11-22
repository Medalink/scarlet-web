<?php

namespace App\Console\Commands;

use App\Actions\Admin\PruneApiCalls;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('api-calls:prune')]
#[Description('Deletes the counted API calls, with their addresses, that are older than the days /admin/api keeps')]
class PruneApiCallsCommand extends Command
{
    public function handle(PruneApiCalls $prune): int
    {
        $this->line('deleted: '.$prune->handle());

        return self::SUCCESS;
    }
}
