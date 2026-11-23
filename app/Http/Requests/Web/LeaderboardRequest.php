<?php

namespace App\Http\Requests\Web;

use App\Support\Cursor;
use App\Support\Server;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The filters and the page of a leaderboard, as query parameters. */
class LeaderboardRequest extends FormRequest
{
    public const int PAGE = 50;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ruleset' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'server' => ['nullable', Rule::enum(Server::class)],
            'class' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z]+$/'],
            'cursor' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    public function rulesetSlug(): ?string
    {
        $slug = $this->validated('ruleset');

        return is_string($slug) ? $slug : null;
    }

    public function serverFilter(): Server
    {
        $server = $this->validated('server');

        return is_string($server) ? Server::from($server) : Server::All;
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
