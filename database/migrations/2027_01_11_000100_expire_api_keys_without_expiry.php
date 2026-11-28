<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * API keys expire (docs/DESIGN.md, Accounts, API keys). A key made before that has no `expires_at`; it gets 30 days
 * from the moment this runs, so no key lives for ever.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('personal_access_tokens')->whereNull('expires_at')->update(['expires_at' => now()->addDays(30)]);
    }
};
