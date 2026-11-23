<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tables behind the feats hub (docs/CONTRACTS.md, "Tables and rollups"). `feat_events` gets a row each time the feat refresh sees a
 * character hold a higher tier than the stored count did, so a catalog change writes none. `feat_shares` holds the share of counted
 * characters at each tier or higher, rebuilt daily. `characters.feat_score` is the tiers held over the general feats that are not gold
 * feats; the feat refresh writes it, so it fills as each character is counted again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feat_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('feat', 32);
            $table->unsignedSmallInteger('tier');
            $table->unsignedSmallInteger('from_tier');
            $table->unsignedBigInteger('session_start')->nullable();
            $table->timestampTz('at');
            $table->index(['character_id', 'at']);
            $table->index(['feat', 'tier', 'at']);
        });

        Schema::create('feat_shares', function (Blueprint $table): void {
            $table->string('feat', 32);
            $table->unsignedSmallInteger('tier');
            $table->double('share');
            $table->unsignedInteger('holders');
            $table->unsignedInteger('of');
            $table->timestampTz('updated_at');
            $table->primary(['feat', 'tier']);
        });

        Schema::table('characters', function (Blueprint $table): void {
            $table->unsignedSmallInteger('feat_score')->default(0);
        });
    }
};
