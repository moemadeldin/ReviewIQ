<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ReviewCompleted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array{summary?: string, score?: int, score_rationale?: string, issues?: array<int, mixed>, highlights?: array<int, mixed>, recommendation?: string}  $review
     */
    public function __construct(
        public readonly string $prId,
        public readonly array $review,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('reviews.'.$this->prId),
        ];
    }

    /**
     * @return array{prId: string, review: array{summary?: string, score?: int, score_rationale?: string, issues?: array<int, mixed>, highlights?: array<int, mixed>, recommendation?: string}}
     */
    public function broadcastWith(): array
    {
        return [
            'prId' => $this->prId,
            'review' => $this->review,
        ];
    }
}
