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
 *
 * It runs outside a transaction, one commit for each chunk of characters, so a deploy never holds a lock for the whole
 * table. Every step is safe to repeat, so a run that stops partway is run again from the start.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    private const int CHUNK = 200;

    public function up(): void
    {
        $store = app(StoreCharacterFeats::class);
        $settle = app(SettleSeenTiers::class);
        $retired = FeatRetired::keys();
        $after = 0;

        DB::table('characters')->select(['id', 'pinned_feats'])->orderBy('id')->chunkById(self::CHUNK, function ($rows) use ($store, $settle, $retired, &$after): void {
            $through = (int) $rows->max('id');

            DB::table('character_feats')->where('character_id', '>', $after)->where('character_id', '<=', $through)->whereIn('feat', $retired)->delete();

            foreach ($rows as $row) {
                if ($row->pinned_feats !== null) {
                    $pinned = json_decode((string) $row->pinned_feats, true, flags: JSON_THROW_ON_ERROR);
                    $kept = array_values(array_filter($pinned, fn (mixed $key): bool => Feat::tryFrom((string) $key) !== null));
                    if ($kept !== $pinned) {
                        DB::table('characters')->where('id', $row->id)->update(['pinned_feats' => json_encode($kept, JSON_THROW_ON_ERROR)]);
                    }
                }
            }

            $withSessions = DB::table('sessions')->whereIn('character_id', $rows->pluck('id'))->distinct()->pluck('character_id');
            foreach ($withSessions as $id) {
                $store->handle((int) $id);
            }

            $settle->handle($after, $through);
            $after = $through;
        });
    }
};
