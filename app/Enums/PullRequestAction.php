<?php

declare(strict_types=1);

namespace App\Enums;

enum PullRequestAction: string
{
    case Opened = 'opened';
    case Synchronize = 'synchronize';
}
