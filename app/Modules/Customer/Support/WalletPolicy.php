<?php

declare(strict_types=1);

namespace App\Modules\Customer\Support;

use App\Modules\Platform\Models\Store;
use InvalidArgumentException;

/**
 * Resolves owner-controlled wallet policy without supplying financial
 * defaults. Product and Party Wallet call this class with different keys,
 * while their ledgers and authorization remain physically separate.
 */
final class WalletPolicy
{
    /** @return array{wallet: string, credit_limit: string, debt_limit: string, settlement_version: int, visibility_version: int} */
    public static function for(string $wallet): array
    {
        $prefix = self::prefix($wallet);
        $credit = self::requiredDecimal($prefix.'.credit_limit');
        $debt = self::requiredDecimal($prefix.'.debt_limit');
        $settlement = self::requiredObject($prefix.'.settlement_policy');
        $visibility = self::requiredObject($prefix.'.visibility_scope');

        if (($settlement['value']['enabled'] ?? null) !== true) {
            throw new InvalidArgumentException(__('The :wallet Wallet settlement policy is not enabled.', ['wallet' => self::label($wallet)]));
        }
        $operations = $settlement['value']['operations'] ?? null;
        if (! is_array($operations) || array_is_list($operations) === false || $operations === []) {
            throw new InvalidArgumentException(__('The :wallet Wallet settlement policy must list enabled operations.', ['wallet' => self::label($wallet)]));
        }
        $operations = array_values(array_unique(array_map(static fn (mixed $operation): string => trim((string) $operation), $operations)));
        if (array_diff($operations, ['credit', 'debit', 'settlement']) !== []) {
            throw new InvalidArgumentException(__('The :wallet Wallet settlement policy contains an unsupported operation.', ['wallet' => self::label($wallet)]));
        }
        if (($visibility['value']['mode'] ?? null) !== 'branch_store') {
            throw new InvalidArgumentException(__('The :wallet Wallet visibility scope must be branch_store.', ['wallet' => self::label($wallet)]));
        }

        return [
            'wallet' => $wallet,
            'credit_limit' => $credit['value'],
            'debt_limit' => $debt['value'],
            'settlement_version' => $settlement['version'],
            'visibility_version' => $visibility['version'],
            'operations' => $operations,
        ];
    }

    /** @return array{value: string, version: int} */
    public static function requiredDecimal(string $key): array
    {
        $record = CustomerPolicy::requiredText($key);
        $value = trim($record['value']);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', $value)) {
            throw new InvalidArgumentException(__('The wallet policy value :key must be a non-negative decimal with up to four places.', ['key' => $key]));
        }

        return ['value' => bcadd($value, '0', 4), 'version' => $record['version']];
    }

    /** @return array{value: array<string, mixed>, version: int} */
    public static function requiredObject(string $key): array
    {
        return CustomerPolicy::requiredObject($key);
    }

    public static function assertOperation(array $policy, string $operation): void
    {
        if (! in_array($operation, $policy['operations'], true)) {
            throw new InvalidArgumentException(__('The configured :wallet Wallet policy does not allow :operation.', ['wallet' => self::label($policy['wallet']), 'operation' => $operation]));
        }
    }

    public static function assertAdjustmentEnabled(string $wallet): int
    {
        $record = self::requiredObject(self::prefix($wallet).'.adjustment_policy');
        if (($record['value']['enabled'] ?? null) !== true || ($record['value']['approval_required'] ?? null) !== true) {
            throw new InvalidArgumentException(__('The :wallet Wallet adjustment policy must be enabled and require approval.', ['wallet' => self::label($wallet)]));
        }

        return $record['version'];
    }

    public static function assertBalance(array $policy, string $balance): void
    {
        $balance = trim($balance);
        if (! preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', $balance)) {
            throw new InvalidArgumentException(__('Wallet balances must be decimal values with up to four places.'));
        }
        $balance = bcadd($balance, '0', 4);
        if (bccomp($balance, $policy['credit_limit'], 4) > 0) {
            throw new InvalidArgumentException(__('The resulting :wallet Wallet balance exceeds its configured credit limit.', ['wallet' => self::label($policy['wallet'])]));
        }
        if (bccomp($balance, '-'.$policy['debt_limit'], 4) < 0) {
            throw new InvalidArgumentException(__('The resulting :wallet Wallet balance exceeds its configured debt limit.', ['wallet' => self::label($policy['wallet'])]));
        }
    }

    public static function decimal(string $value, bool $allowNegative = false): string
    {
        $value = trim($value);
        $pattern = $allowNegative
            ? '/^-?(?:0|[1-9]\d*)(?:\.\d{1,4})?$/'
            : '/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/';
        if (! preg_match($pattern, $value) || bccomp($value, '0', 4) === 0) {
            throw new InvalidArgumentException(__('Wallet amounts must be non-zero decimals with up to four places.'));
        }

        return bcadd($value, '0', 4);
    }

    public static function currencyCode(Store $store): string
    {
        $store->loadMissing('company');
        $currency = strtoupper(trim((string) $store->company?->currency_code));
        if ($currency === '' || $currency === 'TBD') {
            throw new InvalidArgumentException(__('The company currency must be configured before a wallet mutation can be posted.'));
        }

        return $currency;
    }

    /** @param array<string, mixed> $payload */
    public static function payloadHash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function prefix(string $wallet): string
    {
        if (! in_array($wallet, ['product', 'party'], true)) {
            throw new InvalidArgumentException('Unsupported wallet type.');
        }

        return 'wallet.'.$wallet;
    }

    private static function label(string $wallet): string
    {
        return $wallet === 'party' ? 'Party' : 'Product';
    }
}
