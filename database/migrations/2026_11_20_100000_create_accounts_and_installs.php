<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sign-in comes later; the table exists so installs and characters can point at an owner.
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->timestampsTz();
        });

        Schema::create('installs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->binary('public_key')->unique();
            $table->string('name', 64)->nullable();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();
            $table->index('account_id');
        });

        // A link code is stored as the SHA-256 of the code, so a copy of the table cannot link an install.
        Schema::create('link_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('install_id')->constrained('installs')->cascadeOnDelete();
            $table->binary('code_hash')->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index('install_id');
        });

        // Compressed bytes of /upload/parts requests accepted per install and UTC day.
        Schema::create('upload_usage', function (Blueprint $table): void {
            $table->foreignUuid('install_id')->constrained('installs')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedInteger('requests')->default(0);
            $table->primary(['install_id', 'day']);
        });
    }
};
