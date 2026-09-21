<?php

declare(strict_types=1);

namespace App\Services;

use App\Utilities\Constants;
use Illuminate\Support\Sleep;
use Psr\Http\Message\StreamInterface;

final readonly class SseStreamReader
{
    /**
     * @param  callable(string): void  $onLine
     */
    public function read(mixed $stream, callable $onLine): void
    {
        $buffer = '';

        if (is_resource($stream)) {
            $this->readResource($stream, $onLine, $buffer);
        } elseif ($stream instanceof StreamInterface) {
            $this->readPsrStream($stream, $onLine, $buffer);
        }
    }

    private function readPsrStream(StreamInterface $stream, callable $onLine, string &$buffer): void
    {
        while (! $stream->eof()) {
            $chunk = $stream->read(Constants::STREAM_CHUNK_SIZE);

            if ($chunk === '') {
                Sleep::usleep(Constants::STREAM_YIELD_MICROSECONDS);

                continue;
            }

            $buffer .= $chunk;
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines); // Keep incomplete line in buffer

            foreach ($lines as $line) {
                $line = mb_rtrim($line, "\r");
                // Handle \r\n
                if ($line === '') {
                    continue;
                }

                if ($line === '0') {
                    continue;
                }

                // Ignore SSE comment lines (keep-alives like ": " or ":heartbeat")
                if (str_starts_with($line, ': ')) {
                    continue;
                }

                $onLine($line);
            }
        }

        // Process any remaining buffer
        if ($buffer !== '') {
            $buffer = mb_rtrim($buffer, "\r");
            if ($buffer !== '' && ! str_starts_with($buffer, ': ')) {
                $onLine($buffer);
            }
        }
    }

    private function readResource(mixed $stream, callable $onLine, string &$buffer): void
    {
        while (! feof($stream)) {
            $chunk = fread($stream, Constants::STREAM_CHUNK_SIZE);

            if ($chunk === '') {
                Sleep::usleep(Constants::STREAM_YIELD_MICROSECONDS);

                continue;
            }

            $buffer .= $chunk;
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines); // Keep incomplete line in buffer

            foreach ($lines as $line) {
                $line = mb_rtrim($line, "\r");
                // Handle \r\n
                if ($line === '') {
                    continue;
                }

                if ($line === '0') {
                    continue;
                }

                // Ignore SSE comment lines (keep-alives like ": " or ":heartbeat")
                if (str_starts_with($line, ': ')) {
                    continue;
                }

                $onLine($line);
            }
        }

        // Process any remaining buffer
        if ($buffer !== '') {
            $buffer = mb_rtrim($buffer, "\r");
            if ($buffer !== '' && ! str_starts_with($buffer, ': ')) {
                $onLine($buffer);
            }
        }
    }
}
