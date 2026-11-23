<?php

namespace App\Http\Requests\Upload;

use App\Support\Feat;
use Closure;

/** POST /api/v1/installs/me/feats/seen: the tiers the app showed for one character. */
class FeatsSeenRequest extends UploadRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'character' => ['required', 'string', 'regex:'.self::CHARACTER_PATTERN],
            'feats' => ['required', 'array', 'min:1', 'max:'.count(Feat::cases()), function (string $attribute, mixed $value, Closure $fail): void {
                foreach (array_keys((array) $value) as $key) {
                    if (Feat::tryFrom((string) $key) === null) {
                        $fail('The feats must be keyed by feat.');

                        return;
                    }
                }
            }],
            'feats.*' => ['required', 'integer', 'between:1,'.count(Feat::TIERS)],
        ];
    }

    public function character(): string
    {
        return (string) $this->validated('character');
    }

    /** @return array<string, int> feat key => tier shown */
    public function shown(): array
    {
        /** @var array<string, int> */
        return array_map('intval', (array) $this->validated('feats'));
    }
}
