<?php

namespace App\Actions\Rollups;

use Illuminate\Support\Facades\DB;

class RebuildKeyedLoot
{
    private const array TABLES = ['loot_windows_keyed_daily', 'loot_items_keyed_daily', 'loot_windows_character_keyed_daily', 'loot_kills_keyed_daily', 'loot_kill_items_keyed_daily'];

    /**
     * Fill the five keyed loot tables from the windows that carry a corpse key (docs/LOOT.md, Units of counting, rule 7),
     * in one transaction: a reader sees the old tables or the new ones. The windows are the `loot_opens` rows and the
     * `peer_opens` rows (what a group member who runs Scarlet sent), first reads only. A corpse is `(source_kind,
     * source_id, corpse_key, read)`, and for an object the same key more than 5 minutes after the last read is a second
     * opening of a respawned object. Of the windows of one corpse the table keeps one: a character's own before a window a
     * member sent, then the earliest read (`peer_opens.at` less `late_s`), so the items are those of one whole window. A
     * window a member sent has no `read`: a creature's counts as `skin` when it lists a skin item, else as `loot`. A skin
     * item is an item that a `gather_items` row of kind `skin` has and that no `loot` window of a character lists, nor a
     * window a member sent for a corpse with no skin window of its own (a member's window is `skin` or `loot` by these items,
     * so its own class cannot say it is `loot`), since
     * `gather_items` holds the whole list of a skinning read and the meat of a corpse nobody looted would join it. A skin
     * window of a mate that lists leftover meat is still `skin` by its leather, and a mate's loot window of meat alone is
     * `loot`. The set is read from every row, not from the days rebuilt, and the days outside a range keep the set of their
     * own rebuild until the next whole one. An object's window takes the read its own windows have most (`loot`, `herb` or
     * `ore`) and an object nobody opened for itself counts nowhere. A kept window a member sent is also kept out of the
     * three window tables, though not out of the kills, when it pairs with a window with no key: the nearest one within 5 s
     * of its read with the same items and the same loot rule, each of the two in one pair at most (the sender's own read of
     * the same corpse, since a sender on an addon before the key writes none, and the unkeyed aggregates count it). A
     * free-for-all source with windows with no key from two characters or more counts only its one reader's (`LootArm`: the
     * character with most of them over every day, the lowest id on a tie), so a window paired with another character's read
     * is kept, since that read counts nowhere. The
     * `characters` of a row are the characters whose window the table kept. Kills of a corpse are one row each by `(npc_id,
     * corpse_key)`, from a session with a `loot` part, whoever got the credit, with the corpse's loot window when one was
     * kept.
     *
     * `$from` and `$until` (epoch seconds, `$until` excluded) name the days that changed; both null rebuilds everything.
     * Rows read days a corpse can reach, so the days rebuilt are those of the range and the day before and after it (a
     * window a member sent can be read up to a day before it was sent), and the rows of those days are the only ones
     * deleted and inserted. The windows and kills that decide a corpse are read from a day before to a day after those
     * days; two rows of one corpse more than a day apart are two corpses in a range and one in a full rebuild.
     * Raw SQL for the window functions, the temporary tables and the hyperloglog the aggregates use.
     */
    public function handle(?int $from = null, ?int $until = null): void
    {
        DB::transaction(function () use ($from, $until): void {
            DB::statement($this->range(), ['from' => $from, 'until' => $until]);
            foreach (self::TABLES as $table) {
                DB::table($table)->whereRaw('bucket >= (select b_from from loot_range) and bucket < (select b_until from loot_range)')->delete();
            }
            DB::statement($this->corpses());
            DB::statement('create index on loot_corpses (source_kind, source_id, corpse_key)');
            DB::statement('analyze loot_corpses');
            DB::statement($this->items());
            DB::statement($this->kills());
            foreach ($this->inserts() as $insert) {
                DB::statement($insert);
            }
        });
    }

