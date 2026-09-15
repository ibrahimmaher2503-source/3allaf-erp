<?php

namespace App\Modules\Pricing\Services;

use App\Modules\Retail\Support\DecimalMoney;
use Illuminate\Validation\ValidationException;

final class OpenPricePolicy
{
    /**
     * Return whether a configured deviation requires an independent approval.
     * A missing limit means the owner has not enabled this extra branch; it
     * never weakens the permission, bounds, reason, or stale-policy checks.
     */
    public function requiresApproval(string $referenceAmount, string $requestedAmount, ?string $approvalLimitPercent): bool
    {
        if ($approvalLimitPercent === null || trim($approvalLimitPercent) === '') {
            return false;
        }

        $referenceAmount = DecimalMoney::normalize($referenceAmount, 4);
        $requestedAmount = DecimalMoney::normalize($requestedAmount, 4);
        $approvalLimitPercent = DecimalMoney::normalize($approvalLimitPercent, 4);

        if (bccomp($referenceAmount, '0', 4) <= 0 || bccomp($approvalLimitPercent, '0', 4) < 0) {
            throw new \InvalidArgumentException(__('Open-price approval policy values are invalid.'));
        }

        $delta = bccomp($requestedAmount, $referenceAmount, 4) < 0
            ? bcsub($referenceAmount, $requestedAmount, 8)
            : bcsub($requestedAmount, $referenceAmount, 8);
        $deviationPercent = bcdiv(bcmul($delta, '100', 8), $referenceAmount, 8);

        return bccomp($deviationPercent, $approvalLimitPercent, 4) > 0;
    }

    /** @param array<string, scalar|null> $values */
    public function fingerprint(array $values): string
    {
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    /** @return array{allowed: bool, reason: string|null} */
    public function validate(
        string $referenceAmount,
        string $requestedAmount,
        ?string $minimum,
        ?string $maximum,
        bool $hasPermission,
        ?string $reason,
        bool $offline = false,
    ): array {
        if (! $hasPermission) {
            return ['allowed' => false, 'reason' => __('Open-price permission is required.')];
        }

        if ($offline) {
            return ['allowed' => false, 'reason' => __('Open-price selling is blocked while offline.')];
        }

        if ($minimum === null || $maximum === null || trim($referenceAmount) === '') {
            return ['allowed' => false, 'reason' => __('Open-price bounds are pending owner configuration.')];
        }

        try {
            $referenceAmount = DecimalMoney::normalize($referenceAmount, 4);
            $requestedAmount = DecimalMoney::normalize($requestedAmount, 4);
            $minimum = DecimalMoney::normalize($minimum, 4);
            $maximum = DecimalMoney::normalize($maximum, 4);
        } catch (\InvalidArgumentException) {
            return ['allowed' => false, 'reason' => __('Open-price policy values must be valid decimal amounts.')];
        }

        if (bccomp($referenceAmount, '0', 4) <= 0 || bccomp($minimum, '0', 4) < 0 || bccomp($maximum, $minimum, 4) < 0) {
            return ['allowed' => false, 'reason' => __('Open-price policy values are invalid.')];
        }

        if (bccomp($requestedAmount, $minimum, 4) < 0 || bccomp($requestedAmount, $maximum, 4) > 0) {
            return ['allowed' => false, 'reason' => __('The requested open price is outside the configured range.')];
        }

        if (blank($reason)) {
            return ['allowed' => false, 'reason' => __('A reason is required for an open price.')];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /** @return array{allowed: bool, reason: string|null} */
    public function validateOrThrow(
        string $referenceAmount,
        string $requestedAmount,
        ?string $minimum,
        ?string $maximum,
        bool $hasPermission,
        ?string $reason,
        bool $offline = false,
    ): array {
        $result = $this->validate($referenceAmount, $requestedAmount, $minimum, $maximum, $hasPermission, $reason, $offline);
        if (! $result['allowed']) {
            throw ValidationException::withMessages(['open_price' => $result['reason']]);
        }

        return $result;
    }
}
