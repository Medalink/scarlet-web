<?php

namespace App\Actions\Ingest;

use App\Support\SharedKind;
use Illuminate\Support\Facades\DB;

class VoteSharedRows
{
    private const int CHUNK = 500;

    /**
     * Write what a session read of a shared reference table (`SharedKind`): the rows no uploader owns, which every
     * page serves. A new row is written as the session read it. After that each field is a vote, one per voter, and
     * the value the row serves changes only when another value holds strictly more voters than the served one. A
     * voter is an account, or an install that no account is linked to, so one person's installs count once. The
     * installs behind the character vote, each with its latest value of a field and at most
     * `scarlet.ingest.shared_votes_per_install_day` changed or new vote rows a day. A row that has no votes yet
     * when the first vote reaches it keeps its served value as the vote of one voter that is none of the installs. The
     * row a write displaces is kept in `previous` where the table has one.
     *
     * Rows are locked in id order. Two decodes that create rows the other then locks can still deadlock on the insert;
     * PostgreSQL aborts one, and `IngestSession` gives its attempt back and the queue runs it again.
     *
     * An install's vote keeps the values of the newest session that cast it, so a redecode of an older session leaves a
     * newer vote alone.
     *
     * @param  list<array<string, mixed>>  $rows  decoded rows with the kind's key and fields
     * @param  int|null  $sessionStart  the session the rows came from; null votes as if it were the newest
     */
    public function handle(int $characterId, SharedKind $kind, array $rows, ?int $sessionStart = null): void
    {
        $candidates = $this->candidates($kind, $rows);
        if ($candidates === []) {
            return;
        }

        $installs = array_values(DB::table('install_character')->where('character_id', $characterId)->pluck('install_id')->map(fn (mixed $install): string => (string) $install)->all());
        $remaining = $this->remaining($installs);
        foreach (array_chunk($candidates, self::CHUNK, preserve_keys: true) as $chunk) {
            $this->chunk($kind, $chunk, $installs, $remaining, $sessionStart);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, array<string, int|string|bool>> the fields each id has a value for, by ascending id
     */
    private function candidates(SharedKind $kind, array $rows): array
    {
        $candidates = [];
        foreach ($rows as $row) {
            $id = (int) $row[$kind->key()];
            foreach ($kind->fields() as $field => $type) {
                $value = $this->value($type, $row[$field] ?? null);
                if ($value !== null) {
                    $candidates[$id][$field] = $value;
                }
            }
        }
        ksort($candidates);

        return $candidates;
    }

    /**
     * @param  array<int, array<string, int|string|bool>>  $chunk
     * @param  list<string>  $installs
     * @param  array<string, int>  $remaining
     */
    private function chunk(SharedKind $kind, array $chunk, array $installs, array &$remaining, ?int $sessionStart): void
    {
        $ids = array_keys($chunk);
        $existing = DB::table($kind->table())->whereIn($kind->key(), $ids)->pluck($kind->key())->map(fn (mixed $id): int => (int) $id)->all();
        $created = array_diff($ids, $existing);
        $this->create($kind, array_intersect_key($chunk, array_flip($created)));

        $served = $this->lockServed($kind, $ids);
        $this->seedServed($kind, array_intersect_key($served, array_flip($existing)));
        $this->cast($kind, $chunk, $installs, $remaining, $sessionStart);
        $ballots = $this->ballots($kind, $ids);

        foreach ($ids as $id) {
            $won = $this->decide($kind, $served[$id] ?? [], $ballots[$id] ?? []);
            if ($won !== []) {
                $this->write($kind, $id, $won);
            }
        }
    }

    /** @param  array<int, array<string, int|string|bool>>  $rows */
    private function create(SharedKind $kind, array $rows): void
    {
        $insert = [];
        $reagents = [];
        foreach ($rows as $id => $fields) {
            $row = [$kind->key() => $id, 'updated_at' => now()] + $this->columns($kind, $fields);
            if ($kind->stampsSeen()) {
                $row['seen_at'] = now();
            }
            if ($kind === SharedKind::Zone) {
                $row['name'] ??= '';
            }
            $insert[] = $row + array_fill_keys($this->columnNames($kind), null);
            foreach ($this->reagentRows($id, (string) ($fields['reagents'] ?? '')) as $reagent) {
                $reagents[] = $reagent;
            }
        }
        if ($insert === []) {
            return;
        }

        DB::table($kind->table())->insertOrIgnore($insert);
        if ($reagents !== []) {
            DB::table('recipe_reagents')->insertOrIgnore($reagents);
        }
    }

    /**
     * Lock the served rows of the ids, in id order, and read their fields in the form of a vote.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, int|string|bool>>
     */
    private function lockServed(SharedKind $kind, array $ids): array
    {
        $served = [];
        $rows = DB::table($kind->table())->whereIn($kind->key(), $ids)->orderBy($kind->key())->lockForUpdate()->get();
        foreach ($rows as $row) {
            $fields = [];
            foreach ($kind->fields() as $field => $type) {
                $value = $type === SharedKind::REAGENTS ? null : $this->value($type, $row->{$field} ?? null);
                if ($value !== null) {
                    $fields[$field] = $value;
                }
            }
            $served[(int) $row->{$kind->key()}] = $fields;
        }
        if ($kind === SharedKind::Recipe) {
            foreach ($this->servedReagents(array_keys($served)) as $id => $list) {
                $served[$id]['reagents'] = $list;
            }
        }

        return $served;
    }

    /**
     * Back a served row that nobody has voted on yet, a row from before votes or one written while no install had
     * budget, with its own value as one voter, so the first install to disagree ties it instead of replacing it.
     *
     * @param  array<int, array<string, int|string|bool>>  $served
     */
    private function seedServed(SharedKind $kind, array $served): void
    {
        if ($served === []) {
            return;
        }

        $voted = DB::table('shared_votes')->where('kind', $kind->value)->whereIn('row_id', array_keys($served))->distinct()->pluck('row_id')->map(fn (mixed $id): int => (int) $id)->all();
        $seeds = [];
        foreach (array_diff_key($served, array_flip($voted)) as $id => $fields) {
            if ($fields !== []) {
                $seeds[] = ['kind' => $kind->value, 'row_id' => $id, 'install_id' => null, 'fields' => json_encode($fields), 'created_at' => now(), 'updated_at' => now()];
            }
        }
        if ($seeds !== []) {
            DB::table('shared_votes')->insertOrIgnore($seeds);
        }
    }

    /**
     * Record the session's values as the votes of the character's installs: a new vote row, or a vote row whose
     * value changed, uses one of the install's votes of the day.
     *
     * @param  array<int, array<string, int|string|bool>>  $chunk
     * @param  list<string>  $installs
     * @param  array<string, int>  $remaining
     */
    private function cast(SharedKind $kind, array $chunk, array $installs, array &$remaining, ?int $sessionStart): void
    {
        if ($installs === []) {
            return;
        }

        $old = [];
        $newer = [];
        $rows = DB::table('shared_votes')->where('kind', $kind->value)->whereIn('row_id', array_keys($chunk))->whereIn('install_id', $installs)->get(['row_id', 'install_id', 'fields', 'session_start']);
        foreach ($rows as $row) {
            $key = $row->row_id.'|'.$row->install_id;
            $old[$key] = $this->decode((string) $row->fields);
            if ($sessionStart !== null && $row->session_start !== null && (int) $row->session_start > $sessionStart) {
                $newer[$key] = true;
            }
        }

        $votes = [];
        foreach ($chunk as $id => $fields) {
            foreach ($installs as $install) {
                if (isset($newer[$id.'|'.$install])) {
                    continue;
                }
                $before = $old[$id.'|'.$install] ?? null;
                $after = array_replace($before ?? [], $fields);
                if ($before !== null && $this->same($before, $after)) {
                    continue;
                }
                if (($remaining[$install] ?? 0) <= 0) {
                    continue;
                }
                $remaining[$install]--;
                $votes[] = ['kind' => $kind->value, 'row_id' => $id, 'install_id' => $install, 'fields' => json_encode($after), 'session_start' => $sessionStart, 'created_at' => now(), 'updated_at' => now()];
            }
        }
        if ($votes !== []) {
            DB::table('shared_votes')->upsert($votes, ['kind', 'row_id', 'install_id'], ['fields', 'session_start', 'updated_at']);
        }
    }

    /**
     * Every voter's latest value of each field, per id: one voter per account, or per install of no account.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, array<string, int|string|bool>>>
     */
    private function ballots(SharedKind $kind, array $ids): array
    {
        $ballots = [];
        $rows = DB::table('shared_votes as v')->leftJoin('installs as i', 'i.id', '=', 'v.install_id')
            ->where('v.kind', $kind->value)->whereIn('v.row_id', $ids)->orderByRaw('v.session_start asc nulls first')->orderBy('v.updated_at')->orderBy('v.created_at')
            ->get(['v.row_id', 'v.install_id', 'i.account_id', 'v.fields']);
        foreach ($rows as $row) {
            $voter = $row->install_id === null ? 'served' : ($row->account_id === null ? 'install:'.$row->install_id : 'account:'.$row->account_id);
            $id = (int) $row->row_id;
            $ballots[$id][$voter] = array_replace($ballots[$id][$voter] ?? [], $this->decode((string) $row->fields));
        }

        return $ballots;
    }

    /**
     * The fields whose served value another value outvotes. A field with no served value takes the value with the most
     * voters, the smaller digest on a tie; a served value is kept on a tie.
     *
     * @param  array<string, int|string|bool>  $served
     * @param  array<string, array<string, int|string|bool>>  $ballots
     * @return array<string, int|string|bool>
     */
    private function decide(SharedKind $kind, array $served, array $ballots): array
    {
        $won = [];
        foreach (array_keys($kind->fields()) as $field) {
            $tally = [];
            $values = [];
            foreach ($ballots as $ballot) {
                if (isset($ballot[$field])) {
                    $key = $this->digest($ballot[$field]);
                    $tally[$key] = ($tally[$key] ?? 0) + 1;
                    $values[$key] = $ballot[$field];
                }
            }
            ksort($tally);
            $held = isset($served[$field]) ? $this->digest($served[$field]) : null;
            $best = $held;
            $bestCount = $held === null ? 0 : ($tally[$held] ?? 0);
            foreach ($tally as $key => $count) {
                if ($count > $bestCount) {
                    [$best, $bestCount] = [$key, $count];
                }
            }
            if ($best !== null && $best !== $held) {
                $won[$field] = $values[$best];
            }
        }

        return $won;
    }

    /** @param  array<string, int|string|bool>  $won */
    private function write(SharedKind $kind, int $id, array $won): void
    {
        $update = $this->columns($kind, $won) + ['updated_at' => now()];
        if ($kind->stampsSeen()) {
            $update['seen_at'] = now();
        }
        if ($kind->keepsPrevious()) {
            $update['previous'] = $kind === SharedKind::Item ? DB::raw("to_jsonb(items) - 'previous'") : DB::raw("to_jsonb(zones) - 'previous'");
        }
        DB::table($kind->table())->where($kind->key(), $id)->update($update);
        if (isset($won['reagents'])) {
            DB::table('recipe_reagents')->where('recipe_spell_id', $id)->delete();
            DB::table('recipe_reagents')->insert($this->reagentRows($id, (string) $won['reagents']));
        }
    }

    /**
     * @param  list<string>  $installs
     * @return array<string, int> the votes each install has left today
     */
    private function remaining(array $installs): array
    {
        $budget = (int) config('scarlet.ingest.shared_votes_per_install_day');
        $used = DB::table('shared_votes')->whereIn('install_id', $installs)->where('updated_at', '>=', today())
            ->selectRaw('install_id, count(*) as votes')->groupBy('install_id')->pluck('votes', 'install_id');
        $remaining = [];
        foreach ($installs as $install) {
            $remaining[$install] = max(0, $budget - (int) ($used[$install] ?? 0));
        }

        return $remaining;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string> the canonical reagent list of each recipe that has one
     */
    private function servedReagents(array $ids): array
    {
        $lists = [];
        foreach (DB::table('recipe_reagents')->whereIn('recipe_spell_id', $ids)->orderBy('item_id')->get(['recipe_spell_id', 'item_id', 'quantity']) as $row) {
            $lists[(int) $row->recipe_spell_id][] = $row->item_id.':'.$row->quantity;
        }

        return array_map(fn (array $entries): string => implode(',', $entries), $lists);
    }

    /** @return list<array{recipe_spell_id: int, item_id: int, quantity: int}> */
    private function reagentRows(int $id, string $list): array
    {
        $rows = [];
        foreach (explode(',', $list) as $entry) {
            [$item, $quantity] = [...explode(':', $entry, 2), '1'];
            if (ctype_digit($item) && ctype_digit($quantity)) {
                $rows[] = ['recipe_spell_id' => $id, 'item_id' => (int) $item, 'quantity' => (int) $quantity];
            }
        }

        return $rows;
    }

    private function value(string $type, mixed $value): int|string|bool|null
    {
        $normal = SharedKind::normalize($type, $value);

        return $type === SharedKind::REAGENTS && is_string($normal) ? $this->canonicalReagents($normal) : $normal;
    }

    /** The reagent list as `item:count` entries by ascending item, an item named twice summed, or null when none is valid. */
    private function canonicalReagents(string $list): ?string
    {
        $needs = [];
        foreach (explode(',', $list) as $entry) {
            [$item, $count] = [...explode(':', $entry, 2), '1'];
            if (ctype_digit($item) && ctype_digit($count) && (int) $count >= 1) {
                $needs[(int) $item] = ($needs[(int) $item] ?? 0) + (int) $count;
            }
        }
        ksort($needs);

        return $needs === [] ? null : implode(',', array_map(fn (int $item, int $count): string => $item.':'.$count, array_keys($needs), $needs));
    }

    /**
     * @param  array<string, int|string|bool>  $fields
     * @return array<string, int|string|bool> the fields the table has a column for
     */
    private function columns(SharedKind $kind, array $fields): array
    {
        return array_intersect_key($fields, array_flip($this->columnNames($kind)));
    }

    /** @return list<string> */
    private function columnNames(SharedKind $kind): array
    {
        return array_keys(array_filter($kind->fields(), fn (string $type): bool => $type !== SharedKind::REAGENTS));
    }

    /** @return array<string, int|string|bool> */
    private function decode(string $json): array
    {
        $fields = json_decode($json, true);

        return is_array($fields) ? $fields : [];
    }

    /**
     * @param  array<string, int|string|bool>  $a
     * @param  array<string, int|string|bool>  $b
     */
    private function same(array $a, array $b): bool
    {
        ksort($a);
        ksort($b);

        return $a === $b;
    }

    private function digest(int|string|bool $value): string
    {
        return hash('xxh128', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
