<?php

namespace Tests\Feature;

use App\Enums\ContactConsentStatus;
use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ContactEligibilityService;
use App\Services\ContactSegmentCompiler;
use App\Services\PhoneNumberNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ContactSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_normalization_and_tenant_uniqueness(): void
    {
        $phone = app(PhoneNumberNormalizer::class)->normalize('+1 (555) 123-4567');
        $this->assertSame('+15551234567', $phone->e164);
        [$a,$b] = [Tenant::factory()->create(), Tenant::factory()->create()];
        $this->contact($a, $phone->e164);
        $this->contact($b, $phone->e164);
        $this->expectException(QueryException::class);
        $this->contact($a, $phone->e164);
    }

    public function test_opt_out_cannot_be_eligible(): void
    {
        $contact = $this->contact(Tenant::factory()->create(), '+15551234567', ['consent_status' => ContactConsentStatus::Granted, 'opted_out_at' => now()]);
        $this->assertFalse(app(ContactEligibilityService::class)->eligible($contact));
    }

    public function test_segment_rejects_raw_fields(): void
    {
        $this->expectException(ValidationException::class);
        app(ContactSegmentCompiler::class)->apply(Contact::query(), ['rules' => [['field' => '1=1; drop table contacts', 'operator' => 'eq', 'value' => 'x']]]);
    }

    public function test_contact_creation_dispatches_no_jobs(): void
    {
        Queue::fake();
        $this->contact(Tenant::factory()->create(), '+15551234567');
        Queue::assertNothingPushed();
    }

    private function contact(Tenant $tenant, string $phone, array $extra = []): Contact
    {
        $user = $tenant->owner ?? User::factory()->create();

        return Contact::create($extra + ['tenant_id' => $tenant->id, 'created_by' => $user->id, 'phone_input' => $phone, 'phone_normalized' => $phone, 'whatsapp_address' => ltrim($phone, '+').'@c.us', 'status' => ContactStatus::Active, 'consent_status' => ContactConsentStatus::Unknown, 'source' => ContactSource::Manual]);
    }
}
