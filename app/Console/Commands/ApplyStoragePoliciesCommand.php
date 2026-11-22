<?php

namespace App\Console\Commands;

use App\Actions\Rollups\ApplyStoragePolicies;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scarlet:storage-policies')]
#[Description('Applies the chunk intervals, compression and aggregate refresh windows of config/scarlet.php to the hypertables; refuses any retention setting')]
class ApplyStoragePoliciesCommand extends Command
{
    public function handle(ApplyStoragePolicies $policies): int
    {
        $result = $policies->handle();
        if ($result['refused'] !== null) {
            $this->error($result['refused']);

            return self::FAILURE;
        }

        foreach ($result['changes'] as $change) {
            $this->line($change);
        }
        $this->info($result['changes'] === [] ? 'Storage policies already match the config.' : count($result['changes']).' storage policies changed.');

        return self::SUCCESS;
    }
}
