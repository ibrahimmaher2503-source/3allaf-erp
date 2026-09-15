<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class ExportJob extends Model
{
    protected $fillable = ['public_id', 'report_key', 'format', 'status', 'requested_by', 'branch_id', 'store_id', 'filters', 'snapshot_hash', 'request_hash', 'row_count', 'storage_disk', 'storage_path', 'error_message', 'expires_at', 'started_at', 'completed_at'];
    protected $casts = ['filters' => 'array', 'expires_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    protected static function booted(): void { static::creating(fn (self $job): ?string => $job->public_id ??= (string) Str::uuid()); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
}
