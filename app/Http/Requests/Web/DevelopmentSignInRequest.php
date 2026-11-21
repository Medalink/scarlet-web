<?php

namespace App\Http\Requests\Web;

use App\Models\IdentityProvider;
use Illuminate\Foundation\Http\FormRequest;

/** POST /auth/development: allowed only in a local environment with the development sign-in switched on. */
class DevelopmentSignInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return IdentityProvider::developmentAllowed();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }
}
