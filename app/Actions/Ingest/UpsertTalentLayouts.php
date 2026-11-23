<?php

namespace App\Actions\Ingest;

use App\Support\DecodedSession;
use Illuminate\Support\Facades\DB;

class UpsertTalentLayouts
{
    private const int CHUNK = 2000;

    /**
     * Write the talent tree layouts a session carried. The decoder keys a layout by the hash of the `TD` fields it
     * was learned from, so a stored hash's nodes are the ones that hash was computed over and a later session with
     * the same layout lands on the same rows; a hash that is stored already adds one upload to its count.
     *
     * @param  bool  $countUpload  false when the session's layouts were counted by an earlier decode
     */
    public function handle(DecodedSession $decoded, bool $countUpload): void
    {
        $trees = [];
        foreach ($decoded->talentTrees as $row) {
            $trees[] = [
                'tree_id' => $row['tree_id'],
                'build' => $row['build'] ?? '',
                'layout_hash' => $row['layout_hash'],
                'class_id' => $row['class_id'],
                'node_count' => $row['node_count'],
                'uploads' => 1,
            ];
        }
        foreach (array_chunk($trees, self::CHUNK) as $chunk) {
            DB::table('talent_trees')->upsert($chunk, ['tree_id', 'build', 'layout_hash'], [
                'uploads' => DB::raw($countUpload ? 'talent_trees.uploads + 1' : 'talent_trees.uploads'),
            ]);
        }

        $nodes = [];
        foreach ($decoded->talentNodes as $row) {
            $nodes[] = [
                'tree_id' => $row['tree_id'],
                'build' => $row['build'] ?? '',
                'layout_hash' => $row['layout_hash'],
                'node_id' => $row['node_id'],
                'pos_x' => $row['pos_x'],
                'pos_y' => $row['pos_y'],
                'node_type' => $row['node_type'],
                'max_ranks' => $row['max_ranks'],
                'tab_id' => $row['tab_id'],
                'entries' => $row['entries'],
                'edges' => $row['edges'],
            ];
        }
        foreach (array_chunk($nodes, self::CHUNK) as $chunk) {
            DB::table('talent_nodes')->insertOrIgnore($chunk);
        }
    }
}
