<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use LogicException;

final class ProductStoreAssignment extends Model
{
    protected $fillable = ['company_id', 'branch_id', 'store_id', 'product_id', 'status', 'created_by', 'updated_by'];

    protected static function booted(): void
    {
        self::saving(function (self $assignment): void {
            if ($assignment->exists && $assignment->isDirty(['company_id', 'branch_id', 'store_id', 'product_id'])) {
                throw new LogicException('Product assortment context is immutable; deactivate and create the correct assignment.');
            }
            $store = Store::query()->with('branch:id,company_id')->find($assignment->store_id);
            if ($store === null || (int) $store->company_id !== (int) $assignment->company_id || (int) $store->branch_id !== (int) $assignment->branch_id || (int) $store->branch?->company_id !== (int) $assignment->company_id || ! in_array($store->type, ['warehouse', 'selling'], true)) {
                throw ValidationException::withMessages(['store_id' => __('The assortment company, branch, and inventory location must match exactly.')]);
            }
            if (! in_array($assignment->status, ['active', 'inactive'], true)) {
                throw ValidationException::withMessages(['status' => __('The selected assortment status is invalid.')]);
            }
        });
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
