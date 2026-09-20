<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\StreamInterface;

final readonly class SseStreamReader
{
    /**
     * @param  StreamInterface|resource  $stream
     * @param  callable(string): void  $onLine
     * @return void
     */
    public function read($stream, callable $onLine): void
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
            $chunk = $stream->read(8192);

            if ($chunk === '') {
                usleep(10_000);
                continue;
            }

            $buffer .= $chunk;
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines); // Keep incomplete line in buffer

            foreach ($lines as $line) {
                $line = rtrim($line, "\r"); // Handle \r\n

                if ($line === '' || $line === '0') {
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
            $buffer = rtrim($buffer, "\r");
            if ($buffer !== '' && ! str_starts_with($buffer, ': ')) {
                $onLine($buffer);
            }
        }
    }

    private function readResource($stream, callable $onLine, string &$buffer): void
    {
        while (! feof($stream)) {
            $chunk = fread($stream, 8192);

            if ($chunk === '') {
                usleep(10_000);
                continue;
            }

            $buffer .= $chunk;
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines); // Keep incomplete line in buffer

            foreach ($lines as $line) {
                $line = rtrim($line, "\r"); // Handle \r\n

                if ($line === '' || $line === '0') {
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
            $buffer = rtrim($buffer, "\r");
            if ($buffer !== '' && ! str_starts_with($buffer, ': ')) {
                $onLine($buffer);
            }
        }
    }
}