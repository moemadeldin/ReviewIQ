<?php

declare(strict_types=1);

use App\Events\ReviewChunkReceived;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

it('implements ShouldBroadcast', function (): void {
    $event = new ReviewChunkReceived(prId: 'test-id', chunk: 'test-chunk');

    expect($event)->toBeInstanceOf(ShouldBroadcast::class);
});

it('sets properties via constructor', function (): void {
    $event = new ReviewChunkReceived(prId: 'my-id', chunk: 'my-chunk');

    expect($event->prId)->toBe('my-id');
    expect($event->chunk)->toBe('my-chunk');
});

it('broadcasts on private channel with prId', function (): void {
    $event = new ReviewChunkReceived(prId: 'uuid-123', chunk: 'chunk-data');

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-reviews.uuid-123');
});

it('broadcasts with prId and chunk data', function (): void {
    $event = new ReviewChunkReceived(prId: 'abc-456', chunk: 'some-diff');

    expect($event->broadcastWith())->toBe([
        'prId' => 'abc-456',
        'chunk' => 'some-diff',
    ]);
});
