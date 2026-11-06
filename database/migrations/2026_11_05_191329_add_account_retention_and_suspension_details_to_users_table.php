<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false);
            $table->json('admin_permissions')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });

        $firstAdministratorId = DB::table('users')
            ->where('system_role', 'admin')
            ->orderBy('id')
            ->value('id');

        if ($firstAdministratorId !== null) {
            DB::table('users')->where('id', $firstAdministratorId)->update(['is_super_admin' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['suspended_by']);
            $table->dropColumn([
                'is_super_admin',
                'admin_permissions',
                'last_login_at',
                'suspension_reason',
                'suspended_by',
                'deleted_at',
            ]);
        });
    }
};
