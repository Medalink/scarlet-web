<?php

namespace App\View\Pages\Admin;

use App\Http\Requests\Web\AdminCharactersRequest;
use App\View\Dates;
use App\View\Format;
use Illuminate\Support\HtmlString;

/** The view data of /admin/characters from ListCharacters (docs/comps/admin.html for the shared look). */
final class CharactersPage
{
    private const string PATH = '/admin/characters';

    /**
     * @param  array{rows: list<array<string, mixed>>, matched: int, public: int, private: int, page: int, pages: int}  $list
     * @return array<string, mixed>
     */
    public static function data(array $list, string $search, ?bool $visibility): array
    {
        $link = fn (?bool $show, int $page): string => self::PATH.self::query($search, $show, $page);

        return [
            'nav' => AdminCharts::nav(self::PATH),
            'search' => $search,
            'show' => match ($visibility) {
                true => AdminCharactersRequest::PUBLIC,
                false => AdminCharactersRequest::PRIVATE,
                null => '',
            },
            'filters' => [
                ['label' => 'All '.Format::number($list['public'] + $list['private']), 'href' => $link(null, 1), 'on' => $visibility === null],
                ['label' => 'Public '.Format::number($list['public']), 'href' => $link(true, 1), 'on' => $visibility === true],
                ['label' => 'Private '.Format::number($list['private']), 'href' => $link(false, 1), 'on' => $visibility === false],
            ],
            'rows' => array_map(self::row(...), $list['rows']),
            'empty' => $search === '' ? 'No character has been uploaded yet.' : 'No character matches “'.$search.'”.',
            'page' => 'Page '.Format::number($list['page']).' of '.Format::number($list['pages']).', '.Format::count($list['matched'], 'character'),
            'previous' => $list['page'] > 1 ? $link($visibility, $list['page'] - 1) : null,
            'next' => $list['page'] < $list['pages'] ? $link($visibility, $list['page'] + 1) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function row(array $row): array
    {
        $name = (string) $row['name'];
        $class = is_string($row['class']) ? $row['class'] : null;
        $race = is_string($row['race']) ? $row['race'] : null;

        return ['cells' => [
            ['text' => Format::characterName($name), 'dot' => true, 'characterClass' => strtoupper((string) $class), ...($row['public'] ? ['href' => '/c/'.rawurlencode($name)] : []), ...($row['flagged'] ? ['tag' => 'Flagged'] : [])],
            $row['public'] ? 'Public' : 'Private',
            is_int($row['level']) ? (string) $row['level'] : '',
            trim(Format::raceName($race).' '.Format::className($class)),
            is_string($row['realm']) ? $row['realm'] : '',
            is_int($row['account_id']) ? '#'.$row['account_id'] : ['text' => '', 'note' => 'None'],
            Format::number((int) $row['installs']),
            Format::number((int) $row['sessions']),
            (int) $row['played'] > 0 ? Format::duration((int) $row['played']) : '',
            self::when($row['last_seen_at']),
            self::when($row['first_seen_at']),
        ]];
    }

    /** @return string|array{date: HtmlString} */
    private static function when(mixed $instant): string|array
    {
        return is_string($instant) ? ['date' => Dates::moment($instant)] : '';
    }

    private static function query(string $search, ?bool $show, int $page): string
    {
        $query = http_build_query(array_filter([
            'q' => $search,
            'show' => match ($show) {
                true => AdminCharactersRequest::PUBLIC,
                false => AdminCharactersRequest::PRIVATE,
                null => '',
            },
            'page' => $page > 1 ? $page : '',
        ], fn (string|int $value): bool => $value !== ''));

        return $query === '' ? '' : '?'.$query;
    }
}
