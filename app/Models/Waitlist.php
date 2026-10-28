<?php

namespace App\Models;

use Database\Factories\WaitlistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Waitlist extends Model
{
    /** @use HasFactory<WaitlistFactory> */
    use HasFactory;

    protected $fillable = [
        'service_id',
        'provider_id',
        'requested_date',
        'preferred_time',
        'customer_name',
        'customer_email',
        'customer_phone',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
