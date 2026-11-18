<?php

// Laravel Sanctum's standard token-storage migration (laravel/sanctum
// v4.5.0, vendor/laravel/sanctum/database/migrations/2019_12_14_000001_
// create_personal_access_tokens_table.php), copied in rather than
// published at build time so the schema is explicit and reviewable in this
// repo, consistent with every other migration here. Content is unmodified
// from the package's own stub; only the filename/timestamp is this repo's.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
