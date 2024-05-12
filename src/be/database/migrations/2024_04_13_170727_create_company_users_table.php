<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use App\Enums\UserStatus;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_users', function (Blueprint $table) {
            $table->string('fin')->unique()->primary();
            $table->string('name');
            $table->string('surname');
            $table->string('father');
            $table->date('dob');
            $table->string('email')->unique();
            $table->enum('status', Arr::map(UserStatus::cases(), fn($enum) => $enum->value))
                ->default(UserStatus::NEW_REGISTER->value);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_users');
    }
};
