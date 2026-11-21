<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Compressed bytes of /upload/parts requests an install had accepted on one UTC day. */
class UploadUsage extends Model
{
    protected $table = 'upload_usage';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
