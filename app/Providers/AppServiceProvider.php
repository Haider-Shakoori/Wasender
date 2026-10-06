<?php

namespace App\Providers;

use App\Contracts\ContactAudienceResolver;
use App\Contracts\Messaging\MessagingConnector;
use App\Contracts\PlatformAuthorization;
use App\Contracts\RoleInitializer;
use App\Contracts\TenantAuthorization;
use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Contracts\WhatsAppCampaignTransport;
use App\Contracts\WhatsAppMessageTemplateUsageReference;
use App\Events\InboxConversationCreated;
use App\Events\InboxMessageReceived;
use App\Listeners\QueueIntegrationOutboundWebhook;
use App\Listeners\QueueWhatsAppChatbotProcessing;
use App\Models\AuditLog;
use App\Models\AutomationWorkflow;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppConversationNote;
use App\Models\WhatsAppInboxMessage;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppMessageTemplateCategory;
use App\Models\WhatsAppMessageTemplateLabel;
use App\Models\WhatsAppSavedReply;
use App\Policies\AuditLogPolicy;
use App\Policies\AutomationWorkflowPolicy;
use App\Policies\RolePolicy;
use App\Policies\TenantInvitationPolicy;
use App\Policies\TenantMembershipPolicy;
use App\Policies\TenantPolicy;
use App\Policies\WhatsAppCampaignPolicy;
use App\Policies\WhatsAppConversationNotePolicy;
use App\Policies\WhatsAppConversationPolicy;
use App\Policies\WhatsAppInboxMessagePolicy;
use App\Policies\WhatsAppMessageTemplateCategoryPolicy;
use App\Policies\WhatsAppMessageTemplateLabelPolicy;
use App\Policies\WhatsAppMessageTemplatePolicy;
use App\Policies\WhatsAppSavedReplyPolicy;
use App\Services\Campaigns\NodeWhatsAppCampaignTransport;
use App\Services\ContactAudienceQuery;
use App\Services\PlatformAuthorizationService;
use App\Services\RolePermissionService;
use App\Services\Templates\WhatsAppMessageTemplateUsageService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use App\Services\TenantEntitlementService;
use App\Services\WhatsAppConnectorClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RoleInitializer::class, RolePermissionService::class);
        $this->app->scoped(TenantContext::class, fn () => new TenantContextService);
        $this->app->scoped(TenantAuthorization::class, TenantAuthorizationService::class);
        $this->app->scoped(PlatformAuthorization::class, PlatformAuthorizationService::class);
        $this->app->scoped(TenantEntitlements::class, TenantEntitlementService::class);
        $this->app->bind(MessagingConnector::class, WhatsAppConnectorClient::class);
        $this->app->bind(ContactAudienceResolver::class, ContactAudienceQuery::class);
        $this->app->bind(WhatsAppCampaignTransport::class, NodeWhatsAppCampaignTransport::class);
        $this->app->bind(WhatsAppMessageTemplateUsageReference::class, WhatsAppMessageTemplateUsageService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('integration-messages', fn (Request $request) => Limit::perMinute(config('security.rates.integration_messages'))->by('integration:'.$request->route('integration')));
        RateLimiter::for('integration-events', fn (Request $request) => Limit::perMinute(config('security.rates.integration_events'))->by('integration:'.$request->route('integration')));
        RateLimiter::for('credential-rotation', fn (Request $request) => Limit::perHour(5)->by('user:'.$request->user()?->id));
        RateLimiter::for('inbox-replies', fn (Request $request) => Limit::perMinute(config('security.rates.inbox_replies'))->by('user:'.$request->user()?->id));
        RateLimiter::for('automation-manual', fn (Request $request) => Limit::perMinute(config('security.rates.automation_manual'))->by('user:'.$request->user()?->id));
        RateLimiter::for('chatbot-state', fn (Request $request) => Limit::perMinute(config('security.rates.chatbot_state'))->by('user:'.$request->user()?->id));
        RateLimiter::for('template-preview', fn (Request $request) => Limit::perMinute(config('security.rates.template_preview'))->by('user:'.$request->user()?->id));
        Event::listen(InboxMessageReceived::class, QueueWhatsAppChatbotProcessing::class);
        Event::listen(InboxMessageReceived::class, QueueIntegrationOutboundWebhook::class);
        Event::listen(InboxConversationCreated::class, QueueIntegrationOutboundWebhook::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(TenantMembership::class, TenantMembershipPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(AutomationWorkflow::class, AutomationWorkflowPolicy::class);
        Gate::policy(Invitation::class, TenantInvitationPolicy::class);
        Gate::policy(WhatsAppCampaign::class, WhatsAppCampaignPolicy::class);
        Gate::policy(WhatsAppConversation::class, WhatsAppConversationPolicy::class);
        Gate::policy(WhatsAppInboxMessage::class, WhatsAppInboxMessagePolicy::class);
        Gate::policy(WhatsAppConversationNote::class, WhatsAppConversationNotePolicy::class);
        Gate::policy(WhatsAppSavedReply::class, WhatsAppSavedReplyPolicy::class);
        Gate::policy(WhatsAppMessageTemplate::class, WhatsAppMessageTemplatePolicy::class);
        Gate::policy(WhatsAppMessageTemplateCategory::class, WhatsAppMessageTemplateCategoryPolicy::class);
        Gate::policy(WhatsAppMessageTemplateLabel::class, WhatsAppMessageTemplateLabelPolicy::class);

        foreach ([
            'viewTenant' => 'tenant.view', 'updateTenant' => 'tenant.update',
            'viewTeam' => 'team.view', 'inviteTeamMembers' => 'team.invite',
            'manageTeamMembers' => 'team.update', 'viewRoles' => 'roles.view',
            'manageRoles' => 'roles.manage', 'viewAuditLogs' => 'audit_logs.view',
            'manageTenantSettings' => 'settings.manage',
        ] as $ability => $permission) {
            Gate::define($ability, fn () => app(TenantAuthorization::class)->allows($permission));
        }
    }
}
