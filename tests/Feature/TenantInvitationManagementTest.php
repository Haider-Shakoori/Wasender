<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\TenantInvitationStatus;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use App\Services\InvitationTokenService;
use App\Services\RolePermissionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class TenantInvitationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->tenant = Tenant::factory()->for($this->owner, 'owner')->create();
        $ownerRole = app(RolePermissionService::class)->initializeForTenant($this->tenant)->get('owner');
        TenantMembership::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id, 'role_id' => $ownerRole->id,
            'status' => MembershipStatus::Active, 'joined_at' => now(),
        ]);
        $this->owner->update(['last_active_tenant_id' => $this->tenant->id]);
        $this->actingAs($this->owner)->withSession(['active_tenant_id' => $this->tenant->id]);
    }

    public function test_authorized_owner_can_create_normalized_secure_invitation(): void
    {
        Notification::fake();
        $role = $this->tenant->roles()->where('slug', 'administrator')->firstOrFail();
        $this->post(route('tenant.team.invitations.store'), [
            'email' => ' PERSON@Example.Test ', 'role_id' => $role->id,
        ])->assertRedirect()->assertSessionHas('status');
        $invitation = Invitation::sole();
        $this->assertSame('person@example.test', $invitation->email);
        $this->assertSame(TenantInvitationStatus::Pending, $invitation->status);
        $this->assertSame(64, strlen($invitation->getRawOriginal('token_hash')));
        $this->assertTrue($invitation->expires_at->isFuture());
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.member_invited', 'tenant_id' => $this->tenant->id]);
        Notification::assertSentOnDemand(TenantInvitationNotification::class);
    }

    public function test_invitation_validation_blocks_owner_cross_tenant_duplicate_and_existing_members(): void
    {
        $ownerRole = $this->tenant->roles()->where('slug', 'owner')->firstOrFail();
        $foreignOwner = User::factory()->create();
        $foreign = Tenant::factory()->for($foreignOwner, 'owner')->create();
        $foreignRole = app(RolePermissionService::class)->initializeForTenant($foreign)->get('viewer');
        foreach ([$ownerRole->id, $foreignRole->id] as $roleId) {
            $this->post(route('tenant.team.invitations.store'), ['email' => 'new@example.test', 'role_id' => $roleId])
                ->assertSessionHasErrors('role_id');
        }
        $viewer = $this->tenant->roles()->where('slug', 'viewer')->firstOrFail();
        Invitation::factory()->create([
            'tenant_id' => $this->tenant->id, 'role_id' => $viewer->id, 'invited_by' => $this->owner->id,
            'email' => 'duplicate@example.test',
        ]);
        $this->post(route('tenant.team.invitations.store'), ['email' => 'duplicate@example.test', 'role_id' => $viewer->id])
            ->assertSessionHasErrors('email');
        $member = User::factory()->create(['email' => 'member@example.test']);
        TenantMembership::factory()->for($this->tenant)->for($member)->for($viewer)->active()->create();
        $this->post(route('tenant.team.invitations.store'), ['email' => $member->email, 'role_id' => $viewer->id])
            ->assertSessionHasErrors('email');
    }

    public function test_resend_rotates_token_and_revoke_prevents_acceptance(): void
    {
        Notification::fake();
        [$invitation, $plain] = $this->invitation('resend@example.test');
        $oldHash = $invitation->token_hash;
        $this->post(route('tenant.team.invitations.resend', $invitation->uuid))->assertRedirect();
        $invitation->refresh();
        $this->assertNotSame($oldHash, $invitation->token_hash);
        $this->assertSame(2, $invitation->send_count);
        $this->assertFalse(app(InvitationTokenService::class)->matches($plain, $invitation->token_hash));
        $this->delete(route('tenant.team.invitations.revoke', $invitation->uuid))->assertRedirect();
        $this->assertSame(TenantInvitationStatus::Revoked, $invitation->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.invitation_resent']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.invitation_revoked']);
    }

    public function test_existing_matching_user_accepts_and_removed_membership_is_restored(): void
    {
        $user = User::factory()->create(['email' => 'join@example.test', 'email_verified_at' => now()]);
        [$invitation, $plain] = $this->invitation($user->email);
        TenantMembership::factory()->for($this->tenant)->for($user)->for($invitation->role)->removed()->create();
        $this->actingAs($user)->post(route('invitations.accept', $invitation->uuid), ['token' => $plain])->assertRedirect(route('tenant.dashboard'));
        $membership = TenantMembership::where('tenant_id', $this->tenant->id)->where('user_id', $user->id)->sole();
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertSame($invitation->role_id, $membership->role_id);
        $this->assertSame(TenantInvitationStatus::Accepted, $invitation->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.invitation_accepted']);
    }

    public function test_wrong_user_invalid_token_and_expired_invitation_are_rejected(): void
    {
        [$invitation, $plain] = $this->invitation('right@example.test');
        $wrong = User::factory()->create(['email' => 'wrong@example.test']);
        $this->actingAs($wrong)->post(route('invitations.accept', $invitation->uuid), ['token' => $plain])->assertForbidden();
        $right = User::factory()->create(['email' => 'right@example.test']);
        $this->actingAs($right)->post(route('invitations.accept', $invitation->uuid), ['token' => 'wrong'])->assertSessionHasErrors('invitation');
        $invitation->update(['expires_at' => now()->subMinute()]);
        $this->get(route('invitations.show', ['invitationUuid' => $invitation->uuid, 'token' => $plain]))->assertSessionHasErrors('invitation');
        $this->assertSame(TenantInvitationStatus::Expired, $invitation->fresh()->status);
    }

    public function test_new_user_registration_joins_without_creating_tenant_and_requires_terms(): void
    {
        Notification::fake();
        [$invitation, $plain] = $this->invitation('new-user@example.test');
        $tenantCount = Tenant::count();
        auth()->logout();
        $this->post(route('invitations.register.store', $invitation->uuid), [
            'token' => $plain, 'name' => 'New User', 'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ])->assertSessionHasErrors('terms');
        $this->post(route('invitations.register.store', $invitation->uuid), [
            'token' => $plain, 'name' => 'New User', 'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123', 'terms' => '1',
        ])->assertRedirect(route('verification.notice'));
        $user = User::where('email', 'new-user@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($tenantCount, Tenant::count());
        $this->assertDatabaseHas('tenant_user', ['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    }

    public function test_expiration_command_is_selective_and_idempotent(): void
    {
        [$expired] = $this->invitation('expired@example.test');
        $expired->update(['expires_at' => now()->subMinute()]);
        [$valid] = $this->invitation('valid@example.test');
        $this->artisan('tenant-invitations:expire')->expectsOutput('Expired 1 tenant invitation(s).')->assertSuccessful();
        $this->artisan('tenant-invitations:expire')->expectsOutput('Expired 0 tenant invitation(s).')->assertSuccessful();
        $this->assertSame(TenantInvitationStatus::Expired, $expired->fresh()->status);
        $this->assertSame(TenantInvitationStatus::Pending, $valid->fresh()->status);
    }

    /** @return array{Invitation, string} */
    private function invitation(string $email): array
    {
        $token = app(InvitationTokenService::class)->generate();
        $role = $this->tenant->roles()->where('slug', 'viewer')->firstOrFail();
        $invitation = Invitation::factory()->create([
            'tenant_id' => $this->tenant->id, 'role_id' => $role->id, 'invited_by' => $this->owner->id,
            'email' => $email, 'token_hash' => $token->hash,
        ]);

        return [$invitation, $token->plain];
    }
}
