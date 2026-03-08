<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionAttempt extends Model
{
    use HasUuids;

    protected $fillable = [
        'transaction_id',
        'attempt_number',
        'status',
        'rule_triggered',
        'reason',
        'context_snapshot',
    ];

    protected $casts = [
        'context_snapshot' => 'array',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
