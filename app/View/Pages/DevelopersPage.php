<?php

namespace App\View\Pages;

use App\Http\Middleware\PublicApi;
use App\Models\Account;
use App\Support\PublicApiCatalog;
use Illuminate\Support\HtmlString;

/** The data of docs/comps/developers.html, read from the same PublicApiCatalog that registers the routes. */
final class DevelopersPage
{
    /** The path every endpoint sits under. */
    public const string BASE = '/api/v1/public';

    /** @return array<string, mixed> */
    public static function data(string $host): array
    {
        $basics = [['#use', 'Using the API'], ['#limits', 'Limits and keys'], ['#pages', 'Pagination'], ['#errors', 'Errors']];
        $groups = [];
        $endpoints = [];
        foreach (PublicApiCatalog::endpoints() as $endpoint) {
            $id = 'ep-'.$endpoint['name'];
            $groups[$endpoint['group']][] = ['href' => '#'.$id, 'label' => $endpoint['path']];
            $endpoints[] = [
                'id' => $id,
                'title' => self::BASE.$endpoint['path'],
                'summary' => self::inline($endpoint['summary']),
                'returns' => self::inline($endpoint['returns']),
                'params' => array_map(fn (array $param): array => [$param[0], new HtmlString(e(ucfirst($param[1] === 'path' ? 'in the path' : 'query')).', '.e($param[2]).'. '.self::inline($param[3]))], PublicApiCatalog::params($endpoint['params'])),
                'curl' => 'curl '.$host.self::BASE.$endpoint['example'],
            ];
        }

        return [
            'host' => $host,
            'nav' => [
                ['title' => 'Basics', 'links' => array_map(fn (array $link): array => ['href' => $link[0], 'label' => $link[1]], $basics)],
                ...array_map(fn (string $title, array $links): array => ['title' => $title, 'links' => $links], array_keys($groups), array_values($groups)),
            ],
            'endpoints' => $endpoints,
            'anonymous' => (int) config('scarlet.rate.public.anonymous'),
            'token' => (int) config('scarlet.rate.public.token'),
            'ability' => Account::API_ABILITY,
            'maxAge' => PublicApi::MAX_AGE,
            'namedMaxAge' => PublicApi::NAMED_MAX_AGE,
            'badTokens' => PublicApi::BAD_TOKENS_PER_MINUTE,
        ];
    }

    /** Text with `code` spans turned into code elements; everything else is escaped. */
    private static function inline(string $text): HtmlString
    {
        return new HtmlString((string) preg_replace('/`([^`]+)`/', '<code>$1</code>', e($text)));
    }
}
