<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auction prices imported from AHledger's public API (docs/DESIGN.md, Imported market prices): one `markets` row for
 * each of its auction houses, and one `market_price_days` row for each market, item and UTC day, folded from every
 * scan of that day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markets', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('ruleset', 16);
            $table->string('faction', 16);
            $table->string('region', 8);
            $table->string('label', 64);
            $table->boolean('enabled')->default(false);
            $table->string('etag', 128)->nullable();
            $table->timestampTz('scanned_at')->nullable();
            $table->timestampTz('polled_at')->nullable();
            $table->string('status', 32)->nullable();
        });

        Schema::create('market_price_days', function (Blueprint $table): void {
            $table->date('day');
            $table->string('market_id', 64);
            $table->unsignedInteger('item_id');
            $table->timestampTz('scanned_at');
            $table->unsignedBigInteger('low');
            $table->unsignedBigInteger('min_buyout');
            $table->unsignedBigInteger('median');
            $table->unsignedBigInteger('median_7d');
            $table->unsignedBigInteger('median_30d');
            $table->unsignedBigInteger('quantity');
            $table->unsignedBigInteger('sold_x10')->nullable();
            $table->unique(['market_id', 'item_id', 'day']);
            $table->index(['item_id', 'day']);
        });
        DB::statement('select create_hypertable(?::regclass, by_range(\'day\', ?::interval), create_default_indexes => false)', ['market_price_days', '30 days']);
    }
};
