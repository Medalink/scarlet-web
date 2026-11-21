<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\CreatureRecord;
use App\Actions\Public\ItemRecord;
use App\Actions\Public\QuestRecord;
use App\Actions\Public\ZoneRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\RecordRequest;
use App\View\Pages\RecordPages;
use Illuminate\Contracts\View\View;

/** The pages of one creature, item, zone or quest. Public. */
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
