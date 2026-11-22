<?php

namespace App\Actions\Ingest;

use App\Support\AddonKind;
use Illuminate\Support\Facades\DB;

class AuditClaimNotes
{
    /**
     * The notes over what reconcile stored (docs/DESIGN.md, Addon counts, Checks, 8): a claim assigned more looted
     * Scarlet rows than the catches it claims, which a session seam leaves behind when the rows could not move to the
     * neighbouring claim. With no character, every character is read.
     *
     * @return list<string> one line per claim, 20 at most
     */
    public function handle(?int $characterId): array
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
