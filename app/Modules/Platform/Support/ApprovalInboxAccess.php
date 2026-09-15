<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;

final class ApprovalInboxAccess
{
    private const PERMISSIONS = [
        'pricing_labels.submit',
        'pricing_labels.approve',
        'purchase_orders.edit',
        'purchase_orders.approve',
        'purchase_invoices_supplier_returns.edit',
        'purchase_invoices_supplier_returns.approve',
        'purchase_returns.edit',
        'purchase_returns.approve',
        'inventory_stock_card.submit',
        'inventory_stock_card.approve',
        'stock_counts.submit',
        'stock_counts.reconcile',
        'transfers.submit',
        'transfers.approve',
        'shifts_cash_movements.submit',
        'shifts_cash_movements.approve',
        'shifts_cash_movements.reject',
        'pos_sales.open_price_approve',
        'pos_sales.discount_approve',
        'company_settings.approve',
    ];

    public function allows(User $user): bool
    {
        if ($user->status !== 'active') {
            return false;
        }

        return $user->is_super_admin
            || $user->hasPermission('audit_logs.view')
            || collect(self::PERMISSIONS)->contains(
                fn (string $permission): bool => $user->hasPermission($permission),
            );
    }
}
