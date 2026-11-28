<?php

namespace App\Actions\Account;

use App\Models\Account;
use Illuminate\Support\Facades\DB;

class ShowInstallStorage
{
    /**
     * The parts each install of the account lists, from `part_claims`, with their size before compression. A part
     * that two installs list counts for both of them.
     *
     * @return list<array{id: string, name: string, revoked: bool, parts: int, bytes: int}>
     */
    public function handle(Account $account): array
    {
        $installs = $account->installs()->select(['id', 'name', 'revoked_at', 'created_at'])->orderBy('created_at')->get();
        $claims = DB::table('part_claims')
            ->join('parts', 'parts.id', '=', 'part_claims.part_id')
            ->whereIn('part_claims.install_id', $installs->pluck('id')->all())
            ->groupBy('part_claims.install_id')
            ->selectRaw('part_claims.install_id, count(*) as parts, sum(parts.raw_bytes) as bytes')
            ->get()
            ->keyBy('install_id');

        return array_values($installs->map(fn ($install): array => [
            'id' => $install->id,
            'name' => $install->name ?? 'Scarlet app',
            'revoked' => $install->isRevoked(),
            'parts' => (int) ($claims[$install->id]->parts ?? 0),
            'bytes' => (int) ($claims[$install->id]->bytes ?? 0),
        ])->all());
    }
}
