<?php

namespace App\Http\Requests;

final class UpdateAutomationWorkflowRequest extends StoreAutomationWorkflowRequest
{
    public function rules(): array
    {
        return parent::rules() + ['expected_version' => ['required', 'integer', 'min:1']];
    }
}
