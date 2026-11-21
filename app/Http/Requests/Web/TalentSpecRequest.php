<?php

namespace App\Http\Requests\Web;

use App\Support\LevelBracket;
use Illuminate\Validation\Rule;

/** The ruleset, server and level bracket of a talent page, as query parameters. */
class TalentSpecRequest extends ScopeRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...parent::rules(), 'level' => ['nullable', Rule::enum(LevelBracket::class)]];
    }

    public function bracket(): LevelBracket
    {
        $level = $this->validated('level');

        return is_string($level) ? LevelBracket::from($level) : LevelBracket::All;
    }
}
