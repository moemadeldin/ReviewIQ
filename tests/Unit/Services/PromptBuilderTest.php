<?php

declare(strict_types=1);

use App\Models\Review;
use App\Services\PromptBuilder;

it('builds system prompt', function (): void {
    $builder = new PromptBuilder();
    $prompt = $builder->buildSystemPrompt();

    expect($prompt)->toContain('ReviewIQ')
        ->and($prompt)->toContain('valid JSON only');
});

it('builds user prompt with all parameters', function (): void {
    $builder = new PromptBuilder();
    $prompt = $builder->buildUserPrompt(
        diff: 'diff content',
        prTitle: 'Fix login bug',
        prDescription: 'Fixes the login redirect issue',
        repoLanguage: 'PHP',
        customRules: 'No debug code allowed',
    );

    expect($prompt)->toContain('Fix login bug')
        ->and($prompt)->toContain('Fixes the login redirect issue')
        ->and($prompt)->toContain('No debug code allowed')
        ->and($prompt)->toContain('diff content')
        ->and($prompt)->toContain('<pr_title>')
        ->and($prompt)->toContain('<pr_description>')
        ->and($prompt)->toContain('<custom_rules>')
        ->and($prompt)->toContain('<diff>');
});

it('builds user prompt with minimal parameters', function (): void {
    $builder = new PromptBuilder();
    $prompt = $builder->buildUserPrompt(
        diff: 'some diff',
        prTitle: 'My PR',
    );

    expect($prompt)->toContain('My PR')
        ->and($prompt)->toContain('No description provided.')
        ->and($prompt)->not->toContain('Custom rules')
        ->and($prompt)->toContain('<pr_title>')
        ->and($prompt)->toContain('<pr_description>')
        ->and($prompt)->toContain('<custom_rules>')
        ->and($prompt)->toContain('<diff>');
});

it('builds user prompt with custom rules', function (): void {
    $builder = new PromptBuilder();
    $prompt = $builder->buildUserPrompt(
        diff: 'diff',
        prTitle: 'Title',
        customRules: 'Always validate input',
    );

    expect($prompt)->toContain('Always validate input');
});

it('omits incremental section from system prompt when no previous review exists', function (): void {
    $builder = new PromptBuilder();

    expect($builder->buildSystemPrompt())->not->toContain('INCREMENTAL REVIEW MODE');
});

it('adds incremental section to system prompt when previous review exists', function (): void {
    $builder = new PromptBuilder();
    $previous = new Review([
        'score' => 62,
        'score_rationale' => 'Issues found',
        'summary' => 'Needs work',
        'issues' => [
            ['file' => 'app/Http/Controllers/UserController.php', 'line' => 42, 'severity' => 'high', 'description' => 'N+1 query', 'category' => 'performance', 'suggestion' => 'Eager load'],
        ],
        'highlights' => [],
        'recommendation' => 'request_changes',
    ]);

    $prompt = $builder->buildSystemPrompt($previous);

    expect($prompt)->toContain('INCREMENTAL REVIEW MODE')
        ->and($prompt)->toContain('ACKNOWLEDGE FIXES')
        ->and($prompt)->toContain('FLAG REGRESSIONS')
        ->and($prompt)->toContain('ONLY NEW ISSUES')
        ->and($prompt)->toContain('SCORE CONTEXT');
});

it('omits previous review section from user prompt when no previous review exists', function (): void {
    $builder = new PromptBuilder();

    $prompt = $builder->buildUserPrompt(diff: 'diff', prTitle: 'Title');

    expect($prompt)->not->toContain('<previous_review>');
});

it('includes serialized previous review in user prompt', function (): void {
    $builder = new PromptBuilder();
    $previous = new Review([
        'score' => 62,
        'score_rationale' => 'Issues found',
        'summary' => 'Needs work',
        'issues' => [
            ['file' => 'app/Http/Controllers/UserController.php', 'line' => 42, 'severity' => 'high', 'description' => 'N+1 query', 'category' => 'performance', 'suggestion' => 'Eager load'],
        ],
        'highlights' => [],
        'recommendation' => 'request_changes',
    ]);

    $prompt = $builder->buildUserPrompt(
        diff: 'diff content',
        prTitle: 'Title',
        previousReview: $previous,
    );

    expect($prompt)->toContain('<previous_review>')
        ->and($prompt)->toContain('"score":62')
        ->and($prompt)->toContain('N+1 query')
        ->and($prompt)->toContain('"recommendation":"request_changes"');
});

it('truncates oversized previous review while keeping valid JSON', function (): void {
    $builder = new PromptBuilder(
        maxDiffChars: 100000,
        ignorePatterns: [],
        enableIncrementalReviews: true,
        maxPreviousReviewChars: 200,
    );

    $previous = new Review([
        'score' => 62,
        'score_rationale' => str_repeat('a', 500),
        'summary' => 'Needs work',
        'issues' => [
            ['severity' => 'high', 'description' => str_repeat('b', 500), 'suggestion' => str_repeat('c', 500)],
        ],
        'highlights' => [['content' => 'Good naming']],
        'recommendation' => 'request_changes',
    ]);

    $prompt = $builder->buildUserPrompt(
        diff: 'diff content',
        prTitle: 'Title',
        previousReview: $previous,
    );

    expect($prompt)->toContain('<previous_review>')
        ->and($prompt)->toContain('...');

    preg_match('/<previous_review>\s*(.*?)\s*<\/previous_review>/s', $prompt, $matches);
    $decoded = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);

    expect(is_array($decoded))->toBeTrue()
        ->and($decoded)->toHaveKey('score')
        ->and($decoded)->toHaveKey('summary')
        ->and($decoded)->toHaveKey('recommendation');
});

it('drops non-essential previous review keys to fit budget', function (): void {
    $builder = new PromptBuilder(
        maxDiffChars: 100000,
        ignorePatterns: [],
        enableIncrementalReviews: true,
        maxPreviousReviewChars: 100,
        maxPreviousReviewIssues: 2,
    );

    $previous = new Review([
        'score' => 62,
        'score_rationale' => str_repeat('a', 100),
        'summary' => str_repeat('b', 100),
        'issues' => [
            ['severity' => 'high', 'description' => str_repeat('c', 100)],
            ['severity' => 'medium', 'description' => str_repeat('d', 100)],
            ['severity' => 'low', 'description' => str_repeat('e', 100)],
        ],
        'highlights' => [['content' => str_repeat('f', 100)]],
        'recommendation' => 'request_changes',
    ]);

    $prompt = $builder->buildUserPrompt(
        diff: 'diff content',
        prTitle: 'Title',
        previousReview: $previous,
    );

    preg_match('/<previous_review>\s*(.*?)\s*<\/previous_review>/s', $prompt, $matches);
    $json = $matches[1];

    $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    expect($decoded)->not->toHaveKey('highlights')
        ->and(count($decoded['issues'] ?? []))->toBeLessThanOrEqual(2)
        ->and(mb_strlen($json))->toBeLessThanOrEqual(100);
});

it('omits previous review section when incremental reviews disabled', function (): void {
    $builder = new PromptBuilder(enableIncrementalReviews: false);
    $previous = new Review(['score' => 80, 'summary' => 'Good']);

    $prompt = $builder->buildUserPrompt(
        diff: 'diff content',
        prTitle: 'Title',
        previousReview: $previous,
    );

    expect($prompt)->not->toContain('<previous_review>');
});
