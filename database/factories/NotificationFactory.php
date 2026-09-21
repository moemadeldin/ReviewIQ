<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
final class NotificationFactory extends Factory
{
    /** @var class-string<Notification> */
    protected $model = Notification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => User::factory(),
            'data' => ['message' => 'Test notification'],
        ];
    }
}
