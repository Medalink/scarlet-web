<?php

namespace App\Http\Requests\Web;

use App\Models\Account;
use App\Support\WorldLayer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The ruleset, layer, opened zone and character of the world map page, as query parameters. On `/account/map`, declared
 * with `->defaults(ScopeRequest::OWN, true)`, the page is the signed-in account's own characters.
 */
class MapRequest extends FormRequest
{
    private const int LARGEST_ZONE = 1000000;

    public function authorize(): bool
    {
        return true;
    }

    /** A `zone` that is no uiMapID is dropped, so an address someone mistyped still opens the world. */
    protected function prepareForValidation(): void
    {
        if (filter_var($this->query('zone'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::LARGEST_ZONE]]) === false) {
            $this->query->remove('zone');
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ruleset' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'layer' => ['nullable', Rule::enum(WorldLayer::class)],
            'zone' => ['nullable', 'integer', 'min:1', 'max:'.self::LARGEST_ZONE],
            'character' => ['nullable', 'string', 'max:64'],
            'scope' => ['nullable', Rule::in(['everyone', 'mine'])],
        ];
    }

    public function rulesetSlug(): ?string
    {
        $slug = $this->validated('ruleset');

        return is_string($slug) ? $slug : null;
    }

    /** The uiMapID of the zone the map opens on. */
    public function zone(): ?int
    {
        $zone = $this->validated('zone');

        return is_numeric($zone) ? (int) $zone : null;
    }

    public function layer(): WorldLayer
    {
        $layer = $this->validated('layer');

        return is_string($layer) ? WorldLayer::from($layer) : WorldLayer::Paths;
    }

    /** The name of the character whose trails the map shows (`?character=Name-Surname`). */
    public function character(): ?string
    {
        $name = $this->validated('character');

        return is_string($name) && $name !== '' ? $name : null;
    }

    /** Whether the map is the signed-in account's own: the route says so, or a signed-in viewer asks for `?scope=mine`. */
    public function own(): bool
    {
        return ($this->route()?->defaults[ScopeRequest::OWN] ?? false) === true || ($this->viewer() !== null && $this->validated('scope') === 'mine');
    }

    /** The signed-in account, or null for a visitor. */
    public function viewer(): ?Account
    {
        $user = $this->user();

        return $user instanceof Account ? $user : null;
    }
}
