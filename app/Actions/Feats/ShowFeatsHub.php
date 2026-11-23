<?php

namespace App\Actions\Feats;

use App\Models\Character;
use App\Support\Feat;
use Illuminate\Support\Facades\DB;

/**
 * What /feats draws from. A signed-out visitor, or an owner on `everyone`, gets the whole catalog with the Uncommon share of each feat.
 * An owner on `compare` or `mine` gets the feats one of their characters can hold with its tiers, `compare` with the share at the tier held
 * and `mine` without shares.
 */
class ShowFeatsHub
{
    public const array SCOPES = ['everyone', 'compare', 'mine'];

    private const int WEEK = 7;

    public function __construct(
        private readonly ShowFeatCatalog $catalog,
        private readonly BuildCharacterFeats $built,
        private readonly ShowAccountFeats $account,
        private readonly ListAccountCharacterIds $owned,
    ) {}

    /**
     * @param  string  $scope  one of `SCOPES`; an owner with no counted character gets `everyone`
     * @param  string|null  $as  the name of the character shown, the owner's most recently seen one when null or not theirs
     * @return array{mode: string, names: list<string>, character: array<string, mixed>|null, catalog: int, feats: list<array<string, mixed>>, account: array<string, mixed>|null, closest: list<array<string, mixed>>, rarest: array<string, mixed>|null}
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
        $of = $character === null ? null : $this->built->handle($character, $catalog['feats'], $mode === 'compare');
        $feats = $of === null ? $this->everyone($catalog['feats']) : $of['feats'];

        return [
            'mode' => $character === null ? 'everyone' : $mode,
            'names' => $names,
            'character' => $of['character'] ?? null,
            'catalog' => count($catalog['feats']),
            'feats' => $feats,
            'account' => $character === null ? null : $this->account->handle($this->owned->handle((int) $accountId)),
            'closest' => $of['closest'] ?? [],
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
