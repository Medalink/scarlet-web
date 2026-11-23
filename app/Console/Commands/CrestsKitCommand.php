<?php

namespace App\Console\Commands;

use App\Actions\Crests\RenderCrestKit;
use App\Actions\Crests\RenderCrestTierCss;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crests:kit {--kit= : the kit to read; resources/crests/kit.json by default}')]
#[Description('Writes app/View/Sc/CrestKit.php and resources/css/crest-tiers.css from the crest kit')]
class CrestsKitCommand extends Command
{
    public function handle(RenderCrestKit $class, RenderCrestTierCss $css): int
    {
        $path = (string) ($this->option('kit') ?: resource_path('crests/kit.json'));
        $kit = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        file_put_contents(app_path('View/Sc/CrestKit.php'), $class->handle($kit));
        file_put_contents(resource_path('css/crest-tiers.css'), $css->handle($kit));
        $this->info('app/View/Sc/CrestKit.php and resources/css/crest-tiers.css written from '.$path.'.');

        return self::SUCCESS;
    }
}
