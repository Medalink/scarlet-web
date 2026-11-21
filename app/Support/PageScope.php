<?php

namespace App\Support;

use App\Models\Ruleset;

/** What a dashboard page is about: one ruleset, or the rulesets of one server, or every ruleset. */
final readonly class PageScope
{
    /** @param  int|list<int>|null  $ids  the rulesets to read: one id, several, or null for all */
    public function __construct(
        public ?Ruleset $ruleset,
        public Server $server,
        public int|array|null $ids,
    ) {}

    /** @return array{ruleset: string|null, server: string} */
    public function describe(): array
    {
        return ['ruleset' => $this->ruleset?->slug(), 'server' => $this->server->value];
    }
}
