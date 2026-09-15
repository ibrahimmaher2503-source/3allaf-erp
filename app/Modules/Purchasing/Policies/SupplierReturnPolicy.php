<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Policies;

use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Purchasing\Models\FinancialSettingVersion;

final class SupplierReturnPolicy
{
    public function value(string $key): ?string
    {
        $setting = FinancialSettingVersion::query()
            ->where('key', $key)
            ->where('effective_from', '<=', now())
            ->where(static fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
            ->whereHas('approvalRecord', static fn ($query) => $query->where('approval_state', ApprovalState::Approved))
            ->orderByDesc('version')
            ->first();

        return $setting?->value === null ? null : trim($setting->value);
    }

    public function numberPrefix(): ?string
    {
        $value = $this->value('purchasing.supplier_return.number_prefix') ?? $this->value('supplier_return.number_prefix');

        return $value === null || preg_match('/^[A-Z0-9][A-Z0-9_-]{1,24}$/', $value) !== 1 ? null : $value;
    }

    public function approvalLimit(): ?string
    {
        $value = $this->value('purchasing.supplier_return.approval_limit') ?? $this->value('supplier_return.approval_limit');

        return $value !== null && preg_match('/^\d+(?:\.\d{1,4})?$/', $value) === 1 ? $value : null;
    }

    public function printTitle(): string
    {
        return $this->value('purchasing.supplier_return.print_title') ?: __('Supplier Return');
    }

    public function printFooter(): string
    {
        return $this->value('purchasing.supplier_return.print_footer') ?: __('Cost source: original approved purchase invoice line. No WAC fallback.');
    }
}
