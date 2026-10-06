<?php

namespace Tests\Feature;

use App\Data\Templates\WhatsAppTemplateRenderContext;
use App\Enums\TemplateVariableContext;
use App\Enums\WhatsAppMessageTemplateStatus;
use App\Enums\WhatsAppMessageTemplateType;
use App\Enums\WhatsAppMessageTemplateVersionStatus;
use App\Models\Tenant;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppMessageTemplateVersion;
use App\Services\Templates\PreviewWhatsAppMessageTemplateService;
use App\Services\Templates\WhatsAppMessageTemplateContentHasher;
use App\Services\Templates\WhatsAppMessageTemplateRenderer;
use App\Services\Templates\WhatsAppTemplatePlaceholderParser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class WhatsAppTemplateRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_placeholder_parses_and_whitespace_is_canonicalized(): void
    {
        $result = app(WhatsAppTemplatePlaceholderParser::class)->parse('Hello {{ first_name }}', null, TemplateVariableContext::Contact);
        $this->assertTrue($result->valid());
        $this->assertSame('Hello {{first_name}}', $result->body);
        $this->assertSame(['first_name'], $result->variables);
    }

    public function test_unknown_and_executable_placeholder_syntax_is_rejected(): void
    {
        $parser = app(WhatsAppTemplatePlaceholderParser::class);
        $unknown = $parser->parse('{{secret_value}}', null, TemplateVariableContext::Contact);
        $expressions = $parser->parse('{{user.name}} {{first_name()}} {{first_name|upper}} {{$first_name}} {{{first_name}}} {!! first_name !!}', null, TemplateVariableContext::Contact);
        $this->assertContains('unknown_variable', array_column($unknown->errors, 'code'));
        $this->assertNotEmpty($expressions->errors);
        $this->assertSame(['unsupported_expression'], array_values(array_unique(array_column($expressions->errors, 'code'))));
    }

    public function test_missing_required_variable_fails_rendering(): void
    {
        $version = $this->version('Hello {{first_name}}', ['first_name' => ['required' => true, 'default' => null]]);
        $this->expectException(ValidationException::class);
        app(WhatsAppMessageTemplateRenderer::class)->render($version, new WhatsAppTemplateRenderContext(TemplateVariableContext::Contact, [], 'UTC', CarbonImmutable::parse('2026-01-15T12:00:00Z')));
    }

    public function test_optional_variable_uses_default_and_leaves_no_placeholder(): void
    {
        $version = $this->version('Welcome to {{company}}', ['company' => ['required' => false, 'default' => 'Example Company']]);
        $payload = app(WhatsAppMessageTemplateRenderer::class)->render($version, new WhatsAppTemplateRenderContext(TemplateVariableContext::Contact, [], 'UTC', CarbonImmutable::parse('2026-01-15T12:00:00Z')));
        $this->assertSame('Welcome to Example Company', $payload->body);
        $this->assertStringNotContainsString('{{', $payload->body);
    }

    public function test_content_hash_is_deterministic_for_canonical_configuration(): void
    {
        $a = $this->version('{{first_name}} {{company}}', ['company' => ['required' => false, 'default' => 'Acme'], 'first_name' => ['required' => true, 'default' => null]]);
        $b = $this->version('{{first_name}} {{company}}', ['first_name' => ['default' => null, 'required' => true], 'company' => ['default' => 'Acme', 'required' => false]]);
        $hasher = app(WhatsAppMessageTemplateContentHasher::class);
        $this->assertSame($hasher->hash($a, WhatsAppMessageTemplateType::Text), $hasher->hash($b, WhatsAppMessageTemplateType::Text));
    }

    public function test_render_hash_is_stable_and_changes_with_resolved_value(): void
    {
        $version = $this->version('Hello {{first_name}}', ['first_name' => ['required' => true, 'default' => null]]);
        $renderer = app(WhatsAppMessageTemplateRenderer::class);
        $now = CarbonImmutable::parse('2026-01-15T12:00:00Z');
        $alex = $renderer->render($version, new WhatsAppTemplateRenderContext(TemplateVariableContext::Contact, ['first_name' => 'Alex'], 'UTC', $now));
        $same = $renderer->render($version, new WhatsAppTemplateRenderContext(TemplateVariableContext::Contact, ['first_name' => 'Alex'], 'UTC', $now));
        $sam = $renderer->render($version, new WhatsAppTemplateRenderContext(TemplateVariableContext::Contact, ['first_name' => 'Sam'], 'UTC', $now));
        $this->assertSame($alex->renderHash, $same->renderHash);
        $this->assertNotSame($alex->renderHash, $sam->renderHash);
    }

    public function test_preview_uses_fictional_values_without_mutating_version(): void
    {
        $version = $this->version('Hello {{first_name}}', ['first_name' => ['required' => true, 'default' => null]]);
        $before = $version->getAttributes();
        $payload = app(PreviewWhatsAppMessageTemplateService::class)->preview($version, [], 'UTC', CarbonImmutable::parse('2026-01-15T12:00:00Z'));
        $this->assertSame('Hello Alex', $payload->body);
        $this->assertSame($before, $version->getAttributes());
    }

    public function test_published_variable_configuration_is_immutable(): void
    {
        $template = WhatsAppMessageTemplate::forceCreate(['tenant_id' => Tenant::factory()->create()->id, 'name' => 'Published', 'type' => 'text', 'status' => WhatsAppMessageTemplateStatus::Published]);
        $version = WhatsAppMessageTemplateVersion::forceCreate(['whatsapp_message_template_id' => $template->id, 'version_number' => 1, 'status' => WhatsAppMessageTemplateVersionStatus::Published, 'body' => 'Hello', 'variable_context' => 'contact', 'variable_configuration' => [], 'parser_version' => 1, 'renderer_version' => 1]);
        $this->expectException(ValidationException::class);
        $version->forceFill(['variable_configuration' => ['first_name' => ['required' => true, 'default' => null]]])->save();
    }

    private function version(string $body, array $configuration): WhatsAppMessageTemplateVersion
    {
        $template = new WhatsAppMessageTemplate;
        $template->forceFill(['uuid' => '10000000-0000-4000-8000-000000000001', 'name' => 'Test', 'type' => WhatsAppMessageTemplateType::Text, 'status' => WhatsAppMessageTemplateStatus::Draft]);
        $version = new WhatsAppMessageTemplateVersion;
        $version->forceFill(['uuid' => '20000000-0000-4000-8000-000000000001', 'version_number' => 1, 'status' => WhatsAppMessageTemplateVersionStatus::Draft, 'body' => $body, 'caption' => null, 'content_configuration' => [], 'variable_context' => TemplateVariableContext::Contact, 'variable_configuration' => $configuration, 'parser_version' => 1, 'renderer_version' => 1]);
        $version->setRelation('template', $template);
        $version->setRelation('attachment', null);

        return $version;
    }
}
