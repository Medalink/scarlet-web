<?php

namespace App\View\Character;

use App\Models\Character;
use App\Support\ClassPage\ClassIcons;
use App\Support\ClassPage\ClassPage;
use App\Support\Scene;
use App\Support\SceneArt;
use App\View\Format;
use App\View\Pages\FeatsPage;
use App\View\Pages\ImportedHistoryPage;
use Illuminate\Support\Str;

/** The data of /c/{name} (design/final/character-web.html) from the bundle of App\Actions\Public\ShowCharacter. */
final class Page
{
    private const int TREE_POINTS = 31;

    /**
     * @param  array<string, mixed>  $bundle
     * @return array<string, mixed>
     */
    public static function data(array $bundle, bool $asVisitor, ?int $viewerAccountId): array
    {
        $r = $bundle['overview'];
        /** @var Character $character */
        $character = $r['character'];
        /** @var ClassPage|null $class */
        $class = $bundle['class'];
        $feats = FeatsSection::data($bundle['feats'], $character, $r['owner']);
        $summary = $bundle['feats']['character'] + ['max' => FeatsPage::scoreMax()];
        $head = Head::data($r, $summary);
        $first = $head['first'];
        $className = Format::className($character->class);
        $base = '/c/'.rawurlencode($character->name);
        $sessions = $bundle['sessions'];
        $class = self::classSection($class, $r);
        $battlegrounds = Chapters::battlegrounds($bundle['battlegrounds']);
        $levelling = Chapters::levelling($r, $first);
        $time = PlayTime::data($r['playBuckets'], $bundle['splits'], $character, $bundle['everyone'], $first);

        $sections = array_filter([
            'overview' => 'Overview',
            'feats' => $feats === null ? null : 'Feats',
            'sessions' => $sessions === null ? null : 'Sessions',
            'class' => $className === '' || $class['page'] === null ? null : $className,
            'levelling' => $levelling === null ? null : 'Levelling',
            'combat' => 'Combat',
            'loot' => 'Loot and professions',
            'time' => $time === null ? null : 'Play time',
        ]);

        return [
            'id' => $character->id,
            'owner' => $r['owner'],
            'ownerSeesVisitorView' => $asVisitor && $viewerAccountId !== null && $character->account_id === $viewerAccountId,
            'path' => $base,
            'title' => $head['name'].', '.mb_strtolower(Format::character($character->level, $character->race, $character->class)).' · Scarlet',
            'description' => $head['line'].'. Recorded by Scarlet in WoW Forever.',
            'head' => $head,
            'claim' => $character->account_id === null && ! $r['owner'],
            'sections' => $sections,
            'overview' => Overview::data($bundle['rates'], $bundle['class'], $r, $summary, $first),
            'feats' => $feats,
            'sessions' => $sessions,
            'class' => $class,
            'className' => $className,
            'levelling' => $levelling,
            'combat' => Chapters::combat($r, $first),
            'battlegrounds' => $battlegrounds,
            'loot' => Chapters::loot($r),
            'time' => $time,
            'imported' => $bundle['imported'] === null ? null : ImportedHistoryPage::character($bundle['imported']),
        ];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private static function classSection(?ClassPage $class, array $r): array
    {
        $tabs = [];
        foreach ($r['talents']['groups'] ?? [] as $group) {
            if ((int) $group['group'] === (int) $r['talents']['active']) {
                $tabs = $group['tabs'];
            }
        }
        $build = null;
        $best = null;
        foreach ($tabs as $tab) {
            $best = $best === null || $tab['spent'] > $best['spent'] ? $tab : $best;
        }
        if ($best !== null && $best['spent'] > 0 && $class !== null) {
            $build = '/talents/'.strtolower($class->class->value).'/'.Str::slug($best['name']);
        }

        $scene = $class === null ? null : Scene::tryFrom($class->slug());

        return [
            'page' => $class,
            'icons' => $class === null ? null : ClassIcons::of($class),
            'scene' => $scene,
            'art' => $scene === null ? null : SceneArt::stage($scene),
            'tabs' => array_map(fn (array $tab): array => ['name' => $tab['name'], 'points' => (int) $tab['spent'], 'share' => round(min(1, $tab['spent'] / self::TREE_POINTS) * 100, 1)], $tabs),
            'build' => $build,
            'level' => (int) $r['character']->level,
        ];
    }
}
