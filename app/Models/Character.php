<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A character (`Name-Surname`, no realm). The first install to upload a name holds it.
 *
 * @property int $id
 * @property string $name
 * @property string $name_key
 * @property string|null $first_install_id
 * @property int|null $account_id
 * @property bool $is_public
 * @property bool $show_economy
 * @property string $plausibility
 */
class Character extends Model
{
    public const string PLAUSIBLE = 'ok';

    public const string FLAGGED = 'flagged';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'show_economy' => 'boolean',
            'plausibility_flags' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /** The key two spellings of one name share. */
    public static function keyFor(string $name): string
    {
        return mb_strtolower($name);
    }

    /**
     * The characters an install's owner uploaded: those this install sent, and those of its account.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOwnedBy(Builder $query, Install $install): Builder
    {
        return $query->where(function (Builder $owned) use ($install): void {
            $owned->whereIn('characters.id', fn ($ids) => $ids->select('character_id')->from('install_character')->where('install_id', $install->id));
            if ($install->account_id !== null) {
                $owned->orWhere('characters.account_id', $install->account_id);
            }
        });
    }

    /**
     * Lock the rows, lowest id first, for the rest of the transaction. This is the lock a decode takes in
     * StoreDecodedSession, so a removal that holds it cannot deadlock with a decode on the fact rows.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeLockedInOrder(Builder $query): Builder
    {
        return $query->orderBy('characters.id')->lockForUpdate();
    }

    /** @return BelongsTo<Install, $this> */
    public function firstInstall(): BelongsTo
    {
        return $this->belongsTo(Install::class, 'first_install_id');
    }

    /** @return HasMany<Part, $this> */
    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }

    /** @return HasMany<PlaySession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(PlaySession::class);
    }
}
