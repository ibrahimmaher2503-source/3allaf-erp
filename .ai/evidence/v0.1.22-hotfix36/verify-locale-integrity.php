<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$fail = static function (string $message): never {
    fwrite(STDERR, "HOTFIX36_LOCALE_INTEGRITY=FAIL {$message}\n");
    exit(1);
};
$decode = static function (string $path) use ($fail): array {
    try {
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        $fail(basename($path).' '.$exception->getMessage());
    }
    if (! is_array($decoded)) {
        $fail(basename($path).' is not an object');
    }
    return $decoded;
};
$flatten = static function (array $values, string $prefix = '') use (&$flatten): array {
    $leaves = [];
    foreach ($values as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
        if (is_array($value)) {
            $leaves += $flatten($value, $path);
        } else {
            $leaves[$path] = (string) $value;
        }
    }
    return $leaves;
};
$tokens = static function (string $value): array {
    $patterns = [
        '/(?<![A-Za-z0-9_]):[A-Za-z_][A-Za-z0-9_]*/u',
        '/\{\{\s*[^{}]+\s*\}\}/u',
        '/%(?:\d+\$)?[-+0-9.]*[bcdeEfFgGosuxX]/',
        '/<\/?[A-Za-z][^>]*>/u',
        '~https?://[^\s<>"\']+~u',
    ];
    $found = [];
    foreach ($patterns as $pattern) {
        preg_match_all($pattern, $value, $matches);
        array_push($found, ...$matches[0]);
    }
    $found = array_values(array_unique($found));
    sort($found);
    return $found;
};
$assertTokens = static function (string $label, string $source, string $target) use ($fail, $tokens): void {
    if ($tokens($source) !== $tokens($target)) {
        $fail("protected token mismatch at {$label}");
    }
};

$ar = $decode($root.'/lang/ar.json');
$en = $decode($root.'/lang/en.json');
$egyptian = $decode($root.'/lang/ar-EG.json');
$required = array_values(array_unique([...array_keys($ar), ...array_keys($en)]));
sort($required);
$egyptianKeys = array_keys($egyptian);
sort($egyptianKeys);
if ($required !== $egyptianKeys) {
    $fail('JSON key parity mismatch');
}
foreach ($egyptian as $key => $value) {
    if (! is_string($value) || trim($value) === '') {
        $fail("empty/non-string JSON value at {$key}");
    }
    $source = $tokens($key) !== [] ? $key : (string) ($en[$key] ?? $ar[$key] ?? $key);
    $assertTokens('json:'.$key, $source, $value);
}

$corruptionMarkers = [
    'بالمصري:', 'كل عالأنواع', 'منتج بطاقة Excel استيراد', 'Limit:',
    'الامسح', 'الاعرض', 'تحتاج كمّل', 'يختاروا عملية الفترة',
    'مش محدد يختاروا عملية', 'يعملوا منتج', 'مصدر مخفي بنفسك',
    'تعطيل التأكيد مباشرةً',
];
foreach ($corruptionMarkers as $marker) {
    foreach ($egyptian as $key => $value) {
        if (str_contains($value, $marker)) {
            $fail("corruption marker {$marker} remains at {$key}");
        }
    }
}

$representative = [
    'Products' => 'المنتجات', 'Inventory' => 'المخزون',
    'Sales overview' => 'نظرة عامة على المبيعات', 'Purchasing' => 'المشتريات',
    'Alerts' => 'التنبيهات', 'Approvals' => 'الاعتمادات',
    'Purchase Orders' => 'أوامر الشراء', 'Purchase Invoices' => 'فواتير المشتريات',
    'Create PDF export' => 'إنشاء ملف PDF للتصدير',
];
foreach ($representative as $key => $expected) {
    if (($egyptian[$key] ?? null) !== $expected) {
        $fail("representative wording mismatch at {$key}");
    }
}

$phpFiles = ['auth.php', 'company.php', 'offline.php', 'pagination.php', 'passwords.php', 'validation.php'];
$phpLeafCount = 0;
foreach ($phpFiles as $file) {
    $arabic = $flatten(require $root.'/lang/ar/'.$file);
    $target = $flatten(require $root.'/lang/ar-EG/'.$file);
    if (array_keys($arabic) !== array_keys($target)) {
        $fail("PHP leaf-key parity mismatch in {$file}");
    }
    foreach ($target as $key => $value) {
        if (trim($value) === '') {
            $fail("empty PHP locale leaf at {$file}:{$key}");
        }
        $assertTokens("php:{$file}:{$key}", $arabic[$key], $value);
    }
    $phpLeafCount += count($target);
}

if (count($egyptian) !== 6115 || $phpLeafCount !== 244) {
    $fail('expected counts are not 6115 JSON and 244 PHP leaves');
}
echo 'HOTFIX36_LOCALE_INTEGRITY=PASS json=6115 php_leaves=244 parity=exact protected_tokens=exact corruption_markers=zero representative_wording=pass'.PHP_EOL;
