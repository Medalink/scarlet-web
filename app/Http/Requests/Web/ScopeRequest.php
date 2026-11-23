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
    /** The Cache-Control of a page that shows one account's own numbers. */
    public const string PRIVATE_CACHE = 'max-age=0, must-revalidate, no-cache, no-store, private';

    private const int MAX_NAME = 64;

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

    /** Everyone, You vs everyone (`compare`) or Only you (`mine`); the last two are the signed-in viewer's alone, and any other value is everyone. */
    public function choice(): string
    {
        $scope = $this->query('scope');

        return $this->user() instanceof Account && in_array($scope, ['compare', 'mine'], true) ? $scope : 'everyone';
    }

    /** The name of the character `You vs everyone` compares, when the query names one. */
    public function comparedName(): ?string
    {
        $name = $this->query('as');

        return is_string($name) && $name !== '' && mb_strlen($name) <= self::MAX_NAME ? $name : null;
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

    public function viewer(): ?Account
    {
        $account = $this->user();

        return $account instanceof Account ? $account : null;
    }

    /** The signed-in account of a route behind the `auth` middleware. */
    public function account(): Account
    {
        $account = $this->user();

        return $account instanceof Account ? $account : throw new LogicException('A route that reads the account is open to visitors.');
    }

    /**
     * The characters of the signed-in account when the page is asked for with `?scope=mine`, where it shows
     * only a player's own numbers; null otherwise. The ids never come from the request.
     *
     * @return list<int>|null
     */
    public function ownCharacters(OwnCharacterIds $ids): ?array
    {
        $own = $this->choice() === 'mine';

        return $own ? $ids->handle($this->account()) : null;
    }
}
