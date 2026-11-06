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
        Schema::create('event_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('color', 7)->default('#6c4cf1');
            $table->string('icon', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $categories = [
            'Academic',
            'Competition',
            'Seminar',
            'Workshop',
            'Student Organization',
            'Social and Community',
            'School Ceremony',
            'Sports',
            'Cultural',
            'Technology',
            'Fundraising',
            'Community Service',
            'Organizational',
            'Social',
            'Other',
        ];

        foreach (DB::table('events')->whereNotNull('category')->distinct()->pluck('category') as $category) {
            $categories[] = $category;
        }

        foreach (array_unique($categories) as $category) {
            DB::table('event_categories')->insertOrIgnore([
                'name' => $category,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_categories');
    }
};
