<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/** What a provider sends back to /auth/{provider}/callback: a code and the state, or an error. */
class OAuthCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:2048'],
            'state' => ['nullable', 'string', 'max:2048'],
            'error' => ['nullable', 'string', 'max:255'],
        ];
    }
}
