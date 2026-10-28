<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $fillable = [
        'reference_code',
        'service_id',
        'provider_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_notes',
        'starts_at',
        'ends_at',
        'timezone',
        'status',
        'cancellation_reason',
        'cancelled_at',
        'is_recurring',
        'recurring_group_id',
        'reminder_sent',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'is_recurring' => 'boolean',
            'reminder_sent' => 'boolean',
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

    /**
     * Determine if appointment can be cancelled or rescheduled (e.g. >24h in advance)
     */
    public function canBeModified(): bool
    {
        if ($this->status === 'cancelled' || $this->status === 'completed') {
            return false;
        }

        return Carbon::now()->diffInHours($this->starts_at, false) >= 24;
    }
}
