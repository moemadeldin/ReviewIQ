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
        /** @var list<string> $nameRule */
        $nameRule = ['nullable', 'string', 'max:255'];

        /** @var list<string> $passwordRule */
        $passwordRule = ['nullable', 'confirmed', Password::defaults()];

        return [
            'name' => $nameRule,
            'password' => $passwordRule,
        ];
    }
}
