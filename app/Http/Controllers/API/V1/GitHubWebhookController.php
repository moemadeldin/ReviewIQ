<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\WebhookProvider;
use App\Traits\APIResponder;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class GitHubWebhookController
{
    use APIResponder;

    public function __construct(
        private WebhookProvider $webhookService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->webhookService->handle($request);

            return $this->success([], 'Event accepted and dispatched to background processing.', Response::HTTP_ACCEPTED);
        } catch (Exception $exception) {
            return $this->fail($exception->getMessage(), $exception instanceof HttpException ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
