<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Requests to /api/v1 one caller made to one endpoint on one UTC day (docs/DESIGN.md, Admin). */
class ApiCall extends Model
{
    /** The columns that name one row, in the order of the primary key. */
    public const array KEY = ['day', 'surface', 'endpoint', 'address', 'account_id', 'install_id', 'character_key'];

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
