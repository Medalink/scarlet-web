<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A code the app shows so a signed-in person can link the install to their account (/link in the web app).
 *
 * @property int $id
 * @property string $install_id
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 */
class LinkCode extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    /** @return BelongsTo<Install, $this> */
    public function install(): BelongsTo
    {
        return $this->belongsTo(Install::class);
    }
}
