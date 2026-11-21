<?php

namespace App\Http\Requests\Web;

use App\Support\Server;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The ruleset and server filters of a dashboard page, as query parameters. */
class ScopeRequest extends FormRequest
{
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
        ];
    }

    /** The ruleset of the query, unless the path already names one. */
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
}
