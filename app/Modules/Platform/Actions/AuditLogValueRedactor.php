<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Models\AuditLog;

class AuditLogValueRedactor
{
    /** @param array<string, mixed>|null $values */
    public function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $redacted = [];

        foreach ($values as $key => $value) {
            if ($this->isAlwaysRedacted((string) $key)) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }

    /** @param array<string, mixed>|null $values */
    public function redactForViewer(?array $values, User $viewer): ?array
    {
        $values = $this->redact($values);
        if ($values === null || $viewer->is_super_admin) {
            return $values;
        }

        return $this->redactViewerSensitive($values, $viewer);
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function redactViewerSensitive(array $values, User $viewer): array
    {
        foreach ($values as $key => $value) {
            $normalized = strtolower((string) $key);
            if ($this->requiresCostPermission($normalized) && ! $viewer->hasPermission('inventory_stock_card.cost_view')) {
                $values[$key] = '[redacted:cost_permission]';

                continue;
            }
            if (str_contains($normalized, 'wallet')
                && ! $viewer->hasPermission('product_wallet.view')
                && ! $viewer->hasPermission('party_wallet.view')) {
                $values[$key] = '[redacted:wallet_permission]';

                continue;
            }
            if ($this->isCustomerSensitive($normalized) && ! $viewer->hasPermission('customers_children.view')) {
                $values[$key] = '[redacted:customer_permission]';

                continue;
            }
            if (is_array($value)) {
                $values[$key] = $this->redactViewerSensitive($value, $viewer);
            }
        }

        return $values;
    }

    private function requiresCostPermission(string $key): bool
    {
        return str_contains($key, 'cost') || str_contains($key, 'margin') || str_contains($key, 'profit');
    }

    private function isCustomerSensitive(string $key): bool
    {
        return in_array($key, ['customer_name', 'customer_phone', 'customer_email', 'phone', 'email', 'address', 'national_id'], true);
    }

    private function isAlwaysRedacted(string $key): bool
    {
        $normalizedKey = strtolower($key);

        if (in_array($normalizedKey, AuditLog::ALWAYS_REDACTED_FIELDS, true)) {
            return true;
        }

        return str_contains($normalizedKey, 'password')
            || str_contains($normalizedKey, 'secret')
            || str_contains($normalizedKey, 'token')
            || str_contains($normalizedKey, 'api_key')
            || str_contains($normalizedKey, 'authorization')
            || str_contains($normalizedKey, 'cookie')
            || str_contains($normalizedKey, 'private_key')
            || str_contains($normalizedKey, 'recovery_code')
            || str_contains($normalizedKey, 'session');
    }
}
