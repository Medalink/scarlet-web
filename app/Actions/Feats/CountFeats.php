<?php

namespace App\Actions\Feats;

use App\Actions\Feats\Counts\CombatCounts;
use App\Actions\Feats\Counts\CreatureCounts;
use App\Actions\Feats\Counts\DungeonCounts;
use App\Actions\Feats\Counts\LootCounts;
use App\Actions\Feats\Counts\MoneyCounts;
use App\Actions\Feats\Counts\QuestCounts;
use App\Actions\Feats\Counts\SkillCounts;
use App\Actions\Feats\Counts\SocialCounts;
use App\Actions\Feats\Counts\SpecCounts;
use App\Actions\Feats\Counts\TimeCounts;
use App\Actions\Feats\Counts\WorldCounts;
use App\Models\Character;
use App\Support\Feat;
use LogicException;

/** Counts every feat of a character from the base tables (docs/DESIGN.md "Art and game layer"). */
class CountFeats
{
    public function __construct(
        private readonly TimeCounts $time,
        private readonly CombatCounts $combat,
        private readonly QuestCounts $quests,
        private readonly DungeonCounts $dungeons,
        private readonly WorldCounts $world,
        private readonly CreatureCounts $creatures,
        private readonly LootCounts $loot,
        private readonly SkillCounts $skills,
        private readonly MoneyCounts $money,
        private readonly SocialCounts $social,
        private readonly SpecCounts $spec,
    ) {}

    /**
     * One count per `Feat` case, keyed by the enum value; null when the feat does not apply to the character.
     * A case no counter fills is an error, so a feat added to the enum cannot be forgotten here.
     *
     * @return array<string, int|float|null>
     */
    public function handle(Character $character): array
    {
        $counts = array_merge(
            $this->time->handle($character),
            $this->combat->handle($character),
            $this->quests->handle($character),
            $this->dungeons->handle($character),
            $this->world->handle($character),
            $this->creatures->handle($character),
            $this->loot->handle($character),
            $this->skills->handle($character),
            $this->money->handle($character),
            $this->social->handle($character),
            $this->spec->handle($character),
        );
        foreach (Feat::cases() as $feat) {
            if (! array_key_exists($feat->value, $counts)) {
                throw new LogicException('Feat '.$feat->name.' has no counter entry.');
            }
        }

        return $counts;
    }
}
