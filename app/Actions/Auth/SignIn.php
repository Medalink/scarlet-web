<?php

namespace App\Actions\Auth;

use App\Models\Account;
use App\Models\Identity;
use App\Models\IdentityProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * What happens after a provider has answered, whichever provider it was: find the account that holds the
 * identity, or make one, or add the identity to the account that is signed in. Only the provider and the
 * provider's subject id are kept.
 */
class SignIn
{
    /** The fixed identity of the development sign-in. */
    public const string DEVELOPMENT_SUBJECT = 'development-1';

    private const int SUBJECT_LENGTH = 128;

    /** @throws AuthorizationException when the development sign-in is asked for where it must not exist */
    public function handle(IdentityProvider $provider, string $subject, ?Account $signedIn = null): SignInResult
    {
        if ($provider === IdentityProvider::Development && ! IdentityProvider::developmentAllowed()) {
            throw new AuthorizationException('The development sign-in does not exist here.');
        }

        $subject = trim($subject);
        if ($subject === '' || strlen($subject) > self::SUBJECT_LENGTH) {
            return new SignInResult(null, SignInOutcome::Refused);
        }

        try {
            return $this->attempt($provider, $subject, $signedIn);
        } catch (UniqueConstraintViolationException) {
            // Another request stored the same identity first: it exists now, so the second pass reads it.
            return $this->attempt($provider, $subject, $signedIn);
        }
    }

    private function attempt(IdentityProvider $provider, string $subject, ?Account $signedIn): SignInResult
    {
        return DB::transaction(function () use ($provider, $subject, $signedIn): SignInResult {
            $identity = Identity::query()->where('provider', $provider)->where('subject', $subject)->first();

            if ($identity !== null) {
                return $this->existing($identity, $signedIn);
            }
            if ($signedIn === null) {
                $account = Account::query()->create();
                $this->store($account, $provider, $subject);

                return new SignInResult($account, SignInOutcome::SignedIn);
            }
            if ($signedIn->identities()->where('provider', $provider)->exists()) {
                return new SignInResult($signedIn, SignInOutcome::OtherSubject);
            }
            $this->store($signedIn, $provider, $subject);

            return new SignInResult($signedIn, SignInOutcome::Linked);
        });
    }

    private function existing(Identity $identity, ?Account $signedIn): SignInResult
    {
        if ($signedIn === null) {
            return new SignInResult($identity->account, SignInOutcome::SignedIn);
        }

        return $identity->account_id === $signedIn->id
            ? new SignInResult($signedIn, SignInOutcome::Linked)
            : new SignInResult($signedIn, SignInOutcome::Taken);
    }

    private function store(Account $account, IdentityProvider $provider, string $subject): void
    {
        Identity::query()->create(['account_id' => $account->id, 'provider' => $provider, 'subject' => $subject]);
    }
}
