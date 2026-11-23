<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A realm of WoW Forever by the id inside a character's GUID (`Player-<id>-<hex>`), and the ruleset it is. A ruleset
 * is a kind of realm, so a character with no answer of its own takes the kind of its home realm. A row is seeded, or
 * learned once enough installs' characters on it were seen playing the same kind (`LearnRealm`).
 *
 * @property int $id
 * @property string|null $kind
 * @property string|null $name
 * @property string $source
 */
class Realm extends Model
{
    public const string SOURCE_SEED = 'seed';

    public const string SOURCE_ARMORY = 'armory';

    protected $guarded = [];

    public $incrementing = false;

    public function kind(): ?RulesetKind
    {
        return $this->kind === null ? null : RulesetKind::tryFrom($this->kind);
    }
}
