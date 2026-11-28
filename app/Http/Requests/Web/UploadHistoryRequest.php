<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/** GET /account/storage/history: which page of the upload history is read. */
class UploadHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['page' => ['nullable', 'integer', 'min:1', 'max:10000']];
    }

    public function page(): int
    {
        $page = $this->validated('page');

        return is_numeric($page) ? (int) $page : 1;
    }
}
