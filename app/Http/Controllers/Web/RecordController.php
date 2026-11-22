<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\CreatureRecord;
use App\Actions\Public\EncounterRecord;
use App\Actions\Public\ItemRecord;
use App\Actions\Public\QuestRecord;
use App\Actions\Public\SpellRecord;
use App\Actions\Public\ZoneRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\RecordRequest;
use App\View\Pages\EncounterPage;
use App\View\Pages\RecordPages;
use App\View\Pages\SpellPage;
use Illuminate\Contracts\View\View;

/** The pages of one creature, item, spell, boss encounter, zone or quest. Public. */
class RecordController extends Controller
{
    public function creature(RecordRequest $request, CreatureRecord $record, int $id): View
    {
        $answer = $record->handle($id, $request->rulesetSlug(), $request->serverFilter());
        abort_if($answer === null, 404);

        return view('pages.creature', ['d' => RecordPages::creature($answer, $request->lootRule())]);
    }

    public function item(RecordRequest $request, ItemRecord $record, int $id): View
    {
        $answer = $record->handle($id);
        abort_if($answer === null, 404);

        return view('pages.item', ['d' => RecordPages::item($answer)]);
    }

    public function spell(RecordRequest $request, SpellRecord $record, int $id): View
    {
        $answer = $record->handle($id);
        abort_if($answer === null, 404);

        return view('pages.spell', ['d' => SpellPage::from($answer)]);
    }

    public function encounter(RecordRequest $request, EncounterRecord $record, int $id): View
    {
        $answer = $record->handle($id);
        abort_if($answer === null, 404);

        return view('pages.encounter', ['d' => EncounterPage::from($answer)]);
    }

    public function zone(RecordRequest $request, ZoneRecord $record, int $id): View
    {
        $answer = $record->handle($id, $request->rulesetSlug(), $request->serverFilter());
        abort_if($answer === null, 404);

        return view('pages.zone', ['d' => RecordPages::zone($answer)]);
    }

    public function quest(RecordRequest $request, QuestRecord $record, int $id): View
    {
        $answer = $record->handle($id, $request->rulesetSlug(), $request->serverFilter());
        abort_if($answer === null, 404);

        return view('pages.quest', ['d' => RecordPages::quest($answer)]);
    }
}
