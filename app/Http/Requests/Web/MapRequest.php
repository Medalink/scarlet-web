<?php

namespace App\Http\Requests\Web;

use App\Support\WorldLayer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The ruleset, layer and opened zone of the world map page, as query parameters. */
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
}
