<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'charge_id',
        'public_id',
        'external_id',
        'amount',
        'currency',
        'method',
        'status',
        'failure_reason',
        'simulation_rule',
        'qr_code',
        'qr_code_url',
        'processing_until',
        'metadata',
        'card_last4',
        'card_brand',
        'customer_name',
        'customer_email',
        'description',
        'idempotency_key',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processing_until' => 'datetime',
        'metadata' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TransactionAttempt::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            'approved',
            'failed',
            'fraud',
            'canceled',
            'refunded',
        ], strict: true);
    }
}
