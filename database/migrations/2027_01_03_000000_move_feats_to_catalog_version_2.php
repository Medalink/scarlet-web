<?php

use App\Actions\Feats\SettleSeenTiers;
use App\Actions\Feats\StoreCharacterFeats;
use App\Support\Feat;
use App\Support\FeatRetired;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catalog version 2 (resources/feats/feats.json): the 22 retired feats lose their rows and their places in
 * `pinned_feats`, every character with a session is recounted here and not by the queue, so the five added feats have
 * rows, and then each row is marked shown at the tier it holds. Thresholds only fell, so tiers rose and none dropped,
 * and nobody is handed a wall of toasts for a rule change.
 */
return new class extends Migration
{
    private const int CHUNK = 200;

    public function up(): void
    {
        DB::table('character_feats')->whereIn('feat', FeatRetired::keys())->delete();

        DB::table('characters')->whereNotNull('pinned_feats')->select(['id', 'pinned_feats'])->orderBy('id')->chunkById(self::CHUNK, function ($rows): void {
            foreach ($rows as $row) {
                $pinned = json_decode((string) $row->pinned_feats, true, flags: JSON_THROW_ON_ERROR);
                $kept = array_values(array_filter($pinned, fn (mixed $key): bool => Feat::tryFrom((string) $key) !== null));
                if ($kept !== $pinned) {
                    DB::table('characters')->where('id', $row->id)->update(['pinned_feats' => json_encode($kept, JSON_THROW_ON_ERROR)]);
                }
            }
        });

        $store = app(StoreCharacterFeats::class);
        DB::table('characters')->select('id')
            ->whereIn('id', fn ($query) => $query->select('character_id')->distinct()->from('sessions'))
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($rows) use ($store): void {
                foreach ($rows as $row) {
                    $store->handle((int) $row->id);
                }
            });

        app(SettleSeenTiers::class)->handle();
    }
};
