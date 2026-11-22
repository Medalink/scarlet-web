<?php

namespace App\Models;

use App\Support\Database\BinaryValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** @return HasMany<PartClaim, $this> */
    public function claims(): HasMany
    {
        return $this->hasMany(PartClaim::class, 'part_id');
    }

    /**
     * @param  Builder<Part>  $query
     * @return Builder<Part>
     */
    public function scopeClaimedBy(Builder $query, Install $install): Builder
    {
        return $query->whereHas('claims', fn (Builder $claims) => $claims->where('install_id', $install->id));
    }
}
