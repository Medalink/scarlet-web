<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One sign-in of an account: the provider and the provider's stable subject id, nothing else.
        // No email, name, picture or BattleTag is ever stored.
        Schema::create('identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            $table->string('subject', 128);
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['provider', 'subject']);
            $table->unique(['account_id', 'provider']);
        });

        Schema::table('installs', function (Blueprint $table): void {
            $table->timestampTz('linked_at')->nullable();
        });
    }
};
