<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Notification;
use App\Models\PullRequest;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Notification $notification */
        $notification = $this->resource;

        $readAt = $notification->read_at;
        $data = $notification->data;

        if (isset($data['token']) && is_string($data['token'])) {
            $invitation = WorkspaceInvitation::findByRawToken($data['token']);

            if (! $invitation instanceof WorkspaceInvitation || $invitation->isAccepted() || $invitation->isExpired()) {
                unset($data['token']);
            }
        }

        if (isset($data['review_url']) && is_string($data['review_url'])) {
            $data['review_url'] = $this->canonicalReviewUrl($data['review_url']);
        }

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'data' => $data,
            'read_at' => $readAt !== null ? $readAt->toIsoString() : null,
            'created_at' => $notification->created_at->toIsoString(),
        ];
    }

    /**
     * Rewrite a stored review link to the canonical URL for the current host.
     * The stored value can carry a stale host (e.g. an old ngrok tunnel) or a
     * non-resolvable workspace key, so it is rebuilt from the pull request id.
     */
    private function canonicalReviewUrl(string $reviewUrl): string
    {
        $marker = '/reviews/';
        $markerPosition = mb_strrpos($reviewUrl, $marker);

        if ($markerPosition === false) {
            return $reviewUrl;
        }

        $prId = mb_substr($reviewUrl, $markerPosition + mb_strlen($marker));
        $prId = (string) parse_url($prId, PHP_URL_PATH);

        if ($prId === '') {
            return $reviewUrl;
        }

        $pullRequest = PullRequest::query()
            ->with(['repository.workspace'])
            ->find($prId);

        if ($pullRequest === null || $pullRequest->repository?->workspace === null) {
            return $reviewUrl;
        }

        return url()->route(
            'reviews.show',
            [$pullRequest->repository->workspace, $pullRequest],
            absolute: false,
        );
    }
}
