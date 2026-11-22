<?php

namespace App\Support;

/** The spells of the client build each class can learn and cast, read once from the client's CSV tables. */
final class ClientSpells
{
    private const int LEVEL_CAP = 60;

    private const int PASSIVE = 0x40;

    public const array FILES = [
        'SpellName', 'SpellMisc', 'SpellLevels', 'SpellCooldowns', 'SkillLine', 'SkillLineAbility',
        'SkillRaceClassInfo', 'Talent', 'TalentTab',
    ];

    /** @var array<string, array<string, ClientSpell>> class value => spell name => spell */
    private array $spells = [];

    /** The first file of `$tables` that is missing, or null when the folder holds them all. */
    public static function missingFile(string $tables): ?string
    {
        foreach (self::FILES as $file) {
            $path = $tables.'/'.$file.'.csv';
            if (! is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function __construct(string $tables)
    {
        $names = [];
        foreach (self::rows('SpellName', $tables) as $row) {
            $names[(int) $row['ID']] = $row['Name_lang'];
        }
        $passive = [];
        foreach (self::rows('SpellMisc', $tables) as $row) {
            if (((int) $row['Attributes[0]'] & self::PASSIVE) !== 0) {
                $passive[(int) $row['SpellID']] = true;
            }
        }
        $levels = [];
        foreach (self::rows('SpellLevels', $tables) as $row) {
            if ((int) $row['DifficultyID'] === 0) {
                $levels[(int) $row['SpellID']] = (int) $row['BaseLevel'];
            }
        }
        $cooldowns = [];
        foreach (self::rows('SpellCooldowns', $tables) as $row) {
            if ((int) $row['DifficultyID'] === 0) {
                $cooldowns[(int) $row['SpellID']] = max((int) $row['RecoveryTime'], (int) $row['CategoryRecoveryTime']) / 1000;
            }
        }
        $categories = [];
        foreach (self::rows('SkillLine', $tables) as $row) {
            $categories[(int) $row['ID']] = (int) $row['CategoryID'];
        }
        $abilities = self::rows('SkillLineAbility', $tables);
        $tabs = [];
        foreach (self::rows('TalentTab', $tables) as $row) {
            $tabs[(int) $row['ClassMask']][(int) $row['ID']] = true;
        }
        $talents = [];
        foreach (self::rows('Talent', $tables) as $row) {
            $talents[(int) $row['TabID']][] = $row;
        }

        foreach (PlayerClass::cases() as $class) {
            $bit = $class->bit();
            $lines = [];
            foreach (self::rows('SkillRaceClassInfo', $tables) as $row) {
                if (((int) $row['ClassMask'] === $bit) && in_array($categories[(int) $row['SkillID']] ?? 0, [7, 9], true)) {
                    $lines[(int) $row['SkillID']] = true;
                }
            }
            $ids = [];
            foreach ($abilities as $row) {
                $line = (int) $row['SkillLine'];
                $mask = (int) $row['ClassMask'];
                if (isset($lines[$line]) && ($mask === 0 || ($mask & $bit) !== 0)) {
                    $ids[(int) $row['Spell']] = true;
                }
            }
            foreach ($talents as $tabId => $rows) {
                if (! isset($tabs[$bit][$tabId])) {
                    continue;
                }
                foreach ($rows as $row) {
                    for ($rank = 0; $rank <= 8; $rank++) {
                        $id = (int) $row["SpellRank[$rank]"];
                        if ($id > 0) {
                            $ids[$id] = true;
                        }
                    }
                }
            }
            $this->spells[$class->value] = $this->collect($ids, $names, $passive, $levels, $cooldowns);
        }
    }

    /** @return array<string, ClientSpell> the learnable spells of the class, keyed by name */
    public function of(PlayerClass $class): array
    {
        return $this->spells[$class->value];
    }

    /**
     * @param  array<int, true>  $ids
     * @param  array<int, string>  $names
     * @param  array<int, true>  $passive
     * @param  array<int, int>  $levels
     * @param  array<int, float>  $cooldowns
     * @return array<string, ClientSpell>
     */
    private function collect(array $ids, array $names, array $passive, array $levels, array $cooldowns): array
    {
        $byName = [];
        foreach (array_keys($ids) as $id) {
            $name = $names[$id] ?? null;
            if ($name === null || isset($passive[$id])) {
                continue;
            }
            $level = $levels[$id] ?? 0;
            if ($level > self::LEVEL_CAP) {
                continue;
            }
            $cooldown = $cooldowns[$id] ?? 0.0;
            $held = $byName[$name] ?? null;
            $byName[$name] = new ClientSpell(
                $name,
                $held === null ? $level : min($held->level, $level),
                $held === null ? $cooldown : min($held->cooldown, $cooldown),
            );
        }
        ksort($byName);

        return $byName;
    }

    /** @return list<array<string, string>> */
    private static function rows(string $table, string $tables): array
    {
        $handle = fopen($tables.'/'.$table.'.csv', 'r');
        if ($handle === false) {
            return [];
        }
        $header = fgetcsv($handle, escape: '');
        $rows = [];
        if (is_array($header)) {
            $columns = array_map(strval(...), $header);
            while (($row = fgetcsv($handle, escape: '')) !== false) {
                if (count($row) === count($columns)) {
                    $rows[] = array_combine($columns, array_map(strval(...), $row));
                }
            }
        }
        fclose($handle);

        return $rows;
    }
}
