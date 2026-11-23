<?php

namespace App\Actions\Feats;

use App\Support\Feat;
use App\Support\FeatCatalog;
use App\Support\FeatRetired;
use App\Support\FeatRows;
use App\Support\FeatRules;
use App\Support\PlayerClass;
use App\Support\Role;
use App\Support\TalentTree;

/** The feat catalog as the shared JSON of `CONTRACTS.md`: scarlet-web is its one source, and the app and the addon generate code from the file. */
class ExportCatalog
{
    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        return [
            'version' => FeatCatalog::VERSION,
            'tiers' => Feat::TIERS,
            'families' => FeatCatalog::FAMILIES,
            'role_classes' => FeatCatalog::roleClasses(),
            'spec_roles' => FeatCatalog::specRoles(),
            'feats' => array_map($this->feat(...), Feat::cases()),
            'retired' => array_map(
                fn (array $row): array => ['key' => $row[0], 'name' => $row[1], 'into' => $row[2], 'why' => $row[3]],
                FeatRetired::LIST,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function feat(Feat $feat): array
    {
        $row = FeatRows::row($feat);
        $rule = FeatRules::RULES[$feat->value] ?? null;
        $scope = $rule[0] ?? null;
        $out = ['key' => $feat->value, 'name' => $feat->label(), 'family' => $feat->family(), 'collection' => $feat->collection()];
        $out['scope'] = match (true) {
            $scope instanceof PlayerClass => 'class',
            $scope instanceof TalentTree => 'tree',
            $scope instanceof Role => 'role',
            default => 'general',
        };
        if ($scope instanceof PlayerClass) {
            $out['class'] = $scope->name;
        }
        if ($scope instanceof TalentTree) {
            $out['class'] = $scope->class()->name;
            $out['tree'] = $scope->names()[0];
        }
        if ($scope instanceof Role) {
            $out['role'] = $scope->name;
        }
        $out['glyph'] = $row[0];
        if ($feat->engraves() !== null) {
            $out['icon'] = $feat->engraves();
        }
        $out['one'] = $row[1];
        $out['many'] = $row[2];
        if ($feat->unit() !== null) {
            $out['unit'] = $feat->unit();
        }
        $out['thresholds'] = $row[3];
        $out['crest'] = $feat->crest()->value;
        $out['account'] = $feat->account()->value;
        $out['account_thresholds'] = $feat->accountThresholds();
        if ($feat->economy()) {
            $out['economy'] = true;
        }
        if ($rule !== null) {
            $out['measure'] = $rule[1]->name;
            if ($rule[2] !== []) {
                $out['spells'] = $rule[2];
            }
        }
        if ($feat->live() !== null) {
            $out['live'] = $feat->live();
        }

        return $out;
    }
}
