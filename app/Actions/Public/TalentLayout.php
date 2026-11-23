<?php

namespace App\Actions\Public;

use Illuminate\Support\Facades\DB;

class TalentLayout
{
    /**
     * The layout of a class talent tree: the one the most senders sent, a sender being an account, or an install that no
     * account is linked to (a layout stored before senders were recorded counts as one), then the one the most sessions
     * sent, and among equals the newest. Players of the same client build send the same layout, so a layout that a few
     * senders sent differently is outvoted, and one install cannot outvote the rest by sending more sessions. Null when
     * no session sent one.
     *
     * Nodes are keyed by node id: raw position, type, maximum rank, tab, the entries as [entry, spell, max] and the
     * edges to other nodes as [target, type]. A position is the client's raw number; the talent frame draws it divided by
     * 10, with y downward.
     *
     * @return array{tree_id: int, build: string, hash: string, uploads: int, nodes: array<int, array{id: int, x: int, y: int, type: string|null, max: int|null, tab: int|null, entries: list<array{entry: int, spell: int|null, max: int|null}>, edges: list<array{to: int, type: string}>}>}|null
     */
    public function handle(int $treeId): ?array
    {
        $tree = DB::table('talent_trees as t')->where('t.tree_id', $treeId)
            ->selectRaw('t.build, t.layout_hash, t.uploads, t.created_at, greatest(1, (select count(distinct coalesce(i.account_id::text, i.id::text)) from talent_layout_votes v join installs i on i.id = v.install_id where v.tree_id = t.tree_id and v.build = t.build and v.layout_hash = t.layout_hash)) as senders')
            ->orderByDesc('senders')->orderByDesc('t.uploads')->orderByDesc('t.created_at')
            ->first();
        if ($tree === null) {
            return null;
        }
        $nodes = [];
        $rows = DB::table('talent_nodes')->where('tree_id', $treeId)->where('build', $tree->build)->where('layout_hash', $tree->layout_hash)
            ->orderBy('node_id')->get(['node_id', 'pos_x', 'pos_y', 'node_type', 'max_ranks', 'tab_id', 'entries', 'edges']);
        foreach ($rows as $row) {
            $nodes[(int) $row->node_id] = [
                'id' => (int) $row->node_id,
                'x' => (int) $row->pos_x,
                'y' => (int) $row->pos_y,
                'type' => $row->node_type,
                'max' => $row->max_ranks === null ? null : (int) $row->max_ranks,
                'tab' => $row->tab_id === null ? null : (int) $row->tab_id,
                'entries' => $this->entries((string) $row->entries),
                'edges' => $this->edges((string) $row->edges),
            ];
        }

        return $nodes === [] ? null : ['tree_id' => $treeId, 'build' => (string) $tree->build, 'hash' => (string) $tree->layout_hash, 'uploads' => (int) $tree->uploads, 'nodes' => $nodes];
    }

    /** @return list<array{entry: int, spell: int|null, max: int|null}> */
    private function entries(string $entries): array
    {
        $out = [];
        foreach (explode(',', $entries) as $item) {
            $part = explode(':', $item);
            if ($part[0] === '') {
                continue;
            }
            $out[] = [
                'entry' => (int) $part[0],
                'spell' => isset($part[1]) && $part[1] !== '' ? (int) $part[1] : null,
                'max' => isset($part[2]) && $part[2] !== '' ? (int) $part[2] : null,
            ];
        }

        return $out;
    }

    /** @return list<array{to: int, type: string}> */
    private function edges(string $edges): array
    {
        $out = [];
        foreach (explode(',', $edges) as $item) {
            $part = explode(':', $item);
            if ($part[0] === '') {
                continue;
            }
            $out[] = ['to' => (int) $part[0], 'type' => $part[1] ?? ''];
        }

        return $out;
    }
}
