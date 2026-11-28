<?php

namespace App\Http\Requests\Web;

use App\Models\IdentityProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST /auth/development: allowed only in a local environment with the development sign-in switched on. */
class DevelopmentSignInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return IdentityProvider::developmentAllowed();
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['as' => ['nullable', Rule::in([IdentityProvider::Battlenet->value])]];
    }

    /** True when the sign-in asks for the development Battle.net identity (`as=battlenet`). */
    public function asBattlenet(): bool
    {
        return $this->validated('as') === IdentityProvider::Battlenet->value;
    }
}
