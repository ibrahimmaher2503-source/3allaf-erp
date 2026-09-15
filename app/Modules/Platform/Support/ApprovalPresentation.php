<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use Illuminate\Support\Str;

final class ApprovalPresentation
{
    public static function source(string $type, int|string|null $id = null): string
    {
        if ($type === 'pos_shifts') {
            return $id === null ? __('POS shifts') : __('POS shift #:id', ['id' => $id]);
        }

        $label = __(match ($type) {
            'platform_settings' => 'Platform settings',
            'pricing_labels' => 'Pricing labels',
            'purchase_invoices' => 'Purchase invoices',
            'purchase_returns' => 'Purchase returns',
            'loyalty_adjustments' => 'Loyalty adjustments',
            'product_wallet_adjustments' => 'Product Wallet adjustments',
            'party_wallet_adjustments' => 'Party Wallet adjustments',
            'pos_open_price' => 'POS open-price request',
            'pos_discount' => 'POS discount request',
            'asset_events' => 'Asset event',
            default => Str::headline(str_replace('_', ' ', $type)),
        });

        return $id === null ? $label : $label.' #'.$id;
    }

    public static function action(string $action): string
    {
        return __(match ($action) {
            'approve_close' => 'Approve shift close',
            'store_archive', 'store_delete' => 'Request archive',
            default => Str::headline(str_replace('_', ' ', $action)),
        });
    }
}
