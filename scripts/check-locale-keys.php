<?php

declare(strict_types=1);

$root = realpath(__DIR__.'/..');
$locales = [
    'ar' => $root.'/lang/ar.json',
    'en' => $root.'/lang/en.json',
    'ar-EG' => $root.'/lang/ar-EG.json',
];
$keysByLocale = [];

foreach ($locales as $locale => $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "Missing locale file: {$path}\n");
        exit(1);
    }

    try {
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fwrite(STDERR, "Invalid {$locale}.json: {$exception->getMessage()}\n");
        exit(1);
    }

    $keysByLocale[$locale] = [];
    collectKeys($decoded, '', $keysByLocale[$locale]);
    sort($keysByLocale[$locale]);
}

$requiredKeys = array_values(array_unique([
    ...$keysByLocale['ar'],
    ...$keysByLocale['en'],
]));
sort($requiredKeys);
$missing = array_values(array_diff($requiredKeys, $keysByLocale['ar-EG']));
$unexpected = array_values(array_diff($keysByLocale['ar-EG'], $requiredKeys));

if ($missing !== [] || $unexpected !== []) {
    if ($missing !== []) {
        fwrite(STDERR, "Keys missing from ar-EG.json:\n- ".implode("\n- ", $missing)."\n");
    }
    if ($unexpected !== []) {
        fwrite(STDERR, "Unexpected keys in ar-EG.json:\n- ".implode("\n- ", $unexpected)."\n");
    }
    exit(1);
}

printf(
    "Egyptian Arabic locale key parity: PASS (%d required keys; ar=%d, en=%d, ar-EG=%d)\n",
    count($requiredKeys),
    count($keysByLocale['ar']),
    count($keysByLocale['en']),
    count($keysByLocale['ar-EG']),
);

function collectKeys(mixed $value, string $prefix, array &$keys): void
{
    if (! is_array($value)) {
        if ($prefix !== '') {
            $keys[] = $prefix;
        }

        return;
    }

    foreach ($value as $key => $child) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
        collectKeys($child, $path, $keys);
    }
}
