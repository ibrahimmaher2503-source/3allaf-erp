<?php

declare(strict_types=1);

namespace App\Modules\Customer\Support;

use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class CustomerPolicy
{
    /** @return array{value: string, version: int} */
    public static function requiredText(string $key): array
    {
        $record = CustomerPolicySettingVersion::query()->where('key', $key)->latest('version')->first();
        $value = trim((string) $record?->value);
        if ($record === null || $value === '') {
            throw new InvalidArgumentException(__('The required policy value :key is not configured.', ['key' => $key]));
        }

        return ['value' => $value, 'version' => (int) $record->version];
    }

    /** @return array{value: array<string, mixed>, version: int} */
    public static function requiredObject(string $key): array
    {
        $record = self::requiredText($key);
        $decoded = json_decode($record['value'], true);
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidArgumentException(__('The policy value :key must be a JSON object.', ['key' => $key]));
        }

        return ['value' => $decoded, 'version' => $record['version']];
    }

    /** @return array{value: list<string>, version: int} */
    public static function allowedPurposes(string $key): array
    {
        $record = self::requiredText($key);
        $decoded = json_decode($record['value'], true);
        if (! is_array($decoded) || ! array_is_list($decoded) || $decoded === []) {
            throw new InvalidArgumentException(__('The policy value :key must be a non-empty JSON list of purposes.', ['key' => $key]));
        }

        $purposes = array_values(array_filter(array_map(static fn (mixed $purpose): string => trim((string) $purpose), $decoded)));
        if ($purposes === [] || count($purposes) !== count($decoded)) {
            throw new InvalidArgumentException(__('The policy value :key contains an invalid purpose.', ['key' => $key]));
        }

        return ['value' => $purposes, 'version' => $record['version']];
    }

    /** @return array{version: string, text: string, setting_version: int} */
    public static function consentWording(): array
    {
        $record = self::requiredObject('customer.consent.wording');
        $version = trim((string) ($record['value']['version'] ?? ''));
        $text = trim((string) ($record['value']['text'] ?? ''));
        if ($version === '' || $text === '') {
            throw new InvalidArgumentException(__('Consent wording must include a version and text.'));
        }

        return ['version' => $version, 'text' => $text, 'setting_version' => $record['version']];
    }

    /** @return array{days: int, version: int} */
    public static function retentionPolicy(): array
    {
        $record = self::requiredObject('customer.consent.retention');
        $days = filter_var($record['value']['days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($days === false) {
            throw new InvalidArgumentException(__('Consent retention must define a non-negative number of days.'));
        }

        return ['days' => (int) $days, 'version' => $record['version']];
    }

    /** @return array{value: string, version: int} */
    public static function phoneNormalization(): array
    {
        $record = self::requiredText('customer.phone_normalization');
        if ($record['value'] !== 'digits_only') {
            throw new InvalidArgumentException(__('The configured phone normalization policy is unsupported.'));
        }

        return $record;
    }

    /** @return array{value: list<string>, version: int} */
    public static function childPurposes(): array
    {
        try {
            return self::allowedPurposes('customer.children.purpose_scope');
        } catch (InvalidArgumentException) {
            return self::allowedPurposes('customer.consent.purpose');
        }
    }

    /** @return array{value: array<string, mixed>, version: int} */
    public static function loyaltyRule(string $activity): array
    {
        $key = $activity === 'party' ? 'loyalty.party_rule' : 'loyalty.retail_rule';
        $record = self::requiredObject($key);
        $earnRate = (string) ($record['value']['earn_points_per_currency'] ?? '');
        $redeemValue = (string) ($record['value']['redeem_currency_per_point'] ?? '');
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,8})?$/', $earnRate) || bccomp($earnRate, '0', 8) <= 0) {
            throw new InvalidArgumentException(__('The loyalty rule :key must define a positive earn rate.', ['key' => $key]));
        }
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,8})?$/', $redeemValue) || bccomp($redeemValue, '0', 8) <= 0) {
            throw new InvalidArgumentException(__('The loyalty rule :key must define a positive redemption value.', ['key' => $key]));
        }

        $record['value']['earn_points_per_currency'] = $earnRate;
        $record['value']['redeem_currency_per_point'] = $redeemValue;

        return $record;
    }

    /** @return array{days: int, version: int} */
    public static function loyaltyExpiry(): array
    {
        $record = self::requiredObject('loyalty.expiry_policy');
        $days = filter_var($record['value']['days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($days === false) {
            throw new InvalidArgumentException(__('Loyalty expiry must define a positive number of days.'));
        }

        return ['days' => (int) $days, 'version' => $record['version']];
    }

    public static function assertRounding(): int
    {
        $record = self::requiredObject('loyalty.rounding_policy');
        if (($record['value']['earn'] ?? null) !== 'floor' || ($record['value']['redeem'] ?? null) !== 'floor') {
            throw new InvalidArgumentException(__('Loyalty rounding must be configured as floor for earn and redeem.'));
        }

        return $record['version'];
    }

    public static function assertApproval(): int
    {
        $record = self::requiredObject('loyalty.approval_policy');
        if (($record['value']['adjustment_requires_approval'] ?? null) !== true) {
            throw new InvalidArgumentException(__('Loyalty adjustments require the configured approval and separation policy.'));
        }

        return $record['version'];
    }

    public static function assertLedgerIntegrity(): int
    {
        $record = self::requiredObject('loyalty.ledger_integrity');
        if (($record['value']['enabled'] ?? null) !== true) {
            throw new InvalidArgumentException(__('Loyalty ledger integrity must be enabled before posting.'));
        }

        return $record['version'];
    }

    /** @return array{version: string, text: string, retention_until: CarbonImmutable} */
    public static function consentSnapshot(): array
    {
        $wording = self::consentWording();
        $retention = self::retentionPolicy();

        return [
            'version' => $wording['version'],
            'text' => $wording['text'],
            'retention_until' => CarbonImmutable::now()->addDays($retention['days']),
        ];
    }
}
