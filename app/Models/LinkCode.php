<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A code the app shows so a signed-in person can link the install to their account (the web side comes later). */
class LinkCode extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];
}
