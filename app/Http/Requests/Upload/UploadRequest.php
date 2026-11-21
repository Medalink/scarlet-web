<?php

namespace App\Http\Requests\Upload;

use App\Http\Middleware\VerifyInstallSignature;
use App\Models\Install;
use App\Support\Exceptions\ApiException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An upload API request: the middleware authenticated the install, and a validation failure is
 * answered with the API's error shape, not Laravel's.
 */
abstract class UploadRequest extends FormRequest
{
    /** The character name rule: `Name-Surname`, one hyphen, no spaces. */
    public const string CHARACTER_PATTERN = '/^[^\s\-\/\\\\]{1,40}-[^\s\-\/\\\\]{1,40}$/u';

    public function authorize(): bool
    {
        return $this->attributes->get(VerifyInstallSignature::ATTRIBUTE) instanceof Install;
    }

    public function install(): Install
    {
        /** @var Install */
        return $this->attributes->get(VerifyInstallSignature::ATTRIBUTE);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw ApiException::invalidRequest('The request is not valid.', $validator->errors()->toArray());
    }
}
