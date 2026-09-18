<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Support;

use Illuminate\Support\Facades\Log;

final class InventoryMovementLabel
{
    private const LABELS = [
        'opening_balance' => 'Opening inventory', 'opening_inventory' => 'Opening inventory', 'opening_inventory_reversal' => 'Opening inventory reversal',
        'purchase_distribution_in' => 'Purchase distribution in', 'purchase_distribution_out' => 'Purchase distribution out',
        'purchase_receipt' => 'Purchase receipt', 'purchase_receipt_reversal' => 'Purchase receipt reversal',
        'purchase_return' => 'Purchase return', 'purchase_return_reversal' => 'Purchase return reversal',
        'transfer_dispatch' => 'Transfer dispatch', 'transfer_receipt' => 'Transfer receipt',
        'transfer_out' => 'Transfer out', 'transfer_in' => 'Transfer in',
        'inventory_entry' => 'Inventory entry', 'inventory_exit' => 'Inventory exit',
        'inventory_adjustment' => 'Inventory adjustment', 'inventory_reversal' => 'Inventory reversal',
        'count_reconciliation' => 'Count reconciliation', 'retail_sale' => 'Retail sale',
        'sale' => 'Sale', 'sale_reversal' => 'Sale reversal', 'retail_return' => 'Retail return',
        'retail_return_damaged' => 'Damaged retail return', 'retail_exchange_out' => 'Retail exchange out',
        'party_consumable_issue' => 'Party consumable issue', 'party_consumable_return' => 'Party consumable return',
    ];

    public static function label(?string $value): string
    {
        $key = strtolower(trim((string) $value));
        if (isset(self::LABELS[$key])) return __(self::LABELS[$key]);
        if ($key !== '') Log::warning('Unknown inventory movement type encountered in presentation.', ['movement_type' => $key]);
        return __('Unknown movement');
    }

    public static function options(): array
    {
        return collect(self::LABELS)->map(fn (string $label): string => __($label))->all();
    }
}
