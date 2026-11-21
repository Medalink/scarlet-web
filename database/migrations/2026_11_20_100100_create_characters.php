<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `name_key` is the lower-case name: two spellings of one name are one character (WoW Forever has no realms).
        Schema::create('characters', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64);
            $table->string('name_key', 64)->unique();
            $table->foreignUuid('first_install_id')->nullable()->constrained('installs')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('class', 32)->nullable();
            $table->string('race', 32)->nullable();
            $table->string('faction', 16)->nullable();
            $table->unsignedSmallInteger('level')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('show_economy')->default(false);
            $table->string('plausibility', 16)->default('ok');
            $table->jsonb('plausibility_flags')->default('[]');
            $table->unsignedInteger('sessions_decoded')->default(0);
            $table->unsignedBigInteger('played_seconds')->default(0);
            $table->timestampTz('first_seen_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();
            $table->index('account_id');
            $table->index('first_install_id');
        });

        Schema::create('install_character', function (Blueprint $table): void {
            $table->foreignUuid('install_id')->constrained('installs')->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['install_id', 'character_id']);
            $table->index('character_id');
        });

        // A group the owner removed from the server. The owner is an account, or the install of an unlinked one.
        Schema::create('refused_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('install_id')->nullable()->constrained('installs')->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('group_key', 16);
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement('create unique index refused_groups_install_unique on refused_groups (install_id, group_key) where install_id is not null');
        DB::statement('create unique index refused_groups_account_unique on refused_groups (account_id, group_key) where account_id is not null');
        DB::statement('alter table refused_groups add constraint refused_groups_one_owner check ((install_id is null) <> (account_id is null))');
    }
};
