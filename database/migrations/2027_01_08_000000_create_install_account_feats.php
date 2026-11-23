<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The account tier of each feat that an install's app has shown its owner, so `GET /installs/me/feats` lists an account crest as new
 * until `POST /installs/me/feats/seen` with `account: true` reports it. The account values themselves are computed on read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('install_account_feats', function (Blueprint $table): void {
            $table->foreignUuid('install_id')->constrained('installs')->cascadeOnDelete();
            $table->string('feat', 32);
            $table->unsignedSmallInteger('seen_tier');
            $table->primary(['install_id', 'feat']);
        });
    }
};
