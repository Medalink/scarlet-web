<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A sharing group an owner removed from the server: a sync with it still on is answered `group_refused`. */
class RefusedGroup extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
