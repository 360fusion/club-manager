<?php

namespace Tests\Feature;

use App\Models\DefaultEmailTemplate;
use App\Models\SuperAdminAction;
use App\Models\User;
use App\Support\HealthChecks;
use App\Support\SuperAdminLog;
use Database\Seeders\ClubTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use LogicException;
use Tests\TestCase;

class SuperAdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_superadmin_change_is_recorded_with_who_what_and_before_and_after(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true]);
        (new ClubTypeSeeder)->run();
        $template = DefaultEmailTemplate::where('template_key', 'password_reset')->firstOrFail();
        $oldSubject = $template->subject;

        $this->actingAs($superadmin)->get('/superadmin/email-templates')->assertOk();
        $this->assertSame(0, SuperAdminAction::count(), 'looking is not recorded');

        $this->actingAs($superadmin)->put("/superadmin/email-templates/{$template->id}", [
            'subject' => 'NEW SUBJECT {{member_name}}',
            'body_html' => '<p>Body {{reset_url}}</p>',
        ])->assertRedirect();

        $action = SuperAdminAction::with('actor')->sole();
        $this->assertSame($superadmin->id, $action->actor_id);
        $this->assertSame('superadmin.email_templates.update', $action->action);
        $this->assertSame('NEW SUBJECT {{member_name}}', $action->input['subject']);
        $this->assertSame($oldSubject, $action->changes[0]['before']['subject']);
        $this->assertSame('NEW SUBJECT {{member_name}}', $action->changes[0]['after']['subject']);
        $this->assertSame('DefaultEmailTemplate', $action->changes[0]['model']);
    }

    public function test_secrets_are_never_written_to_the_log_and_rows_cannot_be_changed(): void
    {
        $clean = SuperAdminLog::clean(['name' => 'Ada', 'password' => 'hunter2', 'stripe_secret_key' => 'sk_live_x', 'config' => ['api_token' => 'abc'], 'updated_at' => 'now']);

        $this->assertSame(['name' => 'Ada', 'password' => '(changed)', 'stripe_secret_key' => '(changed)', 'config' => '(changed)'], $clean);

        $row = SuperAdminAction::create(['action' => 'x', 'method' => 'POST', 'path' => 'superadmin/x', 'status' => 200]);
        $this->expectException(LogicException::class);
        $row->update(['action' => 'edited']);
    }

    public function test_only_a_superadmin_can_read_the_log(): void
    {
        SuperAdminAction::create(['action' => 'superadmin.lodges.update', 'method' => 'PUT', 'path' => 'superadmin/lodges/1', 'status' => 302]);

        $this->actingAs(User::factory()->create())->get('/superadmin/audit-log')->assertRedirect();

        $this->actingAs(User::factory()->create(['is_super_admin' => true]))->get('/superadmin/audit-log')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('SuperAdmin/AuditLog/Index')->has('actions.data', 1));
    }

    public function test_the_doctor_command_warns_off_production_and_fails_on_production_or_strict(): void
    {
        $this->artisan('app:doctor')->assertSuccessful();
        $this->artisan('app:doctor --strict')->assertFailed();

        $checks = collect(HealthChecks::run())->keyBy('name');
        $this->assertFalse($checks['Debug off']['ok'] && config('app.debug'));
        $this->assertFalse($checks['Mail driver']['ok'], 'the test mailer is array, which sends nothing');
    }
}
