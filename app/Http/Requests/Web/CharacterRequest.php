<?php

namespace App\Http\Requests\Web;

use App\Actions\Public\CharacterOverview;
use Illuminate\Foundation\Http\FormRequest;

/** How many sessions of the play history a character page lists. */
class CharacterRequest extends FormRequest
{
    public const int MAX_HISTORY = 200;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['sessions' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_HISTORY], 'as' => ['nullable', 'in:visitor']];
    }

    public function history(): int
    {
        $sessions = $this->validated('sessions');

        return is_numeric($sessions) ? (int) $sessions : CharacterOverview::PAGE_HISTORY;
    }

    /** Whether the page is asked for as a signed-out visitor sees it (`?as=visitor`). */
    public function asVisitor(): bool
    {
        return $this->validated('as') === 'visitor';
    }

    /** The account the page is shown to, or null for a visitor. */
    public function viewerAccountId(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user->getAuthIdentifier();
    }
}
