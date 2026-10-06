<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_registration_creates_owner_workspace(): void
    {
        Notification::fake();
        $this->post('/register', [
            'name' => 'Ada Lovelace', 'company' => 'Analytical Engines',
            'email' => 'ada@example.test', 'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
            'terms' => '1',
        ])->assertRedirect(route('tenant.onboarding'));
        $this->assertDatabaseHas('tenants', ['name' => 'Analytical Engines']);
        $this->assertDatabaseHas('tenant_user', ['status' => 'active']);
        $this->assertAuthenticated();
        $user = User::where('email', 'ada@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('StrongPass123', $user->password));
        $this->assertNotNull($user->last_active_tenant_id);
        $this->assertSame($user->last_active_tenant_id, session('active_tenant_id'));
        Notification::assertSentTo($user, VerifyEmail::class);
        $tenant = $user->ownedTenants()->firstOrFail();
        $membership = $user->tenantMemberships()->with('role')->firstOrFail();
        $this->assertTrue($tenant->owner->is($user));
        $this->assertSame('owner', $membership->role->slug);
        $this->assertNotNull($membership->joined_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.registered']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.membership_created']);
    }

    public function test_registration_validation_requires_company_terms_and_confirmation(): void
    {
        $this->from('/register')->post('/register', [
            'name' => 'Ada', 'email' => 'ADA@EXAMPLE.TEST',
            'password' => 'StrongPass123', 'password_confirmation' => 'different',
        ])->assertRedirect('/register')->assertSessionHasErrors(['company', 'terms', 'password']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_duplicate_email_is_rejected_after_normalization(): void
    {
        User::factory()->create(['email' => 'ada@example.test']);
        $this->post('/register', [
            'name' => 'Ada', 'company' => 'Acme', 'email' => ' ADA@EXAMPLE.TEST ',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123', 'terms' => '1',
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('tenants', 0);
    }
}
