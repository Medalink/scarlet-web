<?php

namespace App\Actions\Ingest;

use App\Jobs\IngestSession;
use App\Jobs\ReconcileCharacterClaims;
use App\Models\AddonDay;
use App\Models\PlaySession;
use App\Support\PartTool;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class QueueOutdatedSessions
{
    private const int BATCH = 1000;

    private const string CURSOR = 'redecode:outdated-after';

    private const string DAY_CURSOR = 'redecode:outdated-days-after';

    private const string RECONCILE_CURSOR = 'redecode:outdated-reconciles-after';

    public function __construct(private readonly PartTool $partTool) {}

    /**
     * Queue a fresh decode of the next sessions, then of the next addon days, whose rows an older decoder wrote, or no
     * decoder version at all (docs/DESIGN.md, Re-decode), then a reconcile of the next characters whose addon days an
     * older reconcile decided. Nothing is queued while the backfill queue still holds jobs,
     * so a run never queues a session twice. A cursor in the durable cache walks each kind in key order, so a session
     * that keeps failing is tried once a pass and cannot hold the others back; a pass that finds nothing of a kind
     * starts the next one from the beginning, which picks up a session a worker of the previous release decoded during
     * a deploy. Sessions come first and the days share the rest of the batch.
     *
     * @return int sessions, addon days and characters queued
     */
    public function handle(): int
    {
        if (Queue::size((string) config('scarlet.queues.backfill')) > 0) {
            return 0;
        }
        $version = $this->partTool->decoderVersion();
        $sessions = $this->queueNext(PlaySession::class, 'session_start', self::CURSOR, $version, self::BATCH);

        $queued = $sessions + ($sessions < self::BATCH ? $this->queueNext(AddonDay::class, 'day_key', self::DAY_CURSOR, $version, self::BATCH - $sessions) : 0);

        return $queued + ($queued < self::BATCH ? $this->queueReconciles($this->partTool->reconcileVersion(), self::BATCH - $queued) : 0);
    }

    private function queueReconciles(int $version, int $limit): int
    {
        $after = Cache::store('durable')->get(self::RECONCILE_CURSOR);
        $characters = AddonDay::query()
            ->where(fn (Builder $query) => $query->whereNull('reconcile_version')->orWhere('reconcile_version', '<', $version))
            ->when(is_int($after), fn (Builder $query) => $query->where('character_id', '>', $after))
            ->distinct()
            ->orderBy('character_id')
            ->limit($limit)
            ->pluck('character_id');

        if ($characters->isEmpty()) {
            Cache::store('durable')->forget(self::RECONCILE_CURSOR);

            return 0;
        }
        foreach ($characters as $characterId) {
            ReconcileCharacterClaims::dispatch((int) $characterId, true);
        }
        Cache::store('durable')->forever(self::RECONCILE_CURSOR, (int) $characters->last());

        return $characters->count();
    }

    /** @param  class-string<PlaySession|AddonDay>  $model */
    private function queueNext(string $model, string $key, string $cursor, int $version, int $limit): int
    {
        $after = Cache::store('durable')->get($cursor);
        $rows = $model::query()
            ->select(['character_id', $key])
            ->where(fn (Builder $query) => $query->whereNull('decoder_version')->orWhere('decoder_version', '<', $version))
            ->when(is_array($after), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('character_id', '>', (int) $after[0])
                ->orWhere(fn (Builder $query) => $query->where('character_id', (int) $after[0])->where($key, '>', (int) $after[1]))))
            ->orderBy('character_id')
            ->orderBy($key)
            ->limit($limit)
            ->get();

        $last = $rows->last();
        if ($last === null) {
            Cache::store('durable')->forget($cursor);

            return 0;
        }
        foreach ($rows as $row) {
            IngestSession::dispatch((int) $row->getAttribute('character_id'), (int) $row->getAttribute($key), true);
        }
        Cache::store('durable')->forever($cursor, [(int) $last->getAttribute('character_id'), (int) $last->getAttribute($key)]);

        return $rows->count();
    }
}
