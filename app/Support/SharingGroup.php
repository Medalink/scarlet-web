<?php

namespace App\Support;

/**
 * The keys of docs/groups.tsv that code refers to by name. The table is the source of truth for
 * which groups exist; SharingGroups reads it through scarlet-part.
 */
final class SharingGroup
{
    public const string CORE = 'core';

    public const string XP = 'xp';

    public const string QUESTS = 'quests';

    public const string COMBAT = 'combat';

    public const string MOVEMENT = 'movement';

    public const string WORLD = 'world';

    public const string COLLECTIONS = 'collections';

    public const string LOOT = 'loot';

    public const string ECONOMY = 'economy';

    public const string PROFESSIONS = 'professions';

    public const string PVP = 'pvp';

    public const string SOCIAL = 'social';

    public const string BUILD = 'build';

    public const string IMPORTS = 'imports';
}
