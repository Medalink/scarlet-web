<?php

namespace App\Actions\Feats;

use App\Actions\Public\ResolveNames;
use App\Models\Character;
use App\Support\NameKind;
use Illuminate\Support\Facades\DB;

/** Which sessions a character's feat counts cover and the talent tree it played last: the `through` and `spec` of `GET /installs/me/feats`. */
class ShowFeatBasis
{
    /** The level from which a session names a spec. */
    public const int MIN_SPEC_LEVEL = 10;

    public function __construct(private readonly ResolveNames $names) {}

    /** @return array{through: int, spec: string|null} `through` is the start of the newest counted session, 0 for none; `spec` the tab of the newest counted session of level 10 or more that read talents */
    public function handle(Character $character): array
    {
        $through = (int) DB::table('sessions')->where('character_id', $character->id)->where('counted', true)->max('session_start');
        $tab = DB::table('sessions as s')
            ->join('talent_snapshots as t', fn ($join) => $join->on('t.character_id', '=', 's.character_id')->on('t.session_start', '=', 's.session_start'))
            ->where('s.character_id', $character->id)->where('s.counted', true)->where('t.counted', true)
            ->where('s.level_end', '>=', self::MIN_SPEC_LEVEL)->whereNotNull('t.spec_tab_id')
            ->orderByDesc('s.session_start')->orderByDesc('t.at')->value('t.spec_tab_id');

        return ['through' => $through, 'spec' => $tab === null ? null : ($this->names->handle(NameKind::TalentTab, [(int) $tab])[(int) $tab] ?? null)];
    }
}
