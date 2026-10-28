<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BlockedTime;
use App\Models\Provider;
use App\Models\Service;
use App\Models\Waitlist;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingManagerService
{
    public function __construct(
        protected BookingAvailabilityService $availabilityService
    ) {}

    /**
     * Create an appointment.
     */
    public function createAppointment(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {
            $service = Service::findOrFail($data['service_id']);
            $provider = Provider::findOrFail($data['provider_id']);
            $timezone = $data['timezone'] ?? 'UTC';

            $startsAt = Carbon::parse($data['starts_at'], $timezone);
            $endsAt = $startsAt->copy()->addMinutes($service->duration_minutes);
            $endsAtWithBuffer = $endsAt->copy()->addMinutes($service->buffer_minutes);

            $conflict = Appointment::where('provider_id', $provider->id)
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) use ($startsAt, $endsAtWithBuffer) {
                    $q->where('starts_at', '<', $endsAtWithBuffer)
                        ->where('ends_at', '>', $startsAt);
                })
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                throw new Exception('Selected slot was just booked by someone else. Please choose another slot.');
            }

            $blocked = BlockedTime::where(function ($q) use ($provider) {
                $q->where('provider_id', $provider->id)->orWhereNull('provider_id');
            })
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->exists();

            if ($blocked) {
                throw new Exception('The selected time is blocked by the provider or clinic.');
            }

            $referenceCode = $this->generateUniqueReferenceCode();
            $isRecurring = ! empty($data['is_recurring']);
            $recurringGroupId = $isRecurring ? 'REC-'.Str::upper(Str::random(8)) : null;

            $appointment = Appointment::create([
                'reference_code' => $referenceCode,
                'service_id' => $service->id,
                'provider_id' => $provider->id,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'],
                'customer_notes' => $data['customer_notes'] ?? null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => $timezone,
                'status' => 'confirmed',
                'is_recurring' => $isRecurring,
                'recurring_group_id' => $recurringGroupId,
            ]);

            if ($isRecurring && ! empty($data['recurring_weeks'])) {
                $weeks = min((int) $data['recurring_weeks'], 4);
                for ($w = 1; $w <= $weeks; $w++) {
                    $nextStart = $startsAt->copy()->addWeeks($w);
                    $nextEnd = $nextStart->copy()->addMinutes($service->duration_minutes);
                    $nextEndWithBuffer = $nextEnd->copy()->addMinutes($service->buffer_minutes);

                    $recurringConflict = Appointment::where('provider_id', $provider->id)
                        ->where('status', '!=', 'cancelled')
                        ->where(function ($q) use ($nextStart, $nextEndWithBuffer) {
                            $q->where('starts_at', '<', $nextEndWithBuffer)
                                ->where('ends_at', '>', $nextStart);
                        })
                        ->exists();

                    if (! $recurringConflict) {
                        Appointment::create([
                            'reference_code' => $this->generateUniqueReferenceCode(),
                            'service_id' => $service->id,
                            'provider_id' => $provider->id,
                            'customer_name' => $data['customer_name'],
                            'customer_email' => $data['customer_email'],
                            'customer_phone' => $data['customer_phone'],
                            'customer_notes' => ($data['customer_notes'] ?? '').' [Recurring #'.($w + 1).']',
                            'starts_at' => $nextStart,
                            'ends_at' => $nextEnd,
                            'timezone' => $timezone,
                            'status' => 'confirmed',
                            'is_recurring' => true,
                            'recurring_group_id' => $recurringGroupId,
                        ]);
                    }
                }
            }

            return $appointment;
        });
    }

    /**
     * Reschedule appointment.
     */
    public function rescheduleAppointment(Appointment $appointment, string $newStartsAtString, bool $isAdminOverride = false): Appointment
    {
        if (! $isAdminOverride && ! $appointment->canBeModified()) {
            throw new Exception('Appointments cannot be rescheduled within 24 hours of scheduled time.');
        }

        return DB::transaction(function () use ($appointment, $newStartsAtString) {
            $service = $appointment->service;
            $newStartsAt = Carbon::parse($newStartsAtString, $appointment->timezone);
            $newEndsAt = $newStartsAt->copy()->addMinutes($service->duration_minutes);
            $newEndsAtWithBuffer = $newEndsAt->copy()->addMinutes($service->buffer_minutes);

            $conflict = Appointment::where('provider_id', $appointment->provider_id)
                ->where('id', '!=', $appointment->id)
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) use ($newStartsAt, $newEndsAtWithBuffer) {
                    $q->where('starts_at', '<', $newEndsAtWithBuffer)
                        ->where('ends_at', '>', $newStartsAt);
                })
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                throw new Exception('The selected new slot is no longer available.');
            }

            $appointment->update([
                'starts_at' => $newStartsAt,
                'ends_at' => $newEndsAt,
                'status' => 'confirmed',
            ]);

            return $appointment;
        });
    }

    /**
     * Cancel appointment.
     */
    public function cancelAppointment(Appointment $appointment, ?string $reason = null, bool $isAdminOverride = false): Appointment
    {
        if (! $isAdminOverride && ! $appointment->canBeModified()) {
            throw new Exception('Appointments cannot be cancelled within 24 hours of scheduled time.');
        }

        $appointment->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason ?? 'Cancelled by customer',
            'cancelled_at' => Carbon::now(),
        ]);

        return $appointment;
    }

    /**
     * Update appointment status.
     */
    public function updateStatus(Appointment $appointment, string $status): Appointment
    {
        $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
        if (! in_array($status, $validStatuses, true)) {
            throw new Exception("Invalid appointment status: {$status}");
        }

        $appointment->update(['status' => $status]);

        return $appointment;
    }

    /**
     * Block time for a provider or clinic.
     */
    public function blockTime(array $data): BlockedTime
    {
        return BlockedTime::create([
            'provider_id' => $data['provider_id'] ?? null,
            'title' => $data['title'],
            'starts_at' => Carbon::parse($data['starts_at']),
            'ends_at' => Carbon::parse($data['ends_at']),
            'reason' => $data['reason'] ?? null,
        ]);
    }

    /**
     * Add request to waitlist.
     */
    public function addToWaitlist(array $data): Waitlist
    {
        return Waitlist::create([
            'service_id' => $data['service_id'],
            'provider_id' => $data['provider_id'] ?? null,
            'requested_date' => $data['requested_date'],
            'preferred_time' => $data['preferred_time'] ?? 'anytime',
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'],
            'notes' => $data['notes'] ?? null,
            'status' => 'waiting',
        ]);
    }

    /**
     * Generate iCalendar (.ics) content.
     */
    public function generateIcs(Appointment $appointment): string
    {
        $uid = 'apt-'.$appointment->reference_code.'@'.request()->getHost();
        $dtStart = Carbon::parse($appointment->starts_at)->utc()->format('Ymd\THis\Z');
        $dtEnd = Carbon::parse($appointment->ends_at)->utc()->format('Ymd\THis\Z');
        $dtStamp = Carbon::now()->utc()->format('Ymd\THis\Z');
        $summary = "Appointment: {$appointment->service->name} with {$appointment->provider->name}";
        $description = "Booking Reference: {$appointment->reference_code}\\nClient: {$appointment->customer_name}\\nDuration: {$appointment->service->duration_minutes} mins";

        return "BEGIN:VCALENDAR\r\n".
            "VERSION:2.0\r\n".
            "PRODID:-//Appointment Booking Scheduler//EN\r\n".
            "CALSCALE:GREGORIAN\r\n".
            "METHOD:PUBLISH\r\n".
            "BEGIN:VEVENT\r\n".
            "UID:{$uid}\r\n".
            "DTSTAMP:{$dtStamp}\r\n".
            "DTSTART:{$dtStart}\r\n".
            "DTEND:{$dtEnd}\r\n".
            "SUMMARY:{$summary}\r\n".
            "DESCRIPTION:{$description}\r\n".
            "STATUS:CONFIRMED\r\n".
            "END:VEVENT\r\n".
            "END:VCALENDAR\r\n";
    }

    /**
     * Generate Google Calendar URL.
     */
    public function getGoogleCalendarUrl(Appointment $appointment): string
    {
        $dtStart = Carbon::parse($appointment->starts_at)->utc()->format('Ymd\THis\Z');
        $dtEnd = Carbon::parse($appointment->ends_at)->utc()->format('Ymd\THis\Z');
        $title = urlencode("{$appointment->service->name} with {$appointment->provider->name}");
        $details = urlencode("Booking Ref: {$appointment->reference_code}\nService: {$appointment->service->name}\nProvider: {$appointment->provider->name}");

        return "https://calendar.google.com/calendar/render?action=TEMPLATE&text={$title}&dates={$dtStart}/{$dtEnd}&details={$details}";
    }

    /**
     * Generate a unique reference code.
     */
    protected function generateUniqueReferenceCode(): string
    {
        do {
            $code = 'APT-'.strtoupper(Str::random(6));
        } while (Appointment::where('reference_code', $code)->exists());

        return $code;
    }
}
