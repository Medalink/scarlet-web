<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The search, the visibility and the page of `/admin/characters`, as query parameters. AdminOnly has let only an admin
 * in. A value that does not fit falls back to its default, so a hand-edited or old link still shows the list rather
 * than redirecting away from it.
 */
class AdminCharactersRequest extends FormRequest
{
    public const string PUBLIC = 'public';

    public const string PRIVATE = 'private';

    private const int MAX_SEARCH = 64;

    private const int MAX_PAGE = 100000;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function search(): string
    {
        $text = $this->query('q');

        return is_string($text) ? mb_substr(trim((string) preg_replace('/\p{Cc}/u', '', $text)), 0, self::MAX_SEARCH) : '';
    }

    /** @return bool|null true for public characters only, false for private ones only, null for both */
    public function visibility(): ?bool
    {
        return match ($this->query('show')) {
            self::PUBLIC => true,
            self::PRIVATE => false,
            default => null,
        };
    }

    public function page(): int
    {
        $page = $this->query('page');

        return is_string($page) && preg_match('/^\d{1,6}$/', $page) === 1 ? max(1, min((int) $page, self::MAX_PAGE)) : 1;
    }
}
