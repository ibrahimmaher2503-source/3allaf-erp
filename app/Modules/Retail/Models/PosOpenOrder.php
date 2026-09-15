<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PosOpenOrder extends Model
{
    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'store_id', 'cash_drawer_id', 'shift_id', 'cashier_id',
        'customer_id', 'completed_sale_id', 'sequence_number', 'status', 'checkout_token',
        'tax_applicable', 'payment_mode', 'notes', 'subtotal', 'discount_total', 'tax_total', 'total',
        'revision', 'last_activity_at', 'cancelled_at', 'completed_at',
    ];

    protected $casts = [
        'tax_applicable' => 'boolean',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'revision' => 'integer',
        'last_activity_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function cashDrawer(): BelongsTo { return $this->belongsTo(CashDrawer::class); }
    public function shift(): BelongsTo { return $this->belongsTo(PosShift::class, 'shift_id'); }
    public function cashier(): BelongsTo { return $this->belongsTo(User::class, 'cashier_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function completedSale(): BelongsTo { return $this->belongsTo(Sale::class, 'completed_sale_id'); }
    public function lines(): HasMany { return $this->hasMany(PosOpenOrderLine::class)->orderBy('line_position'); }
    public function paymentDrafts(): HasMany { return $this->hasMany(PosOpenOrderPayment::class)->orderBy('line_position'); }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }
}
