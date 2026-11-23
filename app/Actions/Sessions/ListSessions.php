<?php

namespace App\Actions\Sessions;

use App\Models\Character;

/** A page of a character's sessions, newest first, with the title each one has. */
class ListSessions
{
    public const int PAGE = 20;

    public function __construct(private readonly ListCharacterSessions $sessions) {}

    /**
     * Null when the character is unknown or its session pages are not public. `$after` is the start of the oldest session of the page before.
     *
     * @return array{sessions: list<array<string, mixed>>, next: string|null}|null
     */
    public function handle(string $name, ?int $after): ?array
    {
        $character = Character::query()->where('name_key', Character::keyFor($name))->first();
        if ($character === null || ! $character->sessionsVisibleTo(null)) {
            return null;
        }

        return $this->sessions->handle($character, $after, self::PAGE);
    }
}
