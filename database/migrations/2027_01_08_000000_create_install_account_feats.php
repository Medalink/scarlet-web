<?php

use App\Actions\Feats\ShowInstallAccountFeats;
use App\Models\Install;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The account tier of each feat that an install's app has shown its owner, so `GET /installs/me/feats` lists an account crest as new
 * until `POST /installs/me/feats/seen` with `account: true` reports it. The account values themselves are computed on read. Every
 * install that exists now starts at the tiers its account holds, so the first read after the deploy announces nothing.
 */
return new class extends Migration
{
    private const int CHUNK = 200;

    public function up(): void
    {
        Schema::create('install_account_feats', function (Blueprint $table): void {
            $table->foreignUuid('install_id')->constrained('installs')->cascadeOnDelete();
            $table->string('feat', 32);
            $table->unsignedSmallInteger('seen_tier');
            $table->primary(['install_id', 'feat']);
        });

        $show = app(ShowInstallAccountFeats::class);
        Install::query()->orderBy('id')->chunkById(self::CHUNK, function ($installs) use ($show): void {
            $rows = [];
            foreach ($installs as $install) {
                foreach ($show->handle($install)['cards'] ?? [] as $card) {
                    if ($card['tier'] > 0) {
                        $rows[] = ['install_id' => $install->id, 'feat' => $card['key'], 'seen_tier' => $card['tier']];
                    }
                }
            }
            if ($rows !== []) {
                DB::table('install_account_feats')->insert($rows);
            }
        });
    }
};
