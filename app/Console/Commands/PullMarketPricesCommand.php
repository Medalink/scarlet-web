<?php

namespace App\Console\Commands;

use App\Actions\Prices\PullMarketPrices;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('prices:pull')]
#[Description('Brings AHledger\'s auction prices up to date, asking only for price tables that changed')]
class PullMarketPricesCommand extends Command
{
    public function handle(PullMarketPrices $pull): int
    {
        $statuses = $pull->handle();
        if ($statuses === []) {
            $this->warn('AHledger\'s market list could not be read.');

            return self::FAILURE;
        }
        foreach ($statuses as $market => $status) {
            $this->line("{$market}: {$status}");
        }

        return self::SUCCESS;
    }
}
