<?php

declare(strict_types=1);

$all = [
    'tenant.view', 'tenant.update', 'team.view', 'team.invite', 'team.update',
    'team.remove', 'roles.view', 'roles.manage', 'api_keys.view', 'api_keys.manage',
    'sessions.view', 'sessions.manage', 'messages.view', 'messages.send',
    'contacts.view', 'contacts.manage', 'campaigns.view', 'campaigns.create', 'campaigns.update', 'campaigns.prepare',
    'campaigns.duplicate', 'campaigns.schedule', 'campaigns.launch', 'campaigns.pause',
    'campaigns.resume', 'campaigns.cancel', 'campaigns.archive', 'campaigns.view_recipients',
    'campaigns.view_events', 'campaigns.view_content', 'campaigns.view_execution', 'campaigns.retry_failed', 'webhooks.view', 'webhooks.manage',
    'billing.view', 'billing.manage', 'audit_logs.view', 'settings.manage',
    'whatsapp_templates.view', 'whatsapp_templates.create', 'whatsapp_templates.update', 'whatsapp_templates.publish',
    'whatsapp_templates.duplicate', 'whatsapp_templates.archive', 'whatsapp_templates.restore', 'whatsapp_templates.view_versions',
    'whatsapp_templates.manage_media', 'whatsapp_templates.manage_categories', 'whatsapp_templates.manage_labels', 'whatsapp_templates.use',
    'automations.view', 'automations.create', 'automations.update', 'automations.publish', 'automations.enable', 'automations.disable', 'automations.duplicate', 'automations.archive', 'automations.restore', 'automations.view_versions', 'automations.execute', 'automations.cancel_execution', 'automations.view_executions',
    'inbox.view', 'inbox.view_conversations', 'inbox.view_messages',
    'inbox.assign', 'inbox.change_status', 'inbox.change_priority', 'inbox.mark_read', 'inbox.add_notes', 'inbox.edit_own_notes', 'inbox.delete_own_notes', 'inbox.mention_users', 'inbox.manage_saved_replies', 'inbox.manage_labels', 'inbox.view_activity',
    'inbox.reply', 'inbox.retry_message',
    'analytics.view', 'analytics.view_usage',
    'chatbots.view', 'chatbots.create', 'chatbots.update', 'chatbots.publish', 'chatbots.enable', 'chatbots.disable', 'chatbots.archive', 'chatbots.manage_rules', 'chatbots.view_executions', 'chatbots.control_conversation',
    'integrations.view', 'integrations.create', 'integrations.update', 'integrations.enable', 'integrations.disable', 'integrations.rotate_credentials', 'integrations.view_events',
];

