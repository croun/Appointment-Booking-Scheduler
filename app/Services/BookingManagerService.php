<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BlockedTime;
use App\Models\BookingVerification;
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
     * Create an appointment with atomic queue numbering and safeguards.
     */
    public function createAppointment(array $data): Appointment
    {
        // 1. Anti-spam honeypot safeguard
        if (! empty($data['honeypot'])) {
            throw new Exception('Automated booking request rejected.');
        }

        return DB::transaction(function () use ($data) {
            $service = Service::findOrFail($data['service_id']);
            $provider = Provider::findOrFail($data['provider_id']);
            $timezone = $data['timezone'] ?? 'UTC';

            $startsAt = Carbon::parse($data['starts_at'], $timezone);
            $endsAt = $startsAt->copy()->addMinutes($service->duration_minutes);
            $endsAtWithBuffer = $endsAt->copy()->addMinutes($service->buffer_minutes);
            $scheduleDate = $startsAt->format('Y-m-d');

            // 2. Duplicate booking safeguard: 1 active booking per contact per day
            $hasActiveBooking = Appointment::where(function ($q) use ($scheduleDate) {
                $q->whereDate('schedule_date', $scheduleDate)
                    ->orWhereDate('starts_at', $scheduleDate);
            })
                ->whereIn('status', ['confirmed', 'serving', 'pending'])
                ->where(function ($q) use ($data) {
                    $q->where('customer_email', $data['customer_email'])
                        ->orWhere('customer_phone', $data['customer_phone']);
                })
                ->lockForUpdate()
                ->exists();

            if ($hasActiveBooking) {
                throw new Exception("An active booking for this email or phone already exists on {$scheduleDate}. Guests are limited to one active booking per day.");
            }

            // 3. Double booking safeguard: re-check slot capacity inside transaction with row locking
            // 2. Duplicate booking safeguard: 1 active booking per contact per day
            $hasActiveBooking = Appointment::where(function ($q) use ($scheduleDate) {
                $q->whereDate('schedule_date', $scheduleDate)
                    ->orWhereDate('starts_at', $scheduleDate);
            })
                ->whereIn('status', ['confirmed', 'serving', 'pending'])
                ->where(function ($q) use ($data) {
                    $q->where('customer_email', $data['customer_email'])
                        ->orWhere('customer_phone', $data['customer_phone']);
                })
                ->lockForUpdate()
                ->exists();

            if ($hasActiveBooking) {
                throw new Exception("An active booking for this email or phone already exists on {$scheduleDate}. Guests are limited to one active booking per day.");
            }

            // 3. Double booking safeguard: re-check slot capacity inside transaction with row locking
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

            // 4. Verify contact token if supplied
            $verifiedAt = null;
            if (! empty($data['verification_token'])) {
                $verification = BookingVerification::where('token', $data['verification_token'])
                    ->whereNotNull('verified_at')
                    ->first();
                if ($verification) {
                    $verifiedAt = $verification->verified_at;
                }
            } elseif (! empty($data['verified_at'])) {
                $verifiedAt = Carbon::parse($data['verified_at']);
            }

            $referenceCode = $this->generateUniqueReferenceCode();
            $manageToken = $this->generateManageToken();
            $queueNumber = $this->generateQueueNumber($service, $scheduleDate);

            $isRecurring = ! empty($data['is_recurring']);
            $recurringGroupId = $isRecurring ? 'REC-'.Str::upper(Str::random(8)) : null;

            $appointment = Appointment::create([
                'reference_code' => $referenceCode,
                'queue_number' => $queueNumber,
                'manage_token' => $manageToken,
                'service_id' => $service->id,
                'provider_id' => $provider->id,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'],
                'customer_notes' => $data['customer_notes'] ?? null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'schedule_date' => $scheduleDate,
                'timezone' => $timezone,
                'status' => 'confirmed',
                'verified_at' => $verifiedAt,
                'is_recurring' => $isRecurring,
                'recurring_group_id' => $recurringGroupId,
            ]);

            if ($isRecurring && ! empty($data['recurring_weeks'])) {
                $weeks = min((int) $data['recurring_weeks'], 4);
                for ($w = 1; $w <= $weeks; $w++) {
                    $nextStart = $startsAt->copy()->addWeeks($w);
                    $nextEnd = $nextStart->copy()->addMinutes($service->duration_minutes);
                    $nextEndWithBuffer = $nextEnd->copy()->addMinutes($service->buffer_minutes);
                    $nextScheduleDate = $nextStart->format('Y-m-d');

                    $recurringConflict = Appointment::where('provider_id', $provider->id)
                        ->where('status', '!=', 'cancelled')
                        ->where(function ($q) use ($nextStart, $nextEndWithBuffer) {
                            $q->where('starts_at', '<', $nextEndWithBuffer)
                                ->where('ends_at', '>', $nextStart);
                        })
                        ->exists();

                    if (! $recurringConflict) {
                        $nextQueue = $this->generateQueueNumber($service, $nextScheduleDate);
                        $nextManageToken = $this->generateManageToken();

                        Appointment::create([
                            'reference_code' => $this->generateUniqueReferenceCode(),
                            'queue_number' => $nextQueue,
                            'manage_token' => $nextManageToken,
                            'service_id' => $service->id,
                            'provider_id' => $provider->id,
                            'customer_name' => $data['customer_name'],
                            'customer_email' => $data['customer_email'],
                            'customer_phone' => $data['customer_phone'],
                            'customer_notes' => ($data['customer_notes'] ?? '').' [Recurring #'.($w + 1).']',
                            'starts_at' => $nextStart,
                            'ends_at' => $nextEnd,
                            'schedule_date' => $nextScheduleDate,
                            'timezone' => $timezone,
                            'status' => 'confirmed',
                            'verified_at' => $verifiedAt,
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
     * Send an OTP code to a guest contact (email or phone).
     *
     * @return array{success: bool, message: string, contact: string, dev_code: string, expires_at: string}
     */
    public function sendOtp(string $contact): array
    {
        $cleanContact = trim($contact);
        if (empty($cleanContact)) {
            throw new Exception('Please enter a valid email or phone number to verify.');
        }

        // Rate limit: max 5 OTP requests per 10 minutes per contact
        $recentCount = BookingVerification::where('contact', $cleanContact)
            ->where('created_at', '>=', Carbon::now()->subMinutes(10))
            ->count();

        if ($recentCount >= 5) {
            throw new Exception('Too many OTP verification requests. Please wait a few minutes before trying again.');
        }

        // Invalidate older unverified codes
        BookingVerification::where('contact', $cleanContact)
            ->whereNull('verified_at')
            ->delete();

        $code = sprintf('%06d', mt_rand(100000, 999999));
        $expiresAt = Carbon::now()->addMinutes(10);

        BookingVerification::create([
            'contact' => $cleanContact,
            'otp_code' => $code,
            'expires_at' => $expiresAt,
        ]);

        return [
            'success' => true,
            'message' => "A 6-digit verification code has been dispatched to {$cleanContact}.",
            'contact' => $cleanContact,
            'dev_code' => $code, // Surfaced for mock verification & notification display
            'expires_at' => $expiresAt->toISOString(),
        ];
    }

    /**
     * Verify an OTP code.
     *
     * @return array{success: bool, message: string, verification_token: string, verified_at: string}
     */
    public function verifyOtp(string $contact, string $code): array
    {
        $cleanContact = trim($contact);
        $cleanCode = trim($code);

        $verification = BookingVerification::where('contact', $cleanContact)
            ->where('expires_at', '>', Carbon::now())
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (! $verification) {
            throw new Exception('Verification code expired or not found. Please request a new code.');
        }

        if ($verification->attempts >= 5) {
            throw new Exception('Too many invalid attempts. Please request a new verification code.');
        }

        if ($verification->otp_code !== $cleanCode) {
            $verification->increment('attempts');
            throw new Exception('Invalid 6-digit verification code. Please check and try again.');
        }

        $token = Str::random(40);
        $verification->update([
            'verified_at' => Carbon::now(),
            'token' => $token,
        ]);

        return [
            'success' => true,
            'message' => 'Contact verified successfully.',
            'verification_token' => $token,
            'verified_at' => $verification->verified_at->toISOString(),
        ];
    }

    /**
     * Look up a booking by reference code AND email or phone (when guest loses link/email).
     */
    public function lookupBooking(string $referenceCode, string $contact): Appointment
    {
        $code = strtoupper(trim($referenceCode));
        $cleanContact = strtolower(trim($contact));

        $appointment = Appointment::with(['service', 'provider'])
            ->where('reference_code', $code)
            ->where(function ($q) use ($cleanContact) {
                $q->whereRaw('LOWER(customer_email) = ?', [$cleanContact])
                    ->orWhere('customer_phone', $cleanContact);
            })
            ->first();

        if (! $appointment) {
            throw new Exception('No matching booking found for the provided reference code and contact details.');
        }

        return $appointment;
    }

    /**
     * Get appointment by guest manage token (No Login required).
     */
    public function getAppointmentByManageToken(string $token): Appointment
    {
        $appointment = Appointment::with(['service', 'provider'])
            ->where('manage_token', $token)
            ->first();

        if (! $appointment) {
            throw new Exception('Invalid or expired booking management link.');
        }

        return $appointment;
    }

    /**
     * Reschedule appointment by guest manage token.
     */
    public function rescheduleByToken(string $token, string $newStartsAt): Appointment
    {
        $appointment = $this->getAppointmentByManageToken($token);

        return $this->rescheduleAppointment($appointment, $newStartsAt);
    }

    /**
     * Cancel appointment by guest manage token (releases slot back to pool).
     */
    public function cancelByToken(string $token, ?string $reason = null): Appointment
    {
        $appointment = $this->getAppointmentByManageToken($token);

        return $this->cancelAppointment($appointment, $reason);
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
            $newScheduleDate = $newStartsAt->format('Y-m-d');

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

            // Assign new queue number if moving to a different date
            $queueNumber = $appointment->queue_number;
            if ($appointment->schedule_date?->format('Y-m-d') !== $newScheduleDate) {
                $queueNumber = $this->generateQueueNumber($service, $newScheduleDate);
            }

            $appointment->update([
                'starts_at' => $newStartsAt,
                'ends_at' => $newEndsAt,
                'schedule_date' => $newScheduleDate,
                'queue_number' => $queueNumber,
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
        $validStatuses = ['pending', 'confirmed', 'serving', 'completed', 'cancelled', 'no_show'];
        if (! in_array($status, $validStatuses, true)) {
            throw new Exception("Invalid appointment status: {$status}");
        }

        $appointment->update(['status' => $status]);

        return $appointment;
    }

    /**
     * Get privacy-safe "Now Serving" public queue display.
     */
    public function getNowServingQueue(?string $date = null): array
    {
        $scheduleDate = $date ?? Carbon::today()->format('Y-m-d');

        $serving = Appointment::with('service')
            ->where(function ($q) use ($scheduleDate) {
                $q->whereDate('schedule_date', $scheduleDate)
                    ->orWhereDate('starts_at', $scheduleDate);
            })
            ->where('status', 'serving')
            ->orderBy('starts_at')
            ->get()
            ->map(fn ($apt) => [
                'queue_number' => $apt->queue_number,
                'service_name' => $apt->service?->name ?? 'General Service',
                'time' => Carbon::parse($apt->starts_at)->format('H:i'),
                'status' => 'serving',
            ]);

        $nextUp = Appointment::with('service')
            ->where(function ($q) use ($scheduleDate) {
                $q->whereDate('schedule_date', $scheduleDate)
                    ->orWhereDate('starts_at', $scheduleDate);
            })
            ->where('status', 'confirmed')
            ->orderBy('starts_at')
            ->take(12)
            ->get()
            ->map(fn ($apt) => [
                'queue_number' => $apt->queue_number,
                'service_name' => $apt->service?->name ?? 'General Service',
                'time' => Carbon::parse($apt->starts_at)->format('H:i'),
                'status' => 'confirmed',
            ]);

        return [
            'date' => $scheduleDate,
            'serving' => $serving,
            'next_up' => $nextUp,
            'total_serving' => $serving->count(),
            'total_waiting' => $nextUp->count(),
        ];
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
        $summary = "Appointment: {$appointment->service->name} with {$appointment->provider->name} (Queue: {$appointment->queue_number})";
        $description = "Booking Reference: {$appointment->reference_code}\\nQueue Number: {$appointment->queue_number}\\nClient: {$appointment->customer_name}\\nDuration: {$appointment->service->duration_minutes} mins";

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
        $title = urlencode("{$appointment->service->name} with {$appointment->provider->name} [Queue {$appointment->queue_number}]");
        $details = urlencode("Booking Ref: {$appointment->reference_code}\nQueue Number: {$appointment->queue_number}\nService: {$appointment->service->name}\nProvider: {$appointment->provider->name}");

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

    /**
     * Generate an unguessable guest management token.
     */
    protected function generateManageToken(): string
    {
        do {
            $token = Str::random(48);
        } while (Appointment::where('manage_token', $token)->exists());

        return $token;
    }

    /**
     * Atomically generate a per-day, per-service sequential queue number.
     * Resets daily (e.g. A-001, A-002, B-001).
     */
    protected function generateQueueNumber(Service $service, string $scheduleDate): string
    {
        $prefix = chr(65 + (($service->id - 1) % 26));

        $latest = Appointment::where('service_id', $service->id)
            ->where(function ($q) use ($scheduleDate) {
                $q->whereDate('schedule_date', $scheduleDate)
                    ->orWhereDate('starts_at', $scheduleDate);
            })
            ->whereNotNull('queue_number')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $seq = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest->queue_number, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%03d', $prefix, $seq);
    }
}
