<?php

namespace App\Modules\Catalog\Models;

use App\Models\User;
use App\Modules\Platform\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\SupplierAccountAdjustment;
use App\Modules\Purchasing\Models\SupplierPayment;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'barcode_prefix',
        'name_ar',
        'name_en',
        'contact_name',
        'email',
        'phone',
        'tax_number',
        'payment_terms',
        'payment_policy',
        'credit_days',
        'credit_limit',
        'commercial_registration',
        'preferred_payment_method_id',
        'settlement_method',
        'settlement_other_description',
        'address',
        'notes',
        'status',
        'supplier_group_id',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'lock_version' => 'integer',
        'credit_days' => 'integer',
        'credit_limit' => 'decimal:4',
    ];

    public function productSuppliers(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }

    /** @return BelongsTo<SupplierGroup, $this> */
    public function supplierGroup(): BelongsTo
    {
        return $this->belongsTo(SupplierGroup::class, 'supplier_group_id');
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function preferredPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'preferred_payment_method_id');
    }

    /** @return HasMany<SupplierContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    /** @return HasMany<SupplierCommunicationDestination, $this> */
    public function communicationDestinations(): HasMany
    {
        return $this->hasMany(SupplierCommunicationDestination::class);
    }

    /** @return HasMany<PurchaseInvoice, $this> */
    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    /** @return HasMany<PurchaseReturn, $this> */
    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    /** @return HasMany<SupplierPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /** @return HasMany<SupplierAccountAdjustment, $this> */
    public function accountAdjustments(): HasMany
    {
        return $this->hasMany(SupplierAccountAdjustment::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_suppliers')
            ->withPivot([
                'supplier_item_code',
                'is_preferred',
                'last_purchase_price',
                'last_purchase_date',
                'notes',
                'created_by',
                'updated_by',
            ])
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
