<?php

namespace Scarlet\Analysis;

use ReflectionClass;

final class HouseRules
{
    /** @return array<string, HouseRule> */
    public static function definitions(): array
    {
        $rules = [];
        foreach (glob(__DIR__.'/Rules/*Rule.php') ?: [] as $path) {
            $class = __NAMESPACE__.'\\Rules\\'.basename($path, '.php');
            foreach ((new ReflectionClass($class))->getAttributes(HouseRule::class) as $attribute) {
                $rule = $attribute->newInstance();
                $rules[$rule->id] = $rule;
            }
        }

        return $rules;
    }
}
