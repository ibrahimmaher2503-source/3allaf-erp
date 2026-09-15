<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupplierContact extends Model
{
    public const ROLES = ['owner', 'representative', 'order', 'accounting', 'general'];

    protected $fillable = [
        'supplier_id', 'role', 'name', 'email', 'phone', 'mobile', 'whatsapp', 'notes', 'is_primary', 'status',
        'created_by', 'updated_by', 'lock_version',
    ];

    protected $casts = ['is_primary' => 'boolean', 'lock_version' => 'integer'];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
