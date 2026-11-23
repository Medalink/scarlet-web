<?php

namespace App\View\Pages;

use App\View\Format;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Medalink\AppVersion\AppVersion;
use Medalink\AppVersion\Models\ReleaseNote;

/**
 * The data of /release-notes: the releases to pick from, one release with its changes grouped by area, and the
 * build that is running. Rows come from ReleaseNote::changes().
 */
final class ReleaseNotesPage
{
    /** The kinds of change, in the order a release lists them. */
    public const array KINDS = [
        ReleaseNote::SECTION_NEW => 'New',
        ReleaseNote::SECTION_IMPROVED => 'Improved',
        ReleaseNote::SECTION_FIXED => 'Fixed',
    ];

    /** Areas left out of a release's title ("mostly in ..."). */
    private const string CATCH_ALL_AREA = '/^General\b/i';

    /**
     * @param  Collection<int, ReleaseNote>  $releases  newest first
     * @return array<string, mixed>
     */
    public static function data(Collection $releases, ?string $version, ?string $only): array
    {
        $list = $releases->values();
        $index = $list->search(fn (ReleaseNote $release): bool => $release->version === $version);
        $index = $index === false ? 0 : $index;
        $selected = $list->get($index);

        return [
            'build' => self::build(),
            'count' => $list->count(),
            'rail' => $list->map(fn (ReleaseNote $release): array => self::railEntry($release, $release->version === $selected?->version))->all(),
            'release' => $selected instanceof ReleaseNote ? self::release($selected, $list->get($index + 1), $only) : null,
        ];
    }

    /**
     * The version line of the footer: "v0.1.0.12" and the full build string with its commit.
     *
     * @return array{text: string, full: string}
     */
    public static function stamp(): array
    {
        return ['text' => 'v'.AppVersion::version().'.'.AppVersion::build(), 'full' => AppVersion::full()];
    }

    /**
     * A change line with paths, flags and identifiers set as code. The text is
     * escaped first; only the markup added here is trusted.
     */
    public static function text(string $text): HtmlString
    {
        $html = e($text);
        $html = (string) preg_replace('/(^|[\s(])((?:--[a-z][\w-]*)|(?:\/[a-z][\w\/{}.-]*)|(?:[a-z]+(?:_[a-z0-9]+)+)|(?:[a-z]+:[a-z][\w:-]*))/', '$1<code>$2</code>', $html);

        return new HtmlString($html);
    }

    /** @return array{version: string, date: string|null, total: int, current: bool} */
    private static function railEntry(ReleaseNote $release, bool $current): array
    {
        return [
            'version' => $release->version,
            'date' => $release->published_at?->format('j M Y'),
            'total' => count($release->changes()),
            'current' => $current,
        ];
    }

    /** @return array<string, mixed> */
    private static function release(ReleaseNote $release, ?ReleaseNote $previous, ?string $only): array
    {
        $changes = $release->changes();
        $about = [];
        foreach ($release->featureGroups() as $group) {
            $about[$group['title']] = $group['summary'];
        }

        $rows = [];
        foreach ($changes as $change) {
            if ($only !== null && $change['section'] !== $only) {
                continue;
            }
            $rows[$change['area']][] = ['kind' => $change['section'], 'text' => self::text($change['text'])];
        }
        $areas = [];
        foreach ($rows as $title => $areaRows) {
            $areas[] = ['title' => $title, 'about' => $about[$title] ?? '', 'rows' => $areaRows];
        }

        $counts = array_fill_keys(array_keys(self::KINDS), 0);
        foreach ($changes as $change) {
            $counts[$change['section']]++;
        }

        return [
            'version' => $release->version,
            'date' => $release->published_at?->format('j M Y'),
            'since' => $release->previous_version,
            'title' => self::title(count($changes), self::busiest($changes)),
            'total' => count($changes),
            'counts' => $counts,
            'only' => $only,
            'filters' => self::filters($release->version, $only, $counts, count($changes)),
            'areas' => $areas,
            'days' => $previous?->published_at !== null && $release->published_at !== null
                ? max((int) round($previous->published_at->diffInDays($release->published_at)), 1)
                : null,
        ];
    }

    /**
     * @param  list<array{area: string}>  $changes
     * @return list<string> the areas with the most changes, busiest first, without the catch-all
     */
    private static function busiest(array $changes): array
    {
        $sizes = array_count_values(array_column($changes, 'area'));
        arsort($sizes);
        $named = array_filter(array_keys($sizes), fn (string $area): bool => ! preg_match(self::CATCH_ALL_AREA, $area));

        return array_slice(array_values($named), 0, 3);
    }

    /** @param  list<string>  $areas */
    private static function title(int $total, array $areas): string
    {
        $changes = Format::count($total, 'change');
        $last = array_pop($areas);
        if ($last === null) {
            return $changes;
        }

        return $changes.', mostly in '.($areas === [] ? $last : implode(', ', $areas).' and '.$last);
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{label: string, href: string, on: bool}>
     */
    private static function filters(string $version, ?string $only, array $counts, int $total): array
    {
        $items = [['label' => 'All '.$total, 'href' => '/download?v='.urlencode($version).'#release-notes', 'on' => $only === null]];
        foreach (self::KINDS as $key => $label) {
            $items[] = ['label' => $label.' '.$counts[$key], 'href' => '/download?v='.urlencode($version).'&only='.$key.'#release-notes', 'on' => $only === $key];
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private static function build(): array
    {
        $v = AppVersion::toArray();
        $stats = $v['stats'];
        $commits = $stats['total_commits'];
        $span = $v['commitSpanDays'];
        $buildDays = $v['buildSpanDays'];

        return [
            'full' => $v['full'],
            'figures' => array_values(array_filter([
                ['label' => 'Version', 'value' => $v['version']],
                ['label' => 'Build', 'value' => '#'.Format::number($v['build'])],
                ['label' => 'Commit', 'value' => $v['commit']],
                $stats['lines'] > 0 ? ['label' => 'Lines', 'value' => Format::number($stats['lines'])] : null,
                $stats['files'] > 0 ? ['label' => 'Files', 'value' => Format::number($stats['files'])] : null,
            ])),
            'rows' => [
                ['cells' => ['Last commit', $v['commit'], '+'.Format::number($stats['commit_additions']), '−'.Format::number($stats['commit_deletions'])]],
                ['cells' => [
                    'This build',
                    Format::count($v['build'], 'commit').' since v'.$v['version'].($buildDays === null ? '' : ' in '.Format::count($buildDays, 'day')),
                    '+'.Format::number($stats['build_additions']),
                    '−'.Format::number($stats['build_deletions']),
                ]],
                ['cells' => [
                    'All time',
                    Format::count($commits, 'commit').($span === null ? '' : ' in '.Format::count($span, 'day')),
                    '+'.Format::number($stats['lifetime_additions']),
                    '−'.Format::number($stats['lifetime_deletions']),
                ]],
            ],
        ];
    }
}
