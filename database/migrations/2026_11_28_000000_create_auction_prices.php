<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_prices', function (Blueprint $table): void {
            $table->timestampTz('at');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('session_start');
            $table->unsignedInteger('ruleset_id');
            $table->smallInteger('level')->nullable();
            $table->integer('zone_id')->nullable();
            $table->boolean('counted')->default(true);
            $table->string('kind', 1)->nullable();
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedBigInteger('price')->nullable();
            $table->unsignedBigInteger('quantity')->nullable();
            $table->unsignedInteger('listings')->nullable();
            $table->smallInteger('item_level')->nullable();
            $table->integer('suffix')->nullable();
            $table->bigInteger('realm_id')->nullable();
            $table->index(['character_id', 'session_start']);
            $table->index(['character_id', 'at']);
            $table->index(['item_id', 'at']);
        });
        DB::statement('select create_hypertable(?::regclass, by_range(\'at\', ?::interval), create_default_indexes => false)', ['auction_prices', '7 days']);
    }
};
