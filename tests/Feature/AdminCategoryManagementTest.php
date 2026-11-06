<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_rename_updates_existing_events_and_in_use_categories_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['system_role' => 'admin'])->save();
        $category = EventCategory::query()->create([
            'name' => 'Community',
            'color' => '#123ABC',
            'icon' => 'sparkles',
            'is_active' => true,
        ]);
        $event = Event::factory()->for($admin, 'creator')->create(['category' => 'Community']);

        $this->actingAs($admin)
            ->patch(route('admin.categories.update', $category), [
                'name' => 'Community Programs',
                'color' => '#123ABC',
                'icon' => 'sparkles',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Category updated.');

        $this->assertSame('Community Programs', $event->fresh()->category);

        $this->delete(route('admin.categories.destroy', $category))
            ->assertRedirect()
            ->assertSessionHas('error', 'This category is used by existing events and cannot be deleted.');

        $this->assertModelExists($category);
    }

    public function test_category_creation_rejects_an_invalid_color(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['system_role' => 'admin'])->save();

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Community',
                'color' => 'red',
                'icon' => 'sparkles',
            ])
            ->assertSessionHasErrors('color');

        $this->assertDatabaseMissing('event_categories', ['name' => 'Community']);
    }
}
