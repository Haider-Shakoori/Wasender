<?php

namespace App\Jobs;

use App\Contracts\TenantContext;
use App\Data\Campaigns\AudienceCursor;
use App\Data\Templates\WhatsAppTemplateRenderContext;
use App\Enums\WhatsAppCampaignExclusionReason;
use App\Enums\WhatsAppCampaignPreparationStatus;
use App\Enums\WhatsAppCampaignRecipientStatus;
use App\Models\Contact;
use App\Models\WhatsAppCampaignPreparation;
use App\Services\Campaigns\Audience\CampaignAudienceResolverRegistry;
use App\Services\Campaigns\CampaignRecipientEligibilityService;
use App\Services\Campaigns\FailWhatsAppCampaignPreparationService;
use App\Services\Campaigns\FinalizeWhatsAppCampaignPreparationService;
use App\Services\Templates\WhatsAppMessageTemplateRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ProcessWhatsAppCampaignPreparationChunk implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public function __construct(public int $preparationId)
    {
        $this->tries = config('whatsapp_campaigns.preparation_max_retries');
        $this->onQueue('campaign-preparation');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("wa-campaign-preparation:{$this->preparationId}"))->expireAfter(300)];
    }

    public function handle(TenantContext $context, CampaignAudienceResolverRegistry $resolvers, CampaignRecipientEligibilityService $eligibility, FinalizeWhatsAppCampaignPreparationService $finalize, FailWhatsAppCampaignPreparationService $fail, WhatsAppMessageTemplateRenderer $renderer): void
    {
        $preparation = WhatsAppCampaignPreparation::with(['campaign.tenant', 'campaign.messageTemplateVersion.attachment', 'campaign.messageTemplateVersion.template'])->findOrFail($this->preparationId);
        $context->set($preparation->campaign->tenant);
        if (! in_array($preparation->status, [WhatsAppCampaignPreparationStatus::Pending, WhatsAppCampaignPreparationStatus::Running], true)) {
            return;
        }
        try {
            if ($preparation->status === WhatsAppCampaignPreparationStatus::Pending) {
                $preparation->forceFill(['status' => WhatsAppCampaignPreparationStatus::Running, 'started_at' => now()])->save();
            }
            $after = (int) ($preparation->cursor_state['after_contact_id'] ?? 0);
            $chunk = [];
            foreach ($resolvers->for($preparation->campaign->audience_type)->candidates($preparation->campaign->tenant, $preparation->campaign, new AudienceCursor($after)) as $candidate) {
                $chunk[] = $candidate;
                if (count($chunk) >= config('whatsapp_campaigns.preparation_chunk_size')) {
                    break;
                }
            }
            if ($chunk === []) {
                $finalize->finalize($preparation);

                return;
            }

            $eligibleRows = [];
            $excludedRows = [];
            $duplicates = 0;
            $invalid = 0;
            foreach ($chunk as $candidate) {
                $contact = Contact::withTrashed()->where('tenant_id', $preparation->tenant_id)->whereKey($candidate->contactKey)->first();
                if (! $contact) {
                    $excludedRows[] = $this->exclusion($preparation, null, $candidate->contactUuid, WhatsAppCampaignExclusionReason::ContactMissing, $candidate->sourceType, $candidate->sourceReference);

                    continue;
                }
                $dedupe = hash('sha256', $preparation->tenant_id.'|'.$contact->phone_normalized);
                $decision = $eligibility->evaluate($contact, $preparation->campaign);
                if (! $decision->eligible) {
                    if ($decision->primaryReason === WhatsAppCampaignExclusionReason::InvalidPhone->value) {
                        $invalid++;
                    }
                    $excludedRows[] = $this->exclusion($preparation, $contact->id, $contact->uuid, WhatsAppCampaignExclusionReason::from($decision->primaryReason), $candidate->sourceType, $candidate->sourceReference, $dedupe, $decision->reasonCodes);

                    continue;
                }
                if (DB::table('whatsapp_campaign_recipients')->where('whatsapp_campaign_id', $preparation->whatsapp_campaign_id)->where('deduplication_key', $dedupe)->exists()) {
                    $duplicates++;
                    $excludedRows[] = $this->exclusion($preparation, $contact->id, $contact->uuid, WhatsAppCampaignExclusionReason::DuplicatePhone, $candidate->sourceType, $candidate->sourceReference, $dedupe);

                    continue;
                }
                $rendered = null;
                if ($version = $preparation->campaign->messageTemplateVersion) {
                    try {
                        $values = array_merge([
                            'first_name' => $contact->first_name, 'last_name' => $contact->last_name,
                            'full_name' => $contact->display_name, 'company' => $contact->company,
                            'phone' => $contact->phone_normalized, 'email' => $contact->email,
                        ], $preparation->campaign->template_variable_values ?? []);
                        $values = array_filter($values, fn ($value) => $value !== null);
                        $rendered = $renderer->render($version, new WhatsAppTemplateRenderContext($version->variable_context, $values, data_get($contact->custom_attributes, 'timezone', $preparation->campaign->timezone ?? 'UTC')));
                    } catch (Throwable) {
                        $excludedRows[] = $this->exclusion($preparation, $contact->id, $contact->uuid, WhatsAppCampaignExclusionReason::TemplateRenderFailed, $candidate->sourceType, $candidate->sourceReference, $dedupe);

                        continue;
                    }
                }
                $eligibleRows[] = ['uuid' => (string) Str::uuid(), 'tenant_id' => $preparation->tenant_id, 'whatsapp_campaign_id' => $preparation->whatsapp_campaign_id, 'preparation_id' => $preparation->id, 'contact_id' => $contact->id, 'contact_uuid' => $contact->uuid, 'phone_normalized' => $contact->phone_normalized, 'whatsapp_address' => $contact->whatsapp_address, 'display_name' => $contact->display_name, 'preferred_language' => $contact->preferred_language, 'timezone' => data_get($contact->custom_attributes, 'timezone'), 'consent_status' => $contact->consent_status->value, 'recipient_status' => WhatsAppCampaignRecipientStatus::Prepared->value, 'source_type' => $candidate->sourceType, 'source_reference' => $candidate->sourceReference, 'deduplication_key' => $dedupe, 'snapshot_data' => json_encode(array_filter(['first_name' => $contact->first_name, 'last_name' => $contact->last_name, 'company' => $contact->company]), JSON_THROW_ON_ERROR), 'rendered_body' => $rendered?->body, 'rendered_caption' => $rendered?->caption, 'template_render_hash' => $rendered?->renderHash, 'campaign_version' => $preparation->campaign_version, 'campaign_payload_hash' => $preparation->campaign_payload_hash, 'prepared_at' => now(), 'created_at' => now(), 'updated_at' => now()];
            }
            DB::transaction(function () use ($preparation, $chunk, $eligibleRows, $excludedRows, $duplicates, $invalid): void {
                if ($eligibleRows) {
                    DB::table('whatsapp_campaign_recipients')->insertOrIgnore($eligibleRows);
                }
                if ($excludedRows) {
                    DB::table('whatsapp_campaign_exclusions')->insert($excludedRows);
                }
                $processed = count($chunk);
                $last = (int) end($chunk)->contactKey;
                WhatsAppCampaignPreparation::whereKey($preparation->id)->update([
                    'processed_candidates' => DB::raw('processed_candidates + '.(int) $processed),
                    'eligible_count' => DB::raw('eligible_count + '.count($eligibleRows)),
                    'excluded_count' => DB::raw('excluded_count + '.count($excludedRows)),
                    'duplicate_count' => DB::raw('duplicate_count + '.(int) $duplicates),
                    'invalid_count' => DB::raw('invalid_count + '.(int) $invalid),
                ]);
                $preparation->refresh()->forceFill(['cursor_state' => ['after_contact_id' => $last], 'progress_percentage' => $preparation->total_candidates > 0 ? min(100, round($preparation->processed_candidates / $preparation->total_candidates * 100, 2)) : 0])->save();
                $preparation->campaign->forceFill(['progress_percentage' => $preparation->progress_percentage, 'last_progress_at' => now()])->save();
            });
            self::dispatch($preparation->id);
        } catch (Throwable $e) {
            if ($this->attempts() >= $this->tries) {
                $fail->fail($preparation, 'internal_error', 'Recipient preparation could not be completed safely.');
            }
            throw $e;
        }
    }

    private function exclusion(WhatsAppCampaignPreparation $p, ?int $contactId, ?string $contactUuid, WhatsAppCampaignExclusionReason $reason, string $source, ?string $reference, ?string $dedupe = null, array $reasons = []): array
    {
        return ['uuid' => (string) Str::uuid(), 'tenant_id' => $p->tenant_id, 'whatsapp_campaign_id' => $p->whatsapp_campaign_id, 'preparation_id' => $p->id, 'contact_id' => $contactId, 'contact_uuid' => $contactUuid, 'deduplication_key' => $dedupe, 'reason_code' => $reason->value, 'reason_codes' => json_encode($reasons ?: [$reason->value], JSON_THROW_ON_ERROR), 'source_type' => $source, 'source_reference' => $reference, 'safe_summary' => str($reason->value)->replace('_', ' ')->headline(), 'created_at' => now()];
    }
}
