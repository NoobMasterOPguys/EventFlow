<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_report_uses_records_in_the_selected_date_range(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['system_role' => 'admin'])->save();
        $creator = User::factory()->create(['account_status' => 'active']);
        $event = Event::factory()->for($creator, 'creator')->create([
            'category' => 'Academic',
            'status' => 'published',
        ]);
        Task::factory()->for($event)->for($creator, 'creator')->create([
            'status' => 'completed',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertViewHas('report', function (array $report): bool {
                return $report['registrations'] === 2
                    && $report['event_count'] === 1
                    && $report['task_count'] === 1
                    && $report['completion_rate'] === 100
                    && $report['event_categories'] === ['Academic' => 1];
            });
    }

    public function test_report_export_is_a_csv_and_requires_report_permission(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['system_role' => 'admin'])->save();

        $this->actingAs($admin)
            ->get(route('admin.reports.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $limitedAdmin = User::factory()->create();
        $limitedAdmin->forceFill([
            'system_role' => 'admin',
            'admin_permissions' => ['dashboard.view'],
        ])->save();

        $this->actingAs($limitedAdmin)
            ->get(route('admin.reports.export'))
            ->assertForbidden();
    }
}
