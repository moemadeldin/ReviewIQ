<?php

declare(strict_types=1);

use App\Exceptions\ReviewParseException;
use App\Services\ReviewResponseParser;

beforeEach(function (): void {
    $this->parser = new ReviewResponseParser();
});

$validResponse = json_encode([
    'summary' => 'Good code',
    'score' => 85,
    'score_rationale' => 'Well written',
    'issues' => [],
    'highlights' => ['Clean code'],
    'recommendation' => 'approve',
]);

it('parses valid json response', function () use ($validResponse): void {
    $result = $this->parser->parse($validResponse, 'test-model');

    expect($result['summary'])->toBe('Good code')
        ->and($result['score'])->toBe(85)
        ->and($result['recommendation'])->toBe('approve')
        ->and($result['issues'])->toBe([])
        ->and($result['highlights'][0]['content'])->toBe('Clean code')
        ->and($result['meta']['model'])->toBe('test-model');
});

it('strips markdown fences', function () use ($validResponse): void {
    $fenced = "```json\n{$validResponse}\n```";
    $result = $this->parser->parse($fenced, 'test-model');

    expect($result['summary'])->toBe('Good code');
});

it('extracts json object from surrounding text', function () use ($validResponse): void {
    $withText = "Here is the response:\n{$validResponse}\nDone.";
    $result = $this->parser->parse($withText, 'test-model');

    expect($result['summary'])->toBe('Good code');
});

it('throws on missing required fields', function (): void {
    $invalid = json_encode(['summary' => 'test']);
    expect(fn () => $this->parser->parse($invalid, 'test-model'))
        ->toThrow(ReviewParseException::class, 'missing required fields');
});

it('sanitizes score to 0-100 range', function (): void {
    $low = json_encode(['summary' => 'test', 'score' => -10, 'issues' => []]);
    $result = $this->parser->parse($low, 'test-model');
    expect($result['score'])->toBe(0);

    $high = json_encode(['summary' => 'test', 'score' => 150, 'issues' => []]);
    $result = $this->parser->parse($high, 'test-model');
    expect($result['score'])->toBe(100);

    $float = json_encode(['summary' => 'test', 'score' => 85.7, 'issues' => []]);
    $result = $this->parser->parse($float, 'test-model');
    expect($result['score'])->toBe(85);
});

it('normalizes issue severity and moves praise to highlights', function (): void {
    $withPraise = json_encode([
        'summary' => 'test',
        'score' => 90,
        'issues' => [
            ['file' => 'a.php', 'line' => 10, 'severity' => 'praise', 'description' => 'Great!'],
            ['file' => 'b.php', 'line' => 5, 'severity' => 'critical', 'description' => 'Bug'],
            ['file' => 'c.php', 'line' => 3, 'severity' => 'invalid', 'description' => 'Default'],
        ],
        'highlights' => [],
        'recommendation' => 'approve',
    ]);

    $result = $this->parser->parse($withPraise, 'test-model');

    // Praise moved to highlights
    expect($result['issues'])->toHaveCount(2);
    expect($result['highlights'])->toHaveCount(1);
    expect($result['highlights'][0]['content'])->toBe('Great!');

    // Invalid severity defaults to medium
    expect($result['issues'][1]['severity'])->toBe('medium');
    expect($result['issues'][1]['category'])->toBe('maintainability');
});

it('normalizes issue text field to description only', function (): void {
    $withFields = json_encode([
        'summary' => 'test',
        'score' => 80,
        'issues' => [
            ['file' => 'a.php', 'line' => 1, 'severity' => 'high', 'message' => 'Old field'],
        ],
        'highlights' => [],
        'recommendation' => 'comment',
    ]);

    $result = $this->parser->parse($withFields, 'test-model');

    expect($result['issues'][0])->toHaveKey('description')
        ->and($result['issues'][0]['description'])->toBe('Old field')
        ->and($result['issues'][0])->not->toHaveKey('message');
});

it('validates and defaults recommendation', function (): void {
    $invalidRec = json_encode([
        'summary' => 'test', 'score' => 50, 'issues' => [],
        'highlights' => [], 'recommendation' => 'invalid',
    ]);
    $result = $this->parser->parse($invalidRec, 'test-model');
    expect($result['recommendation'])->toBe('comment');
});

