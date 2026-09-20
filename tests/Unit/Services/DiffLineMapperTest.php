<?php

declare(strict_types=1);

use App\Services\DiffLineMapper;

beforeEach(function (): void {
    $this->mapper = new DiffLineMapper();
});

$sampleDiff = <<<'DIFF'
diff --git a/src/User.php b/src/User.php
index 1234567..abcdefg 100644
--- a/src/User.php
+++ b/src/User.php
@@ -1,5 +1,6 @@
 <?php
 
 namespace App\Models;
 
 class User
 {
+    public string $name;
     public string $email;
 }
DIFF;

$multiHunkDiff = <<<'DIFF'
diff --git a/src/File.php b/src/File.php
index 1234567..abcdefg 100644
--- a/src/File.php
+++ b/src/File.php
@@ -1,3 +1,4 @@
 <?php
 
+namespace App;
 class File
 {
@@ -10,3 +11,4 @@
     }
 }
+// New comment
DIFF;

$renameDiff = <<<'DIFF'
diff --git a/src/OldName.php b/src/NewName.php
similarity index 100%
rename from src/OldName.php
rename to src/NewName.php
DIFF;

$deletedFileDiff = <<<'DIFF'
diff --git a/src/Deleted.php b/src/Deleted.php
deleted file mode 100644
index 1234567..0000000
--- a/src/Deleted.php
+++ /dev/null
@@ -1,3 +0,0 @@
-<?php
-
-class Deleted
-{
-}
DIFF;

$noNewlineDiff = <<<'DIFF'
diff --git a/src/File.php b/src/File.php
index 1234567..abcdefg 100644
--- a/src/File.php
+++ b/src/File.php
@@ -1,3 +1,3 @@
-<?php
+<?php declare(strict_types=1);
 class File
 {
 }
\ No newline at end of file
DIFF;

it('maps valid right-side lines for a simple diff', function () use ($sampleDiff): void {
    $map = $this->mapper->map($sampleDiff);

    expect($map)->toHaveKey('src/User.php');
    // Lines in the hunk: line 1 (context), line 2 (context), line 3 (context), line 4 (added), line 5 (context), line 6 (context)
    expect($map['src/User.php'])->toHaveKeys([1, 2, 3, 4, 5, 6]);
});

it('handles multi-hunk diffs', function () use ($multiHunkDiff): void {
    $map = $this->mapper->map($multiHunkDiff);

    expect($map)->toHaveKey('src/File.php');
    // First hunk starts at new file line 1, has 4 context/added lines (1-4)
    // Second hunk starts at new file line 11, has 3 context/added lines (11-13)
    expect($map['src/File.php'])->toHaveKeys([1, 2, 3, 4, 11, 12, 13]);
});

it('handles renamed files (b/ path)', function () use ($renameDiff): void {
    $map = $this->mapper->map($renameDiff);
    // Rename diff has no content changes, just metadata
    expect($map)->toBeEmpty();
});

it('handles deleted files', function () use ($deletedFileDiff): void {
    $map = $this->mapper->map($deletedFileDiff);
    // Deleted file has no right-side lines
    expect($map)->toBeEmpty();
});

it('handles "No newline at end of file"', function () use ($noNewlineDiff): void {
    $map = $this->mapper->map($noNewlineDiff);

    expect($map)->toHaveKey('src/File.php');
    // Line 1 is context (space), line 2 is context, line 3 is context
    expect($map['src/File.php'])->toHaveKeys([1, 2, 3]);
});

it('annotates diff with line numbers', function () use ($sampleDiff): void {
    $annotated = $this->mapper->annotate($sampleDiff);

    // Check that context and added lines have #L<number> suffix
    expect($annotated)->toContain(' namespace App\\Models; #L3')
        ->and($annotated)->toContain('+     public string $name; #L7');
});

it('validates issue against map - valid line', function () use ($sampleDiff): void {
    $map = $this->mapper->map($sampleDiff);
    $result = $this->mapper->validateIssue($map, 'src/User.php', 4);

    expect($result['valid'])->toBeTrue()
        ->and($result['line'])->toBe(4);
});

it('validates issue against map - invalid line', function () use ($sampleDiff): void {
    $map = $this->mapper->map($sampleDiff);
    $result = $this->mapper->validateIssue($map, 'src/User.php', 999);

    expect($result['valid'])->toBeFalse()
        ->and($result['line'])->toBeNull();
});

it('validates issue against map - file not in diff', function () use ($sampleDiff): void {
    $map = $this->mapper->map($sampleDiff);
    $result = $this->mapper->validateIssue($map, 'src/Other.php', 1);

    expect($result['valid'])->toBeFalse()
        ->and($result['line'])->toBeNull();
});

it('validates issue with null line (always valid)', function () use ($sampleDiff): void {
    $map = $this->mapper->map($sampleDiff);
    $result = $this->mapper->validateIssue($map, 'src/User.php', null);

    expect($result['valid'])->toBeTrue()
        ->and($result['line'])->toBeNull();
});

it('handles file path variations (a/, b/, no prefix)', function () use ($sampleDiff): void {
    $map = $this->mapper->map($sampleDiff);

    // All these should resolve to the same file
    $result1 = $this->mapper->validateIssue($map, 'src/User.php', 4);
    $result2 = $this->mapper->validateIssue($map, 'a/src/User.php', 4);
    $result3 = $this->mapper->validateIssue($map, 'b/src/User.php', 4);

    expect($result1['valid'])->toBeTrue()
        ->and($result2['valid'])->toBeTrue()
        ->and($result3['valid'])->toBeTrue();
});
