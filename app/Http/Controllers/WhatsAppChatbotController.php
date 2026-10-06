<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Enums\WhatsAppChatbotActionType;
use App\Enums\WhatsAppChatbotMatchType;
use App\Models\WhatsAppChatbot;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppSession;
use App\Services\Automations\AutomationConditionDefinitionValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class WhatsAppChatbotController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $query = WhatsAppChatbot::forTenant($context->id())->with('sessions:id,uuid,name')->withCount('rules')->withMax('executions', 'processed_at');
        $query->when($request->string('search')->trim()->toString(), fn ($q, $v) => $q->where('name', 'like', '%'.addcslashes($v, '%_').'%'))->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))->when($request->filled('enabled'), fn ($q) => $q->where('is_enabled', $request->boolean('enabled')))->when($request->input('session_uuid'), fn ($q, $v) => $q->whereHas('sessions', fn ($s) => $s->where('uuid', $v)));

        return view('tenant.chatbots.index', ['chatbots' => $query->latest('updated_at')->paginate(15)->withQueryString(), 'sessions' => WhatsAppSession::forTenant($context->id())->orderBy('name')->get(['uuid', 'name'])]);
    }

    public function create(TenantContext $context): View
    {
        return $this->form(new WhatsAppChatbot(['status' => 'draft', 'is_enabled' => false, 'fallback_behavior' => 'none']), $context);
    }

    public function store(Request $request, TenantContext $context, AutomationConditionDefinitionValidator $conditions): RedirectResponse
    {
        $bot = new WhatsAppChatbot;
        $this->save($request, $context, $bot, $conditions);

        return redirect()->route('tenant.chatbots.show', $bot)->with('status', 'Chatbot created.');
    }

    public function show(WhatsAppChatbot $chatbot, TenantContext $context): View
    {
        $this->owned($chatbot, $context);
        $chatbot->load(['sessions:id,uuid,name', 'rules', 'executions' => fn ($q) => $q->with(['rule:id,uuid,name', 'conversation:id,uuid'])->latest('processed_at')->limit(50)]);

        return view('tenant.chatbots.show', compact('chatbot'));
    }

    public function edit(WhatsAppChatbot $chatbot, TenantContext $context): View
    {
        $this->owned($chatbot, $context);
        $chatbot->load(['sessions', 'rules' => fn ($q) => $q->orderBy('priority')->orderBy('id')]);

        return $this->form($chatbot, $context);
    }

    public function update(Request $request, WhatsAppChatbot $chatbot, TenantContext $context, AutomationConditionDefinitionValidator $conditions): RedirectResponse
    {
        $this->owned($chatbot, $context);
        $this->save($request, $context, $chatbot, $conditions);

        return redirect()->route('tenant.chatbots.show', $chatbot)->with('status', 'Chatbot updated as draft.');
    }

    public function publish(WhatsAppChatbot $chatbot, TenantContext $context): RedirectResponse
    {
        $this->owned($chatbot, $context);
        abort_if($chatbot->rules()->where('is_enabled', true)->doesntExist() || $chatbot->sessions()->doesntExist(), 422, 'An enabled rule and assigned session are required.');
        $chatbot->update(['status' => 'published', 'published_at' => now(), 'archived_at' => null, 'updated_by' => auth()->id()]);

        return back()->with('status', 'Chatbot published.');
    }

    public function toggle(WhatsAppChatbot $chatbot, TenantContext $context): RedirectResponse
    {
        $this->owned($chatbot, $context);
        abort_if(! $chatbot->is_enabled && $chatbot->status->value !== 'published', 422, 'Publish this chatbot before enabling it.');
        if (! $chatbot->is_enabled) {
            $conflict = DB::table('whatsapp_chatbot_session_assignments as a')->join('whatsapp_chatbots as b', 'b.id', '=', 'a.chatbot_id')->whereIn('a.whatsapp_session_id', $chatbot->sessions()->pluck('whatsapp_sessions.id'))->where('b.is_enabled', true)->where('b.id', '!=', $chatbot->id)->exists();
            abort_if($conflict, 422, 'An assigned session already has an enabled chatbot.');
        }
        $chatbot->update(['is_enabled' => ! $chatbot->is_enabled, 'updated_by' => auth()->id()]);

        return back()->with('status', $chatbot->is_enabled ? 'Chatbot enabled.' : 'Chatbot disabled.');
    }

    public function archive(WhatsAppChatbot $chatbot, TenantContext $context): RedirectResponse
    {
        $this->owned($chatbot, $context);
        $chatbot->update(['status' => 'archived', 'is_enabled' => false, 'archived_at' => now(), 'updated_by' => auth()->id()]);

        return back()->with('status', 'Chatbot archived.');
    }

    public function restore(WhatsAppChatbot $chatbot, TenantContext $context): RedirectResponse
    {
        $this->owned($chatbot, $context);
        $chatbot->update(['status' => 'draft', 'is_enabled' => false, 'archived_at' => null, 'published_at' => null, 'updated_by' => auth()->id()]);

        return back()->with('status', 'Chatbot restored as draft.');
    }

    private function save(Request $request, TenantContext $context, WhatsAppChatbot $bot, AutomationConditionDefinitionValidator $conditions): void
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'description' => 'nullable|string|max:1000', 'session_uuid' => 'required|uuid', 'priority' => 'nullable|integer|min:1|max:10000', 'fallback_behavior' => ['required', Rule::in(['none', 'reply_text', 'reply_template', 'handoff'])], 'fallback_text' => 'nullable|string|max:4096', 'fallback_template_uuid' => 'nullable|uuid', 'rules' => 'required|array|min:1|max:'.config('chatbots.max_rules'), 'rules.*.name' => 'required|string|max:120', 'rules.*.priority' => 'required|integer|min:1|max:10000', 'rules.*.is_enabled' => 'nullable|boolean', 'rules.*.match_type' => ['required', Rule::enum(WhatsAppChatbotMatchType::class)], 'rules.*.match_value' => 'nullable|string|max:500', 'rules.*.condition_field' => 'nullable|string|max:100', 'rules.*.condition_operator' => 'nullable|string|max:40', 'rules.*.condition_value' => 'nullable|string|max:500', 'rules.*.action_type' => ['required', Rule::enum(WhatsAppChatbotActionType::class)], 'rules.*.reply_text' => 'nullable|string|max:4096', 'rules.*.template_uuid' => 'nullable|uuid', 'rules.*.stop_processing' => 'nullable|boolean']);
        $session = WhatsAppSession::forTenant($context->id())->where('uuid', $data['session_uuid'])->firstOrFail();
        $conflict = DB::table('whatsapp_chatbot_session_assignments')->where('whatsapp_session_id', $session->id)->when($bot->exists, fn ($q) => $q->where('chatbot_id', '!=', $bot->id))->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['session_uuid' => 'This session is already assigned to another chatbot.']);
        }
        DB::transaction(function () use ($data, $bot, $context, $session, $conditions): void {
            $fallback = $this->actionConfiguration($data['fallback_behavior'] === 'reply_text' ? 'reply_text' : ($data['fallback_behavior'] === 'reply_template' ? 'reply_template' : null), ['reply_text' => $data['fallback_text'] ?? null, 'template_uuid' => $data['fallback_template_uuid'] ?? null], $context);
            $bot->fill(['tenant_id' => $context->id(), 'name' => $data['name'], 'description' => $data['description'] ?? null, 'priority' => $data['priority'] ?? 100, 'status' => 'draft', 'is_enabled' => false, 'fallback_behavior' => $data['fallback_behavior'], 'fallback_configuration' => $fallback, 'published_at' => null, 'created_by' => $bot->created_by ?? auth()->id(), 'updated_by' => auth()->id()])->save();
            $bot->sessions()->sync([$session->id => ['tenant_id' => $context->id(), 'uuid' => (string) Str::uuid()]]);
            $bot->rules()->delete();
            foreach ($data['rules'] as $item) {
                $match = $item['match_type'];
                if (in_array($match, ['exact', 'contains', 'starts_with'], true) && blank($item['match_value'] ?? null)) {
                    throw ValidationException::withMessages(['rules' => 'Matching text is required.']);
                }
                $condition = null;
                if ($match === 'condition') {
                    $operator = $item['condition_operator'] ?? '';
                    $condition = ['type' => 'group', 'version' => config('automations.condition_schema_version'), 'logic' => 'and', 'children' => [['type' => 'rule', 'field' => $item['condition_field'] ?? '', 'operator' => $operator, 'value' => in_array($operator, ['is_empty', 'is_not_empty', 'is_true', 'is_false'], true) ? null : ($item['condition_value'] ?? null)]]];
                    if ($errors = $conditions->validate($condition, 'contact_created')) {
                        throw ValidationException::withMessages(['rules' => collect($errors)->pluck('message')->all()]);
                    }
                }
                $configuration = $this->actionConfiguration($item['action_type'], $item, $context);
                $bot->rules()->create(['tenant_id' => $context->id(), 'name' => $item['name'], 'priority' => $item['priority'], 'is_enabled' => (bool) ($item['is_enabled'] ?? false), 'match_type' => $match, 'match_value' => $item['match_value'] ?? null, 'condition_definition' => $condition, 'action_type' => $item['action_type'], 'action_configuration' => $configuration, 'stop_processing' => (bool) ($item['stop_processing'] ?? false)]);
            }
        });
    }

    private function actionConfiguration(?string $type, array $data, TenantContext $context): ?array
    {
        if (! $type) {
            return null;
        }
        if ($type === 'reply_text') {
            if (blank($data['reply_text'] ?? null)) {
                throw ValidationException::withMessages(['reply_text' => 'Reply text is required.']);
            }

            return ['text' => trim($data['reply_text'])];
        }
        if ($type === 'reply_template') {
            $template = WhatsAppMessageTemplate::forTenant($context->id())->where('uuid', $data['template_uuid'] ?? '')->where('status', 'published')->with('currentPublishedVersion')->first();
            if (! $template?->currentPublishedVersion) {
                throw ValidationException::withMessages(['template_uuid' => 'Select a published template.']);
            }

            return ['template_uuid' => $template->uuid, 'template_version_uuid' => $template->currentPublishedVersion->uuid];
        }

        return [];
    }

    private function form(WhatsAppChatbot $bot, TenantContext $context): View
    {
        return view('tenant.chatbots.form', ['chatbot' => $bot, 'sessions' => WhatsAppSession::forTenant($context->id())->where('status', 'ready')->orderBy('name')->get(['uuid', 'name']), 'templates' => WhatsAppMessageTemplate::forTenant($context->id())->where('status', 'published')->whereNotNull('current_published_version_id')->orderBy('name')->get(['uuid', 'name'])]);
    }

    private function owned(WhatsAppChatbot $bot, TenantContext $context): void
    {
        abort_unless($bot->tenant_id === $context->id(), 404);
    }
}
