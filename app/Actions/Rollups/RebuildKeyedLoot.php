<?php

namespace App\Actions\Rollups;

use Illuminate\Support\Facades\DB;

class RebuildKeyedLoot
{
    /**
     * Fill the five keyed loot tables whole from the windows that carry a corpse key (docs/LOOT.md, Units of counting,
     * rule 7), in one transaction: a reader sees the old tables or the new ones. The windows are the `loot_opens` rows
     * and the `peer_opens` rows (what a group member who runs Scarlet sent), first reads only. A corpse is
     * `(source_kind, source_id, corpse_key, read)`, and for an object the same key more than 5 minutes after the last read
     * is a second opening of a respawned object. Of the windows of one corpse the table keeps one: a character's own
     * before a window a member sent, then the earliest read (`peer_opens.at` less `late_s`), so the items are those of one
     * whole window. A window a member sent has no `read`: a creature's counts as `loot`, an object's takes the read its own
     * windows have most (`loot`, `herb` or `ore`) and an object nobody opened for itself counts nowhere. The `characters`
     * of a row are the characters whose window the table kept. Kills of a corpse are one row each by `(npc_id,
     * corpse_key)`, from a session with a `loot` part, with the corpse's loot window when one was kept. Raw SQL for the
     * window functions, the temporary tables and the hyperloglog the aggregates use.
     */
    public function handle(): void
    {
        DB::transaction(function (): void {
            foreach (['loot_windows_keyed_daily', 'loot_items_keyed_daily', 'loot_windows_character_keyed_daily', 'loot_kills_keyed_daily', 'loot_kill_items_keyed_daily'] as $table) {
                DB::table($table)->delete();
            }
            DB::statement($this->corpses());
            DB::statement('create index on loot_corpses (source_kind, source_id, corpse_key)');
            DB::statement($this->items());
            DB::statement($this->kills());
            foreach ($this->inserts() as $insert) {
                DB::statement($insert);
            }
        });
    }

    private function corpses(): string
    {
        return <<<'SQL'
            create temporary table loot_corpses on commit drop as
            with objects as (
                select source_id, mode() within group (order by read) as read
                from loot_opens
                where counted and source_kind = 'g' and source_id is not null and read in ('loot', 'herb', 'ore')
                group by source_id
            ), candidates as (
                select 0 as peer, o.at as read_at, o.at, o.character_id, o.session_start, o.source_kind, o.source_id, o.read, o.loot_rule, o.group_size, o.zone_id, o.item_rows, o.corpse_key
                from loot_opens o
                where o.counted and o.has_items and o.first_read is not false and o.source_id is not null and o.corpse_key is not null
                union all
                select 1, p.at - make_interval(secs => coalesce(p.late_s, 0)), p.at, p.character_id, p.session_start, p.source_kind, p.source_id,
                       case when p.source_kind = 'c' then 'loot' else coalesce(case when p.read = 'fish' then 'fish' end, g.read) end,
                       p.loot_rule, p.group_size, p.zone_id, p.item_rows, p.corpse_key
                from peer_opens p
                left join objects g on p.source_kind = 'g' and g.source_id = p.source_id
                where p.counted and p.has_items and p.first_read is not false and p.source_id is not null and p.corpse_key is not null
            ), gapped as (
                select c.*, case when c.source_kind = 'g' and c.read_at - lag(c.read_at) over w > interval '5 minutes' then 1 else 0 end as split
                from candidates c
                where c.peer = 0 or c.read is not null
                window w as (partition by c.source_kind, c.source_id, c.corpse_key, c.read order by c.read_at)
            ), clustered as (
                select g.*, sum(g.split) over (partition by g.source_kind, g.source_id, g.corpse_key, g.read order by g.read_at rows unbounded preceding) as cluster
                from gapped g
            ), ranked as (
                select c.*, row_number() over (partition by c.source_kind, c.source_id, c.corpse_key, c.read, c.cluster order by c.peer, c.read_at, c.character_id, c.session_start) as rn
                from clustered c
            )
            select row_number() over () as id, r.* from ranked r where r.rn = 1
            SQL;
    }

