<?php

declare(strict_types=1);

namespace App\Support\DataExchange;

use InvalidArgumentException;

final class ImportWorkbook
{
    public const DATA_SHEET = 'Data Entry';

    /** @return object */
    public static function dataSheet(object $reader): object
    {
        foreach ($reader->getSheetIterator() as $sheet) {
            if (strcasecmp(trim((string) $sheet->getName()), self::DATA_SHEET) === 0) {
                return $sheet;
            }
        }

        throw new InvalidArgumentException(__('The workbook does not contain the required Data Entry sheet.'));
    }

    public static function assertMetadata(object $reader, string $expectedSchema, ?string $companyCode = null): void
    {
        $metadata = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            if (strcasecmp(trim((string) $sheet->getName()), 'Instructions') !== 0) continue;
            $metadata = [];
            foreach ($sheet->getRowIterator() as $row) {
                $metadata[] = array_map(static fn ($cell): string => trim((string) ($cell->getValue() ?? '')), $row->getCells());
                if (count($metadata) >= 4) break;
            }
            break;
        }
        if (($metadata[1][1] ?? null) !== $expectedSchema) {
            throw new InvalidArgumentException(__('The workbook schema identity does not match the current import template.'));
        }
        if ($companyCode !== null && ! str_starts_with((string) ($metadata[3][1] ?? ''), $companyCode.' ')) {
            throw new InvalidArgumentException(__('The workbook belongs to a different company scope. Download a fresh template.'));
        }
    }

    /** @param list<mixed> $values @param list<string> $expected */
    public static function assertHeaders(array $values, array $expected, string $message): void
    {
        $actual = array_map(static fn (mixed $value): string => strtolower(trim((string) $value)), array_slice($values, 0, count($expected)));
        if ($actual !== $expected || count(array_filter(array_slice($values, count($expected)), static fn (mixed $value): bool => trim((string) $value) !== '')) > 0) {
            throw new InvalidArgumentException($message);
        }
    }
}
