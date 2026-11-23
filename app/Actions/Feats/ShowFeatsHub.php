<?php

namespace App\Actions\Feats;

use App\Models\Character;
use App\Support\Feat;
use Illuminate\Support\Facades\DB;

/**
 * What /feats draws from. A signed-out visitor, or an owner on `everyone`, gets the whole catalog with the Copper share of each feat.
 * An owner on `compare` or `mine` gets the feats one of their characters can hold with its tiers, `compare` with the share at the tier held
 * and `mine` without shares.
 */
class ShowFeatsHub
{
    public const array SCOPES = ['everyone', 'compare', 'mine'];

    private const int CLOSEST = 3;

    private const int WEEK = 7;

    public function __construct(
        private readonly ShowFeatCatalog $catalog,
        private readonly ShowCharacterFeats $rows,
        private readonly ShowFeatCardFields $fields,
    ) {}

    /**
     * @param  string  $scope  one of `SCOPES`; an owner with no counted character gets `everyone`
     * @param  string|null  $as  the name of the character shown, the owner's most recently seen one when null or not theirs
     * @return array{mode: string, names: list<string>, character: array<string, mixed>|null, catalog: int, feats: list<array<string, mixed>>, closest: list<array<string, mixed>>, rarest: array<string, mixed>|null}
     */
    public function handle(?int $accountId, string $scope, ?string $as): array
    {
        $characters = $accountId === null ? [] : Character::query()->where('account_id', $accountId)
            ->whereExists(fn ($rows) => $rows->selectRaw('1')->from('character_feats')->whereColumn('character_feats.character_id', 'characters.id'))
            ->orderByRaw('characters.last_seen_at desc nulls last')->orderBy('characters.id')->get()->all();
        $names = array_values(array_map(fn (Character $character): string => $character->name, $characters));
        $mode = $characters === [] || ! in_array($scope, self::SCOPES, true) ? 'everyone' : $scope;
        $character = $mode === 'everyone' ? null : $characters[0];
        foreach ($characters as $candidate) {
            if ($mode !== 'everyone' && $candidate->name === $as) {
                $character = $candidate;
            }
        }
        $catalog = $this->catalog->handle();
        $feats = $character === null ? $this->everyone($catalog['feats']) : $this->ofCharacter($character, $catalog['feats'], $mode === 'compare');

        return [
            'mode' => $character === null ? 'everyone' : $mode,
            'names' => $names,
            'character' => $character === null ? null : $this->summary($character, $feats),
            'catalog' => count($catalog['feats']),
            'feats' => $feats,
            'closest' => $character === null ? [] : $this->closest($feats),
            'rarest' => $this->rarest(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $catalog
     * @return list<array<string, mixed>>
     */
    private function everyone(array $catalog): array
    {
        return array_map(fn (array $feat): array => $feat + [
            'tier' => 0, 'value' => null, 'progress' => 0.0, 'counted' => null, 'to_next' => null, 'next' => null, 'left' => null, 'spell' => null,
            'share' => $feat['shares'][0] ?? null,
        ], $catalog);
    }

    /**
     * @param  list<array<string, mixed>>  $catalog
     * @return list<array<string, mixed>>
     */
    private function ofCharacter(Character $character, array $catalog, bool $shares): array
    {
        $rows = $this->rows->handle($character, (bool) $character->show_economy);
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
            'of' => $character->is_public ? $public->count() : null,
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

    /** @return array<string, mixed>|null the tier of the week reached by a public character that fewest characters hold */
    private function rarest(): ?array
    {
        $gold = array_map(fn (Feat $feat): string => $feat->value, array_filter(Feat::cases(), fn (Feat $feat): bool => $feat->economy()));
        $row = DB::table('feat_events as e')
            ->join('characters as c', 'c.id', '=', 'e.character_id')
            ->join('feat_shares as s', fn ($join) => $join->on('s.feat', '=', 'e.feat')->on('s.tier', '=', 'e.tier'))
            ->where('c.is_public', true)->where('s.of', '>=', ReadFeatShares::MIN_OF)->where('e.at', '>=', now()->subDays(self::WEEK))
            ->where(fn ($economy) => $economy->whereNotIn('e.feat', $gold)->orWhere('c.show_economy', true))
            ->orderBy('s.share')->orderByDesc('e.at')->first(['e.feat', 'e.tier', 'c.name', 's.share']);
        $feat = $row === null ? null : Feat::tryFrom((string) $row->feat);
        if ($row === null || $feat === null) {
            return null;
        }

        return ['key' => $feat->value, 'name' => $feat->label(), 'tier' => (int) $row->tier, 'character' => (string) $row->name, 'share' => (float) $row->share];
    }
}
