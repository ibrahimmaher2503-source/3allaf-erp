<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BarcodeSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_code',
        'next_serial',
    ];

    protected $casts = [
        'next_serial' => 'integer',
    ];
}
