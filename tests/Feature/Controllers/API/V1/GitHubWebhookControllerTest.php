<?php

declare(strict_types=1);

use App\Contracts\WebhookProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

it('accepts valid github webhook and returns 202', function (): void {
    $mockService = $this->mock(WebhookProvider::class);
    $mockService->shouldReceive('handle')
        ->once()
        ->with(Mockery::type(Request::class));

    $payload = [
        'action' => 'opened',
        'repository' => ['id' => 12345],
        'pull_request' => [
            'id' => 67890,
            'title' => 'Test PR',
            'number' => 1,
            'user' => ['login' => 'testuser'],
            'diff_url' => 'https://github.com/test/repo/pull/1.diff',
            'head' => ['sha' => 'abc123'],
        ],
    ];

    $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), (string) config('services.github.webhook_secret'));

    $response = $this->postJson(route('api.v1.webhooks.github'), $payload, [
        'X-GitHub-Event' => 'pull_request',
        'X-Hub-Signature-256' => $signature,
    ]);

    $response->assertStatus(Response::HTTP_ACCEPTED)
        ->assertJsonPath('status', 'Success')
        ->assertJsonPath('message', 'Event accepted and dispatched to background processing.');
});

it('returns 500 on webhook exception', function (): void {
    $mockService = $this->mock(WebhookProvider::class);
    $mockService->shouldReceive('handle')
        ->once()
        ->andThrow(new Exception('Webhook processing failed'));

    $payload = ['action' => 'opened'];

    $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), (string) config('services.github.webhook_secret'));

    $response = $this->postJson(route('api.v1.webhooks.github'), $payload, [
        'X-GitHub-Event' => 'pull_request',
        'X-Hub-Signature-256' => $signature,
    ]);

    $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->assertJsonPath('status', 'Failed')
        ->assertJsonPath('message', 'Webhook processing failed');
});

it('returns 500 on http exception', function (): void {
    $mockService = $this->mock(WebhookProvider::class);
    $mockService->shouldReceive('handle')
        ->once()
        ->andThrow(new AccessDeniedHttpException('Invalid signature'));

    $payload = ['action' => 'opened'];

    $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), (string) config('services.github.webhook_secret'));

    $response = $this->postJson(route('api.v1.webhooks.github'), $payload, [
        'X-GitHub-Event' => 'pull_request',
        'X-Hub-Signature-256' => $signature,
    ]);

    $response->assertStatus(Response::HTTP_FORBIDDEN)
        ->assertJsonPath('status', 'Failed')
        ->assertJsonPath('message', 'Invalid signature');
});
