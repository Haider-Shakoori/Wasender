<?php

namespace Tests\Feature;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Templates\ArchiveWhatsAppMessageTemplateData;
use App\Data\Templates\CreateDraftFromPublishedTemplateData;
use App\Data\Templates\CreateWhatsAppMessageTemplateData;
use App\Data\Templates\DuplicateWhatsAppMessageTemplateData;
use App\Data\Templates\PublishWhatsAppMessageTemplateData;
use App\Data\Templates\RestoreWhatsAppMessageTemplateData;
use App\Data\Templates\UpdateWhatsAppMessageTemplateDraftData;
use App\Enums\WhatsAppMessageTemplateStatus;
use App\Enums\WhatsAppMessageTemplateVersionStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppMessageTemplate;
use App\Services\Templates\ArchiveWhatsAppMessageTemplateService;
use App\Services\Templates\CreateWhatsAppMessageTemplateDraftVersionService;
use App\Services\Templates\CreateWhatsAppMessageTemplateService;
use App\Services\Templates\DuplicateWhatsAppMessageTemplateService;
use App\Services\Templates\PublishWhatsAppMessageTemplateService;
use App\Services\Templates\RestoreWhatsAppMessageTemplateService;
use App\Services\Templates\UpdateWhatsAppMessageTemplateDraftService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class WhatsAppMessageTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->actor = User::factory()->create();
        app(TenantContext::class)->set($this->tenant);
        $entitlements = $this->mock(TenantEntitlements::class);
        $entitlements->shouldReceive('requireFeature')->with('whatsapp_message_templates')->zeroOrMoreTimes();
        $entitlements->shouldReceive('requireCapacity')->withAnyArgs()->zeroOrMoreTimes();
    }

    public function test_creation_transactionally_creates_version_one_as_active_draft(): void
    {
        $template = $this->create();
        $this->assertSame(WhatsAppMessageTemplateStatus::Draft, $template->status);
        $this->assertNull($template->current_published_version_id);
        $this->assertSame(1, $template->currentDraftVersion->version_number);
        $this->assertSame(WhatsAppMessageTemplateVersionStatus::Draft, $template->currentDraftVersion->status);
    }

    public function test_publishing_is_idempotent_and_published_content_is_immutable(): void
    {
        $template = $this->create();
        $published = app(PublishWhatsAppMessageTemplateService::class)->publish($template, new PublishWhatsAppMessageTemplateData(1), $this->actor);
        $again = app(PublishWhatsAppMessageTemplateService::class)->publish($template->refresh(), new PublishWhatsAppMessageTemplateData(1), $this->actor);
        $this->assertTrue($published->is($again));
        $this->assertDatabaseCount('whatsapp_message_template_versions', 1);
        $this->expectException(ValidationException::class);
        $published->forceFill(['body' => 'rewritten'])->save();
    }

    public function test_draft_from_published_is_copied_once_and_preserves_published_content(): void
    {
        $template = $this->create();
        $published = app(PublishWhatsAppMessageTemplateService::class)->publish($template, new PublishWhatsAppMessageTemplateData(1), $this->actor);
        $service = app(CreateWhatsAppMessageTemplateDraftVersionService::class);
        $draft = $service->create($template->refresh(), new CreateDraftFromPublishedTemplateData(2), $this->actor);
        $same = $service->create($template->refresh(), new CreateDraftFromPublishedTemplateData(2), $this->actor);
        $this->assertTrue($draft->is($same));
        $this->assertSame(2, $draft->version_number);
        $this->assertSame($published->body, $draft->body);
        $this->assertSame(WhatsAppMessageTemplateVersionStatus::Published, $published->refresh()->status);
    }

    public function test_new_publish_supersedes_previous_version_without_rewriting_it(): void
    {
        $template = $this->create();
        $first = app(PublishWhatsAppMessageTemplateService::class)->publish($template, new PublishWhatsAppMessageTemplateData(1), $this->actor);
        app(CreateWhatsAppMessageTemplateDraftVersionService::class)->create($template->refresh(), new CreateDraftFromPublishedTemplateData(2), $this->actor);
        $template = app(UpdateWhatsAppMessageTemplateDraftService::class)->update($template->refresh(), new UpdateWhatsAppMessageTemplateDraftData('Greeting', null, 'text', 'Version two', null, 3), $this->actor);
        $second = app(PublishWhatsAppMessageTemplateService::class)->publish($template, new PublishWhatsAppMessageTemplateData(4), $this->actor);
        $this->assertSame(WhatsAppMessageTemplateVersionStatus::Superseded, $first->refresh()->status);
        $this->assertSame('Hello', $first->body);
        $this->assertSame(2, $second->version_number);
        $this->assertSame($second->id, $template->refresh()->current_published_version_id);
    }

    public function test_archive_preserves_versions_and_blocks_draft_updates(): void
    {
        $template = $this->create();
        app(ArchiveWhatsAppMessageTemplateService::class)->archive($template, new ArchiveWhatsAppMessageTemplateData(1), $this->actor);
        $this->assertDatabaseCount('whatsapp_message_template_versions', 1);
        try {
            app(UpdateWhatsAppMessageTemplateDraftService::class)->update($template->refresh(), new UpdateWhatsAppMessageTemplateDraftData('Blocked', null, 'text', 'No', null, 2), $this->actor);
            $this->fail('Archived template was editable.');
        } catch (ValidationException) {
            $this->assertSame('Hello', $template->currentDraftVersion->body);
        }
        $restored = app(RestoreWhatsAppMessageTemplateService::class)->restore($template->refresh(), new RestoreWhatsAppMessageTemplateData(2), $this->actor);
        $this->assertSame(WhatsAppMessageTemplateStatus::Draft, $restored->status);
        $this->assertSame('Hello', $restored->currentDraftVersion->body);
    }

    public function test_cross_tenant_service_access_and_duplicate_history_are_safe(): void
    {
        $source = $this->create();
        $copy = app(DuplicateWhatsAppMessageTemplateService::class)->duplicate($source, new DuplicateWhatsAppMessageTemplateData('Greeting copy', true), $this->actor);
        $this->assertNotSame($source->uuid, $copy->uuid);
        $this->assertSame(1, $copy->currentDraftVersion->version_number);
        $this->assertNull($copy->current_published_version_id);
        app(TenantContext::class)->set(Tenant::factory()->create());
        $this->expectException(ModelNotFoundException::class);
        app(DuplicateWhatsAppMessageTemplateService::class)->duplicate($source, new DuplicateWhatsAppMessageTemplateData('Forbidden'), $this->actor);
    }

    private function create(): WhatsAppMessageTemplate
    {
        return app(CreateWhatsAppMessageTemplateService::class)->create(new CreateWhatsAppMessageTemplateData('Greeting', null, 'text', 'Hello', null), $this->actor);
    }
}
