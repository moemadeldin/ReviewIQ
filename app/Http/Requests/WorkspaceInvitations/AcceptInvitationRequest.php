<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkspaceInvitations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class AcceptInvitationRequest extends FormRequest
{
    /**
     * @return array<string, array<array<string>|string>>
     */
    public function rules(): array
    {

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['sometimes', 'required', 'confirmed', Password::defaults()],
        ];
    }
}
