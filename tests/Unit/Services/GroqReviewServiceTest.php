<?php

declare(strict_types=1);

use App\Exceptions\ReviewParseException;
use App\Services\GroqReviewService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;

beforeEach(function (): void {
    Config::set('services.groq.base_url', 'https://api.groq.com/openai/v1/');
    Config::set('services.groq.api_key', 'test-api-key');
    Config::set('services.groq.model', 'llama-3.3-70b-versatile');
    Config::set('services.groq.temperature', 0.2);
    Config::set('services.groq.max_tokens', 2000);
    Log::spy();
});

function validJsonResponse(): string
{
    return json_encode([
        'summary' => 'Good code',
        'score' => 85,
        'score_rationale' => 'Well written',
        'issues' => [],
        'highlights' => ['Clean code'],
        'recommendation' => 'approve',
    ]);
}

it('performs non-streaming review successfully', function (): void {
    $response = new Response(200, [], json_encode([
        'choices' => [
            ['message' => ['content' => validJsonResponse()]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = new GroqReviewService($client);
    $result = $service->review('system prompt', 'user prompt');

    expect($result['content'])->toBeJson();
    $data = json_decode($result['content'], true);
    expect($data['summary'])->toBe('Good code')
        ->and($data['score'])->toBe(85);
});

it('performs streaming review successfully', function (): void {
    $chunks = [
        "data: {\"choices\":[{\"delta\":{\"content\":\"{\\\"summary\\\":\\\"Good code\\\",\\\"score\\\":85,\\\"score_rationale\\\":\\\"Well\\\",\\\"issues\\\":[],\\\"highlights\\\":[],\\\"recommendation\\\":\\\"approve\\\"}\"}}]}\n\n",
        "data: [DONE]\n\n",
    ];

    $readIndex = 0;

    $stream = $this->createMock(StreamInterface::class);
    $stream->method('eof')
        ->willReturnCallback(function () use ($chunks, &$readIndex): bool {
            return $readIndex >= count($chunks);
        });
    $stream->method('read')
        ->willReturnCallback(function () use ($chunks, &$readIndex): string {
            $chunk = $chunks[$readIndex] ?? '';
            $readIndex++;

            return $chunk;
        });

    $response = $this->createMock(Response::class);
    $response->method('getBody')->willReturn($stream);

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = new GroqReviewService($client);
    $chunksReceived = '';
    $result = $service->stream(
        systemPrompt: 'test',
        userPrompt: 'test',
        onChunk: function (string $chunk) use (&$chunksReceived): void {
            $chunksReceived .= $chunk;
        },
    );

    expect($result['content'])->toBeJson();
    expect($chunksReceived)->toContain('Good code');
    expect($chunksReceived)->toContain('approve');
});

it('throws on http failure during review', function (): void {
    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willThrowException(new RequestException(
            message: 'Connection timeout',
            request: new Request('POST', 'test'),
        ));

    $service = new GroqReviewService($client);

    expect(fn (): array => $service->review('system', 'user'))
        ->toThrow(RuntimeException::class, 'Groq API error: Connection timeout');
});

it('throws on invalid json response', function (): void {
    $response = new Response(200, [], json_encode([
        'choices' => [
            ['message' => ['content' => 'not valid json']],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = new GroqReviewService($client);

    expect(fn (): array => $service->review('system', 'user'))
        ->toThrow(ReviewParseException::class);
});

it('throws on missing required fields', function (): void {
    $response = new Response(200, [], json_encode([
        'choices' => [
            ['message' => ['content' => json_encode(['summary' => 'test'])]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = new GroqReviewService($client);

    expect(fn (): array => $service->review('system', 'user'))
        ->toThrow(ReviewParseException::class, 'Groq response missing required fields');
});

it('sanitizes score to int', function (): void {
    $response = new Response(200, [], json_encode([
        'choices' => [
            ['message' => ['content' => json_encode([
                'summary' => 'test',
                'score' => '85',
                'issues' => [],
            ])]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = new GroqReviewService($client);
    $result = $service->review('system', 'user');

    $data = json_decode($result['content'], true);
    expect($data['score'])->toBe(85);
});

it('provides defaults for missing optional fields', function (): void {
    $response = new Response(200, [], json_encode([
        'choices' => [
            ['message' => ['content' => json_encode([
                'summary' => 'test',
                'score' => 50,
                'issues' => [],
            ])]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = new GroqReviewService($client);
    $result = $service->review('system', 'user');

    $data = json_decode($result['content'], true);
    expect($data['highlights'])->toBe([])
        ->and($data['recommendation'])->toBe('comment')
        ->and($data['score_rationale'])->toBe('');
});

it('parses json with markdown fences', function (): void {
    $response = new Response(200, [], json_encode([
        'choices' => [
            ['message' => ['content' => "```json\n".validJsonResponse()."\n```"]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = new GroqReviewService($client);
    $result = $service->review('system', 'user');

    $data = json_decode($result['content'], true);
    expect($data['summary'])->toBe('Good code');
});

it('sanitizes issues severity', function (): void {
    $response = new Response(200, [], json_encode([
        'choices' => [
            ['message' => ['content' => json_encode([
                'summary' => 'test',
                'score' => 70,
                'issues' => [
                    ['severity' => 'invalid', 'file' => 'test.php'],
                ],
            ])]],
        ],
    ]));

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = new GroqReviewService($client);
    $result = $service->review('system', 'user');

    $data = json_decode($result['content'], true);
    expect($data['issues'][0]['severity'])->toBe('medium');
});

it('handles streaming edge cases', function (): void {
    $rawSse = "data: {\"choices\":[{\"delta\":{\"content\":\"{\\\"summary\\\":\\\"test\\\",\\\"score\\\":50,\\\"issues\\\":[]}\"}}]}\n\n:heartbeat\n\n0\n\ndata: {\"choices\":[{\"delta\":{}}]}\n\ndata: [DONE]\n\n";

    $readCallCount = 0;
    $stream = $this->createMock(StreamInterface::class);
    $stream->method('eof')
        ->willReturnCallback(function () use (&$readCallCount): bool {
            return $readCallCount >= 3;
        });
    $stream->method('read')
        ->willReturnCallback(function () use ($rawSse, &$readCallCount): string {
            $readCallCount++;

            return match ($readCallCount) {
                1 => $rawSse,
                2 => '',
                3 => '0',
                default => '',
            };
        });

    $response = $this->createMock(Response::class);
    $response->method('getBody')->willReturn($stream);

    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willReturn($response);

    $service = new GroqReviewService($client);
    $chunksReceived = '';
    $result = $service->stream('test', 'test', function (string $chunk) use (&$chunksReceived): void {
        $chunksReceived .= $chunk;
    });

    expect($result['content'])->toBeJson();
    $data = json_decode($result['content'], true);
    expect($data['summary'])->toBe('test')
        ->and($data['score'])->toBe(50);
    expect($chunksReceived)->toContain('test');
});

it('throws on stream request failure', function (): void {
    $client = $this->createMock(Client::class);
    $client->expects($this->once())
        ->method('post')
        ->willThrowException(new RequestException(
            message: 'Stream failed',
            request: new Request('POST', 'test'),
        ));

    $service = new GroqReviewService($client);

    expect(fn (): array => $service->stream('test', 'test', fn (string $chunk): null => null))
        ->toThrow(RuntimeException::class, 'Groq API error: Stream failed');
});

it('throws on config missing', function (): void {
    Config::set('services.groq.base_url');

    $client = $this->createMock(Client::class);

    $service = new GroqReviewService($client);

    expect(fn (): array => $service->review('system', 'user'))
        ->toThrow(RuntimeException::class, 'Invalid Groq configuration');
});