return [
    'permissions' => $all,
    'groups' => [
        'Workspace' => ['tenant.view', 'tenant.update'],
        'Team' => ['team.view', 'team.invite', 'team.update', 'team.remove'],
        'Roles' => ['roles.view', 'roles.manage'],
        'API Keys' => ['api_keys.view', 'api_keys.manage'],
        'WhatsApp Sessions' => ['sessions.view', 'sessions.manage'],
        'Messages' => ['messages.view', 'messages.send'],
        'Contacts' => ['contacts.view', 'contacts.manage'],
        'Campaigns' => ['campaigns.view', 'campaigns.create', 'campaigns.update', 'campaigns.duplicate', 'campaigns.schedule', 'campaigns.prepare', 'campaigns.launch', 'campaigns.pause', 'campaigns.resume', 'campaigns.cancel', 'campaigns.archive', 'campaigns.view_recipients', 'campaigns.view_events', 'campaigns.view_content', 'campaigns.view_execution', 'campaigns.retry_failed'],
        'Message Templates' => ['whatsapp_templates.view', 'whatsapp_templates.create', 'whatsapp_templates.update', 'whatsapp_templates.publish', 'whatsapp_templates.duplicate', 'whatsapp_templates.archive', 'whatsapp_templates.restore', 'whatsapp_templates.view_versions', 'whatsapp_templates.manage_media', 'whatsapp_templates.manage_categories', 'whatsapp_templates.manage_labels', 'whatsapp_templates.use'],
        'Automations' => ['automations.view', 'automations.create', 'automations.update', 'automations.publish', 'automations.enable', 'automations.disable', 'automations.duplicate', 'automations.archive', 'automations.restore', 'automations.view_versions', 'automations.execute', 'automations.cancel_execution', 'automations.view_executions'],
        'Shared Inbox' => ['inbox.view', 'inbox.view_conversations', 'inbox.view_messages', 'inbox.assign', 'inbox.change_status', 'inbox.change_priority', 'inbox.mark_read', 'inbox.add_notes', 'inbox.edit_own_notes', 'inbox.delete_own_notes', 'inbox.mention_users', 'inbox.manage_saved_replies', 'inbox.manage_labels', 'inbox.view_activity', 'inbox.reply', 'inbox.retry_message'],
        'Analytics' => ['analytics.view', 'analytics.view_usage'],
        'Chatbots' => ['chatbots.view', 'chatbots.create', 'chatbots.update', 'chatbots.publish', 'chatbots.enable', 'chatbots.disable', 'chatbots.archive', 'chatbots.manage_rules', 'chatbots.view_executions', 'chatbots.control_conversation'],
        'Integrations' => ['integrations.view', 'integrations.create', 'integrations.update', 'integrations.enable', 'integrations.disable', 'integrations.rotate_credentials', 'integrations.view_events'],
        'Webhooks' => ['webhooks.view', 'webhooks.manage'],
        'Billing' => ['billing.view', 'billing.manage'],
        'Audit Logs' => ['audit_logs.view'],
        'Settings' => ['settings.manage'],
    ],
    'descriptions' => collect($all)->mapWithKeys(fn (string $slug) => [
        $slug => match ($slug) {
            'roles.view' => 'View roles and assigned permissions.',
            'roles.manage' => 'Create, update, and delete tenant roles.',
            default => 'Use the '.str($slug)->replace(['.', '_'], ' ')->lower().' capability.',
        },
    ])->all(),
    'templates' => [
        'owner' => ['name' => 'Owner', 'permissions' => $all],
        'administrator' => [
            'name' => 'Administrator',
            'permissions' => array_values(array_diff($all, ['billing.manage'])),
        ],
        'developer' => [
            'name' => 'Developer',
            'permissions' => [
                'tenant.view', 'team.view', 'api_keys.view', 'api_keys.manage',
                'sessions.view', 'sessions.manage', 'messages.view', 'messages.send',
                'contacts.view', 'webhooks.view', 'webhooks.manage',
                'whatsapp_templates.view', 'whatsapp_templates.create', 'whatsapp_templates.update', 'whatsapp_templates.publish', 'whatsapp_templates.duplicate', 'whatsapp_templates.archive', 'whatsapp_templates.restore', 'whatsapp_templates.view_versions', 'whatsapp_templates.use',
            ],
        ],
        'billing-manager' => [
            'name' => 'Billing Manager',
            'permissions' => ['tenant.view', 'team.view', 'billing.view', 'billing.manage'],
        ],
        'support-agent' => [
            'name' => 'Support Agent',
            'permissions' => ['tenant.view', 'team.view', 'sessions.view', 'messages.view', 'messages.send', 'contacts.view', 'contacts.manage', 'inbox.view', 'inbox.view_conversations', 'inbox.view_messages', 'inbox.assign', 'inbox.change_status', 'inbox.change_priority', 'inbox.mark_read', 'inbox.add_notes', 'inbox.edit_own_notes', 'inbox.delete_own_notes', 'inbox.mention_users', 'inbox.manage_saved_replies', 'inbox.manage_labels', 'inbox.view_activity', 'inbox.reply', 'inbox.retry_message'],
        ],
        'viewer' => [
            'name' => 'Viewer',
            'permissions' => [
                'tenant.view', 'team.view', 'roles.view', 'api_keys.view',
                'sessions.view', 'messages.view', 'contacts.view', 'webhooks.view',
                'whatsapp_templates.view', 'whatsapp_templates.view_versions',
                'billing.view', 'audit_logs.view',
            ],
        ],
    ],
];
