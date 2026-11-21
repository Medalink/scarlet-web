<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/** The code of /link, as the app shows it: eight letters and digits, with or without the hyphen. */
class LinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['code' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{4}-?[A-Za-z0-9]{4}$/']];
    }

    /** The code in the app's form, "XXXX-XXXX", or null when none was sent. */
    public function code(): ?string
    {
        $code = $this->validated('code');
        if (! is_string($code) || $code === '') {
            return null;
        }
        $plain = strtoupper(str_replace('-', '', $code));

        return substr($plain, 0, 4).'-'.substr($plain, 4);
    }
}
