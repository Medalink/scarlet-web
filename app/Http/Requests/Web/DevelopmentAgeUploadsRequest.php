<?php

namespace App\Http\Requests\Web;

use App\Models\IdentityProvider;
use Illuminate\Foundation\Http\FormRequest;

/** POST /auth/development/age-uploads: allowed only where the development sign-in is, for one character of the account and a number of hours. */
class DevelopmentAgeUploadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return IdentityProvider::developmentAllowed();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['character' => ['required', 'string', 'max:64'], 'hours' => ['required', 'integer', 'between:-8760,8760']];
    }

    public function character(): string
    {
        return (string) $this->validated('character');
    }

    public function hours(): int
    {
        return (int) $this->validated('hours');
    }
}
