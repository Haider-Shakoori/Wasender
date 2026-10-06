<?php

namespace App\Services\Campaigns;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Campaigns\CreateWhatsAppCampaignData;
use App\Data\Campaigns\UpdateWhatsAppCampaignData;
use App\Enums\WhatsAppCampaignAudienceType;
use App\Enums\WhatsAppCampaignEvent;
use App\Enums\WhatsAppCampaignScheduleType;
use App\Enums\WhatsAppCampaignSessionStrategy;
use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContactLabel;
use App\Models\ContactSegment;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignAudienceReference;
use App\Models\WhatsAppCampaignEventRecord;
use App\Models\WhatsAppCampaignSession;
use App\Models\WhatsAppSession;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class WhatsAppCampaignService
{
    public function __construct(private TenantContext $context, private TenantEntitlements $entitlements, private CampaignPayloadHasher $hasher, private WhatsAppCampaignAttachmentService $attachments, private WhatsAppCampaignLifecycleService $lifecycle, private WhatsAppCampaignValidationService $validator, private AuditService $audit) {}

    public function create(CreateWhatsAppCampaignData $d, User $actor, ?UploadedFile $file = null): WhatsAppCampaign
    {
        $this->entitlements->requireFeature('campaigns.manage');
        $this->entitlements->requireCapacity('campaigns.active_max');
        $tenant = $this->context->get();
        $requestHash = $this->hasher->array((array) $d);
        $existing = DB::table('whatsapp_campaign_idempotency')->where(['tenant_id' => $tenant->id, 'operation' => 'create', 'idempotency_key' => $d->idempotencyKey])->first();
        if ($existing) {
            if ($existing->payload_hash !== $requestHash) {
                throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used with different data.']);
            }

            return WhatsAppCampaign::forTenant($tenant)->findOrFail($existing->whatsapp_campaign_id);
        }

        return DB::transaction(function () use ($d, $actor, $file, $tenant, $requestHash) {
            $c = new WhatsAppCampaign;
            $c->forceFill([...$this->attributes($d), 'tenant_id' => $tenant->id, 'status' => WhatsAppCampaignStatus::Draft, 'created_by' => $actor->id, 'updated_by' => $actor->id, 'payload_hash' => str_repeat('0', 64), 'version' => 1])->save();
            $this->syncReferences($c, $d);
            if ($file) {
                $this->attachments->store($c, $file);
            } $c->load('attachment');
            $c->forceFill(['payload_hash' => $this->hasher->for($c)])->save();
            $this->event($c, WhatsAppCampaignEvent::Created, $actor);
            DB::table('whatsapp_campaign_idempotency')->insert(['tenant_id' => $tenant->id, 'operation' => 'create', 'idempotency_key' => $d->idempotencyKey, 'payload_hash' => $requestHash, 'whatsapp_campaign_id' => $c->id, 'created_at' => now(), 'updated_at' => now()]);

            return $c->refresh();
        });
    }

    public function update(WhatsAppCampaign $c, UpdateWhatsAppCampaignData $u, User $actor, ?UploadedFile $file = null): WhatsAppCampaign
    {
        return DB::transaction(function () use ($c, $u, $actor, $file) {
            $c = WhatsAppCampaign::forTenant($this->context->id())->whereKey($c->id)->lockForUpdate()->firstOrFail();
            if (! $c->status->editable()) {
                throw ValidationException::withMessages(['status' => 'This campaign is read-only.']);
            }if ($c->version !== $u->expectedVersion) {
                throw ValidationException::withMessages(['expected_version' => 'This campaign changed in another request. Refresh and try again.']);
            }$c->forceFill([...$this->attributes($u->campaign), 'message_template_id' => null, 'message_template_version_id' => null, 'template_uuid' => null, 'template_version_uuid' => null, 'template_version_number' => null, 'template_content_hash' => null, 'template_variable_values' => null, 'template_content_customized' => true, 'template_rendered_at' => null, 'updated_by' => $actor->id, 'version' => $c->version + 1])->save();
            $this->syncReferences($c, $u->campaign);
            if ($file) {
                $this->attachments->replace($c, $file);
            }$c->load('attachment');
            $c->forceFill(['payload_hash' => $this->hasher->for($c), 'status' => WhatsAppCampaignStatus::Draft])->save();
            $this->event($c, WhatsAppCampaignEvent::Updated, $actor);

            return $c->refresh();
        });
    }

    public function markReady(WhatsAppCampaign $c, User $actor, int $version): array
    {
        $c = $this->lifecycle->transition($c, WhatsAppCampaignStatus::Validating, WhatsAppCampaignEvent::Validated, $actor, $version);
        $result = $this->validator->validate($c->load(['attachment', 'sessionSelections.session']));
        $to = $result->valid() ? WhatsAppCampaignStatus::Ready : WhatsAppCampaignStatus::NeedsAttention;
        $event = $result->valid() ? WhatsAppCampaignEvent::Ready : WhatsAppCampaignEvent::NeedsAttention;
        $c = $this->lifecycle->transition($c, $to, $event, $actor, null, ['errors' => $result->errors, 'warnings' => $result->warnings]);

        return [$c, $result];
    }

    public function duplicate(WhatsAppCampaign $source, User $actor, string $key): WhatsAppCampaign
    {
        $source->load(['attachment', 'sessionSelections', 'audienceReferences']);
        $d = new CreateWhatsAppCampaignData($source->name.' (copy)', $source->description, $source->message_type->value, $source->body, $source->audience_type->value, $source->audience_config ?? [], $source->session_strategy->value, $source->sessionSelections->map(fn ($s) => $s->session->uuid)->all(), 'send_now', null, $source->timezone, $source->send_window_config ?? [], $source->execution_config ?? [], $key);
        $copy = $this->create($d, $actor);
        if ($source->attachment) {
            $disk = $source->attachment->disk;
            $path = Storage::disk($disk)->path($source->attachment->storage_key);
            $uploaded = new UploadedFile($path, $source->attachment->original_name, $source->attachment->mime_type, null, true);
            $this->attachments->store($copy, $uploaded);
            $copy->load('attachment')->forceFill(['payload_hash' => $this->hasher->for($copy), 'metadata' => ['source_campaign_uuid' => $source->uuid]])->save();
        }$this->event($copy, WhatsAppCampaignEvent::Duplicated, $actor);
        if ($source->template_version_uuid) {
            $copy->forceFill(['message_template_id' => $source->message_template_id, 'message_template_version_id' => $source->message_template_version_id, 'template_uuid' => $source->template_uuid, 'template_version_uuid' => $source->template_version_uuid, 'template_version_number' => $source->template_version_number, 'template_content_hash' => $source->template_content_hash, 'template_variable_values' => $source->template_variable_values, 'template_content_customized' => $source->template_content_customized, 'template_rendered_at' => $source->template_rendered_at])->save();
            $copy->load('attachment')->forceFill(['payload_hash' => $this->hasher->for($copy)])->save();
        }

        return $copy->refresh();
    }

    private function attributes(CreateWhatsAppCampaignData $d): array
    {
        $utc = null;
        if ($d->scheduleType === 'scheduled' && $d->scheduledAtLocal && $d->timezone) {
            $utc = CarbonImmutable::parse($d->scheduledAtLocal, $d->timezone)->utc();
            if ($utc->lt(now()->subMinutes(config('whatsapp_campaigns.schedule_grace_minutes'))) || $utc->gt(now()->addDays(config('whatsapp_campaigns.max_schedule_days')))) {
                throw ValidationException::withMessages(['scheduled_at_local' => 'Schedule is outside the allowed range.']);
            }
        }

        return ['name' => $d->name, 'description' => $d->description, 'message_type' => WhatsAppMessageType::from($d->messageType), 'body' => $d->body, 'audience_type' => WhatsAppCampaignAudienceType::from($d->audienceType), 'audience_config' => $d->audienceConfig, 'session_strategy' => WhatsAppCampaignSessionStrategy::from($d->sessionStrategy), 'session_config' => [], 'schedule_type' => WhatsAppCampaignScheduleType::from($d->scheduleType), 'scheduled_at_local' => $d->scheduledAtLocal, 'scheduled_at_utc' => $utc, 'timezone' => $d->timezone ?: config('whatsapp_campaigns.default_timezone'), 'send_window_config' => $d->sendWindow, 'execution_config' => $d->execution];
    }

    private function syncReferences(WhatsAppCampaign $c, CreateWhatsAppCampaignData $d): void
    {
        $c->audienceReferences()->delete();
        $map = ['segment' => ContactSegment::class, 'groups' => ContactGroup::class, 'labels' => ContactLabel::class, 'manual_contacts' => Contact::class];
        foreach (array_values(array_unique($d->audienceConfig)) as $uuid) {
            $model = $map[$d->audienceType] ?? null;
            if (! $model || ! $model::where('tenant_id', $c->tenant_id)->where('uuid', $uuid)->exists()) {
                throw ValidationException::withMessages(['audience_config' => 'An audience reference is invalid for this workspace.']);
            }WhatsAppCampaignAudienceReference::create(['tenant_id' => $c->tenant_id, 'whatsapp_campaign_id' => $c->id, 'reference_type' => $d->audienceType, 'reference_uuid' => $uuid]);
        }$c->sessionSelections()->delete();
        foreach (array_values(array_unique($d->sessionUuids)) as $i => $uuid) {
            $s = WhatsAppSession::forTenant($c->tenant_id)->where('uuid', $uuid)->whereNull('deleted_at')->first();
            if (! $s) {
                throw ValidationException::withMessages(['session_uuids' => 'A session is invalid for this workspace.']);
            }WhatsAppCampaignSession::create(['tenant_id' => $c->tenant_id, 'whatsapp_campaign_id' => $c->id, 'whatsapp_session_id' => $s->id, 'priority' => $i]);
        }
    }

    private function event(WhatsAppCampaign $c, WhatsAppCampaignEvent $event, User $actor): void
    {
        WhatsAppCampaignEventRecord::create(['tenant_id' => $c->tenant_id, 'whatsapp_campaign_id' => $c->id, 'event' => $event->value, 'to_status' => $c->status->value, 'source' => 'user', 'actor_type' => $actor->getMorphClass(), 'actor_id' => $actor->id, 'occurred_at' => now()]);
        $this->audit->recordDomain('whatsapp_campaign.'.str($event->value)->after('.'), $actor, $c->tenant, $c, ['campaign_uuid' => $c->uuid, 'status' => $c->status->value, 'message_type' => $c->message_type->value, 'audience_type' => $c->audience_type->value, 'session_strategy' => $c->session_strategy->value, 'schedule_type' => $c->schedule_type->value, 'scheduled_at' => $c->scheduled_at_utc?->toIso8601String(), 'version' => $c->version]);
    }
}
