<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What other addons counted (docs/DESIGN.md, Addon counts): one row per claim key per character, the parts that carry
 * each claim and the days decoded. Decode reads and writes only these tables, so Scarlet's own rows cannot move. The
 * columns from `status` on are written by reconcile alone and stay null until it runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('origin', 8);
            $table->string('store', 64)->default('');
            $table->char('scope', 1);
            $table->string('window', 8);
            $table->string('kind', 16);
            $table->unsignedBigInteger('from_at')->nullable();
            $table->unsignedBigInteger('to_at');
            $table->jsonb('dims');
            $table->jsonb('amounts');
            $table->unsignedBigInteger('key_from')->storedAs("case when \"window\" = 'life' then coalesce(from_at, 0) else 0 end");
            $table->string('status', 12)->nullable();
            $table->jsonb('counted')->nullable();
            $table->jsonb('evidence')->nullable();
            $table->unsignedInteger('ruleset_id')->nullable();
            $table->boolean('counted_ok')->nullable();
            $table->unsignedSmallInteger('level')->nullable();
            $table->integer('zone_id')->nullable();
            $table->unsignedSmallInteger('reconcile_version')->nullable();
            $table->foreign('ruleset_id')->references('id')->on('rulesets')->nullOnDelete();
            $table->unique(['character_id', 'origin', 'store', 'scope', 'window', 'kind', 'dims', 'to_at', 'key_from'], 'addon_claims_key');
            $table->index(['character_id', 'kind', 'to_at']);
        });

        Schema::create('addon_claim_parts', function (Blueprint $table): void {
            $table->foreignId('claim_id')->constrained('addon_claims')->cascadeOnDelete();
            $table->binary('part_id');
            $table->jsonb('amounts');
            $table->unsignedBigInteger('from_at')->nullable();
            $table->primary(['claim_id', 'part_id']);
            $table->foreign('part_id')->references('id')->on('parts')->cascadeOnDelete();
            $table->index('part_id');
        });

        Schema::create('addon_days', function (Blueprint $table): void {
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('day_key');
            $table->unsignedSmallInteger('decoder_version')->nullable();
            $table->unsignedSmallInteger('reconcile_version')->nullable();
            $table->primary(['character_id', 'day_key']);
            $table->index('decoder_version');
        });
    }
};
