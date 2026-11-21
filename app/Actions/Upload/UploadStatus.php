<?php

namespace App\Actions\Upload;

use App\Models\Install;
use App\Models\Part;
use App\Support\GroupRefusals;
use App\Support\UploadQuota;

class UploadStatus
{
    public function __construct(
        private readonly GroupRefusals $refusals,
        private readonly UploadQuota $quota,
    ) {}

    /**
     * What the server holds for the characters this install sent, and what is still waiting to be decoded.
     *
     * @return array<string, mixed>
     */
    public function handle(Install $install): array
    {
        $rows = Part::query()
            ->join('install_character', 'install_character.character_id', '=', 'parts.character_id')
            ->join('characters', 'characters.id', '=', 'parts.character_id')
            ->where('install_character.install_id', $install->id)
            ->groupBy('characters.id', 'characters.name')
            ->orderBy('characters.name')
            ->selectRaw('characters.name as name')
            ->selectRaw('count(*) as parts')
            ->selectRaw('sum(parts.raw_bytes) as raw_bytes')
            ->selectRaw('count(distinct parts.session_start) as sessions')
            ->selectRaw('count(distinct parts.session_start) filter (where parts.decoded_at is null) as sessions_waiting')
            ->selectRaw('max(parts.created_at) as last_part_at')
            ->toBase()
            ->get();

        return [
            'characters' => $rows->map(fn ($row): array => [
                'name' => $row->name,
                'parts' => (int) $row->parts,
                'raw_bytes' => (int) $row->raw_bytes,
                'sessions' => (int) $row->sessions,
                'sessions_waiting' => (int) $row->sessions_waiting,
                'last_part_at' => $row->last_part_at === null ? null : gmdate('Y-m-d\TH:i:s\Z', (int) strtotime((string) $row->last_part_at)),
            ])->all(),
            'usage' => [
                'day' => $this->quota->today(),
                'bytes' => $this->quota->used($install),
                'limit_bytes' => $this->quota->limit(),
            ],
            'groups_refused' => $this->refusals->all($install),
        ];
    }
}
