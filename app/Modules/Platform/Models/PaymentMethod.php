<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Support\PaymentMethodSemantics;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return PaymentMethodFactory::new();
    }

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'type',
        'requires_evidence',
        'offline_eligible',
        'status',
        'policy_notes',
    ];

    protected $casts = [
        'requires_evidence' => 'boolean',
        'offline_eligible' => 'boolean',
    ];

    public function isCash(): bool
    {
        return PaymentMethodSemantics::isCashType((string) $this->getAttribute('type'));
    }
}
