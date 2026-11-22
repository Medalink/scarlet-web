<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One UTC day of one character whose `counts` parts were decoded, with the decoder and reconcile versions that did
 * it, so the scheduled pass can find the days an older rule wrote.
 *
 * @property int $character_id
 * @property int $day_key
 * @property int|null $decoder_version
 * @property int|null $reconcile_version
 */
class AddonDay extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
