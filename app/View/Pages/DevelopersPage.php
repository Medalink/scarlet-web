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

    /** The header every example sends: the reader's key in the environment variable `SCARLET_API_KEY`. */
    public const string KEY_HEADER = '-H "Authorization: Bearer $SCARLET_API_KEY"';

    private const int COLLAPSE_FROM = 320;

    /** @return array<string, mixed> */
    public static function data(string $host): array
    {
        $basics = [['#use', 'Using the API'], ['#keys', 'API keys'], ['#limits', 'Limits'], ['#pages', 'Pagination'], ['#errors', 'Errors']];
        $groups = [];
        $endpoints = [];
        $examples = self::examples();
        foreach (PublicApiCatalog::endpoints() as $endpoint) {
            $id = 'ep-'.$endpoint['name'];
            $groups[$endpoint['group']][] = ['href' => '#'.$id, 'label' => $endpoint['path']];
            $endpoints[] = [
                'id' => $id,
                'title' => self::BASE.$endpoint['path'],
                'summary' => self::inline($endpoint['summary']),
                'returns' => self::inline($endpoint['returns']),
                'long' => mb_strlen($endpoint['returns']) > self::COLLAPSE_FROM,
                'params' => array_map(fn (array $param): array => [$param[0], new HtmlString(e(ucfirst($param[1] === 'path' ? 'in the path' : 'query')).', '.e($param[2]).'. '.self::inline($param[3]))], PublicApiCatalog::params($endpoint['params'])),
                'curl' => 'curl '.self::KEY_HEADER.' '.$host.self::BASE.$endpoint['example'],
                'example' => $examples[$endpoint['name']] ?? null,
            ];
        }

        return [
            'host' => $host,
            'nav' => [
                ['title' => 'Basics', 'links' => array_map(fn (array $link): array => ['href' => $link[0], 'label' => $link[1]], $basics)],
                ...array_map(fn (string $title, array $links): array => ['title' => $title, 'links' => $links], array_keys($groups), array_values($groups)),
            ],
            'endpoints' => $endpoints,
            'token' => (int) config('scarlet.rate.public.token'),
            'ability' => Account::API_ABILITY,
            'keyDays' => Account::API_KEY_DAYS,
            'keyMaxDays' => Account::API_KEY_MAX_DAYS,
            'maxKeys' => Account::MAX_API_KEYS,
            'keyHeader' => self::KEY_HEADER,
            'maxAge' => PublicApi::MAX_AGE,
            'namedMaxAge' => PublicApi::NAMED_MAX_AGE,
            'badTokens' => PublicApi::BAD_TOKENS_PER_MINUTE,
            'keepDays' => (int) config('scarlet.api_calls_days'),
        ];
    }

    /**
     * The example answers scripts/api-examples.mjs wrote from the e2e fixture, by endpoint name.
     *
     * @return array<string, string>
     */
    private static function examples(): array
    {
        $file = resource_path('api-examples.json');
        $read = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($read) ? array_filter($read, is_string(...)) : [];
    }

    /** Text with `code` spans turned into code elements; everything else is escaped. */
    private static function inline(string $text): HtmlString
    {
        return new HtmlString((string) preg_replace('/`([^`]+)`/', '<code>$1</code>', e($text)));
    }
}
