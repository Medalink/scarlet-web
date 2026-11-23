<?php

namespace Database\Seeders;

use App\Actions\Rollups\RefreshAggregates;
use App\Models\Character;
use Database\Seeders\Population\CharacterPlan;
use Database\Seeders\Population\Recorder;
use Database\Seeders\Population\Roster;
use Database\Seeders\Population\Schedule;
use Database\Seeders\Population\SessionScript;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

/**
 * The recordings of every character in the plan, uploaded through the real upload actions and decoded by the real
 * queue, so every page reads what the decoder made. A session that is already stored is skipped, so a second run
 * uploads nothing, and a character gets only the sessions it lacks.
 */
class RecordingSeeder extends Seeder
{
    private const int STALL_SECONDS = 180;

    private const int WAIT_SECONDS = 1800;

    public function run(): void
    {
        $now = time();
        $recorder = app(Recorder::class);
        $plan = Roster::plan();
        $pending = [];
        $uploaded = 0;

        foreach ($plan->characters as $at => $one) {
            $install = Roster::install($one->install) ?? throw new RuntimeException('Run AccountSeeder first: an install of the plan is missing.');
            $missing = $this->missing($one, $now);

            foreach ($missing as $session) {
                $pending[$session['characterKey'].':'.$session['start']] = [$session['characterKey'], $session['start']];
            }
            $recorder->upload($install, $one->name, array_map(fn (array $session): array => ['start' => $session['start'], 'lines' => $session['lines']], $missing));
            $uploaded += count($missing);
            $this->settleKind($one);

            if ($at % 20 === 19) {
                $this->command?->info(($at + 1).' of '.count($plan->characters).' characters uploaded, '.$uploaded.' sessions');
            }
        }
        $this->command?->info($uploaded.' sessions uploaded');

        if ($pending === []) {
            return;
        }
        $this->awaitDecodes($pending);
        $this->awaitQueues();
        $this->refreshRollups(min(array_column($pending, 1)), $now);
    }

    /** The ruleset of the plan stands in for the Armory's answer, so the ruleset job never asks the real Armory about an invented name. */
    private function settleKind(CharacterPlan $one): void
    {
        Character::query()
            ->where('name_key', Character::keyFor($one->name))
            ->whereNull('armory_kind')
            ->update(['armory_kind' => $one->kind->value, 'armory_slug' => $one->kind->value, 'armory_checked_at' => now()]);
    }

    /**
     * The sessions of a character that are not stored yet, composed.
     *
     * @return list<array{characterKey: string, start: int, lines: list<array{0: int, 1: list<int|float|string>}>}>
     */
    private function missing(CharacterPlan $one, int $now): array
    {
        $character = Character::query()->where('name_key', Character::keyFor($one->name))->first();
        $stored = $character === null ? collect() : DB::table('parts')->where('character_id', $character->id)->distinct()->pluck('session_start');
        $anchor = $stored->isEmpty() ? Schedule::firstStart($one, $now) : (int) $stored->min();
        $sessions = [];

        foreach (Schedule::build($one, $anchor, $now)->sessions as $index => $slot) {
            if ($stored->contains($slot['start'])) {
                continue;
            }
            $sessions[] = [
                'characterKey' => Character::keyFor($one->name),
                'start' => $slot['start'],
                'lines' => (new SessionScript($one, $index, $slot))->compose(),
            ];
        }

        return $sessions;
    }

    /**
     * Wait until every uploaded session has its decoded row, and stop with a plain message when nothing moves, which
     * means Horizon is not running.
     *
     * @param  array<string, array{0: string, 1: int}>  $pending
     */
    private function awaitDecodes(array $pending): void
    {
        $ids = DB::table('characters')->pluck('id', 'name_key');
        $wanted = [];

        foreach ($pending as [$key, $start]) {
            $wanted[(int) $ids[$key]][] = $start;
        }
        $total = count($pending);
        $deadline = time() + self::WAIT_SECONDS;
        $best = 0;
        $movedAt = time();

        while (true) {
            $done = 0;

            foreach ($wanted as $characterId => $starts) {
                $done += DB::table('sessions')->where('character_id', $characterId)->whereIn('session_start', $starts)->count();
            }
            $this->command?->info("$done of $total sessions decoded");

            if ($done >= $total) {
                return;
            }
            if ($done > $best) {
                $best = $done;
                $movedAt = time();
            }
            if (time() - $movedAt > self::STALL_SECONDS || time() > $deadline) {
                throw new RuntimeException("The ingest queue stopped at $done of $total sessions. Is Horizon running (bin/dev start)?");
            }
            sleep(10);
        }
    }

    /** Wait until the queues the ingest feeds, feats and standings among them, have nothing waiting or running. */
    private function awaitQueues(): void
    {
        $queues = array_map(fn (string $name): string => (string) config("scarlet.queues.$name"), ['ingest', 'backfill', 'rollups']);
        $quiet = 0;
        $deadline = time() + self::WAIT_SECONDS;

        while ($quiet < 3) {
            $size = array_sum(array_map(fn (string $queue): int => Queue::size($queue), [...$queues, 'default']));
            $quiet = $size === 0 ? $quiet + 1 : 0;

            if ($quiet === 0) {
                $this->command?->info("$size jobs left on the queues");
            }
            if (time() > $deadline) {
                throw new RuntimeException("The queues still hold $size jobs.");
            }
            sleep(5);
        }
    }

    /** One refresh of every aggregate over the days the run wrote, so the pages read them at once. */
    private function refreshRollups(int $from, int $until): void
    {
        $this->command?->info('Refreshing the rollups');
        app(RefreshAggregates::class)->handle($from, $until);
    }
}
