<?php

declare(strict_types=1);

namespace App\Modules\Retail\Services;

use App\Models\User;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use InvalidArgumentException;

/**
 * POS-05 non-stacking, enforced in the service (DEC-066 / POSF-04).
 *
 * `docs/48` §4 is explicit that a stack prevented only by a disabled button is
 * not enforcement. Every discount application routes through here.
 *
 * DEC-066 resolved POSF-04 to *replacement*: applying a second discount to the
 * same amount replaces the first as an explicit, audited choice. Discounts are
 * never summed.
 */
final class DiscountPolicy
{
    public const TYPE_LINE = 'line';

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_CUSTOMER_GROUP = 'customer_group';

    /**
     * Types that compete for the same amount and therefore cannot coexist on it.
     *
     * @var list<string>
     */
    private const COMPETING_TYPES = [self::TYPE_LINE, self::TYPE_INVOICE, self::TYPE_CUSTOMER_GROUP];

    /**
     * Decide what happens when `$newType` is applied to an amount that already
     * carries `$existingType`.
     *
     * @return array{action: 'apply'|'replace', replaces: ?string}
     */
    public function resolveApplication(?string $existingType, string $newType): array
    {
        $this->assertKnownType($newType);

        if ($existingType === null) {
            return ['action' => 'apply', 'replaces' => null];
        }

        $this->assertKnownType($existingType);

        // POSF-04: replace, never sum.
        return ['action' => 'replace', 'replaces' => $existingType];
    }

    /**
     * A discount above the configured limit requires an approval bound to the
     * invoice (`docs/48` §4).
     *
     * The limit is an owner value. When it is unset we cannot silently permit
     * an arbitrary discount, so any non-zero discount requires approval.
     *
     * @param  numeric-string  $discountAmount
     * @param  numeric-string  $baseAmount
     */
    public function requiresApproval(string $discountAmount, string $baseAmount): bool
    {
        if (bccomp($discountAmount, '0', 2) <= 0) {
            return false;
        }

        if (bccomp($baseAmount, '0', 2) <= 0) {
            return true;
        }

        $limit = PosFinancialSettingRegistry::numericValue(PosFinancialSettingRegistry::DISCOUNT_APPROVAL_LIMIT);
        if ($limit === null) {
            return true;
        }

        $percent = bcdiv(bcmul($discountAmount, '100', 4), $baseAmount, 4);

        return bccomp($percent, $limit, 4) > 0;
    }

    /**
     * @param  numeric-string  $discountAmount
     * @param  numeric-string  $baseAmount
     * @return array{
     *     discount_amount: numeric-string,
     *     discount_type: string,
     *     discount_reason: ?string,
     *     discount_applied_by: int,
     *     discount_replaced_by: ?int,
     *     discount_replaced_at: ?string
     * }
     */
    public function buildLineDiscount(
        User $actor,
        string $discountAmount,
        string $baseAmount,
        string $newType,
        ?string $existingType = null,
        ?string $reason = null,
        bool $approved = false,
    ): array {
        $resolution = $this->resolveApplication($existingType, $newType);

        if ($resolution['action'] === 'replace' && ($reason === null || trim($reason) === '')) {
            throw new InvalidArgumentException(__('Replacing an existing discount requires a reason.'));
        }

        if ($this->requiresApproval($discountAmount, $baseAmount) && ! $approved) {
            throw new InvalidArgumentException(__('This discount exceeds the approved limit and requires an approval bound to this sale.'));
        }

        return [
            'discount_amount' => $discountAmount,
            'discount_type' => $newType,
            'discount_reason' => $reason,
            'discount_applied_by' => $actor->id,
            'discount_replaced_by' => $resolution['action'] === 'replace' ? $actor->id : null,
            'discount_replaced_at' => $resolution['action'] === 'replace' ? now()->toDateTimeString() : null,
        ];
    }

    private function assertKnownType(string $type): void
    {
        if (! in_array($type, [self::TYPE_LINE, self::TYPE_INVOICE, self::TYPE_CUSTOMER_GROUP], true)) {
            throw new InvalidArgumentException(__('Unknown discount type.'));
        }
    }
}
