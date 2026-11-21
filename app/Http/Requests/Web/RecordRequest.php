<?php

namespace App\Http\Requests\Web;

/** The ruleset and server filters of a record page, and the loot rule a creature page shows. */
class RecordRequest extends ScopeRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...parent::rules(), 'rule' => ['nullable', 'string', 'max:24', 'regex:/^[a-z]+$/']];
    }

    public function lootRule(): ?string
    {
        $rule = $this->validated('rule');

        return is_string($rule) ? $rule : null;
    }
}
