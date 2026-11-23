<?php

namespace App\Http\Requests\Web;

use App\Actions\Account\OwnCharacterIds;
use App\Models\Account;
use App\Support\Server;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

/** The ruleset and server filters of a dashboard page, as query parameters. */
class ScopeRequest extends FormRequest
{
    /** The route default that declares a page a player's own. */
    public const string OWN = 'own';

    private const array CHOICES = ['everyone', 'compare', 'mine'];

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
            'scope' => ['nullable', Rule::in(self::CHOICES)],
            'as' => ['nullable', 'string', 'max:64'],
        ];
    }

    /** Everyone, You vs everyone (`compare`) or Only you (`mine`); the last two are the signed-in viewer's alone. */
    public function choice(): string
    {
        $scope = $this->validated('scope');

        return $this->user() instanceof Account && in_array($scope, ['compare', 'mine'], true) ? $scope : 'everyone';
    }

    /** The name of the character `You vs everyone` compares, when the query names one. */
    public function comparedName(): ?string
    {
        $name = $this->validated('as');

        return is_string($name) && $name !== '' ? $name : null;
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

    /** The signed-in account of a route behind the `auth` middleware. */
    public function account(): Account
    {
        $account = $this->user();

        return $account instanceof Account ? $account : throw new LogicException('A route that reads the account is open to visitors.');
    }

    /**
     * The characters of the signed-in account on a route declared with `->defaults(ScopeRequest::OWN, true)`, where the page shows
     * only a player's own numbers; null on a public route. The ids never come from the request.
     *
     * @return list<int>|null
     */
    public function ownCharacters(OwnCharacterIds $ids): ?array
    {
        $own = ($this->route()?->defaults[self::OWN] ?? false) === true || $this->choice() === 'mine';

        return $own ? $ids->handle($this->account()) : null;
    }
}
