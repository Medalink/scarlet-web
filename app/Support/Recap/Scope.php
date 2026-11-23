<?php

namespace App\Support\Recap;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One session of one character: the key that every fact table is read by. */
final readonly class Scope
{
    public function __construct(public int $character, public int $start) {}

    public function rows(string $table): Builder
    {
        return DB::table($table)->where('character_id', $this->character)->where('session_start', $this->start);
    }
}
