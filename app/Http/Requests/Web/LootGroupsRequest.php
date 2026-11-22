<?php

namespace App\Http\Requests\Web;

use App\Support\LootRule;
use App\Support\LootSourceRef;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The source, the two loot rules and the developer list of `/loot/groups`, as query parameters. */
class LootGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $compared = ['nullable', Rule::enum(LootRule::class)->except([LootRule::Personal, LootRule::Unknown])];

        return [
            'source' => ['nullable', 'string', 'regex:/^[cg][0-9]{1,10}$/'],
            'a' => $compared,
            'b' => $compared,
            'marks' => ['nullable', 'boolean'],
        ];
    }

    public function source(): ?LootSourceRef
    {
        $source = $this->validated('source');

        return is_string($source) ? LootSourceRef::parse($source) : null;
    }

    public function ruleA(): ?LootRule
    {
        return $this->rule('a');
    }

    public function ruleB(): ?LootRule
    {
        return $this->rule('b');
    }

    public function marks(): bool
    {
        return $this->boolean('marks');
    }

    private function rule(string $key): ?LootRule
    {
        $value = $this->validated($key);

        return is_string($value) ? LootRule::tryFrom($value) : null;
    }
}
