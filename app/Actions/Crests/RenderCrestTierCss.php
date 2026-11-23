<?php

namespace App\Actions\Crests;

use InvalidArgumentException;

final class RenderCrestTierCss
{
    /** @param  array<string, mixed>  $kit  the decoded resources/crests/kit.json */
    public function handle(array $kit): string
    {
        $quality = $kit['quality'] ?? null;
        if (! is_array($quality)) {
            throw new InvalidArgumentException('The crest kit has no `quality`.');
        }
        $tokens = [];
        foreach ($quality as $tier => $row) {
            $tokens[] = '--tier-'.$tier.': '.$row['color'].';';
        }

        return "@layer base {\n:root {\n  ".implode(' ', $tokens)."\n}\n}\n";
    }
}
