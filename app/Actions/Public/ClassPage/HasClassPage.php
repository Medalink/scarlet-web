<?php

namespace App\Actions\Public\ClassPage;

use App\Models\Character;
use App\Support\ClassPage\ClassBuilders;
use App\Support\PlayerClass;
use Illuminate\Support\Facades\DB;

class HasClassPage
{
    /**
     * Whether a character has a class page: its class has a builder and its sessions counted at least one cast. The
     * route, the band and the navigation all ask this, so a link never leads to a 404.
     */
    public function handle(Character $character): bool
    {
        $class = PlayerClass::tryFrom(strtoupper((string) $character->class));

        return $class !== null && ClassBuilders::has($class)
            && DB::table('spell_casts')->where('character_id', $character->id)->where('counted', true)->exists();
    }
}
