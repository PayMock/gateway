<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use HasUuids;

    protected $fillable = [
        'event_id',
        'attempt_number',
        'response_code',
        'response_body',
        'is_success',
        'duration_ms',
    ];

    protected $casts = [
        'is_success' => 'boolean',
        'duration_ms' => 'integer',
        'response_code' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class, 'event_id');
    }
}
