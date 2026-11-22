<?php

namespace App\Actions\Public\ClassPage;

use App\Actions\Public\ResolveNames;
use App\Actions\Public\TalentLayout;
use App\Models\Character;
use App\Support\ClassPage\Build;
use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\ClassPeers;
use App\Support\ClassPage\HeldTalent;
use App\Support\ClassPage\TalentModules;
use App\Support\LevelBracket;
use App\Support\NameKind;
use App\Support\PlayerClass;
use App\Support\TalentRanks;
use App\Support\TalentTree;
use Illuminate\Support\Facades\DB;

class ReadClassFacts
{
    public function __construct(private readonly ResolveNames $names, private readonly TalentLayout $layouts) {}

    /**
     * Who the character is for its class page and what its latest talent build holds: the build as the points in the
     * class's three trees, and the talents of the build that have a module. A talent whose most ranks the tree's layout
     * does not give has no module, since its tag could not say "2 of 2".
     */
    public function handle(Character $character, PlayerClass $class): ClassFacts
    {
        $snapshot = DB::table('talent_snapshots')->where('character_id', $character->id)->orderByDesc('at')->first(['tree_id', 'tab_points', 'ranks']);
        $build = $tree = null;
        $talents = [];
        if ($snapshot !== null) {
            $tabs = TalentRanks::tabs($snapshot->tab_points);
            $tabNames = $this->names->handle(NameKind::TalentTab, array_column($tabs, 'id'));
            $build = $this->build($tabs, $tabNames);
            $tree = $build === null ? null : TalentTree::of($class, $build->tree);
            $talents = $this->talents($class, (int) $snapshot->tree_id, (string) $snapshot->ranks);
        }
        $hours = ($character->played_known ? $character->played_seconds : $character->recorded_seconds) / 3600;

        return new ClassFacts(
            $class, $character->name, (int) $character->level, $hours, $build, $tree, $talents,
            new ClassPeers($class, (int) $character->id, LevelBracket::of((int) $character->level)),
        );
    }

    /**
     * @param  list<array{id: int, spent: int}>  $tabs
     * @param  array<int, string>  $tabNames
     */
    private function build(array $tabs, array $tabNames): ?Build
    {
        if (count($tabs) !== 3) {
            return null;
        }
        $most = $tabs[0];
        foreach ($tabs as $tab) {
            $most = $tab['spent'] > $most['spent'] ? $tab : $most;
        }

        return new Build($tabNames[$most['id']] ?? '', array_column($tabs, 'spent'));
    }

    /** @return list<HeldTalent> */
    private function talents(PlayerClass $class, int $treeId, string $ranks): array
    {
        $held = TalentRanks::parse($ranks);
        $names = $this->names->handle(NameKind::Spell, array_column($held, 'spell'));
        $wanted = TalentModules::of($class);
        $chosen = array_filter($held, fn (array $node): bool => $node['spell'] !== null && in_array($names[$node['spell']] ?? '', $wanted, true));
        if ($chosen === []) {
            return [];
        }
        $nodes = $this->layouts->handle($treeId)['nodes'] ?? [];
        $talents = [];
        foreach ($chosen as $node) {
            $max = $nodes[$node['node']]['max'] ?? null;
            if ($max !== null) {
                $talents[] = new HeldTalent($names[$node['spell']], $node['spell'], $node['rank'], $max);
            }
        }

        return array_slice($talents, 0, TalentModules::MOST);
    }
}
