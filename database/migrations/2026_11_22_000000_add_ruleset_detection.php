<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detecting a character's ruleset (docs/DESIGN.md, Rulesets). The Forever Armory names it for a character it
 * knows (`armory_*`); a realm that has been seen with a ruleset names it for every character whose home realm it
 * is (`realms`, `home_realm_id`); `declared_kind` carries what the owner confirmed for characters recorded before
 * either could be asked. All are forward-only additions: nothing is read until the new code runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table): void {
            $table->string('armory_kind', 16)->nullable();
            $table->string('armory_slug', 32)->nullable();
            $table->timestampTz('armory_checked_at')->nullable();
            $table->timestampTz('armory_next_at')->nullable();
            $table->unsignedSmallInteger('armory_misses')->default(0);
            // A ruleset the site's owner declared for characters recorded before any signal named it (`rulesets:declare`).
            $table->string('declared_kind', 16)->nullable();
            $table->bigInteger('home_realm_id')->nullable();
        });

        // The realm id inside a character's GUID, and the ruleset that realm is. The first two are the realms the
        // owner's characters were first seen on, named by the client while it still reported a realm name.
        Schema::create('realms', function (Blueprint $table): void {
            $table->bigInteger('id')->primary();
            $table->string('kind', 16)->nullable();
            $table->string('name', 64)->nullable();
            $table->string('source', 16);
            $table->timestampsTz();
        });
        DB::table('realms')->insert([
            ['id' => 6794, 'kind' => 'normal', 'name' => 'CAMWOW US1 Realm 1 PvE', 'source' => 'seed', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6931, 'kind' => 'normal', 'name' => 'CAMWOW US2 Realm 5 PvE', 'source' => 'seed', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // A kind is part of a ruleset's identity from now on. The rows the stopgap `rulesets.kind` marked keep their
        // ids, so their sessions follow.
        DB::statement("update rulesets set key = key || '|k' || kind where kind is not null and key not like '%|k%'");
    }
};
