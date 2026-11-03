<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->enum('priority', ['low', 'medium', 'high']);
            $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('not_started');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign(['event_id', 'assigned_to'], 'tasks_event_assignee_fk')
                ->references(['event_id', 'user_id'])
                ->on('event_members')
                ->restrictOnDelete();

            $table->index(['event_id', 'status']);
            $table->index(['assigned_to', 'status', 'due_at']);
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};