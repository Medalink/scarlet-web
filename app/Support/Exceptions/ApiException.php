<?php

namespace App\Support\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * An API refusal with a stable code (docs/API.md lists them): the answer is
 * `{"error": {"code": ..., "message": ..., ...extra}}` with the HTTP status.
 */
class ApiException extends RuntimeException
{
    /** @param  array<string, mixed>  $extra */
    private function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['error' => ['code' => $this->errorCode, 'message' => $this->getMessage(), ...$this->extra]], $this->status);
    }

    /** @param  array<string, mixed>  $errors */
    public static function invalidRequest(string $message, array $errors = []): self
    {
        return new self(422, 'invalid_request', $message, $errors === [] ? [] : ['fields' => $errors]);
    }

    public static function invalidBatch(string $detail): self
    {
        return new self(422, 'invalid_batch', 'The request body is not a valid batch: '.$detail);
    }

    public static function missingHeaders(): self
    {
        return new self(401, 'missing_signature', 'Scarlet-Install, Scarlet-Time and Scarlet-Signature are required.');
    }

    public static function unknownInstall(): self
    {
        return new self(401, 'unknown_install', 'No install has this id.');
    }

    public static function installRevoked(): self
    {
        return new self(401, 'install_revoked', 'This install was revoked.');
    }

    public static function clockSkew(int $window): self
    {
        return new self(401, 'clock_skew', "Scarlet-Time is more than {$window} seconds from the server's clock.", ['server_time' => time()]);
    }

    public static function badSignature(): self
    {
        return new self(401, 'bad_signature', 'The signature does not match the request.');
    }

    public static function replayed(): self
    {
        return new self(401, 'replayed_request', 'This signed request was already seen.');
    }

    public static function wrongSite(): self
    {
        return new self(401, 'wrong_site', 'The request reached another host than the site the signature names, or than the install registered on.');
    }

    public static function bodyTooLarge(int $limit): self
    {
        return new self(413, 'body_too_large', "The request body is larger than {$limit} bytes.", ['limit' => $limit]);
    }

    public static function unsupportedType(string $expected): self
    {
        return new self(415, 'unsupported_media_type', "Content-Type must be {$expected}.", ['expected' => $expected]);
    }

    public static function rateLimited(int $retryAfter): self
    {
        return new self(429, 'rate_limited', 'Too many requests.', ['retry_after' => $retryAfter]);
    }

    public static function invalidToken(): self
    {
        return new self(401, 'invalid_token', 'The bearer token is unknown, expired, or lacks the public:read ability.');
    }

    public static function notFound(string $what): self
    {
        return new self(404, 'not_found', $what.' was not found.');
    }

    public static function dailyLimit(int $limit, int $used): self
    {
        return new self(429, 'daily_limit', 'This install reached its upload limit for today (UTC).', ['limit_bytes' => $limit, 'used_bytes' => $used]);
    }

    public static function nameHeld(string $character): self
    {
        return new self(409, 'name_held', 'Another install holds this character name.', ['character' => $character]);
    }

    /** @param  list<string>  $groups */
    public static function groupRefused(array $groups): self
    {
        return new self(409, 'group_refused', 'These groups were removed from the server; switch them off in the app.', ['groups' => $groups]);
    }

    public static function unknownGroup(string $key): self
    {
        return new self(422, 'unknown_group', 'There is no sharing group with this key.', ['group' => $key]);
    }

    public static function groupNotSwitchable(string $key): self
    {
        return new self(422, 'group_not_switchable', 'This group cannot be switched off or removed on its own.', ['group' => $key]);
    }

    public static function tooManyCharacters(int $limit): self
    {
        return new self(422, 'too_many_characters', "An install can upload at most {$limit} characters.", ['limit' => $limit]);
    }

    public static function partNotRequested(string $id): self
    {
        return new self(422, 'part_not_requested', 'A part in this request was not asked for by the last manifest.', ['part' => $id]);
    }

    public static function unknownCharacter(string $name): self
    {
        return new self(422, 'unknown_character', 'Send a manifest for this character before its parts.', ['character' => $name]);
    }

    public static function alreadyLinked(): self
    {
        return new self(409, 'already_linked', 'This install already belongs to an account.');
    }
}
