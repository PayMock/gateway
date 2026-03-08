<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'transaction_id',
        'public_id',
        'event_type',
        'payload',
        'status',
        'attempts',
        'next_attempt_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'next_attempt_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'event_id');
    }
}
