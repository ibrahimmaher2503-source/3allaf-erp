<?php
namespace App\Modules\Catalog\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SupplierImportRow extends Model { protected $fillable=['supplier_import_batch_id','row_number','raw_data','mapped_data','errors','status','supplier_id']; protected $casts=['raw_data'=>'array','mapped_data'=>'array','errors'=>'array']; public function batch():BelongsTo{return $this->belongsTo(SupplierImportBatch::class,'supplier_import_batch_id');} public function supplier():BelongsTo{return $this->belongsTo(Supplier::class);} }
