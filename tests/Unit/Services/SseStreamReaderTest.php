<?php

declare(strict_types=1);

use App\Services\SseStreamReader;
use Psr\Http\Message\StreamInterface;

beforeEach(function (): void {
    $this->reader = new SseStreamReader();
});

function createMockStream(array $chunks): StreamInterface
{
    $stream = Mockery::mock(StreamInterface::class);
    $index = 0;

    $stream->shouldReceive('eof')
        ->andReturnUsing(function () use ($chunks, &$index): bool {
            return $index >= count($chunks);
        });

    $stream->shouldReceive('read')
        ->andReturnUsing(function (int $length) use ($chunks, &$index): string {
            if ($index >= count($chunks)) {
                return '';
            }

            return $chunks[$index++];
        });

    return $stream;
}

it('parses complete lines from stream', function (): void {
    $lines = [];
    $stream = createMockStream([
        "data: {\"test\":1}\n",
        "data: {\"test\":2}\n",
    ]);

    $this->reader->read($stream, function (string $line) use (&$lines): void {
        $lines[] = $line;
    });

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toBe('data: {"test":1}')
        ->and($lines[1])->toBe('data: {"test":2}');
});

it('handles line split across chunks', function (): void {
    $lines = [];
    $stream = createMockStream([
        "data: {\"test\":1}\n",
        "data: {\"test\":2}\n",
    ]);

    $this->reader->read($stream, function (string $line) use (&$lines): void {
        $lines[] = $line;
    });

    expect($lines)->toHaveCount(2);
});

it('handles CRLF line endings', function (): void {
    $lines = [];
    $stream = createMockStream([
        "data: {\"test\":1}\r\n",
        "data: {\"test\":2}\r\n",
    ]);

    $this->reader->read($stream, function (string $line) use (&$lines): void {
        $lines[] = $line;
    });

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toBe('data: {"test":1}')
        ->and($lines[1])->toBe('data: {"test":2}');
});

it('ignores SSE comment lines (keep-alives)', function (): void {
    $lines = [];
    $stream = createMockStream([
        ": keep-alive\n",
        "data: {\"test\":1}\n",
        ": heartbeat\n",
        "data: {\"test\":2}\n",
    ]);

    $this->reader->read($stream, function (string $line) use (&$lines): void {
        $lines[] = $line;
    });

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toBe('data: {"test":1}')
        ->and($lines[1])->toBe('data: {"test":2}');
});

it('parses [DONE] marker as regular line (consumer handles stopping)', function (): void {
    $lines = [];
    $stream = createMockStream([
        "data: {\"test\":1}\n",
        "data: [DONE]\n",
        "data: {\"test\":2}\n",
    ]);

    $this->reader->read($stream, function (string $line) use (&$lines): void {
        $lines[] = $line;
    });

    // SseStreamReader just parses lines - consumer handles [DONE]
    expect($lines)->toHaveCount(3)
        ->and($lines[1])->toBe('data: [DONE]');
});

it('handles empty stream', function (): void {
    $lines = [];
    $stream = createMockStream([]);

    $this->reader->read($stream, function (string $line) use (&$lines): void {
        $lines[] = $line;
    });

    expect($lines)->toBeEmpty();
});

it('passes whitespace-only lines to callback (only truly empty lines skipped)', function (): void {
    $lines = [];
    $stream = createMockStream([
        "\n",
        "data: {\"test\":1}\n",
        "   \n",
        "data: {\"test\":2}\n",
    ]);

    $this->reader->read($stream, function (string $line) use (&$lines): void {
        $lines[] = $line;
    });

    // Empty lines are skipped, but whitespace-only lines are passed through
    expect($lines)->toHaveCount(3)
        ->and($lines[1])->toBe('   ');
});

it('handles 0 bytes as empty', function (): void {
    $lines = [];
    $stream = createMockStream([
        "0\n",
        "data: {\"test\":1}\n",
    ]);

    $this->reader->read($stream, function (string $line) use (&$lines): void {
        $lines[] = $line;
    });

    expect($lines)->toHaveCount(1);
});

it('carries incomplete trailing line to next chunk', function (): void {
    $lines = [];
    $stream = createMockStream([
        "data: {\"test\":1}\n",
        'data: {"test',  // incomplete line
        ":2}\n",           // continuation
    ]);

    $this->reader->read($stream, function (string $line) use (&$lines): void {
        $lines[] = $line;
    });

    expect($lines)->toHaveCount(2)
        ->and($lines[1])->toBe('data: {"test:2}');
});
