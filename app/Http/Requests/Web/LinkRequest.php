<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The code the person types on /link, as the app shows it: eight letters and digits, with or without the hyphen.
 * Only the confirm POST reads it; the page ignores a code in its address. The `redeem` is the base64 signature of
 * the app's key over that code, which the address carries in and the confirm POST carries back.
 */
class LinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'code' => $this->isMethod('post') ? ['nullable', 'string', 'regex:/^[A-Za-z0-9]{4}-?[A-Za-z0-9]{4}$/'] : ['nullable'],
            'redeem' => ['nullable', 'string', 'max:128'],
        ];
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

    /** The redemption signature the app put in the link's address, or null when there was none. */
    public function redeem(): ?string
    {
        $redeem = $this->validated('redeem');

        return is_string($redeem) && $redeem !== '' ? $redeem : null;
    }
}
