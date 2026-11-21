<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per combination of the signals of DESIGN.md "Rulesets"; `key` is those signals as text,
        // so a missing signal is a value and the unique index needs no NULLS NOT DISTINCT.
        Schema::create('rulesets', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 48)->unique();
            $table->smallInteger('preset')->nullable();
            $table->boolean('hardcore')->nullable();
            $table->string('war_mode', 8);
            $table->boolean('xp_off')->nullable();
            $table->integer('game_mode')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        // The part text is the truth. `body` is the text compressed alone by scarlet-part (zstd 19), kept
        // out of line and never recompressed by PostgreSQL.
        Schema::create('parts', function (Blueprint $table): void {
            $table->binary('id')->primary();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('session_start');
            $table->string('group_key', 16);
            $table->unsignedInteger('lines');
            $table->unsignedInteger('raw_bytes');
            $table->binary('body');
            $table->timestampTz('decoded_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['character_id', 'session_start']);
            $table->index(['character_id', 'group_key']);
        });
        DB::statement('alter table parts alter column body set storage external');

        Schema::create('sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('session_start');
            $table->unsignedInteger('ruleset_id');
            $table->foreign('ruleset_id')->references('id')->on('rulesets');
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->unsignedBigInteger('played_ms');
            $table->unsignedInteger('lines');
            $table->boolean('has_start');
            $table->boolean('ended');
            $table->jsonb('groups');
            $table->string('class', 32)->nullable();
            $table->string('race', 32)->nullable();
            $table->string('faction', 16)->nullable();
            $table->smallInteger('level_start')->nullable();
            $table->smallInteger('level_end')->nullable();
            $table->unsignedBigInteger('xp_total')->nullable();
            $table->unsignedInteger('kills')->nullable();
            $table->unsignedInteger('deaths')->nullable();
            $table->unsignedInteger('quests_done')->nullable();
            $table->unsignedBigInteger('copper_earned')->nullable();
            $table->unsignedBigInteger('copper_spent')->nullable();
            $table->bigInteger('money_start')->nullable();
            $table->bigInteger('money_end')->nullable();
            $table->unsignedSmallInteger('zones')->default(0);
            $table->unsignedSmallInteger('dungeon_runs')->nullable();
            $table->unsignedInteger('tp_total_s')->nullable();
            $table->jsonb('flags')->default('[]');
            // False for a session with a plausibility flag: its rows stay out of the ruleset rollups.
            $table->boolean('counted')->default(true);
            // Everything else of the decoder's `sessions` row (build, addon version, xp bar, ids of CD).
            $table->jsonb('extra');
            // Names of this session were already counted as votes (a second decode adds none).
            $table->boolean('names_counted')->default(false);
            $table->timestampTz('decoded_at');
            $table->timestampsTz();
            $table->unique(['character_id', 'session_start']);
            $table->index('ruleset_id');
        });

        // The shown name of an id is the one most sessions reported (DESIGN.md "Decoded facts").
        Schema::create('names', function (Blueprint $table): void {
            $table->string('kind', 16);
            $table->integer('id');
            $table->string('name', 255);
            $table->unsignedInteger('votes')->default(1);
            $table->primary(['kind', 'id', 'name']);
        });

        Schema::create('items', function (Blueprint $table): void {
            $table->integer('item_id')->primary();
            $table->integer('class_id')->nullable();
            $table->integer('subclass_id')->nullable();
            $table->string('equip_loc', 32)->nullable();
            $table->integer('item_level')->nullable();
            $table->integer('required_level')->nullable();
            $table->bigInteger('sell_price')->nullable();
            $table->integer('bind_type')->nullable();
            $table->integer('stack')->nullable();
            $table->integer('quality')->nullable();
            $table->text('tooltip')->nullable();
            $table->timestampTz('updated_at')->useCurrent();
        });

        // The id is the decoder's zone id: a uiMapID, or the negative instanceMapID of an instance.
        Schema::create('zones', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('name', 128);
            $table->string('instance_type', 16)->nullable();
            $table->integer('instance_id')->nullable();
            $table->timestampTz('updated_at')->useCurrent();
        });

        // The clear name behind a `~` pseudonym, from `pn` lines of a social part. Only the owner reads it.
        Schema::create('pseudonyms', function (Blueprint $table): void {
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('pseudonym', 16);
            $table->string('name', 64);
            $table->timestampTz('updated_at')->useCurrent();
            $table->primary(['character_id', 'pseudonym']);
        });
    }
};
