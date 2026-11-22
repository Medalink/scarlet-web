<?php

namespace App\Actions\Ingest;

use App\Jobs\IngestSession;
use App\Models\PlaySession;
use App\Support\PartTool;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class QueueOutdatedSessions
{
    private const int BATCH = 1000;

    private const string CURSOR = 'redecode:outdated-after';

    public function __construct(private readonly PartTool $partTool) {}

    /**
     * Queue a fresh decode of the next sessions whose rows an older decoder wrote, or no decoder version at all
     * (docs/DESIGN.md, Re-decode). Nothing is queued while the backfill queue still holds jobs, so a run never queues
     * a session twice. A cursor in the durable cache walks the sessions in key order, so a session that keeps failing
     * is tried once a pass and cannot hold the others back; a pass that finds nothing starts the next one from the
     * beginning, which picks up a session a worker of the previous release decoded during a deploy.
     *
     * @return int sessions queued
     */
    public function handle(): int
    {
        if (Queue::size((string) config('scarlet.queues.backfill')) > 0) {
            return 0;
        }
        $version = $this->partTool->decoderVersion();
        $after = Cache::store('durable')->get(self::CURSOR);
        $sessions = PlaySession::query()
            ->select(['character_id', 'session_start'])
            ->where(fn (Builder $query) => $query->whereNull('decoder_version')->orWhere('decoder_version', '<', $version))
            ->when(is_array($after), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('character_id', '>', (int) $after[0])
                ->orWhere(fn (Builder $query) => $query->where('character_id', (int) $after[0])->where('session_start', '>', (int) $after[1]))))
            ->orderBy('character_id')
            ->orderBy('session_start')
            ->limit(self::BATCH)
            ->get();

        $last = $sessions->last();
        if ($last === null) {
            Cache::store('durable')->forget(self::CURSOR);

            return 0;
        }
        foreach ($sessions as $session) {
            IngestSession::dispatch((int) $session->character_id, (int) $session->session_start, true);
        }
        Cache::store('durable')->forever(self::CURSOR, [(int) $last->character_id, (int) $last->session_start]);

        return $sessions->count();
    }
}
