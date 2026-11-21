<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One decoded session of a character (the `sessions` table; Laravel's own session store is Redis here).
 *
 * @property int $character_id
 * @property int $session_start
 */
class PlaySession extends Model
{
    protected $table = 'sessions';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['groups' => 'array', 'flags' => 'array', 'extra' => 'array', 'counted' => 'boolean', 'start_at' => 'datetime', 'end_at' => 'datetime'];
    }

    /** @return BelongsTo<Character, $this> */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
