<?php

declare(strict_types=1);

$quickCash = array_values(array_filter(array_map(
    static fn (string $value): ?int => ctype_digit(trim($value)) && (int) trim($value) > 0 ? (int) trim($value) : null,
    explode(',', (string) env('POS_CASH_QUICK_DENOMINATIONS', '20,50,100,200,500,1000')),
)));

return [
    'cash_quick_denominations' => $quickCash,
];
