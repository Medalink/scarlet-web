<?php

namespace App\View\Design;

use App\Support\Scene;

/** The head of a profession page with invented facts, to check a scene in the real app before its page exists. */
final class SceneHead
{
    /**
     * @return array{title: string, label: string, facts: list<string>, credit: string, callouts: list<array{key: string, title: string, text: string, share: string}>}
     */
    public static function data(Scene $scene): array
    {
        return match ($scene) {
            Scene::Fishing => [
                'title' => 'Fishing',
                'label' => "A fishing spot at night, drawn from the game's own models: a bobber on the water beside a school of fish, with a tackle box, a pole and a rack of fish on the bank",
                'facts' => ['1,204,880 casts', '4,310 characters', '6,120 hours at the water'],
                'credit' => 'Bobber, fish school, tackle box, pole and fish rack models, textures, ripples and splashes © Blizzard Entertainment, read from the WoW Forever client. The water, the bank and the moonlight are drawn by this page.',
                'callouts' => [
                    ['key' => 'bobber', 'title' => 'Bobber', 'text' => 'The wait for a bite is 15 seconds', 'share' => '82% of casts catch'],
                    ['key' => 'rack', 'title' => 'Raw Spotted Yellowtail', 'text' => 'The most caught fish', 'share' => '43% of catches'],
                    ['key' => 'box', 'title' => 'Clams, crates and trunks', 'text' => 'Catches that open for more', 'share' => '1 catch in 13'],
                ],
            ],
        };
    }
}
