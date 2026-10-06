<?php

namespace App\Services\Campaigns;

final class WhatsAppCampaignFailurePresenter
{
    public function present(?string $code): ?array
    {
        if (! $code) {
            return null;
        }

        return match ($code) {
            'session_disconnected', 'session_unavailable', 'session_reconnecting' => $this->result('Session unavailable', 'The selected WhatsApp session is temporarily unavailable.', 'Check the session connection before retrying.', true),
            'session_restricted' => $this->result('Session restricted', 'The selected session cannot send campaign messages.', 'Review the session status. Do not retry until the restriction is resolved.', false),
            'opted_out_after_preparation', 'contact_opted_out' => $this->result('Recipient opted out', 'The recipient withdrew messaging consent.', 'No action is required; this recipient remains excluded.', false),
            'suppressed_after_preparation', 'contact_suppressed' => $this->result('Recipient suppressed', 'The recipient is on the tenant suppression list.', 'Review suppression only through contact-management controls.', false),
            'consent_changed', 'consent_not_granted' => $this->result('Consent unavailable', 'Current messaging consent could not be confirmed.', 'Obtain valid consent before a future campaign.', false),
            'usage_limit_exceeded', 'reservation_exhausted' => $this->result('Usage limit reached', 'The workspace has insufficient reserved message capacity.', 'Review the subscription or wait for capacity to renew.', false),
            'attachment_download_failed', 'attachment_checksum_mismatch', 'attachment_mime_mismatch' => $this->result('Attachment could not be verified', 'The private attachment failed a security or integrity check.', 'Replace the attachment in a new draft.', false),
            'transport_unavailable', 'connector_unavailable' => $this->result('Connector unavailable', 'The internal WhatsApp connector cannot currently accept dispatches.', 'Restore connector health; eligible attempts may retry automatically.', true),
            'transport_state_unknown', 'dispatch_lookup_failed' => $this->result('Transport result uncertain', 'The previous send outcome is being reconciled and will not be resent yet.', 'Allow reconciliation to finish.', false),
            'attempt_limit_reached' => $this->result('Attempt limit reached', 'Automatic retry attempts are exhausted.', 'Review the failure before requesting a manual retry.', false),
            'campaign_stale', 'payload_hash_mismatch' => $this->result('Campaign configuration changed', 'The prepared snapshot no longer matches the campaign payload.', 'Invalidate and prepare a new snapshot.', false),
            'preparation_failed' => $this->result('Preparation failed', 'The recipient snapshot could not be completed.', 'Review the preparation warning and retry safely.', true),
            default => $this->result('Campaign operation needs attention', 'The operation could not be completed safely.', 'Review session, consent, usage, and campaign state before retrying.', false),
        };
    }

    private function result(string $title, string $description, string $action, bool $retry): array
    {
        return compact('title', 'description', 'action', 'retry');
    }
}
