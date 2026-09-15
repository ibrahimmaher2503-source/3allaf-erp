<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalProductCodeSequence extends Model
{
    protected $fillable = ['sequence_name', 'next_number'];

    protected $casts = ['next_number' => 'integer'];
}
