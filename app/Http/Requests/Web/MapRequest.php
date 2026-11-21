<?php

namespace App\Http\Requests\Web;

use App\Support\WorldLayer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The ruleset and layer of the world map page, as query parameters. */
class MapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ruleset' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'layer' => ['nullable', Rule::enum(WorldLayer::class)],
        ];
    }

    public function rulesetSlug(): ?string
    {
        $slug = $this->validated('ruleset');

        return is_string($slug) ? $slug : null;
    }

    public function layer(): WorldLayer
    {
        $layer = $this->validated('layer');

        return is_string($layer) ? WorldLayer::from($layer) : WorldLayer::Paths;
    }
}
