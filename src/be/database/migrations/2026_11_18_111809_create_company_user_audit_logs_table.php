<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     *
     * Deliberately NO foreign key to company_users.fin: an audit row must
     * survive the deletion of the company_users row it describes (that is
     * the entire point of a change-history log covering hard deletes).
     */
    public function up(): void
    {
        Schema::create('company_user_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('fin');
            $table->enum('action', ['created', 'updated', 'deleted']);
            $table->json('changes');
            $table->string('performed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('fin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_user_audit_logs');
    }
};
