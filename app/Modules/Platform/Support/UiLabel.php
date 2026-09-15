<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use Illuminate\Support\Str;

final class UiLabel
{
    /** @var array<string, string> */
    private const STATUS_LABELS = [
        'active' => 'Active',
        'approved' => 'Approved',
        'archived' => 'Archived',
        'assigned' => 'Assigned',
        'cancelled' => 'Cancelled',
        'closed' => 'Closed',
        'closing_submitted' => 'Closing submitted',
        'completed' => 'Completed',
        'count_submitted' => 'Count submitted',
        'damaged' => 'Damaged',
        'degraded' => 'Degraded',
        'difference_review' => 'Difference review',
        'draft' => 'Draft',
        'expired' => 'Expired',
        'failed' => 'Failed',
        'inactive' => 'Inactive',
        'in_progress' => 'In progress',
        'in_transit' => 'In transit',
        'maintenance' => 'Maintenance',
        'offline' => 'Offline',
        'open' => 'Open',
        'partially_received' => 'Partially received',
        'pending' => 'Pending',
        'pending_approval' => 'Pending approval',
        'queued' => 'Queued',
        'received' => 'Received',
        'reconciled' => 'Reconciled',
        'rejected' => 'Rejected',
        'reserved' => 'Reserved',
        'review' => 'In Review',
        'sent' => 'Sent',
        'submitted' => 'Submitted',
        'suspended' => 'Suspended',
        'void' => 'Voided',
        'purchase_distribution_in' => 'Purchase distribution in',
        'purchase_distribution_out' => 'Purchase distribution out',
        'purchase_receipt' => 'Purchase receipt',
        'purchase_receipt_reversal' => 'Purchase receipt reversal',
        'purchase_return' => 'Purchase return',
        'purchase_return_reversal' => 'Purchase return reversal',
        'sale' => 'Sale',
        'sale_reversal' => 'Sale reversal',
        'transfer_out' => 'Transfer out',
        'transfer_in' => 'Transfer in',
    ];

    public static function status(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return '—';
        }

        return __(
            self::STATUS_LABELS[$normalized] ?? Str::headline(str_replace('_', ' ', $normalized)),
        );
    }
}
