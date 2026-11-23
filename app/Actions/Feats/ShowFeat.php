<?php

namespace App\Actions\Feats;

use App\Support\Feat;
use App\Support\FeatCatalog;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** One feat of the catalog with who holds the most of it and who reached a tier of it last: `GET /api/v1/public/feats/{key}`. Public characters only. */
class ShowFeat
{
    public const int TOP = 20;

    public const int RECENT = 10;

    public function __construct(
        private readonly ShowFeatCatalog $catalog,
    ) {}

    /** @return array<string, mixed>|null null for a key that is not a feat; the gold feats list only characters that show their economy */
    public function handle(string $key): ?array
    {
        $feat = Feat::tryFrom($key);
        if ($feat === null) {
            return null;
        }
        foreach ($this->catalog->handle()['feats'] as $entry) {
            if ($entry['key'] === $feat->value) {
                return ['catalog' => FeatCatalog::VERSION] + $entry + ['top' => $this->top($feat), 'recent' => $this->recent($feat)];
            }
        }

        return null;
    }

    /** @return list<array{character: string, value: int|float, tier: int}> */
    private function top(Feat $feat): array
    {
        $rows = $this->listed(DB::table('character_feats as cf')->join('characters as c', 'c.id', '=', 'cf.character_id'), $feat)
            ->where('cf.feat', $feat->value)->where('cf.value', '>', 0)
            ->orderByDesc('cf.value')->orderBy('c.id')->limit(self::TOP)->get(['c.name', 'cf.value']);

        return array_values($rows->map(function (object $row) use ($feat): array {
            $value = $feat->decimals() === 0 ? (int) floor((float) $row->value) : round((float) $row->value, $feat->decimals());

            return ['character' => (string) $row->name, 'value' => $value, 'tier' => $feat->tier($value)];
        })->all());
    }

    /** @return list<array{character: string, tier: int, at: string}> */
    private function recent(Feat $feat): array
    {
        $rows = $this->listed(DB::table('feat_events as e')->join('characters as c', 'c.id', '=', 'e.character_id'), $feat)
            ->where('e.feat', $feat->value)->orderByDesc('e.at')->orderByDesc('e.id')->limit(self::RECENT)->get(['c.name', 'e.tier', 'e.at']);

        return array_values($rows->map(fn (object $row): array => ['character' => (string) $row->name, 'tier' => (int) $row->tier, 'at' => Carbon::parse((string) $row->at)->utc()->format('Y-m-d\TH:i:s\Z')])->all());
    }

    private function listed(Builder $query, Feat $feat): Builder
    {
        return $query->where('c.is_public', true)->when($feat->economy(), fn ($economy) => $economy->where('c.show_economy', true));
    }
}
