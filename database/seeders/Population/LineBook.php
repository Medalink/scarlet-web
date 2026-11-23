<?php

namespace Database\Seeders\Population;

/**
 * The lines of one session as they are written, `[milliseconds since the session began, code, ...fields]`, and the
 * name lines (`SN`, `IN`, `N`) of the spells, items and creatures a line mentions that the dictionary does not name yet.
 */
final class LineBook
{
    /** @var list<array{int, string, list<int|float|string>}> */
    private array $lines = [];

    /** @var array<string, true> */
    private array $named = [];

    public function at(int $ms, string $code, int|float|string ...$fields): void
    {
        $this->lines[] = [$ms, $code, array_values($fields)];
    }

    public function spell(int $id, ?string $name): void
    {
        $this->name('SN', 'spell', $id, $name);
    }

    public function item(int $id): void
    {
        $this->name('IN', 'item', $id, World::ITEMS[$id] ?? null);
    }

    public function creature(int $id, ?string $name = null): void
    {
        $this->name('N', 'npc', $id, $name ?? World::PREY[$id][0] ?? null);
    }

    /** @return list<array{0: int, 1: list<int|float|string>}> in time order, each line as `[ms, [code, ...fields]]` */
    public function sorted(): array
    {
        $lines = $this->lines;
        usort($lines, fn (array $one, array $other): int => $one[0] <=> $other[0]);

        return array_map(fn (array $line): array => [$line[0], [$line[1], ...$line[2]]], $lines);
    }

    private function name(string $code, string $kind, int $id, ?string $name): void
    {
        $key = $code.$id;

        if ($name === null || isset($this->named[$key])) {
            return;
        }
        $this->named[$key] = true;

        if (Dictionary::offer($kind, $id)) {
            $this->at(100 + count($this->named), $code, $id, $name);
        }
    }
}
