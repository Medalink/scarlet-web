<?php

namespace App\Actions\Public\ClassPage;

use App\Actions\Public\ResolveNames;
use App\Models\Character;
use App\Support\ClassPage\ClassBuilders;
use App\Support\ClassPage\ClassRead;
use App\Support\ClassRows\ClassRows;
use App\Support\PlayerClass;

class ReadClassParts
{
    public function __construct(private readonly HasClassPage $has, private readonly ReadClassFacts $facts, private readonly ResolveNames $names) {}

    /** The parts of a character's class page, or null when the character has none. */
    public function handle(Character $character): ?ClassRead
    {
        $class = PlayerClass::tryFrom(strtoupper((string) $character->class));
        if ($class === null || ! $this->has->handle($character)) {
            return null;
        }
        $rows = ClassRows::of($character, $this->names);
        $facts = $this->facts->handle($character, $class);

        return new ClassRead($character, $class, $rows, $facts, resolve(ClassBuilders::for($class))->handle($rows, $facts));
    }
}
