<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The rectangle of a zone map on the stitched world (docs/DESIGN.md, Maps), one row per uiMapID.
 *
 * @property int $ui_map_id
 * @property string $name
 * @property float $u0
 * @property float $v0
 * @property float $u1
 * @property float $v1
 */
class MapRect extends Model
{
    protected $primaryKey = 'ui_map_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
