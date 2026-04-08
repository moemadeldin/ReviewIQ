<?php

declare(strict_types=1);

namespace App\Enums;

enum PullRequestStatus: string
{
    case Pending = 'pending';
    case Reviewing = 'reviewing';
    case Reviewed = 'reviewed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Reviewing => 'Reviewing',
            self::Reviewed => 'Reviewed',
            self::Failed => 'Failed'
        };
    }
}
