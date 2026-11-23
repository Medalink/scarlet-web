<?php

namespace App\Support;

/** What the catalog says about the feats as a whole: its version, the families and which classes can fill a role. */
final class FeatCatalog
{
    /** Rises by one with every change to a key or a threshold; the apps and the addon compare against it. */
    public const int VERSION = 2;

    public const array FAMILIES = ['journey', 'combat', 'quests', 'dungeons', 'world', 'trade', 'class', 'role'];

    /** @return array<string, list<string>> role name => the classes with a tree that plays it, in class order */
    public static function roleClasses(): array
    {
        $roles = [];
        foreach (Role::cases() as $role) {
            $roles[$role->name] = [];
        }
        foreach (PlayerClass::cases() as $class) {
            foreach (self::trees($class) as $tree) {
                foreach ($tree->roles() as $role) {
                    $roles[$role->name][$class->name] = $class->name;
                }
            }
        }

        return array_map(fn (array $classes): array => array_values($classes), $roles);
    }

    /** @return array<string, array<string, list<string>>> class name => tree label => the roles the tree plays */
    public static function specRoles(): array
    {
        $specs = [];
        foreach (PlayerClass::cases() as $class) {
            foreach (self::trees($class) as $tree) {
                $specs[$class->name][$tree->names()[0]] = array_map(fn (Role $role): string => $role->name, $tree->roles());
            }
        }

        return $specs;
    }

    /** @return list<TalentTree> */
    private static function trees(PlayerClass $class): array
    {
        return array_values(array_filter(TalentTree::cases(), fn (TalentTree $tree): bool => $tree->class() === $class));
    }
}