    /**
     * `b_*` bound the days rebuilt, `s_*` the days the rows are read from, and `peer_until` the last day a window sent by
     * a member can have been sent on for a read inside `s_*`.
     */
    private function range(): string
    {
        return <<<'SQL'
            create temporary table loot_range on commit drop as
            select b_from, b_until, b_from - interval '24 hours' as s_from, b_until + interval '24 hours' as s_until, b_until + interval '48 hours' as peer_until
            from (
                select case when p.f is null then '-infinity'::timestamptz else time_bucket('1 day', to_timestamp(p.f) - interval '24 hours') end as b_from,
                       case when p.u is null then 'infinity'::timestamptz else time_bucket('1 day', to_timestamp(p.u) - interval '1 microsecond') + interval '48 hours' end as b_until
                from (select :from::bigint as f, :until::bigint as u) p
            ) b
            SQL;
    }

    private function corpses(): string
    {
        return <<<'SQL'
            create temporary table loot_corpses on commit drop as
            with objects as (
                select source_id, mode() within group (order by read) as read
                from loot_opens
                where counted and source_kind = 'g' and source_id is not null and read in ('loot', 'herb', 'ore')
                    and source_id in (
                        select source_id from peer_opens
                        where source_kind = 'g' and at >= (select s_from from loot_range) and at < (select peer_until from loot_range)
                    )
                group by source_id
            ), skin_items as materialized (
                select item_id from gather_items where kind = 'skin'
                except
                select item_id from loot_drops where via = 'window' and counted and read = 'loot'
                except
                select d.item_id from peer_drops d
                where d.counted and d.corpse_key is not null and d.source_kind = 'c'
                    and not exists (
                        select 1 from loot_opens s
                        where s.source_kind = d.source_kind and s.source_id = d.source_id and s.corpse_key = d.corpse_key and s.read = 'skin' and s.counted
                    )
            ), readers as (
                select source_kind, source_id, (array_agg(character_id order by windows desc, character_id))[1] as reader, count(*) as unkeyed_characters
                from (
                    select source_kind, source_id, character_id, sum(windows) as windows
                    from loot_windows_character_unkeyed_daily
                    where read = 'loot' and loot_rule = 'freeforall'
                    group by source_kind, source_id, character_id
                ) per
                group by source_kind, source_id
            ), peer_signatures as (
                select character_id, session_start, at, source_kind, source_id, corpse_key,
                       count(*) filter (where skin) as skin_count,
                       string_agg(item_id::text || 'x' || quantity, ',' order by item_id) as signature
                from (
                    select d.character_id, d.session_start, d.at, d.source_kind, d.source_id, d.corpse_key, d.item_id, sum(d.quantity) as quantity, bool_or(s.item_id is not null) as skin
                    from peer_drops d
                    left join skin_items s on s.item_id = d.item_id
                    where d.counted and d.corpse_key is not null and d.at >= (select s_from from loot_range) and d.at < (select peer_until from loot_range)
                    group by d.character_id, d.session_start, d.at, d.source_kind, d.source_id, d.corpse_key, d.item_id
                ) merged
                group by character_id, session_start, at, source_kind, source_id, corpse_key
            ), unkeyed_signatures as (
                select o.character_id, o.session_start, o.at as read_at, o.source_kind, o.source_id, o.loot_rule, coalesce(g.signature, '') as signature
                from loot_opens o
                left join (
                    select character_id, session_start, at, source_kind, source_id, string_agg(item_id::text || 'x' || quantity, ',' order by item_id) as signature
                    from (
                        select character_id, session_start, at, source_kind, source_id, item_id, sum(quantity) as quantity
                        from loot_drops
                        where counted and via = 'window' and first_read is not false and source_id is not null and corpse_key is null
                            and at >= (select s_from from loot_range) and at < (select s_until from loot_range)
                        group by character_id, session_start, at, source_kind, source_id, item_id
                    ) merged
                    group by character_id, session_start, at, source_kind, source_id
                ) g on g.character_id = o.character_id and g.session_start = o.session_start and g.at = o.at and g.source_kind = o.source_kind and g.source_id = o.source_id
                where o.counted and o.has_items and o.first_read is not false and o.source_id is not null and o.corpse_key is null
                    and o.at >= (select s_from from loot_range) and o.at < (select s_until from loot_range)
            ), peer_reads as (
                select p.at - make_interval(secs => coalesce(p.late_s, 0)) as read_at, p.at, p.character_id, p.session_start, p.source_kind, p.source_id,
                       case when p.source_kind = 'c' then case when sig.skin_count > 0 then 'skin' else 'loot' end
                            else coalesce(case when p.read = 'fish' then 'fish' end, g.read) end as read,
                       coalesce(sig.signature, '') as signature, p.loot_rule, p.group_size, p.zone_id, p.item_rows, p.corpse_key
                from peer_opens p
                left join objects g on p.source_kind = 'g' and g.source_id = p.source_id
                left join peer_signatures sig on sig.character_id = p.character_id and sig.session_start = p.session_start and sig.at = p.at
                    and sig.source_kind = p.source_kind and sig.source_id = p.source_id and sig.corpse_key = p.corpse_key
                where p.counted and p.has_items and p.first_read is not false and p.source_id is not null and p.corpse_key is not null
                    and p.at >= (select s_from from loot_range) and p.at < (select peer_until from loot_range)
                    and p.at - make_interval(secs => coalesce(p.late_s, 0)) >= (select s_from from loot_range)
                    and p.at - make_interval(secs => coalesce(p.late_s, 0)) < (select s_until from loot_range)
            ), near_pairs as (
                select r.character_id, r.session_start, r.at, r.source_kind, r.source_id, r.corpse_key, r.loot_rule, r.read,
                       u.character_id as u_character_id, u.session_start as u_session_start, u.read_at as u_read_at,
                       abs(extract(epoch from u.read_at - r.read_at)) as gap
                from peer_reads r
                join unkeyed_signatures u on u.source_kind = r.source_kind and u.source_id = r.source_id and u.signature = r.signature and u.loot_rule = r.loot_rule
                    and u.read_at between r.read_at - interval '5 seconds' and r.read_at + interval '5 seconds'
            ), nearest_unkeyed as (
                select distinct on (character_id, session_start, at, source_kind, source_id, corpse_key) *
                from near_pairs
                order by character_id, session_start, at, source_kind, source_id, corpse_key, gap, u_character_id, u_session_start, u_read_at
            ), matched_peers as (
                select distinct on (n.u_character_id, n.u_session_start, n.u_read_at, n.source_kind, n.source_id) n.character_id, n.session_start, n.at, n.source_kind, n.source_id, n.corpse_key,
                       coalesce(n.loot_rule = 'freeforall' and n.read = 'loot' and rd.unkeyed_characters >= 2 and n.u_character_id <> rd.reader, false) as uncounted
                from nearest_unkeyed n
                left join readers rd on rd.source_kind = n.source_kind and rd.source_id = n.source_id
                order by n.u_character_id, n.u_session_start, n.u_read_at, n.source_kind, n.source_id, n.gap, n.character_id, n.session_start, n.at, n.corpse_key
            ), candidates as (
                select 0 as peer, false as matched, o.at as read_at, o.at, o.character_id, o.session_start, o.source_kind, o.source_id, o.read, o.loot_rule, o.group_size, o.zone_id, o.item_rows, o.corpse_key
                from loot_opens o
                where o.counted and o.has_items and o.first_read is not false and o.source_id is not null and o.corpse_key is not null
                    and o.at >= (select s_from from loot_range) and o.at < (select s_until from loot_range)
                union all
                select 1, m.corpse_key is not null and not m.uncounted, r.read_at, r.at, r.character_id, r.session_start, r.source_kind, r.source_id, r.read, r.loot_rule, r.group_size, r.zone_id, r.item_rows, r.corpse_key
                from peer_reads r
                left join matched_peers m on m.character_id = r.character_id and m.session_start = r.session_start and m.at = r.at
                    and m.source_kind = r.source_kind and m.source_id = r.source_id and m.corpse_key = r.corpse_key
            ), gapped as (
                select c.*, case when c.source_kind = 'g' and c.read_at - lag(c.read_at) over w > interval '5 minutes' then 1 else 0 end as split
                from candidates c
                where c.peer = 0 or c.read is not null
                window w as (partition by c.source_kind, c.source_id, c.corpse_key, c.read order by c.read_at)
            ), clustered as (
                select g.*, sum(g.split) over (partition by g.source_kind, g.source_id, g.corpse_key, g.read order by g.read_at rows unbounded preceding) as cluster
                from gapped g
            ), ranked as (
                select c.*, row_number() over (partition by c.source_kind, c.source_id, c.corpse_key, c.read, c.cluster order by c.peer, c.read_at, c.character_id, c.session_start) as rn,
                       bool_or(c.matched) over (partition by c.source_kind, c.source_id, c.corpse_key, c.read, c.cluster) as any_matched
                from clustered c
            )
            select row_number() over () as id, r.*, (r.peer = 1 and r.any_matched) as dup from ranked r where r.rn = 1
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
                and d.at >= (select s_from from loot_range) and d.at < (select s_until from loot_range)
            group by c.id, d.item_id
            union all
            select c.id, d.item_id, sum(d.quantity)
            from loot_corpses c
            join peer_drops d on c.peer = 1 and d.counted and d.character_id = c.character_id and d.session_start = c.session_start and d.at = c.at
                and d.source_kind = c.source_kind and d.source_id = c.source_id and d.corpse_key = c.corpse_key
                and d.at >= (select s_from from loot_range) and d.at < (select peer_until from loot_range)
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
                where k.counted and not k.player and k.npc_id is not null and k.corpse_key is not null
                    and k.at >= (select s_from from loot_range) and k.at < (select s_until from loot_range)
                order by k.npc_id, k.corpse_key, (k.loot_rule is null), k.at, k.character_id
            ) picked
            where picked.loot_rule is not null
                and picked.at >= (select b_from from loot_range) and picked.at < (select b_until from loot_range)
            SQL;
    }

    /** @return list<string> */
    private function inserts(): array
    {
        return [
            <<<'SQL'
                insert into loot_windows_keyed_daily
                select time_bucket('1 day', read_at), source_kind, source_id, read, loot_rule, group_size, zone_id, count(*), sum(item_rows), hyperloglog(1024, character_id)
                from loot_corpses
                where not dup and read_at >= (select b_from from loot_range) and read_at < (select b_until from loot_range)
                group by 1, 2, 3, 4, 5, 6, 7
                SQL,
            <<<'SQL'
                insert into loot_items_keyed_daily
                select time_bucket('1 day', c.read_at), c.source_kind, c.source_id, c.read, c.loot_rule, c.group_size, c.zone_id, i.item_id, count(*), sum(i.quantity), hyperloglog(1024, c.character_id)
                from loot_corpses c join loot_corpse_items i on i.corpse = c.id
                where not c.dup and c.read_at >= (select b_from from loot_range) and c.read_at < (select b_until from loot_range)
                group by 1, 2, 3, 4, 5, 6, 7, 8
                SQL,
            <<<'SQL'
                insert into loot_windows_character_keyed_daily
                select time_bucket('1 day', read_at), source_kind, source_id, read, loot_rule, group_size, character_id, count(*), sum(item_rows), sum(item_rows::bigint * item_rows)
                from loot_corpses
                where not dup and read_at >= (select b_from from loot_range) and read_at < (select b_until from loot_range)
                group by 1, 2, 3, 4, 5, 6, 7
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
