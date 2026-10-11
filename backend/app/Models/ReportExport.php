<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReportExport extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'report_type',
        'format',
        'file_name',
        'file_path',
        'file_size',
        'status',
        'error_message',
        'filter_payload',
        'metadata',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'filter_payload' => 'array',
        'metadata' => 'array',
        'file_size' => 'integer',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canBeDownloadedBy(User $user): bool
    {
        if ($this->report_type === 'cash' && ! $user->can('view_cash')) {
            return false;
        }
        if (! $user->isOwner() && (! $user->hasPermission('export_reports') || (int) $this->user_id !== (int) $user->id)) {
            return false;
        }
        $containsCost = $this->metadata['contains_cost'] ?? in_array($this->report_type, ['inventory', 'profit_loss', 'purchases'], true);

        return ! $containsCost || $user->isOwner() || $user->hasPermission('view_cost_price');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }

    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }
}