it('validates and defaults category', function (): void {
    $withCat = json_encode([
        'summary' => 'test', 'score' => 50, 'issues' => [
            ['file' => 'a.php', 'line' => 1, 'severity' => 'high', 'description' => 'Test', 'category' => 'invalid'],
        ],
        'highlights' => [], 'recommendation' => 'comment',
    ]);
    $result = $this->parser->parse($withCat, 'test-model');
    expect($result['issues'][0]['category'])->toBe('maintainability');
});

it('handles string highlights', function (): void {
    $withStrings = json_encode([
        'summary' => 'test', 'score' => 80, 'issues' => [],
        'highlights' => ['Clean code', 'Good docs'],
        'recommendation' => 'approve',
    ]);
    $result = $this->parser->parse($withStrings, 'test-model');
    expect($result['highlights'])->toHaveCount(2);
    expect($result['highlights'][0]['content'])->toBe('Clean code');
});

it('repairs trailing commas', function (): void {
    $withTrailing = '{"summary":"test","score":85,"issues":[],}';
    $result = $this->parser->parse($withTrailing, 'test-model');
    expect($result['score'])->toBe(85);
});

it('repairs unquoted keys', function (): void {
    $unquoted = '{summary:"test",score:85,issues:[]}';
    $result = $this->parser->parse($unquoted, 'test-model');
    expect($result['summary'])->toBe('test');
});

it('repairs missing colon', function (): void {
    $missingColon = '{"summary":"test","score":85,"issues"[{}]}';
    $result = $this->parser->parse($missingColon, 'test-model');
    expect($result['issues'])->toHaveCount(1);
});

it('preserves emoji and non-english text', function (): void {
    $unicode = json_encode([
        'summary' => 'Great work! 🎉 优秀',
        'score' => 95,
        'issues' => [],
        'highlights' => ['Perfecto! 👌'],
        'recommendation' => 'approve',
    ]);
    $result = $this->parser->parse($unicode, 'test-model');
    expect($result['summary'])->toBe('Great work! 🎉 优秀')
        ->and($result['highlights'][0]['content'])->toBe('Perfecto! 👌');
});

it('preserves escaped quotes', function (): void {
    $escaped = json_encode([
        'summary' => 'He said "Hello"',
        'score' => 70,
        'issues' => [
            ['file' => 'a.php', 'line' => 1, 'severity' => 'medium', 'description' => 'Quote: "test"'],
        ],
        'highlights' => [], 'recommendation' => 'comment',
    ]);
    $result = $this->parser->parse($escaped, 'test-model');
    expect($result['summary'])->toBe('He said "Hello"')
        ->and($result['issues'][0]['description'])->toBe('Quote: "test"');
});

it('captures usage in meta', function (): void {
    $withUsage = json_encode([
        'summary' => 'test', 'score' => 80, 'issues' => [],
        'highlights' => [], 'recommendation' => 'comment',
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150],
    ]);
    $result = $this->parser->parse($withUsage, 'test-model');
    expect($result['meta']['usage']['prompt_tokens'])->toBe(100)
        ->and($result['meta']['usage']['total_tokens'])->toBe(150);
});

it('throws on completely invalid json', function (): void {
    expect(fn () => $this->parser->parse('not json at all', 'test-model'))
        ->toThrow(ReviewParseException::class, 'Invalid JSON');
});

it('handles empty issues array', function (): void {
    $empty = json_encode(['summary' => 'test', 'score' => 50, 'issues' => [], 'highlights' => [], 'recommendation' => 'comment']);
    $result = $this->parser->parse($empty, 'test-model');
    expect($result['issues'])->toBe([])
        ->and($result['highlights'])->toBe([]);
});

it('handles null line in issues', function (): void {
    $nullLine = json_encode([
        'summary' => 'test', 'score' => 50,
        'issues' => [['file' => 'a.php', 'line' => null, 'severity' => 'low', 'description' => 'General']],
        'highlights' => [], 'recommendation' => 'comment',
    ]);
    $result = $this->parser->parse($nullLine, 'test-model');
    expect($result['issues'][0]['line'])->toBeNull();
});
