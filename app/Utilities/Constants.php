<?php

declare(strict_types=1);

namespace App\Utilities;

final class Constants
{
    // Streaming
    public const int STREAM_CHUNK_SIZE = 8192;

    public const int STREAM_YIELD_MICROSECONDS = 10_000;

    // AI / Reviews
    public const int AI_MAX_FALLBACK_RETRIES = 2;

    public const int AI_RETRY_DELAY_BASE_MS = 1000;

    public const int AI_SCORE_MIN = 0;

    public const int AI_SCORE_MAX = 100;

    // Jobs
    public const int REVIEW_JOB_TRIES = 3;

    public const int REVIEW_JOB_TIMEOUT_SECONDS = 600;

    /** @var array<int, int> */
    public const array REVIEW_JOB_BACKOFF_SECONDS = [30, 120, 300];

    public const int REVIEW_STALE_MINUTES = 10;

    // Pagination / dashboard
    public const int PAGE_LIMIT = 10;

    public const int DASHBOARD_RECENT_PULL_REQUESTS = 8;

    // Invitations
    public const int INVITATION_TOKEN_EXPIRY_HOURS = 48;

    public const int INVITATION_TOKEN_LENGTH = 64;

    // GitHub
    public const int GITHUB_INSTALLATION_TOKEN_TTL_SECONDS = 55 * 60;

    public const int GITHUB_JWT_TTL_SECONDS = 600;

    public const int GITHUB_DIFF_CACHE_TTL_SECONDS = 86400;

    public const int GITHUB_WEBHOOK_DEDUP_TTL_SECONDS = 86400;

    public const int GITHUB_REPOS_CACHE_TTL_SECONDS = 300;

    public const int GITHUB_API_RETRIES = 2;

    public const int GITHUB_API_RETRY_DELAY_MS = 200;

    public const int GITHUB_API_REPOS_PER_PAGE = 100;

    // Prompt builder
    public const int PROMPT_MAX_DIFF_CHARS_DEFAULT = 100000;

    public const int PROMPT_MAX_CUSTOM_RULES_LENGTH = 5000;

    public const int PROMPT_PRIORITY_BASE = 100;

    public const int PROMPT_PRIORITY_PENALTY_LOCKFILE = 50;

    public const int PROMPT_PRIORITY_PENALTY_GENERATED = 40;

    public const int PROMPT_PRIORITY_PENALTY_MINIFIED = 30;

    public const int PROMPT_PRIORITY_PENALTY_VENDOR = 60;

    public const int PROMPT_PRIORITY_PENALTY_BINARY = 80;

    public const int PROMPT_PRIORITY_PENALTY_SNAPSHOT = 20;

    public const int PROMPT_PRIORITY_BONUS_SOURCE = 20;

    public const int PROMPT_PRIORITY_BONUS_TEST = 15;

    public const int PROMPT_PRIORITY_BONUS_CONFIG = 10;

    // HTTP (not present in Symfony constant set)
    public const int HTTP_PAGE_EXPIRED = 419;
}
