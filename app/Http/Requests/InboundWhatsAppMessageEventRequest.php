<?php

namespace App\Http\Requests;

use App\Data\Inbox\InboundWhatsAppMediaData;
use App\Data\Inbox\InboundWhatsAppMessageData;
use App\Data\Inbox\InboundWhatsAppMessageEventData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class InboundWhatsAppMessageEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'uuid'], 'event_type' => ['required', 'in:inbox.message.received'], 'occurred_at' => ['required', 'date'],
            'tenant_uuid' => ['required', 'uuid'], 'session_uuid' => ['required', 'uuid'], 'message' => ['required', 'array'],
            'message.whatsapp_message_id' => ['required', 'string', 'max:191'], 'message.serialized_id' => ['nullable', 'string', 'max:512'],
            'message.from' => ['required', 'regex:/^[1-9][0-9]{7,14}@c\.us$/', 'max:40'], 'message.to' => ['required', 'regex:/^[1-9][0-9]{7,14}@c\.us$/', 'max:40'],
            'message.type' => ['required', 'in:text,image,document,audio,video'], 'message.body' => ['nullable', 'string', 'max:65535'], 'message.caption' => ['nullable', 'string', 'max:65535'],
            'message.reply_to_message_id' => ['nullable', 'string', 'max:191'], 'message.timestamp' => ['required', 'date'], 'message.from_me' => ['required', 'boolean', 'declined'],
            'message.has_media' => ['required', 'boolean'], 'message.media' => ['nullable', 'array:mime_type,size_bytes,checksum_sha256,retrieval_reference'],
            'message.media.mime_type' => ['required_if:message.has_media,true', 'string', 'max:120'], 'message.media.size_bytes' => ['required_if:message.has_media,true', 'integer', 'min:1', 'max:16777216'],
            'message.media.checksum_sha256' => ['required_if:message.has_media,true', 'regex:/^[a-f0-9]{64}$/'], 'message.media.retrieval_reference' => ['required_if:message.has_media,true', 'string', 'max:512'],
        ];
    }

    public function data(): InboundWhatsAppMessageEventData
    {
        $data = $this->validated();
        $message = $data['message'];
        $media = $message['media'] ?? null;

        return new InboundWhatsAppMessageEventData($data['event_id'], $data['tenant_uuid'], $data['session_uuid'], CarbonImmutable::parse($data['occurred_at']), new InboundWhatsAppMessageData(
            $message['whatsapp_message_id'], $message['serialized_id'] ?? null, $message['from'], $message['to'], $message['type'], $message['body'] ?? null, $message['caption'] ?? null,
            $message['reply_to_message_id'] ?? null, CarbonImmutable::parse($message['timestamp']), $media ? new InboundWhatsAppMediaData($media['mime_type'], (int) $media['size_bytes'], $media['checksum_sha256'], $media['retrieval_reference']) : null,
        ), hash('sha256', $this->getContent()));
    }
}
