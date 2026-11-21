<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ruleset a row is known to be from outside the recording: the uploaders who played it said so. Set with
 * `rulesets:kind`; null leaves the row to the signals in `RulesetKind::of()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rulesets', function (Blueprint $table): void {
            $table->string('kind', 16)->nullable();
        });
    }
};
