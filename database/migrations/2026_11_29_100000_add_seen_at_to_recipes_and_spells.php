<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the uploader's client read the line a reference row came from (Medalink/scarlet-web#18). A decode of an older
 * session must not replace the row of a newer one, so an upsert keeps the row with the later `seen_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['recipes', 'spells'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->timestampTz('seen_at')->nullable();
            });
        }
    }
};
