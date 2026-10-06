<?php

namespace App\Http\Requests;

use App\Contracts\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreWhatsAppSavedReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('savedReply')?->id;

        return ['name' => ['required', 'string', 'max:120'], 'shortcut' => ['nullable', 'string', 'max:50', Rule::unique('whatsapp_saved_replies')->where('tenant_id', app(TenantContext::class)->id())->ignore($id)], 'body' => ['required', 'string', 'max:5000']];
    }
}
