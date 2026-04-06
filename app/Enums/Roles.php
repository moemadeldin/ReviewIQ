<?php

declare(strict_types=1);

namespace App\Enums;

enum Roles: string
{
    case Admin = 'admin';
    case Owner = 'owner';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Owner => 'Owner',
            self::Member => 'Member',
        };
    }
}
