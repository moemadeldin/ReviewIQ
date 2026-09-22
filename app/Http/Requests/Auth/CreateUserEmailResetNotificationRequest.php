<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use RyanChandler\LaravelCloudflareTurnstile\Rules\Turnstile;

final class CreateUserEmailResetNotificationRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|Turnstile>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'turnstile_token' => ['required', new Turnstile],
        ];
    }
}
