<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One AHledger auction house: a game, ruleset, faction and region, keyed by its API id (`forever.pvp.horde.us`). The
 * import keeps the ETag and scan time of the price table it last stored, so an unchanged table costs a 304.
 *
 * @property string $id
 * @property string $ruleset
 * @property string $faction
 * @property string $region
 * @property string $label
 * @property bool $enabled
 * @property string|null $etag
 * @property Carbon|null $scanned_at
 * @property Carbon|null $polled_at
 * @property string|null $status
 */
class Market extends Model
{
    public const string GAME = 'forever';

    public const string STATUS_STORED = 'stored';

    public const string STATUS_UNCHANGED = 'unchanged';

    public const string STATUS_SAME_SCAN = 'same scan';

    public const string STATUS_UNREADABLE = 'unreadable';

    public const string STATUS_REFUSED = 'refused';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'scanned_at' => 'datetime', 'polled_at' => 'datetime'];
    }

    /** The market as the price table's header names it: `forever/pvp/horde/us`. */
    public function slug(): string
    {
        return str_replace('.', '/', $this->id);
    }
}
