<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $id
 * @property-read string $type
 * @property-read string $notifiable_type
 * @property-read string $notifiable_id
 * @property-read array<string, mixed> $data
 * @property-read CarbonInterface|null $read_at
 * @property-read CarbonInterface $created_at
 */
final class Notification extends Model
{
    use HasFactory;
    use HasUuids;

    protected $casts = [
        'type' => 'string',
        'data' => 'array',
        'read_at' => 'datetime',
    ];
}
