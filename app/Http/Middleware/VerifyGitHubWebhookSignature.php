<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\WebhookException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class VerifyGitHubWebhookSignature
{
    public function handle(Request $request, Closure $next): mixed
    {
        $signature = $request->header('X-Hub-Signature-256');
        /** @var string|null $secret */
        $secret = config('services.github.webhook_secret');

        if (! $signature) {
            throw new WebhookException('Missing signature.');
        }

        if (! $secret) {
            throw new WebhookException('Webhook secret not configured.');
        }

        /** @var string $body */
        $body = $request->getContent();
        $computed = 'sha256='.hash_hmac('sha256', $body, $secret);

        if (! hash_equals($computed, $signature)) {
            throw new AccessDeniedHttpException('Invalid signature.');
        }

        return $next($request);
    }
}