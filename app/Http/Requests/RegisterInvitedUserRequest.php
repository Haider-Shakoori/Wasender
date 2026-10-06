<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterInvitedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
            'terms' => ['accepted'],
            'token' => ['required', 'string', 'max:255'],
            'email' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'role_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
