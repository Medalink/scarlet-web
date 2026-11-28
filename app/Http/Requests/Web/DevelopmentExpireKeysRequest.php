<?php

namespace App\Http\Requests\Web;

use App\Models\IdentityProvider;
use Illuminate\Foundation\Http\FormRequest;

/** POST /auth/development/expire-keys: allowed only where the development sign-in is, for the keys with the given name. */
class DevelopmentExpireKeysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return IdentityProvider::developmentAllowed();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:60']];
    }

    public function keyName(): string
    {
        return (string) $this->validated('name');
    }
}
