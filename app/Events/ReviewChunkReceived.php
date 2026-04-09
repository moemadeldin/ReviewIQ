<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ReviewChunkReceived implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $prId,
        public readonly string $chunk,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('reviews.'.$this->prId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'prId' => $this->prId,
            'chunk' => $this->chunk,
        ];
    }
}