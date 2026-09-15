<?php

declare(strict_types=1);

namespace App\Modules\Customer\Support;

use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class CustomerIdentity
{
    /** @return array{0:?string,1:?string} */
    public static function normalizedPhones(string $primary, ?string $secondary = null): array
    {
        $normalizedPrimary = filled($primary) ? PhoneNormalizer::normalize($primary) : null;
        $normalizedSecondary = filled($secondary) ? PhoneNormalizer::normalize((string) $secondary) : null;
        if ($normalizedSecondary !== null && $normalizedSecondary === $normalizedPrimary) {
            throw new InvalidArgumentException(__('Primary and secondary phone numbers must be different.'));
        }

        return [$normalizedPrimary, $normalizedSecondary];
    }

    public static function assertAvailable(?string $primary, ?string $secondary = null, ?int $exceptCustomerId = null): void
    {
        $phones = array_values(array_filter([$primary, $secondary]));
        if ($phones === []) {
            return;
        }
        $duplicate = Customer::query()
            ->when($exceptCustomerId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptCustomerId))
            ->where(function (Builder $query) use ($phones): void {
                $query->whereIn('phone_normalized', $phones)->orWhereIn('secondary_phone_normalized', $phones);
            })->lockForUpdate()->exists();
        if ($duplicate) {
            throw new InvalidArgumentException(__('A customer already exists for one of these phone numbers. Review the existing profile instead of creating a duplicate.'));
        }
    }
}
