<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Public\CreatureRecord;
use App\Actions\Public\EncounterRecord;
use App\Actions\Public\ItemRecord;
use App\Actions\Public\QuestRecord;
use App\Actions\Public\SpellRecord;
use App\Actions\Public\ZoneRecord;
use App\Http\Requests\Api\PublicRequest;
use App\Support\HeatLevels;
use Illuminate\Http\JsonResponse;

/** One creature, item, spell, boss encounter, zone or quest. */
class RecordController extends PublicController
{
    public function creature(PublicRequest $request, CreatureRecord $record, int $id): JsonResponse
    {
        $answer = $record->handle($id, $request->rulesetSlug(), $request->serverFilter());
        if ($answer === null) {
            $this->missing('The creature');
        }
        $heat = array_map(fn (array $row): array => ['i' => (int) $row['cell_i'], 'j' => (int) $row['cell_j'], 'value' => $row['value'] + 0, 'characters' => (int) $row['characters']], $answer['heat']);

        return $this->respond([
            'id' => $answer['id'], 'name' => $answer['name'], 'facts' => $answer['facts'], 'kills' => $answer['kills'],
            'deaths' => $answer['deaths'], 'deaths_by_level' => $answer['deaths_by_level'], 'drops' => $answer['drops'],
            'skinning' => ['windows' => $answer['skin_table']['windows'], 'rows' => $answer['skin_table']['rows']],
            'solo' => $answer['solo'],
            'cells' => array_map(fn (array $cell, array $level): array => [...$cell, 'level' => $level[2]], $heat, HeatLevels::assign($answer['heat'])),
        ], $answer['scope']->describe());
    }

    public function item(PublicRequest $request, ItemRecord $record, int $id): JsonResponse
    {
        $answer = $record->handle($id);
        if ($answer === null) {
            $this->missing('The item');
        }
        unset($answer['item']['tooltip']);

        return $this->respond($answer);
    }

    public function spell(PublicRequest $request, SpellRecord $record, int $id): JsonResponse
    {
        $answer = $record->handle($id);
        if ($answer === null) {
            $this->missing('The spell');
        }

        return $this->respond($answer);
    }

    public function encounter(PublicRequest $request, EncounterRecord $record, int $id): JsonResponse
    {
        $answer = $record->handle($id);
        if ($answer === null) {
            $this->missing('The encounter');
        }

        return $this->respond($answer);
    }

    public function zone(PublicRequest $request, ZoneRecord $record, int $id): JsonResponse
    {
        $answer = $record->handle($id, $request->rulesetSlug(), $request->serverFilter());
        if ($answer === null) {
            $this->missing('The zone');
        }

        return $this->respond([
            'id' => $answer['id'], 'name' => $answer['name'], 'instance_type' => $answer['instance_type'], 'rect' => $answer['rect'],
            'played' => $answer['played'], 'deaths' => $answer['deaths'], 'killers' => $answer['killers'], 'creatures' => $answer['creatures'],
        ], $answer['scope']->describe());
    }

    public function quest(PublicRequest $request, QuestRecord $record, int $id): JsonResponse
    {
        $answer = $record->handle($id, $request->rulesetSlug(), $request->serverFilter());
        if ($answer === null) {
            $this->missing('The quest');
        }

        return $this->respond([
            'id' => $answer['id'], 'name' => $answer['name'], 'totals' => $answer['totals'], 'levels' => $answer['levels'], 'zones' => $answer['zones'],
        ], $answer['scope']->describe());
    }
}
