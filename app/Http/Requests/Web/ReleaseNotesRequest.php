<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/** The release and the kind of change /release-notes shows, as query parameters. */
class ReleaseNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'v' => ['nullable', 'string', 'max:32', 'regex:/^[0-9][0-9A-Za-z.+-]*$/'],
            'only' => ['nullable', 'string', 'in:new,improved,fixed'],
        ];
    }

    public function version(): ?string
    {
        $version = $this->validated('v');

        return is_string($version) ? $version : null;
    }

    public function kind(): ?string
    {
        $only = $this->validated('only');

        return is_string($only) ? $only : null;
    }
}
