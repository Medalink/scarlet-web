<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An install that listed a part. The part stays while any install claims it.
 *
 * @property string $install_id
 */
class PartClaim extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
