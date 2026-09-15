<?php

declare(strict_types=1);

namespace App\Support\DataExchange;

use RuntimeException;

final class ArabicPdfText
{
    /** @param list<string> $values @return list<string> */
    public function visualOrder(array $values): array
    {
        if ($values === []) {
            return [];
        }

        $binary = '/usr/bin/fribidi';
        if (! is_executable($binary)) {
            throw new RuntimeException('The trusted local FriBidi renderer is required for Arabic PDF output.');
        }

        $process = proc_open([$binary, '--charset', 'UTF-8', '--rtl', '--nopad', '--nobreak'], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start the trusted local Arabic PDF renderer.');
        }

        $normalized = array_map(static fn (string $value): string => str_replace(["\r", "\n"], ' ', $value), $values);
        fwrite($pipes[0], implode("\n", $normalized)."\n");
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0) {
            throw new RuntimeException('Arabic PDF shaping failed'.($error === '' ? '.' : ': '.$error));
        }

        $result = explode("\n", rtrim((string) $output, "\n"));
        if (count($result) !== count($values)) {
            throw new RuntimeException('Arabic PDF shaping returned an unexpected row count.');
        }

        return $result;
    }
}
