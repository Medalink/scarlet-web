<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * API keys need a Battle.net sign-in and expire (docs/DESIGN.md, Accounts, API keys). A key made before that belongs to
 * an account that may not have one: the keys of an account without Battle.net are deleted, and the other keys with no
 * `expires_at` get 30 days from the moment this runs, so no key lives for ever.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('personal_access_tokens')
            ->where('tokenable_type', (new Account)->getMorphClass())
            ->whereNotIn('tokenable_id', DB::table('identities')->where('provider', 'battlenet')->select('account_id'))
            ->delete();
        DB::table('personal_access_tokens')->whereNull('expires_at')->update(['expires_at' => now()->addDays(30)]);
    }
};
