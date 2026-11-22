<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\CreatureRecord;
use App\Actions\Public\EncounterRecord;
use App\Actions\Public\ItemLoot;
use App\Actions\Public\ItemRecord;
use App\Actions\Public\ObjectRecord;
use App\Actions\Public\QuestRecord;
use App\Actions\Public\SpellRecord;
use App\Actions\Public\ZoneLoot;
use App\Actions\Public\ZoneRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\RecordRequest;
use App\Support\GameObjects;
use App\Support\Profession;
use App\View\Pages\EncounterPage;
use App\View\Pages\ItemLootModules;
use App\View\Pages\ObjectPage;
use App\View\Pages\RecordPages;
use App\View\Pages\SpellPage;
use App\View\Pages\ZoneLootModule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** The pages of one creature, object, item, spell, boss encounter, zone or quest. Public. */
class RecordController extends Controller
{
    public function creature(RecordRequest $request, CreatureRecord $record, int $id): View
    {
        $answer = $record->handle($id, $request->rulesetSlug(), $request->serverFilter(), $request->lootRule());
        abort_if($answer === null, 404);

        return view('pages.creature', ['d' => RecordPages::creature($answer)]);
    }

    public function object(RecordRequest $request, ObjectRecord $record, int $id): View|RedirectResponse
    {
        if ($id === GameObjects::FISHING_BOBBER) {
            return redirect('/professions/'.Profession::Fishing->slug());
        }
        $answer = $record->handle($id, $request->lootRule());
        abort_if($answer === null, 404);

        return view('pages.object', ['d' => ObjectPage::from($answer)]);
    }

    public function item(RecordRequest $request, ItemRecord $record, ItemLoot $loot, int $id): View
    {
        $answer = $record->handle($id, $request->lootRule());
        abort_if($answer === null, 404);

        return view('pages.item', ['d' => RecordPages::item($answer), 'loot' => ItemLootModules::from($loot->handle($id))]);
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

    public function zone(RecordRequest $request, ZoneRecord $record, ZoneLoot $loot, int $id): View
    {
        $answer = $record->handle($id, $request->rulesetSlug(), $request->serverFilter());
        abort_if($answer === null, 404);

        return view('pages.zone', ['d' => RecordPages::zone($answer), 'loot' => ZoneLootModule::from($id, $answer['name'], $loot->handle($id))]);
    }

    public function quest(RecordRequest $request, QuestRecord $record, int $id): View
    {
        $answer = $record->handle($id, $request->rulesetSlug(), $request->serverFilter());
        abort_if($answer === null, 404);

        return view('pages.quest', ['d' => RecordPages::quest($answer)]);
    }
}
