<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

final class AuditLogPresentation
{
    /** @var array<string,string> */
    private const EVENTS = [
        'create_store' => 'Store created',
        'update_store' => 'Store updated',
        'delete_store' => 'Store archived',
        'toggle_store_status' => 'Store status changed',
        'map_branch_selling_store' => 'Branch point of sale mapped',
        'create_branch' => 'Branch created',
        'update_branch' => 'Branch updated',
        'delete_branch' => 'Branch archived',
        'toggle_branch_status' => 'Branch status changed',
        'create_payment_method' => 'Payment method created',
        'update_payment_method' => 'Payment method updated',
        'update_local_settings' => 'Local settings updated',
        'create_cash_drawer' => 'Cash drawer created',
        'update_cash_drawer' => 'Cash drawer updated',
        'delete_cash_drawer' => 'Cash drawer archived',
        'toggle_cash_drawer_status' => 'Cash drawer status changed',
        'update_user_authorization' => 'User authorization updated',
        'update_role_permissions' => 'Role permissions updated',
        'create_product' => 'Product created',
        'update_product' => 'Product updated',
        'toggle_product_status' => 'Product status changed',
        'create_category' => 'Category created',
        'update_category' => 'Category updated',
        'create_brand' => 'Brand created',
        'update_brand' => 'Brand updated',
        'create_supplier' => 'Supplier created',
        'update_supplier' => 'Supplier updated',
        'activate_supplier' => 'Supplier activated',
        'deactivate_supplier' => 'Supplier deactivated',
        'approval_requested' => 'Approval requested',
        'approval_approved' => 'Approval approved',
        'approval_rejected' => 'Approval rejected',
        'approval_cancelled' => 'Approval cancelled',
        'approval_withdrawn' => 'Approval withdrawn',
        'approval_expired' => 'Approval expired',
        'attachment_stored' => 'Attachment stored',
        'attachment_linked' => 'Attachment linked',
        'attachment_accessed' => 'Attachment accessed',
        'attachment_revoked' => 'Attachment revoked',
        'finalize_sale' => 'Sale finalized',
        'open_shift' => 'Shift opened',
        'submit_shift_close' => 'Shift close submitted',
        'cash_movement' => 'Cash movement recorded',
        'price_proposal_created' => 'Price proposal created',
        'price_proposal_submitted' => 'Price proposal submitted',
        'price_version_approved' => 'Price version approved',
        'price_version_rejected' => 'Price version rejected',
    ];

    /** @var array<string,string> */
    private const SOURCES = [
        'Store' => 'Store',
        'BranchSellingStore' => 'Branch point of sale mapping',
        'PaymentMethod' => 'Payment method',
        'Company' => 'Company',
        'Branch' => 'Branch',
        'CashDrawer' => 'Cash drawer',
        'User' => 'User',
        'Role' => 'Role',
        'Product' => 'Product',
        'Category' => 'Category',
        'Brand' => 'Brand',
        'Supplier' => 'Supplier',
        'AuditLog' => 'Audit log',
        'ApprovalRecord' => 'Approval record',
        'PrinterConfiguration' => 'Printer profile',
        'DocumentSequence' => 'Document sequence',
        'TaxSetting' => 'Tax setting',
    ];

    public function event(string $event): string
    {
        return __(self::EVENTS[$event] ?? 'Other audit event');
    }

    public function source(?string $sourceType): string
    {
        $name = class_basename((string) $sourceType);

        return __(self::SOURCES[$name] ?? 'Other source record');
    }

    public function category(string $category): string
    {
        return __(match ($category) {
            'master_data' => 'Master data',
            'platform' => 'Platform',
            'security' => 'Security',
            'inventory' => 'Inventory',
            'purchasing' => 'Purchasing',
            'pricing' => 'Pricing',
            'retail' => 'Retail',
            'customer' => 'Customers',
            'party' => 'Parties',
            default => 'Other audit category',
        });
    }
}
