<?php

namespace App\Actions\Feats;

use App\Models\Character;
use Illuminate\Support\Facades\DB;

/** The feats one character can hold with its tiers, the three closest to the next tier and its summary. */
class BuildCharacterFeats
{
    private const int CLOSEST = 3;

    private ?int $publicScored = null;

    public function __construct(
        private readonly ShowCharacterFeats $rows,
        private readonly ShowFeatCardFields $fields,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $catalog
     * @param  bool  $shares  whether each feat carries the share of the tier held
     * @param  bool|null  $economy  whether the gold feats are in; the character's own choice when null
     * @return array{character: array<string, mixed>, feats: list<array<string, mixed>>, closest: list<array<string, mixed>>}
     */
    public function handle(Character $character, array $catalog, bool $shares, ?bool $economy = null): array
    {
        $feats = $this->ofCharacter($character, $catalog, $shares, $economy ?? (bool) $character->show_economy);

        return ['character' => $this->summary($character, $feats), 'feats' => $feats, 'closest' => $this->closest($feats)];
    }

    /**
     * @param  list<array<string, mixed>>  $catalog
     * @return list<array<string, mixed>>
     */
    private function ofCharacter(Character $character, array $catalog, bool $shares, bool $economy): array
    {
        $rows = $this->rows->handle($character, $economy);
        $fields = $this->fields->handle($character, $rows);
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row['key']] = $row;
        }
        $feats = [];
        foreach ($catalog as $feat) {
            $row = $byKey[$feat['key']] ?? null;
            if ($row === null) {
                continue;
            }
            $field = $fields[$row['key']];
            $at = $feat['shares'][max($row['tier'], 1) - 1] ?? null;
            $feats[] = $feat + [
                'tier' => $row['tier'], 'value' => $row['value'], 'progress' => $field['progress'], 'counted' => $field['counted'], 'to_next' => $field['to_next'],
                'next' => $row['next'], 'left' => $field['left'], 'spell' => $field['spell'], 'share' => $shares ? $at : null,
            ];
        }

        return $feats;
    }

    /**
     * @param  list<array<string, mixed>>  $feats
     * @return array<string, mixed>
     */
    private function summary(Character $character, array $feats): array
    {
        $tiers = [0, 0, 0, 0, 0];
        $general = $classes = $roleFeats = 0;
        $roles = [];
        foreach ($feats as $feat) {
            if ($feat['tier'] > 0) {
                $tiers[$feat['tier'] - 1]++;
            }
            if ($feat['scope'] === 'general') {
                $general++;
            }
            if ($feat['scope'] === 'class' || $feat['scope'] === 'tree') {
                $classes++;
            }
            if ($feat['scope'] === 'role') {
                $roleFeats++;
                $roles[(string) $feat['collection']] = (string) $feat['collection'];
            }
        }
        $public = DB::table('characters')->where('is_public', true)->where('feat_score', '>', 0);
        $this->publicScored ??= (clone $public)->count();

        return [
            'name' => $character->name,
            'class' => (string) $character->class,
            'race' => $character->race,
            'level' => $character->level,
            'held' => array_sum($tiers),
            'total' => count($feats),
            'tiers' => $tiers,
            'score' => (int) $character->feat_score,
            'place' => $character->is_public && $character->feat_score > 0 ? 1 + (clone $public)->where('feat_score', '>', $character->feat_score)->count() : null,
            'of' => $character->is_public ? $this->publicScored : null,
            'general' => $general,
            'class_feats' => $classes,
            'role_feats' => $roleFeats,
            'roles' => array_values($roles),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $feats
     * @return list<array<string, mixed>>
     */
    private function closest(array $feats): array
    {
        $open = array_values(array_filter($feats, fn (array $feat): bool => $feat['tier'] < 5 && $feat['value'] > 0));
        usort($open, fn (array $a, array $b): int => [$b['progress'], $a['key']] <=> [$a['progress'], $b['key']]);

        return array_slice($open, 0, self::CLOSEST);
    }
}
