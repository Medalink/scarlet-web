<?php

namespace App\Support;

use App\Models\Ruleset;

/** What a dashboard page is about: one ruleset, or the rulesets of one server, or every ruleset, and for a player's own page only these characters of it. */
final readonly class PageScope
{
    /**
     * @param  int|list<int>|null  $ids  the rulesets to read: one id, several, or null for all
     * @param  list<int>|null  $characters  null for everyone's numbers; the characters of one account, none included, for a player's own
     */
    public function __construct(
        public ?Ruleset $ruleset,
        public Server $server,
        public int|array|null $ids,
        public ?array $characters = null,
    ) {}

    /**
     * The scope the rollup reads take: the rulesets, or the characters within them.
     *
     * @return int|list<int>|CharacterScope|null
     */
    public function rollup(): int|array|CharacterScope|null
    {
        return $this->characters === null ? $this->ids : new CharacterScope($this->characters, $this->ids);
    }

    /** @return array{ruleset: string|null, server: string} */
    public function describe(): array
    {
        return ['ruleset' => $this->ruleset?->slug(), 'server' => $this->server->value];
    }
}
