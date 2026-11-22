<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which installs listed a part. A part is stored once by its hash, so two PCs that play one character share
 * the rows they have in common, and a manifest may remove only a part that its own install listed and no other
 * install still lists. Every part stored so far is claimed by each install of its character, which is what the
 * server assumed until now, so no sync removes anything it did not remove before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_claims', function (Blueprint $table): void {
            $table->binary('part_id');
            $table->foreignUuid('install_id')->constrained('installs')->cascadeOnDelete();
            $table->primary(['part_id', 'install_id']);
            $table->foreign('part_id')->references('id')->on('parts')->cascadeOnDelete();
            $table->index('install_id');
        });

        DB::statement(
            'insert into part_claims (part_id, install_id) select parts.id, install_character.install_id from parts join install_character on install_character.character_id = parts.character_id on conflict do nothing',
        );
    }
};
