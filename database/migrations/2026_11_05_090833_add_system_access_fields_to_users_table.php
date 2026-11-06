<?php

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
        Schema::table('users', function (Blueprint $table) {
            $table->string('system_role', 20)->default('user')->index();
            $table->string('account_status', 20)->default('active')->index();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['system_role', 'account_status', 'suspended_at', 'last_activity_at']);
        });
    }
};
