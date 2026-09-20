<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\WebhookException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class VerifyGitHubWebhookSignature
{
    public function handle(Request $request, Closure $next): mixed
    {
        $signature = $request->header('X-Hub-Signature-256');
        /** @var string|null $secret */
        $secret = config('services.github.webhook_secret');

        throw_unless($signature, WebhookException::class, 'Missing signature.');

        throw_unless($secret, WebhookException::class, 'Webhook secret not configured.');

        /** @var string $body */
        $body = $request->getContent();
        $computed = 'sha256='.hash_hmac('sha256', $body, $secret);

        throw_unless(hash_equals($computed, $signature), AccessDeniedHttpException::class, 'Invalid signature.');

        return $next($request);
    }
}
