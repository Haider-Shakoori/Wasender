<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DuplicateAutomationWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:'.config('automations.max_name_length')]];
    }
}
