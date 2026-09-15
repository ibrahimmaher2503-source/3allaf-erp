<?php

namespace App\Modules\Platform\Support;

final class DocumentTypeCatalog
{
    /** @var array<string, array{label: string, code: string}> */
    public const NUMBERING_TYPES = [
        'retail_sale' => ['label' => 'Sales invoice', 'code' => 'SI'],
        'retail_return' => ['label' => 'Sales return', 'code' => 'SR'],
        'retail_exchange' => ['label' => 'Sales exchange', 'code' => 'SE'],
        'gift_receipt' => ['label' => 'Gift receipt', 'code' => 'GR'],
        'shift_close' => ['label' => 'Shift closing', 'code' => 'SH'],
        'barcode_label' => ['label' => 'Barcode label', 'code' => 'BL'],
        'purchase_order' => ['label' => 'Purchase order', 'code' => 'PO'],
        'purchase_invoice' => ['label' => 'Purchase invoice', 'code' => 'PI'],
        'supplier_return' => ['label' => 'Supplier return', 'code' => 'PR'],
        'stock_transfer' => ['label' => 'Stock transfer', 'code' => 'ST'],
        'inventory_adjustment' => ['label' => 'Inventory adjustment', 'code' => 'IA'],
        'opening_inventory' => ['label' => 'Opening inventory', 'code' => 'OI'],
        'stock_count' => ['label' => 'Stock count', 'code' => 'SC'],
        'quotation' => ['label' => 'Quotation', 'code' => 'QT'],
        'party_booking' => ['label' => 'Party booking', 'code' => 'PB'],
        'party_invoice' => ['label' => 'Party invoice draft', 'code' => 'PID'],
        'party_payment_receipt' => ['label' => 'Party payment receipt', 'code' => 'PPR'],
        'party_final_invoice' => ['label' => 'Party invoice', 'code' => 'PFI'],
        'party_final_receipt' => ['label' => 'Party payment receipt', 'code' => 'PPR'],
        'party_operating_order' => ['label' => 'Party operating order', 'code' => 'POO'],
    ];

    /** @var array<string, string> */
    public const PRINT_TYPES = [
        'sales_invoice' => 'Sales invoice',
        'sales_return' => 'Sales return',
        'gift_receipt' => 'Gift receipt',
        'shift_closing' => 'Shift closing',
        'barcode_label' => 'Barcode label',
        'purchase_order' => 'Purchase order',
        'purchase_invoice' => 'Purchase invoice',
        'supplier_return' => 'Supplier return',
        'stock_transfer' => 'Stock transfer',
        'inventory_adjustment' => 'Inventory adjustment',
        'stock_count' => 'Stock count',
        'quotation' => 'Quotation',
        'party_booking' => 'Party booking',
        'party_invoice' => 'Party invoice',
        'party_payment_receipt' => 'Party payment receipt',
    ];

    public static function numberingLabel(string $type): string
    {
        return __(self::NUMBERING_TYPES[$type]['label'] ?? str($type)->headline()->toString());
    }

    public static function printLabel(string $type): string
    {
        return __(self::PRINT_TYPES[$type] ?? str($type)->headline()->toString());
    }

    public static function prefix(string $branchCode, string $type): string
    {
        $code = self::NUMBERING_TYPES[$type]['code'] ?? strtoupper(substr($type, 0, 3));

        return strtoupper(trim($branchCode)).'-'.$code.'-';
    }
}
