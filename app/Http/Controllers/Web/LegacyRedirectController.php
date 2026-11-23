<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

/**
 * A permanent redirect from an address that moved. The route says where with defaults: `to` (a path that may hold the
 * route's own parameters as `{name}`), `with` (query parameters added to the visitor's own, values may hold `{name}` too)
 * and `anchor`. The visitor's query string travels along.
 */
class LegacyRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $route = $request->route();
        $defaults = $route instanceof Route ? $route->defaults : [];
        $parameters = array_map(fn (mixed $value): string => (string) $value, $route instanceof Route ? $route->parameters() : []);
        $fill = function (string $text, bool $encode) use ($parameters): string {
            foreach ($parameters as $key => $value) {
                $text = str_replace('{'.$key.'}', $encode ? rawurlencode($value) : $value, $text);
            }

            return $text;
        };
        $with = is_array($defaults['with'] ?? null) ? $defaults['with'] : [];
        $query = array_merge($request->query(), array_map(fn (mixed $value): string => $fill((string) $value, false), $with));
        $anchor = (string) ($defaults['anchor'] ?? '');

        return redirect()->to($fill((string) ($defaults['to'] ?? '/'), true).($query === [] ? '' : '?'.http_build_query($query)).($anchor === '' ? '' : '#'.$anchor), 301);
    }
}
