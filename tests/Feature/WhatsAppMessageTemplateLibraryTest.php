<?php

namespace Tests\Feature;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Templates\AssignWhatsAppMessageTemplateLabelsData;
use App\Data\Templates\CreateDraftFromPublishedTemplateData;
use App\Data\Templates\CreateWhatsAppMessageTemplateData;
use App\Data\Templates\PublishWhatsAppMessageTemplateData;
use App\Data\Templates\StoreWhatsAppMessageTemplateAttachmentData;
use App\Data\Templates\TemplateCategoryData;
use App\Data\Templates\TemplateLabelData;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppMessageTemplate;
use App\Services\Templates\CreateWhatsAppMessageTemplateDraftVersionService;
use App\Services\Templates\CreateWhatsAppMessageTemplateService;
use App\Services\Templates\PublishWhatsAppMessageTemplateService;
use App\Services\Templates\StoreWhatsAppMessageTemplateAttachmentService;
use App\Services\Templates\WhatsAppMessageTemplateQuery;
use App\Services\Templates\WhatsAppMessageTemplateTaxonomyService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class WhatsAppMessageTemplateLibraryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->tenant = Tenant::factory()->create();
        $this->actor = User::factory()->create();
        app(TenantContext::class)->set($this->tenant);
        $entitlements = $this->mock(TenantEntitlements::class);
        $entitlements->shouldReceive('requireFeature')->zeroOrMoreTimes();
        $entitlements->shouldReceive('requireCapacity')->zeroOrMoreTimes();
    }

    public function test_unsafe_or_mismatched_mime_is_rejected(): void
    {
        $template = $this->template('image');
        $this->expectException(ValidationException::class);
        app(StoreWhatsAppMessageTemplateAttachmentService::class)->store($template, new StoreWhatsAppMessageTemplateAttachmentData(UploadedFile::fake()->createWithContent('payload.php', '<?php echo 1;')), $this->actor);
    }

    public function test_private_attachment_stores_detected_metadata_and_checksum(): void
    {
        $template = $this->template('image');
        $file = $this->png('client-name.png');
        $attachment = app(StoreWhatsAppMessageTemplateAttachmentService::class)->store($template, new StoreWhatsAppMessageTemplateAttachmentData($file), $this->actor);
        $this->assertSame('image/png', $attachment->mime_type);
        $this->assertSame(hash('sha256', $this->pngBytes()), $attachment->checksum_sha256);
        $this->assertStringStartsWith("message-templates/{$this->tenant->id}/{$template->uuid}/", $attachment->storage_key);
        $this->assertStringNotContainsString('client-name', $attachment->storage_key);
        Storage::disk('local')->assertExists($attachment->storage_key);
    }

    public function test_draft_replacement_changes_hash_without_mutating_published_media(): void
    {
        $template = $this->template('image');
        $first = app(StoreWhatsAppMessageTemplateAttachmentService::class)->store($template, new StoreWhatsAppMessageTemplateAttachmentData($this->png('first.png')), $this->actor);
        $published = app(PublishWhatsAppMessageTemplateService::class)->publish($template->refresh(), new PublishWhatsAppMessageTemplateData(2), $this->actor);
        $publishedHash = $published->content_hash;
        app(CreateWhatsAppMessageTemplateDraftVersionService::class)->create($template->refresh(), new CreateDraftFromPublishedTemplateData(3), $this->actor);
        $secondBytes = str_replace('A', 'B', $this->pngBytes());
        $second = app(StoreWhatsAppMessageTemplateAttachmentService::class)->store($template->refresh(), new StoreWhatsAppMessageTemplateAttachmentData(UploadedFile::fake()->createWithContent('second.png', $secondBytes), 4), $this->actor);
        $this->assertNotSame($publishedHash, $template->refresh()->currentDraftVersion->content_hash);
        $this->assertSame($first->checksum_sha256, $published->refresh()->attachment->checksum_sha256);
        $this->assertNotSame($first->checksum_sha256, $second->checksum_sha256);
    }

    public function test_published_attachment_cannot_be_replaced_or_deleted_directly(): void
    {
        $template = $this->template('image');
        $attachment = app(StoreWhatsAppMessageTemplateAttachmentService::class)->store($template, new StoreWhatsAppMessageTemplateAttachmentData($this->png('first.png')), $this->actor);
        app(PublishWhatsAppMessageTemplateService::class)->publish($template->refresh(), new PublishWhatsAppMessageTemplateData(2), $this->actor);
        $this->expectException(ValidationException::class);
        $attachment->refresh()->delete();
    }

    public function test_cross_tenant_media_and_label_assignment_are_rejected(): void
    {
        $template = $this->template('image');
        $label = app(WhatsAppMessageTemplateTaxonomyService::class)->saveLabel(null, new TemplateLabelData('Important', 'red'), $this->actor);
        app(TenantContext::class)->set(Tenant::factory()->create());
        try {
            app(WhatsAppMessageTemplateTaxonomyService::class)->assignLabels($template, new AssignWhatsAppMessageTemplateLabelsData([$label->uuid]), $this->actor);
            $this->fail('Cross-tenant label assignment succeeded.');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }
        $this->expectException(ModelNotFoundException::class);
        app(StoreWhatsAppMessageTemplateAttachmentService::class)->store($template, new StoreWhatsAppMessageTemplateAttachmentData($this->png('foreign.png')), $this->actor);
    }

    public function test_category_and_label_filters_are_unique_and_archival_preserves_template(): void
    {
        $service = app(WhatsAppMessageTemplateTaxonomyService::class);
        $template = $this->template('text');
        $category = $service->saveCategory(null, new TemplateCategoryData('Marketing'), $this->actor);
        $label = $service->saveLabel(null, new TemplateLabelData('Featured', 'blue'), $this->actor);
        $service->assignCategory($template, $category->uuid, $this->actor);
        $service->assignLabels($template, new AssignWhatsAppMessageTemplateLabelsData([$label->uuid]), $this->actor);
        $this->assertSame(1, app(WhatsAppMessageTemplateQuery::class)->paginate(['category' => $category->uuid, 'label' => $label->uuid])->total());
        $service->archive($category, $this->actor);
        $service->archive($label, $this->actor);
        $this->assertDatabaseHas('whatsapp_message_templates', ['id' => $template->id]);
        $this->assertTrue($service->restoreCategory($category->uuid, $this->actor)->is_active);
        $this->assertTrue($service->restoreLabel($label->uuid, $this->actor)->is_active);
    }

    public function test_media_template_cannot_publish_without_attachment(): void
    {
        $template = $this->template('video');
        $this->expectException(ValidationException::class);
        app(PublishWhatsAppMessageTemplateService::class)->publish($template, new PublishWhatsAppMessageTemplateData(1), $this->actor);
    }

    private function template(string $type): WhatsAppMessageTemplate
    {
        return app(CreateWhatsAppMessageTemplateService::class)->create(new CreateWhatsAppMessageTemplateData('Library item', null, $type, $type === 'text' ? 'Hello' : null, 'Caption'), $this->actor);
    }

    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->pngBytes());
    }

    private function pngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}
