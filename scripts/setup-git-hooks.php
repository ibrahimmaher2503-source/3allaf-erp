<?php

declare(strict_types=1);

$root = realpath(__DIR__.'/..');
$gitRoot = trim((string) shell_exec('git -C '.escapeshellarg($root).' rev-parse --show-toplevel 2>/dev/null'));
$expectedProject = 'toy-joy-phase-1-documentation';

if ($gitRoot === '' || basename($gitRoot) !== $expectedProject || $gitRoot !== $root) {
    fwrite(STDERR, "Git hook setup refused: project identity mismatch.\n");
    exit(1);
}

$command = 'git -C '.escapeshellarg($root).' config core.hooksPath .githooks';
exec($command, $output, $status);

if ($status !== 0) {
    fwrite(STDERR, "Unable to configure core.hooksPath.\n");
    exit($status);
}

fwrite(STDOUT, "Git hooks configured for {$expectedProject} at {$gitRoot}.\n");
