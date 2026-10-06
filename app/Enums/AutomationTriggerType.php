<?php

namespace App\Enums;

enum AutomationTriggerType: string
{
    case Manual = 'manual';
    case ContactCreated = 'contact_created';
    case ContactUpdated = 'contact_updated';
    case ContactAddedToGroup = 'contact_added_to_group';
    case ContactLabelAssigned = 'contact_label_assigned';
    case ConsentGranted = 'consent_granted';
    case ConsentWithdrawn = 'consent_withdrawn';
    case IncomingWhatsAppMessage = 'incoming_whatsapp_message';
    case MessageDelivered = 'message_delivered';
    case MessageRead = 'message_read';
    case MessageFailed = 'message_failed';
    case CampaignCompleted = 'campaign_completed';
    case ScheduledDatetime = 'scheduled_datetime';
    case ContactBirthday = 'contact_birthday';
    case ContactCustomDate = 'contact_custom_date';
    case WebhookReceived = 'webhook_received';
    case ApiEvent = 'api_event';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
