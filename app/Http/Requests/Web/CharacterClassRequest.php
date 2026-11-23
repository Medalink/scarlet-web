<?php

namespace App\Http\Requests\Web;

/** The class page reads no query parameter, so it validates none: `?sessions=` and `?as=` mean nothing to it. */
class CharacterClassRequest extends CharacterRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
