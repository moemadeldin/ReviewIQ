<?php

declare(strict_types=1);

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
        ->and($prompt)->toContain('PHP')
        ->and($prompt)->toContain('No debug code allowed')
        ->and($prompt)->toContain('diff content');
});

it('builds user prompt with minimal parameters', function (): void {
    $builder = new PromptBuilder();
    $prompt = $builder->buildUserPrompt(
        diff: 'some diff',
        prTitle: 'My PR',
    );

    expect($prompt)->toContain('My PR')
        ->and($prompt)->toContain('No description provided.')
        ->and($prompt)->toContain('Unknown')
        ->and($prompt)->not->toContain('Custom rules');
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
