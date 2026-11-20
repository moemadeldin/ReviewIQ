<?php

declare(strict_types=1);

use App\Models\PullRequest;
use App\Models\Review;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

it('computes metrics from the local database and log file', function (): void {
    $logPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'reviewiq-metrics-'.Str::uuid().'.log';
    $base = now()->subHour();

    $prA = PullRequest::factory()->create(['created_at' => $base->copy()]);
    Review::factory()->create([
        'pull_request_id' => $prA->id,
        'created_at' => $base->copy()->addSeconds(60),
        'raw_response' => json_encode([
            'meta' => ['model' => 'primary-model', 'usage' => ['total_tokens' => 1000]],
        ]),
    ]);

    $prB = PullRequest::factory()->create(['created_at' => $base->copy()]);
    Review::factory()->create([
        'pull_request_id' => $prB->id,
        'created_at' => $base->copy()->addSeconds(120),
        'raw_response' => json_encode([
            'meta' => ['model' => 'fallback-model', 'usage' => ['total_tokens' => 2000]],
        ]),
    ]);

    $prC = PullRequest::factory()->create(['created_at' => $base->copy()]);
    Review::factory()->create([
        'pull_request_id' => $prC->id,
        'created_at' => $base->copy()->addSeconds(30),
        'raw_response' => json_encode(['meta' => ['model' => 'primary-model']]),
    ]);
    Review::factory()->create([
        'pull_request_id' => $prC->id,
        'created_at' => $base->copy()->addSeconds(90),
        'raw_response' => json_encode(['meta' => ['model' => 'primary-model']]),
    ]);

    File::put($logPath, implode("\n", [
        '[2026-01-01 00:00:00] local.WARNING: Issue line invalid, moved to summary {"file":"a.php"}',
        '[2026-01-01 00:00:01] local.WARNING: Issue line invalid, moved to summary {"file":"b.php"}',
        '[2026-01-01 00:00:02] local.INFO: Webhook received {"event":"pull_request"}',
        '[2026-01-01 00:00:03] local.INFO: Webhook received {"event":"pull_request"}',
        '[2026-01-01 00:00:04] local.INFO: Webhook received {"event":"pull_request"}',
        '[2026-01-01 00:00:05] local.INFO: Duplicate webhook delivery ignored {"delivery_id":"x"}',
    ]));

    try {
        $this->artisan('reviews:metrics', ['--log' => $logPath])
            ->expectsOutputToContain('First-review time, average: 70.0 s (n=3 PRs with at least one review)')
            ->expectsOutputToContain('First-review time, p95: 120.0 s (n=3 PRs with at least one review')
            ->expectsOutputToContain('Invalid-line warnings per review: 0.500 (2 warnings / 4 reviews; n=4 reviews)')
            ->expectsOutputToContain('Reviews by model (n=4 reviews):')
            ->expectsOutputToContain('- primary-model: 3')
            ->expectsOutputToContain('- fallback-model: 1')
            ->expectsOutputToContain('Token usage: 3000 total_tokens across 2 reviews with usage (n=2 of 4 reviews; 2 without usage)')
            ->expectsOutputToContain('Duplicate webhook deliveries ignored: 1 of 3 received (n=3 deliveries; n=1 duplicates)')
            ->expectsOutputToContain('may have rotated')
            ->assertSuccessful();
    } finally {
        File::delete($logPath);
    }
});

it('reports zero sample sizes when the log file is missing', function (): void {
    $this->artisan('reviews:metrics', ['--log' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'missing-'.Str::uuid().'.log'])
        ->expectsOutputToContain('Log file: NOT FOUND')
        ->expectsOutputToContain('n=0')
        ->assertSuccessful();
});
