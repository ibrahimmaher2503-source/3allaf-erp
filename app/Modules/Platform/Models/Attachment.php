<?php

namespace App\Modules\Platform\Models;

use App\Models\User;
use App\Modules\Platform\Enums\AttachmentState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Attachment extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    private bool $mutating = false;

    protected $fillable = [
        'id',
        'source_type',
        'source_id',
        'purpose',
        'original_filename',
        'storage_filename',
        'storage_disk',
        'storage_path',
        'mime_type',
        'detected_mime_type',
        'extension',
        'size_bytes',
        'sha256',
        'uploaded_by',
        'branch_id',
        'store_id',
        'visibility',
        'status',
        'request_id',
        'metadata',
        'retention_until',
        'expires_at',
        'deleted_at',
    ];

    protected $casts = [
        'status' => AttachmentState::class,
        'size_bytes' => 'integer',
        'metadata' => 'array',
        'retention_until' => 'datetime',
        'expires_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $attachment): void {
            if (! $attachment->mutating) {
                throw new LogicException('Attachments may only change through a named action.');
            }
        });

        static::deleting(fn (): never => throw new LogicException('Attachment history is preserved; use a named revocation action.'));
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @param array<string, mixed> $attributes */
    public function mutate(array $attributes): void
    {
        $this->mutating = true;

        try {
            $this->fill($attributes)->save();
        } finally {
            $this->mutating = false;
        }
    }
}
