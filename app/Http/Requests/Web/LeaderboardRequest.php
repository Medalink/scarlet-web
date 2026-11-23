<?php

namespace App\Http\Requests\Web;

use App\Support\Cursor;

/** The filters, the scope and the page of a leaderboard, as query parameters. */
class LeaderboardRequest extends ScopeRequest
{
    public const int PAGE = 50;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules() + [
            'class' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z]+$/'],
            'cursor' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    public function characterClass(): ?string
    {
        $class = $this->validated('class');

        return is_string($class) ? strtoupper($class) : null;
    }

    public function offset(): int
    {
        $cursor = $this->validated('cursor');

        return Cursor::offset(is_string($cursor) ? $cursor : null);
    }

    /** The account the page is shown to, for highlighting its own characters. */
    public function viewerAccountId(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user->getAuthIdentifier();
    }
}
