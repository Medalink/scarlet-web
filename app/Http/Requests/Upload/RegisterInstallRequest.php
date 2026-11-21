<?php

namespace App\Http\Requests\Upload;

use App\Support\Exceptions\ApiException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/** POST /api/v1/installs: the only unsigned upload request. */
class RegisterInstallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'public_key' => ['required', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:64'],
        ];
    }

    /** The 32 bytes of the Ed25519 public key. */
    public function publicKey(): string
    {
        $key = base64_decode((string) $this->validated('public_key'), strict: true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw ApiException::invalidRequest('public_key must be the 32 bytes of an Ed25519 public key, in base64.', ['public_key' => ['Not a base64 Ed25519 public key.']]);
        }

        return $key;
    }

    public function label(): ?string
    {
        $name = $this->validated('name');

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    protected function failedValidation(Validator $validator): never
    {
        throw ApiException::invalidRequest('The request is not valid.', $validator->errors()->toArray());
    }
}
