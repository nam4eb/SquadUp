<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_verified_active_admins_can_access_panel(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $regular = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $suspended = User::factory()->create([
            'is_admin' => true, 'account_status' => AccountStatus::Suspended,
        ]);

        $this->assertFalse($regular->canAccessPanel(Filament::getCurrentPanel()));
        $this->assertTrue($admin->canAccessPanel(Filament::getCurrentPanel()));
        $this->assertFalse($suspended->canAccessPanel(Filament::getCurrentPanel()));
    }

    public function test_admin_route_rejects_regular_user_and_renders_for_admin(): void
    {
        $regular = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($regular)->get('/admin')->assertForbidden();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Dashboard');
    }

    public function test_admin_resources_render_and_domain_aggregates_cannot_be_created_directly(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        foreach (['users', 'activity-categories', 'activity-topics', 'activities', 'clans', 'reports', 'report-reasons', 'audit-logs'] as $resource) {
            $this->get("/admin/{$resource}")->assertOk();
        }
        $this->get('/admin/activity-categories/create')->assertOk();
        $this->get('/admin/activity-topics/create')->assertOk();
        $this->get('/admin/activities/create')->assertForbidden();
        $this->get('/admin/clans/create')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
        $this->get('/admin/reports/create')->assertForbidden();
        $this->get('/admin/audit-logs/create')->assertNotFound();
    }

    public function test_audit_logs_are_immutable(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $log = AuditLog::query()->create([
            'actor_id' => $actor->id,
            'action' => 'admin.test',
            'target_type' => User::class,
            'target_id' => $actor->id,
            'metadata' => ['before' => null],
        ]);

        $log->action = 'admin.tampered';

        $this->assertFalse($log->save());
        $this->assertFalse($log->delete());
        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action' => 'admin.test',
        ]);
    }
}
