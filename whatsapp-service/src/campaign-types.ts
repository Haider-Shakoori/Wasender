export const uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const hashPattern = /^[0-9a-f]{64}$/;
const directAddressPattern = /^[1-9][0-9]{7,14}@c\.us$/;
const allowedTypes = new Set(['text', 'image', 'document', 'audio', 'video']);

export type CampaignAttachment = {
  uuid: string; retrieval_token: string; mime_type: string; size_bytes: number;
  checksum_sha256: string; original_name: string; retrieval_url: string;
};
export type CampaignDispatch = {
  version: 1; tenant_uuid: string; campaign_uuid: string; execution_uuid: string;
  recipient_execution_uuid: string; dispatch_attempt_uuid: string; session_uuid: string;
  recipient: { phone_normalized: string; whatsapp_address: string };
  message: { type: string; body: string | null; caption: string | null; attachment: CampaignAttachment | null };
  attempt_number: number; campaign_payload_hash: string; transport_request_hash: string;
  idempotency_key: string; requested_at: string; metadata: { source: 'campaign' };
};
export type CampaignFailure = { class: string; code: string; retryable: boolean; message: string };
export type CampaignDispatchResult = {
  success: boolean; request_id: string; idempotency_key: string; dispatch_attempt_uuid: string;
  status: 'accepted' | 'sent' | 'failed' | 'sending' | 'unknown'; transport_reference: string | null;
  whatsapp_message_id: string | null; duplicate: boolean; failure: CampaignFailure | null; server_time: string;
};

function exactKeys(value: Record<string, unknown>, allowed: string[]): void {
  if (Object.keys(value).some((key) => !allowed.includes(key))) throw new Error('invalid_request_schema');
}
function object(value: unknown): Record<string, unknown> {
  if (!value || typeof value !== 'object' || Array.isArray(value)) throw new Error('invalid_request_schema');
  return value as Record<string, unknown>;
}

export function validateCampaignDispatch(input: unknown): CampaignDispatch {
  const root = object(input);
  exactKeys(root, ['version', 'tenant_uuid', 'campaign_uuid', 'execution_uuid', 'recipient_execution_uuid', 'dispatch_attempt_uuid', 'session_uuid', 'recipient', 'message', 'attempt_number', 'campaign_payload_hash', 'transport_request_hash', 'idempotency_key', 'requested_at', 'metadata']);
  if (root.version !== 1) throw new Error('unsupported_protocol_version');
  for (const key of ['tenant_uuid', 'campaign_uuid', 'execution_uuid', 'recipient_execution_uuid', 'dispatch_attempt_uuid', 'session_uuid']) {
    if (typeof root[key] !== 'string' || !uuidPattern.test(root[key] as string)) throw new Error('invalid_request_schema');
  }
  const recipient = object(root.recipient); exactKeys(recipient, ['phone_normalized', 'whatsapp_address']);
  if (typeof recipient.phone_normalized !== 'string' || !/^\+[1-9][0-9]{7,14}$/.test(recipient.phone_normalized) ||
      recipient.whatsapp_address !== `${recipient.phone_normalized.slice(1)}@c.us` || !directAddressPattern.test(String(recipient.whatsapp_address))) {
    throw new Error('unsupported_recipient_address');
  }
  const message = object(root.message); exactKeys(message, ['type', 'body', 'caption', 'attachment']);
  if (typeof message.type !== 'string' || !allowedTypes.has(message.type)) throw new Error('unsupported_message_type');
  if (message.body !== null && (typeof message.body !== 'string' || message.body.length > 65_536)) throw new Error('invalid_request_schema');
  if (message.caption !== null && (typeof message.caption !== 'string' || message.caption.length > 4096)) throw new Error('invalid_request_schema');
  if (message.type === 'text' && (typeof message.body !== 'string' || !message.body.trim()) || message.type !== 'text' && !message.attachment) throw new Error('invalid_request_schema');
  if (message.attachment) {
    const media = object(message.attachment); exactKeys(media, ['uuid', 'retrieval_token', 'mime_type', 'size_bytes', 'checksum_sha256', 'original_name', 'retrieval_url']);
    if (!uuidPattern.test(String(media.uuid)) || typeof media.retrieval_token !== 'string' || media.retrieval_token.length < 32 ||
        typeof media.mime_type !== 'string' || typeof media.size_bytes !== 'number' || media.size_bytes < 1 ||
        !hashPattern.test(String(media.checksum_sha256)) || typeof media.original_name !== 'string' || media.original_name.length > 160 ||
        typeof media.retrieval_url !== 'string') throw new Error('invalid_request_schema');
    const mime = String(media.mime_type);
    const categoryMatches = message.type === 'image' ? mime.startsWith('image/')
      : message.type === 'video' ? mime.startsWith('video/')
        : message.type === 'audio' ? mime.startsWith('audio/')
          : message.type === 'document';
    if (!categoryMatches) throw new Error('attachment_mime_mismatch');
  }
  if (!Number.isInteger(root.attempt_number) || Number(root.attempt_number) < 1 || Number(root.attempt_number) > 100 ||
      !hashPattern.test(String(root.campaign_payload_hash)) || !hashPattern.test(String(root.transport_request_hash)) ||
      typeof root.idempotency_key !== 'string' || root.idempotency_key.length < 32 || root.idempotency_key.length > 160 ||
      typeof root.requested_at !== 'string' || !Number.isFinite(Date.parse(root.requested_at))) throw new Error('invalid_request_schema');
  const metadata = object(root.metadata); exactKeys(metadata, ['source']);
  if (metadata.source !== 'campaign') throw new Error('invalid_request_schema');
  return root as unknown as CampaignDispatch;
}
