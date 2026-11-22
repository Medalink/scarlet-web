<?php

namespace App\Contracts;

use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\ClassParts;
use App\Support\ClassRows\ClassRows;

/**
 * The part of a class page that only the class knows. A builder holds no SQL: it asks `ClassRows` for what the class
 * tables hold and `ClassFacts` for the character, and answers with the callouts, figures, rows and talent modules of
 * its class. Each class has one, listed in `App\Support\ClassPage\ClassBuilders`.
 */
interface ClassBuilder
{
    public function handle(ClassRows $rows, ClassFacts $facts): ClassParts;
}
