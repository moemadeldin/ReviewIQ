<?php

declare(strict_types=1);

namespace App\Services;

final readonly class DiffLineMapper
{
    /**
     * Parse a unified diff and extract valid right-side line numbers per file.
     *
     * @return array<string, array<int, true>> file => [lineNumber => true]
     */
    public function map(string $diff): array
    {
        $validLines = [];
        $lines = explode("\n", $diff);

        $currentFile = '';
        $rightLine = 0;
        $inHunk = false;

        foreach ($lines as $line) {
            // File header: +++ b/file.php (new file) or +++ /dev/null (deleted)
            if (preg_match('/^\+\+\+\s+(.+)$/', $line, $matches)) {
                $filePath = $matches[1];

                // Skip deleted files (/dev/null)
                if ($filePath === '/dev/null') {
                    $currentFile = '';
                    $inHunk = false;

                    continue;
                }

                // Strip a/ or b/ prefix
                $currentFile = preg_replace('/^(?:a|b)\//', '', $filePath);
                $validLines[$currentFile] = [];
                $rightLine = 0;
                $inHunk = false;

                continue;
            }

            // Hunk header: @@ -oldStart,oldCount +newStart,newCount @@
            if (preg_match('/^@@\s+-\d+(?:,\d+)?\s+\+(\d+)(?:,\d+)?\s+@@/', $line, $matches)) {
                $rightLine = (int) $matches[1] - 1; // Will be incremented on first context/added line
                $inHunk = true;

                continue;
            }

            if (! $inHunk) {
                continue;
            }

            if ($currentFile === '') {
                continue;
            }

            // Context line (space prefix) or added line (+ prefix)
            if ($line !== '' && ($line[0] === ' ' || $line[0] === '+')) {
                $rightLine++;
                $validLines[$currentFile][$rightLine] = true;
            }

            // Deleted line (- prefix) - does not increment rightLine
            // Binary file marker, "No newline at end of file" - ignored
        }

        return $validLines;
    }

    /**
     * Annotate a unified diff with right-side line numbers.
     * Adds line number comments to context and added lines.
     *
     * @return string Annotated diff
     */
    public function annotate(string $diff): string
    {
        $this->map($diff);
        $lines = explode("\n", $diff);
        $annotated = [];

        $currentFile = '';
        $rightLine = 0;
        $inHunk = false;

        foreach ($lines as $line) {
            // File header
            if (preg_match('/^\+\+\+\s+(?:b\/)?(.+)$/', $line, $matches)) {
                $currentFile = $matches[1];
                $rightLine = 0;
                $inHunk = false;
                $annotated[] = $line;

                continue;
            }

            // Hunk header
            if (preg_match('/^@@\s+-\d+(?:,\d+)?\s+\+(\d+)(?:,\d+)?\s+@@/', $line, $matches)) {
                $rightLine = (int) $matches[1] - 1;
                $inHunk = true;
                $annotated[] = $line;

                continue;
            }

            if (! $inHunk || $currentFile === '') {
                $annotated[] = $line;

                continue;
            }

            // Context or added line - annotate with line number
            if ($line !== '' && ($line[0] === ' ' || $line[0] === '+')) {
                $rightLine++;
                $prefix = $line[0];
                $content = mb_substr($line, 1);
                $annotated[] = sprintf('%s %s #L%d', $prefix, $content, $rightLine);
            } else {
                // Deleted line or other - keep as-is
                $annotated[] = $line;
            }
        }

        return implode("\n", $annotated);
    }

    /**
     * Validate an issue's file and line against the diff map.
     *
     * @param  array<string, array<int, true>>  $map
     * @return array{file: string, line: int|null, valid: bool}
     */
    public function validateIssue(array $map, string $file, ?int $line): array
    {
        $normalizedFile = mb_ltrim($file, '/');

        // Check if file exists in map (try exact and with a/ b/ prefixes)
        $fileKey = $this->findFileKey($map, $normalizedFile);

        if ($fileKey === null) {
            return ['file' => $file, 'line' => null, 'valid' => false];
        }

        if ($line === null) {
            return ['file' => $file, 'line' => null, 'valid' => true];
        }

        $isValid = isset($map[$fileKey][$line]);

        return ['file' => $file, 'line' => $isValid ? $line : null, 'valid' => $isValid];
    }

    /**
     * Find the actual key in the map for a given file path.
     */
    public function findFileKey(array $map, string $file): ?string
    {
        // Try exact match
        if (isset($map[$file])) {
            return $file;
        }

        // Try with a/ prefix (diff source)
        $withAPrefix = 'a/'.$file;
        if (isset($map[$withAPrefix])) {
            return $withAPrefix;
        }

        // Try with b/ prefix (diff destination)
        $withBPrefix = 'b/'.$file;
        if (isset($map[$withBPrefix])) {
            return $withBPrefix;
        }

        // Try without a/ or b/ prefix
        $withoutPrefix = preg_replace('/^(?:a|b)\//', '', $file);
        if (isset($map[$withoutPrefix])) {
            return $withoutPrefix;
        }

        return null;
    }
}
