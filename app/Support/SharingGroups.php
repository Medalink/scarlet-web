<?php

namespace App\Support;

use App\Support\Exceptions\ApiException;

/** The sharing groups of docs/groups.tsv, read once per process from scarlet-part. */
class SharingGroups
{
    /** @var array<string, bool>|null  group key => switchable, in the order of the table */
    private static ?array $groups = null;

    /** @var array<string, string>|null  group key => label */
    private static ?array $labels = null;

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

    /**
     * The groups for a settings page, in the order of the table.
     *
     * @return array<string, string> group key => label
     */
    public function labels(): array
    {
        if (self::$labels === null) {
            self::$labels = [];
            foreach ($this->tool->groups() as $group) {
                self::$labels[$group['key']] = $group['label'];
            }
        }

        return self::$labels;
    }

    /** True for a group the owner may switch off (every group but `core` and `names`). */
    public function isSwitchable(string $key): bool
    {
        return ($this->all()[$key] ?? false) === true;
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
