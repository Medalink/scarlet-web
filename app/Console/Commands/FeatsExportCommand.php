<?php

namespace App\Console\Commands;

use App\Actions\Feats\ExportCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('feats:export {path? : where to write the catalog; resources/feats/feats.json by default}')]
#[Description('Writes the feat catalog as the JSON the app and the addon generate their code from')]
class FeatsExportCommand extends Command
{
    public function handle(ExportCatalog $export): int
    {
        $path = (string) ($this->argument('path') ?? resource_path('feats/feats.json'));
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $catalog = $export->handle();
        file_put_contents($path, json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
        $this->info(count($catalog['feats']).' feats, catalog version '.$catalog['version'].', written to '.$path.'.');

        return self::SUCCESS;
    }
}
