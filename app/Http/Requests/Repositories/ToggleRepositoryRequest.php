<?php

declare(strict_types=1);

namespace App\Http\Requests\Repositories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;

final class ToggleRepositoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $workspace = $this->attributes->get('current_workspace');

        if (! $workspace instanceof Workspace) {
            return false;
        }

        return $workspace->isOwner($user);
    }

    public function rules(): array
    {
        return [
            'repo_id' => ['required', 'string'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
