<?php

namespace App\Models;

use App\Support\Database\BinaryValue;
use Illuminate\Database\Eloquent\Model;

/**
 * One group of one session of one character, kept as the SHA-256 of its text and its zstd storage form.
 *
 * @property int $character_id
 * @property int $session_start
 * @property string $group_key
 */
class Part extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function body(): string
    {
        return (string) BinaryValue::read($this->attributes['body'] ?? null);
    }
}
