<?php

namespace App\Enums;

enum AutomationActionType: string
{
    case SendWhatsAppMessage = 'send_whatsapp_message';
    case SendWhatsAppTemplate = 'send_whatsapp_template';
    case AddContactLabel = 'add_contact_label';
    case RemoveContactLabel = 'remove_contact_label';
    case AddContactToGroup = 'add_contact_to_group';
    case RemoveContactFromGroup = 'remove_contact_from_group';
    case UpdateContact = 'update_contact';
    case SendWebhook = 'send_webhook';
    case NotifyTenantUser = 'notify_tenant_user';
    case CreateInternalTask = 'create_internal_task';
    case StartWorkflow = 'start_workflow';
    case StopWorkflow = 'stop_workflow';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
