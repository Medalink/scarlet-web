<?php

namespace App\Support;

use App\Support\Exceptions\ApiException;

/** The sharing groups of docs/groups.tsv, read once per process from scarlet-part. */
class SharingGroups
{
    /** @var array<string, bool>|null  group key => switchable, in the order of the table */
    private static ?array $groups = null;

    public function __construct(private readonly PartTool $tool) {}

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /** A group key a client may switch off or remove; anything else is refused with the reason. */
    public function requireSwitchable(string $key): void
    {
        $all = $this->all();
        if (! array_key_exists($key, $all)) {
            throw ApiException::unknownGroup($key);
        }
        if (! $all[$key]) {
            throw ApiException::groupNotSwitchable($key);
        }
    }

    /** @return array<string, bool> */
    private function all(): array
    {
        if (self::$groups === null) {
            self::$groups = [];
            foreach ($this->tool->groups() as $group) {
                self::$groups[$group['key']] = $group['switchable'];
            }
        }

        return self::$groups;
    }
}