    private function items(): string
    {
        return <<<'SQL'
            create temporary table loot_corpse_items on commit drop as
            select c.id as corpse, d.item_id, sum(d.quantity) as quantity
            from loot_corpses c
            join loot_drops d on c.peer = 0 and d.via = 'window' and d.counted and d.character_id = c.character_id and d.session_start = c.session_start and d.at = c.at
                and d.source_kind = c.source_kind and d.source_id = c.source_id and d.corpse_key = c.corpse_key
            group by c.id, d.item_id
            union all
            select c.id, d.item_id, sum(d.quantity)
            from loot_corpses c
            join peer_drops d on c.peer = 1 and d.counted and d.character_id = c.character_id and d.session_start = c.session_start and d.at = c.at
                and d.source_kind = c.source_kind and d.source_id = c.source_id and d.corpse_key = c.corpse_key
            group by c.id, d.item_id
            SQL;
    }

    private function kills(): string
    {
        return <<<'SQL'
            create temporary table loot_kill_corpses on commit drop as
            select * from (
                select distinct on (k.npc_id, k.corpse_key) k.at, k.character_id, k.npc_id, k.corpse_key, k.loot_rule, k.group_size
                from kills k
                where k.counted and not k.player and k.npc_id is not null and k.corpse_key is not null and k.credit in (0, 1)
                order by k.npc_id, k.corpse_key, (k.loot_rule is null), k.at, k.character_id
            ) picked
            where picked.loot_rule is not null
            SQL;
    }

    /** @return list<string> */
    private function inserts(): array
    {
        return [
            <<<'SQL'
                insert into loot_windows_keyed_daily
                select time_bucket('1 day', read_at), source_kind, source_id, read, loot_rule, group_size, zone_id, count(*), sum(item_rows), hyperloglog(1024, character_id)
                from loot_corpses group by 1, 2, 3, 4, 5, 6, 7
                SQL,
            <<<'SQL'
                insert into loot_items_keyed_daily
                select time_bucket('1 day', c.read_at), c.source_kind, c.source_id, c.read, c.loot_rule, c.group_size, c.zone_id, i.item_id, count(*), sum(i.quantity), hyperloglog(1024, c.character_id)
                from loot_corpses c join loot_corpse_items i on i.corpse = c.id
                group by 1, 2, 3, 4, 5, 6, 7, 8
                SQL,
            <<<'SQL'
                insert into loot_windows_character_keyed_daily
                select time_bucket('1 day', read_at), source_kind, source_id, read, loot_rule, group_size, character_id, count(*), sum(item_rows), sum(item_rows::bigint * item_rows)
                from loot_corpses group by 1, 2, 3, 4, 5, 6, 7
                SQL,
            <<<'SQL'
                insert into loot_kills_keyed_daily
                select time_bucket('1 day', k.at), k.npc_id, k.loot_rule, k.group_size, count(*), count(c.id), hyperloglog(1024, k.character_id)
                from loot_kill_corpses k
                left join loot_corpses c on c.source_kind = 'c' and c.source_id = k.npc_id and c.corpse_key = k.corpse_key and c.read = 'loot'
                group by 1, 2, 3, 4
                SQL,
            <<<'SQL'
                insert into loot_kill_items_keyed_daily
                select time_bucket('1 day', k.at), k.npc_id, k.loot_rule, k.group_size, i.item_id, count(*), hyperloglog(1024, k.character_id)
                from loot_kill_corpses k
                join loot_corpses c on c.source_kind = 'c' and c.source_id = k.npc_id and c.corpse_key = k.corpse_key and c.read = 'loot'
                join loot_corpse_items i on i.corpse = c.id
                group by 1, 2, 3, 4, 5
                SQL,
        ];
    }
}
