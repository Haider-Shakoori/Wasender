export type SessionInput = {
  session_uuid: string;
  storage_key: string;
  tenant_uuid?: string;
};

export type DirectSendInput = {
  message_uuid: string;
  session_uuid: string;
  request_id: string;
  recipient: string;
  type: string;
  body?: string | null;
  media?: {
    url: string;
    mime_type: string;
    filename: string;
    size: number;
    checksum_sha256: string;
  } | null;
  expires_at?: string | null;
};

export type CampaignSendInput = {
  tenantUuid: string;
  sessionUuid: string;
  recipientAddress: string;
  type: string;
  body: string | null;
  attachment: {
    retrieval_url: string;
    retrieval_token: string;
    mime_type: string;
    original_name: string;
    size_bytes: number;
    checksum_sha256: string;
  } | null;
  campaignKey: string;
};

export interface SessionRuntime {
  initialize(input: SessionInput): Promise<void>;
  status(uuid: string): Record<string, unknown> | null;
  restart(uuid: string, fallback?: Pick<SessionInput, 'storage_key' | 'tenant_uuid'>): Promise<void>;
  disconnect(uuid: string): Promise<void>;
  logout(uuid: string): Promise<void>;
  remove(input: SessionInput): Promise<void>;
  heartbeat(): Promise<void>;
  health(): Record<string, unknown>;
  send(input: DirectSendInput): Promise<{ accepted: boolean; whatsapp_message_id: string }>;
  onCampaignAcknowledgement(listener: (campaignKey: string, messageId: string, ack: number) => void): void;
  sendCampaign(input: CampaignSendInput): Promise<string>;
  destroyAll(): Promise<void>;
}
