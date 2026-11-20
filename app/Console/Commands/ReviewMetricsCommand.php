<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PullRequest;
use App\Models\Review;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;

#[Signature('reviews:metrics {--log= : Path to the Laravel log file to scan}')]
#[Description('Compute local review metrics from the database and log file')]
final class ReviewMetricsCommand extends Command
{
    private const string INVALID_LINES = 'Issue line invalid, moved to summary';

    private const string DUPLICATE_DELIVERY = 'Duplicate webhook delivery ignored';

    private const string WEBHOOK_RECEIVED = 'Webhook received';

    public function handle(): int
    {
        $logPath = $this->resolveLogPath();
        $logCounts = $this->countLogLines($logPath);

        $reviews = Review::query()
            ->with('pullRequest:id,created_at')
            ->get(['id', 'pull_request_id', 'created_at', 'raw_response']);

        $durations = $this->firstReviewDurations($reviews);
        [$modelCounts, $tokenRows, $totalTokens] = $this->modelAndTokenCounts($reviews);

        $this->line('Review metrics (local data only; no external services called)');
        $this->line('');

        $durationCount = count($durations);
        if ($durationCount === 0) {
            $this->line('First-review time: n/a (n=0 PRs with at least one review)');
            $this->line('First-review time, p95: n/a (n=0 PRs with at least one review)');
        } else {
            sort($durations);
            $p95Index = (int) ceil(0.95 * $durationCount) - 1;
            $this->line(sprintf(
                'First-review time, average: %.1f s (n=%d PRs with at least one review)',
                array_sum($durations) / $durationCount,
                $durationCount,
            ));
            $this->line(sprintf(
                'First-review time, p95: %.1f s (n=%d PRs with at least one review; nearest-rank)',
                $durations[$p95Index],
                $durationCount,
            ));
        }

        $reviewCount = $reviews->count();
        $this->line(sprintf(
            'Invalid-line warnings per review: %.3f (%d warnings / %d reviews; n=%d reviews)',
            $reviewCount > 0 ? $logCounts[self::INVALID_LINES] / $reviewCount : 0,
            $logCounts[self::INVALID_LINES],
            $reviewCount,
            $reviewCount,
        ));

        if ($modelCounts === []) {
            $this->line('Reviews by model: none (n=0 reviews)');
        } else {
            arsort($modelCounts);
            $this->line(sprintf('Reviews by model (n=%d reviews):', $reviewCount));
            foreach ($modelCounts as $model => $count) {
                $this->line(sprintf('  - %s: %d (%.1f%%)', $model, $count, 100 * $count / $reviewCount));
            }
        }

        $this->line(sprintf(
            'Token usage: %d total_tokens across %d reviews with usage (n=%d of %d reviews; %d without usage)',
            $totalTokens,
            $tokenRows,
            $tokenRows,
            $reviewCount,
            $reviewCount - $tokenRows,
        ));

        $this->line(sprintf(
            'Duplicate webhook deliveries ignored: %d of %d received (n=%d deliveries; n=%d duplicates)',
            $logCounts[self::DUPLICATE_DELIVERY],
            $logCounts[self::WEBHOOK_RECEIVED],
            $logCounts[self::WEBHOOK_RECEIVED],
            $logCounts[self::DUPLICATE_DELIVERY],
        ));

        $this->line('');
        if ($logPath === null) {
            $this->line('Log file: NOT FOUND; log-based counts are 0 (n=0 log lines).');
        } else {
            $this->line('Log file: '.$logPath.' (n='.array_sum($logCounts).' matched log lines).');
        }
        $this->line('Log data may have rotated: counts reflect only the current file, so older events may be missing.');
        $this->line('Timing proxy: pull_requests.created_at (first webhook) to first reviews.created_at.');

        return Command::SUCCESS;
    }

    private function resolveLogPath(): ?string
    {
        $option = $this->option('log');
        $path = is_string($option) && $option !== '' ? $option : storage_path('logs/laravel.log');

        return File::exists($path) ? $path : null;
    }

    /**
     * @return array<string, int>
     */
    private function countLogLines(?string $logPath): array
    {
        $counts = [
            self::INVALID_LINES => 0,
            self::DUPLICATE_DELIVERY => 0,
            self::WEBHOOK_RECEIVED => 0,
        ];

        if ($logPath === null) {
            return $counts;
        }

        $handle = fopen($logPath, 'r');
        if ($handle === false) {
            return $counts;
        }

        while (($line = fgets($handle)) !== false) {
            foreach ($counts as $needle => $count) {
                if (str_contains($line, $needle)) {
                    $counts[$needle] = $count + 1;
                }
            }
        }

        fclose($handle);

        return $counts;
    }

    /**
     * @param  Collection<int, Review>  $reviews
     * @return array<int, int>
     */
    private function firstReviewDurations(Collection $reviews): array
    {
        $durations = [];

        foreach ($reviews->groupBy('pull_request_id') as $pullRequestReviews) {
            $first = $pullRequestReviews->sortBy('created_at')->first();
            if (! $first instanceof Review) {
                continue;
            }

            $pullRequest = $first->pullRequest;

            if (! $pullRequest instanceof PullRequest) {
                continue;
            }

            $seconds = $first->created_at->getTimestamp() - $pullRequest->created_at->getTimestamp();
            if ($seconds >= 0) {
                $durations[] = $seconds;
            }
        }

        return $durations;
    }

    /**
     * @param  Collection<int, Review>  $reviews
     * @return array{array<string, int>, int, int}
     */
    private function modelAndTokenCounts(Collection $reviews): array
    {
        $modelCounts = [];
        $tokenRows = 0;
        $totalTokens = 0;

        foreach ($reviews as $review) {
            $decoded = json_decode((string) $review->raw_response, associative: true);
            $meta = is_array($decoded) && is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [];

            $model = $meta['model'] ?? 'unknown';
            $modelName = is_string($model) && $model !== '' ? $model : 'unknown';
            $modelCounts[$modelName] = ($modelCounts[$modelName] ?? 0) + 1;

            $usage = is_array($meta['usage'] ?? null) ? $meta['usage'] : [];
            $tokens = $usage['total_tokens'] ?? null;
            if (is_numeric($tokens)) {
                $tokenRows++;
                $totalTokens += (int) $tokens;
            }
        }

        return [$modelCounts, $tokenRows, $totalTokens];
    }
}
