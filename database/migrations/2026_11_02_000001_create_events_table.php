<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('location', 255);
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->enum('status', ['draft', 'published', 'cancelled', 'completed'])->default('draft');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['created_by', 'start_at']);
            $table->index(['status', 'start_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE `events` ADD CONSTRAINT `events_end_after_start_chk` CHECK (`end_at` > `start_at`)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};