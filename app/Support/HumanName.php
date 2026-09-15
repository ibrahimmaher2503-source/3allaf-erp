<?php

declare(strict_types=1);

namespace App\Support;

final class HumanName
{
    public static function name(object|null $record, ?string $locale = null): string
    {
        if ($record === null) {
            return __('Incomplete data');
        }
        $locale ??= app()->getLocale();
        $nameLocale = str_starts_with($locale, 'ar') ? 'ar' : 'en';
        $primary = trim((string) ($record->{'name_'.$nameLocale} ?? ''));
        $alternate = trim((string) ($record->{$nameLocale === 'ar' ? 'name_en' : 'name_ar'} ?? ''));
        if ($primary !== '') {
            return $primary;
        }
        if ($alternate !== '') {
            return $alternate;
        }

        return __('Incomplete data');
    }

    public static function code(object|null $record): string
    {
        return trim((string) ($record->item_code ?? $record->code ?? ''));
    }

    public static function text(object|null $record, ?string $locale = null): string
    {
        $name = self::name($record, $locale);
        $code = self::code($record);

        return $code === '' ? $name : $name.' — '.$code;
    }
}
