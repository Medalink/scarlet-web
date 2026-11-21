<?php

namespace App\Http\Requests\Upload;

/** POST /api/v1/upload/sync: the app's count and set digest of one character's parts. */
class SyncRequest extends UploadRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'character' => ['required', 'string', 'regex:'.self::CHARACTER_PATTERN],
            'groups' => ['required', 'array', 'min:1', 'max:64'],
            'groups.*' => ['required', 'string', 'max:16', 'distinct'],
            'count' => ['required', 'integer', 'min:0'],
            'digest' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/'],
        ];
    }

    public function character(): string
    {
        return (string) $this->validated('character');
    }

    /** @return list<string> */
    public function groups(): array
    {
        /** @var list<string> */
        return array_values((array) $this->validated('groups'));
    }

    public function count(): int
    {
        return (int) $this->validated('count');
    }

    public function digest(): string
    {
        return (string) $this->validated('digest');
    }
}
