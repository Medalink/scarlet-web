<?php

namespace App\Actions\Upload;

use App\Models\Install;
use App\Models\LinkCode;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ShowInstall
{
    /**
     * What the app polls for: this install, whether an account owns it yet and whether a link code is waiting.
     *
     * @return array<string, mixed>
     */
    public function handle(Install $install): array
    {
        $code = LinkCode::query()->where('install_id', $install->id)->whereNull('used_at')->where('expires_at', '>', now())->orderByDesc('id')->first();

        return [
            'install' => [
                'id' => $install->id,
                'name' => $install->name,
                'created_at' => $this->time($install->created_at),
                'last_seen_at' => $this->time($install->last_seen_at),
            ],
            'linked' => $install->account_id !== null,
            'link_code_expires_at' => $code === null ? null : $this->time($code->expires_at),
            'characters' => DB::table('install_character')->where('install_id', $install->id)->count(),
        ];
    }

    private function time(mixed $time): ?string
    {
        return $time instanceof CarbonInterface ? $time->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }
}
