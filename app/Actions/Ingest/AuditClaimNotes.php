<?php

namespace App\Actions\Ingest;

use App\Support\AddonKind;
use App\Support\ClaimStatus;
use App\Support\PartTool;
use App\Support\SharingGroup;
use Illuminate\Support\Facades\DB;

class AuditClaimNotes
{
    /** Seconds around a session with a combat part in which a boss claim is held. */
    private const int BOSS_SESSION_REACH = 60;

    public function __construct(private readonly PartTool $tool) {}

    /**
     * The notes over what reconcile stored (docs/DESIGN.md, Addon counts, Checks, 8): a claim assigned more looted
     * Scarlet rows than the catches it claims, which a session seam leaves behind when the rows could not move to the
     * neighbouring claim, and the boss claims held beside a session that is already decoded at the current version,
     * per encounter, which measures whether the addon's encounter id is Scarlet's for a boss Scarlet has no kill of.
     * With no character, every character is read.
     *
     * @return list<string> one line per claim, 20 at most, then one per character and encounter held
     */
    public function handle(?int $characterId): array
    {
        return [...$this->looted($characterId), ...$this->bossesHeld($characterId)];
    }

    /** @return list<string> */
    private function bossesHeld(?int $characterId): array
    {
        if (! DB::table('addon_claims')->where('kind', AddonKind::Boss->value)->where('status', ClaimStatus::Held->value)->exists()) {
            return [];
        }
        $lines = [];
        foreach (DB::select(
            <<<'SQL'
            select ch.name as character_name, c.origin, c.dims ->> 'encounter' as encounter, count(*) as claims
            from addon_claims c join characters ch on ch.id = c.character_id
            where c.kind = ? and c.status = ? and c.evidence ->> 'rule' = '2' and jsonb_exists(c.evidence, 'why')
                and (?::bigint is null or c.character_id = ?)
                and exists (
                    select 1 from sessions s
                    where s.character_id = c.character_id and jsonb_exists(s.groups, ?) and s.decoder_version >= ?
                        and c.to_at between extract(epoch from s.start_at) - ?::bigint and extract(epoch from s.end_at) + ?::bigint
                )
            group by c.character_id, ch.name, c.origin, c.dims ->> 'encounter'
            order by ch.name, c.origin, encounter
            limit 20
            SQL,
            [AddonKind::Boss->value, ClaimStatus::Held->value, $characterId, $characterId, SharingGroup::COMBAT, $this->tool->decoderVersion(), self::BOSS_SESSION_REACH, self::BOSS_SESSION_REACH],
        ) as $row) {
            $lines[] = "{$row->character_name}: {$row->claims} boss claims of {$row->origin} for encounter {$row->encounter} are held beside a session decoded at the current version, which has no kill of it";
        }

        return $lines;
    }

    /** @return list<string> */
    private function looted(?int $characterId): array
    {
        $lines = [];
        foreach (DB::select(
            <<<'SQL'
            select c.id, ch.name as character_name, c.origin, c.to_at,
                (c.evidence #>> '{rows,looted}')::int as looted, (c.amounts ->> 'landed')::numeric as landed
            from addon_claims c join characters ch on ch.id = c.character_id
            where c.kind = ? and (c.evidence #>> '{rows,looted}')::int > (c.amounts ->> 'landed')::numeric
                and (?::bigint is null or c.character_id = ?)
            order by c.id
            limit 20
            SQL,
            [AddonKind::Fishing->value, $characterId, $characterId],
        ) as $row) {
            $lines[] = "claim {$row->id} of {$row->character_name} ({$row->origin} to {$row->to_at}) was assigned {$row->looted} looted Scarlet rows and claims {$row->landed} landed";
        }

        return $lines;
    }
}
