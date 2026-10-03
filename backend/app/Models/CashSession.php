<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    protected $fillable = [
        'session_number',
        'cash_account_id',
        'opened_by',
        'status',
        'opened_at',
        'opening_balance',
        'closed_at',
        'closed_by',
        'expected_closing_balance',
        'actual_closing_balance',
        'difference',
        'difference_reason',
        'difference_status',
        'difference_approved_by',
        'difference_approved_at',
        'has_pending_offline_sync',
        'notes',
    ];

    protected $casts = [
        'opening_balance' => 'integer',
        'expected_closing_balance' => 'integer',
        'actual_closing_balance' => 'integer',
        'difference' => 'integer',
        'has_pending_offline_sync' => 'boolean',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'difference_approved_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'difference_approved_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'cash_session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'OPEN';
    }

    public function isClosed(): bool
    {
        return $this->status === 'CLOSED';
    }

    public function isProvisional(): bool
    {
        return $this->status === 'PROVISIONAL';
    }
}
