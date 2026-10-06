<?php

namespace App\Services;

use App\Contracts\TenantEntitlements;
use App\Enums\WhatsAppSessionStatus;
use App\Jobs\InitializeWhatsAppSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class WhatsAppSessionService
{
    public function __construct(private TenantEntitlements $entitlements, private AuditService $audit) {}

    public function create(Tenant $tenant, User $actor, string $name): WhatsAppSession
    {
        return DB::transaction(function () use ($tenant, $actor, $name): WhatsAppSession {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $this->entitlements->requireFeature('whatsapp.sessions');
            $this->entitlements->requireCapacity('whatsapp_sessions.max');
            $session = WhatsAppSession::create([
                'tenant_id' => $tenant->id,
                'name' => trim($name),
                'storage_key' => 'wa_'.Str::lower(Str::random(48)),
                'status' => WhatsAppSessionStatus::Creating,
                'created_by' => $actor->id,
            ]);
            $this->audit->recordDomain('whatsapp.session_created', $actor, $tenant, $session, ['session_uuid' => $session->uuid]);
            InitializeWhatsAppSession::dispatch($session->id)->afterCommit();

            return $session;
        }, 3);
    }
}
