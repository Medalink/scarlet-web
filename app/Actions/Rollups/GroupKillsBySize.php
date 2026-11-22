<?php

namespace App\Actions\Rollups;

use App\Support\GroupSpans;
use App\Support\RollupRead;

class GroupKillsBySize
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Kills an hour by group size (docs/LOOT.md, Metrics: Kills an hour by size): the kills each member got credit for
     * while the group was at the size, over the member hours the groups of that size lasted. Time alone is not counted. A
     * size that fewer than `scarlet.min_characters` characters contributed to, on either side, is left out.
     *
     * @return list<array{size: int, kills: int, seconds: int}>
     */
    public function handle(): array
    {
        $threshold = $this->read->threshold(null);
        $hours = $this->read->rows(GroupSpans::timed()->selectRaw('s.size, sum(extract(epoch from s.ended_at - s.at)) as seconds')->groupBy('s.size')
            ->havingRaw('count(distinct s.character_id) >= ?', [$threshold]));
        $kills = $this->read->rows(GroupSpans::killed(GroupSpans::timed()->select('s.character_id', 's.session_start', 's.at', 's.size'))
            ->selectRaw('s.size, count(*) as kills')->groupBy('s.size')->havingRaw('count(distinct k.character_id) >= ?', [$threshold]));

        $by = [];
        foreach ($kills as $row) {
            $by[(int) $row['size']]['kills'] = (int) $row['kills'];
        }
        foreach ($hours as $row) {
            $by[(int) $row['size']]['seconds'] = (int) $row['seconds'];
        }
        ksort($by);
        $rows = [];
        foreach ($by as $size => $counts) {
            if (isset($counts['kills'], $counts['seconds'])) {
                $rows[] = ['size' => $size, 'kills' => $counts['kills'], 'seconds' => $counts['seconds']];
            }
        }

        return $rows;
    }
}
