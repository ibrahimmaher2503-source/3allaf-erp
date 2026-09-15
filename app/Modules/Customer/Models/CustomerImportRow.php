<?php
namespace App\Modules\Customer\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerImportRow extends Model { protected $fillable=['customer_import_batch_id','row_number','raw_data','mapped_data','errors','status','customer_id']; protected $casts=['raw_data'=>'array','mapped_data'=>'array','errors'=>'array']; }
