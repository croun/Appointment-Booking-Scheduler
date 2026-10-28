<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BlockedTime;
use App\Models\Holiday;
use App\Models\Provider;
use App\Models\Service;
use Carbon\Carbon;

class BookingAvailabilityService
{
    /**
     * Default working hours per day.
     */
    protected array $defaultWorkingHours = [
        'monday' => ['start' => '09:00', 'end' => '17:00'],
        'tuesday' => ['start' => '09:00', 'end' => '17:00'],
        'wednesday' => ['start' => '09:00', 'end' => '17:00'],
        'thursday' => ['start' => '09:00', 'end' => '17:00'],
        'friday' => ['start' => '09:00', 'end' => '17:00'],
        'saturday' => ['start' => '10:00', 'end' => '15:00'],
        'sunday' => null,
    ];

    /**
     * Minimum lead time in minutes before a slot can be booked.
     */
    protected int $leadTimeMinutes = 120;

    /**
     * Get available slots for a given service, provider, date, and timezone.
     */
    public function getSlotsForDate(
        Service $service,
        ?Provider $provider,
        string $dateString,
        string $timezone = 'UTC'
    ): array {
        $date = Carbon::parse($dateString, $timezone)->startOfDay();
        $today = Carbon::now($timezone)->startOfDay();

        if ($date->lt($today)) {
            return [
                'date' => $date->format('Y-m-d'),
                'is_available' => false,
                'reason' => 'Date is in the past',
                'slots' => [],
            ];
        }

        $holiday = Holiday::whereDate('date', $date->format('Y-m-d'))->first();
        if ($holiday) {
            return [
                'date' => $date->format('Y-m-d'),
                'is_available' => false,
                'reason' => 'Holiday: '.$holiday->name,
                'holiday_name' => $holiday->name,
                'slots' => [],
            ];
        }

        $providers = $provider
            ? collect([$provider])
            : $service->providers()->where('is_active', true)->get();

        if ($providers->isEmpty()) {
            $providers = Provider::where('is_active', true)->get();
        }

        if ($providers->isEmpty()) {
            return [
                'date' => $date->format('Y-m-d'),
                'is_available' => false,
                'reason' => 'No active providers available',
                'slots' => [],
            ];
        }

        $duration = $service->duration_minutes;
        $buffer = $service->buffer_minutes;
        $totalSlotLength = $duration + $buffer;
        $dayName = strtolower($date->format('l'));

        $slots = [];
        $now = Carbon::now($timezone);
        $providerIds = $providers->pluck('id')->all();

        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();

        $existingAppointments = Appointment::whereIn('provider_id', $providerIds)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($dayStart, $dayEnd) {
                $q->whereBetween('starts_at', [$dayStart, $dayEnd])
                    ->orWhereBetween('ends_at', [$dayStart, $dayEnd]);
            })
            ->get();

        $blockedTimes = BlockedTime::where(function ($q) use ($providerIds) {
            $q->whereIn('provider_id', $providerIds)->orWhereNull('provider_id');
        })
            ->where(function ($q) use ($dayStart, $dayEnd) {
                $q->whereBetween('starts_at', [$dayStart, $dayEnd])
                    ->orWhereBetween('ends_at', [$dayStart, $dayEnd]);
            })
            ->get();

        if ($service->max_daily_bookings) {
            $serviceDailyCount = Appointment::where('service_id', $service->id)
                ->where('status', '!=', 'cancelled')
                ->whereDate('starts_at', $date->format('Y-m-d'))
                ->count();

            if ($serviceDailyCount >= $service->max_daily_bookings) {
                return [
                    'date' => $date->format('Y-m-d'),
                    'is_available' => false,
                    'reason' => 'Daily capacity reached for this service',
                    'capacity_reached' => true,
                    'slots' => [],
                ];
            }
        }

        $stepMinutes = $duration < 30 ? 15 : 30;

        $dayWorkingHours = [];
        foreach ($providers as $prov) {
            $hours = $prov->working_hours[$dayName] ?? ($this->defaultWorkingHours[$dayName] ?? null);
            if ($hours && ! empty($hours['start']) && ! empty($hours['end'])) {
                $dayWorkingHours[$prov->id] = $hours;
            }
        }

        if (empty($dayWorkingHours)) {
            return [
                'date' => $date->format('Y-m-d'),
                'is_available' => false,
                'reason' => 'Closed on '.$date->format('l').'s',
                'slots' => [],
            ];
        }

        $minStart = '23:59';
        $maxEnd = '00:00';
        foreach ($dayWorkingHours as $h) {
            if ($h['start'] < $minStart) {
                $minStart = $h['start'];
            }
            if ($h['end'] > $maxEnd) {
                $maxEnd = $h['end'];
            }
        }

        $cursor = Carbon::parse($date->format('Y-m-d').' '.$minStart, $timezone);
        $closingTime = Carbon::parse($date->format('Y-m-d').' '.$maxEnd, $timezone);

        while ($cursor->copy()->addMinutes($duration)->lte($closingTime)) {
            $slotStart = $cursor->copy();
            $slotEnd = $slotStart->copy()->addMinutes($duration);
            $slotEndWithBuffer = $slotStart->copy()->addMinutes($totalSlotLength);

            $hour = $slotStart->hour;
            $period = 'Morning';
            if ($hour >= 12 && $hour < 17) {
                $period = 'Afternoon';
            } elseif ($hour >= 17) {
                $period = 'Evening';
            }

            $isPastLeadTime = $slotStart->lt($now->copy()->addMinutes($this->leadTimeMinutes));
            $availableProviderId = null;
            $isBooked = false;
            $reason = null;

            if ($isPastLeadTime) {
                $reason = 'Past lead time (min 2h notice)';
            } else {
                foreach ($providers as $prov) {
                    $provHours = $dayWorkingHours[$prov->id] ?? null;
                    if (! $provHours) {
                        continue;
                    }

                    $provStart = Carbon::parse($date->format('Y-m-d').' '.$provHours['start'], $timezone);
                    $provEnd = Carbon::parse($date->format('Y-m-d').' '.$provHours['end'], $timezone);

                    if ($slotStart->lt($provStart) || $slotEnd->gt($provEnd)) {
                        continue;
                    }

                    $provDayAppointments = $existingAppointments->where('provider_id', $prov->id);
                    if ($provDayAppointments->count() >= $prov->daily_capacity) {
                        continue;
                    }

                    $hasAppointmentConflict = $provDayAppointments->some(function ($apt) use ($slotStart, $slotEndWithBuffer) {
                        $aptStart = Carbon::parse($apt->starts_at);
                        $aptEnd = Carbon::parse($apt->ends_at)->addMinutes($apt->service->buffer_minutes ?? 15);

                        return $slotStart->lt($aptEnd) && $slotEndWithBuffer->gt($aptStart);
                    });

                    if ($hasAppointmentConflict) {
                        $isBooked = true;

                        continue;
                    }

                    $hasBlockedConflict = $blockedTimes->some(function ($block) use ($prov, $slotStart, $slotEnd) {
                        if ($block->provider_id !== null && $block->provider_id !== $prov->id) {
                            return false;
                        }
                        $bStart = Carbon::parse($block->starts_at);
                        $bEnd = Carbon::parse($block->ends_at);

                        return $slotStart->lt($bEnd) && $slotEnd->gt($bStart);
                    });

                    if ($hasBlockedConflict) {
                        $isBooked = true;
                        $reason = 'Blocked by provider/admin';

                        continue;
                    }

                    $availableProviderId = $prov->id;
                    break;
                }
            }

            $isAvailable = ! $isPastLeadTime && ($availableProviderId !== null);

            $slots[] = [
                'time' => $slotStart->format('H:i'),
                'datetime' => $slotStart->format('Y-m-d H:i:s'),
                'formatted_time' => $slotStart->format('g:i A'),
                'period' => $period,
                'available' => $isAvailable,
                'reason' => $isAvailable ? null : ($reason ?? ($isBooked ? 'Taken' : 'Unavailable')),
                'provider_id' => $availableProviderId ?? ($provider ? $provider->id : $providers->first()->id),
                'duration_minutes' => $duration,
                'buffer_minutes' => $buffer,
            ];

            $cursor->addMinutes($stepMinutes);
        }

        $availableCount = collect($slots)->where('available', true)->count();

        return [
            'date' => $date->format('Y-m-d'),
            'formatted_date' => $date->format('l, F j, Y'),
            'is_available' => $availableCount > 0,
            'available_count' => $availableCount,
            'total_count' => count($slots),
            'slots' => $slots,
        ];
    }

    /**
     * Get monthly availability summary.
     */
    public function getMonthCalendarSummary(
        Service $service,
        ?Provider $provider,
        int $year,
        int $month,
        string $timezone = 'UTC'
    ): array {
        $firstDay = Carbon::createFromDate($year, $month, 1, $timezone)->startOfDay();
        $daysInMonth = $firstDay->daysInMonth;
        $today = Carbon::now($timezone)->startOfDay();

        $summary = [];

        $holidays = Holiday::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->pluck('name', 'date')
            ->all();

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::createFromDate($year, $month, $d, $timezone)->startOfDay();
            $dateStr = $date->format('Y-m-d');
            $dayOfWeek = strtolower($date->format('l'));

            $item = [
                'date' => $dateStr,
                'day' => $d,
                'day_of_week' => $date->dayOfWeek,
                'is_today' => $date->equalTo($today),
                'is_past' => $date->lt($today),
                'is_holiday' => isset($holidays[$dateStr]),
                'holiday_name' => $holidays[$dateStr] ?? null,
                'is_closed' => ($dayOfWeek === 'sunday'),
                'available_slots' => 0,
                'status' => 'available',
            ];

            if ($item['is_past']) {
                $item['status'] = 'past';
            } elseif ($item['is_holiday']) {
                $item['status'] = 'holiday';
            } elseif ($item['is_closed']) {
                $item['status'] = 'closed';
            } else {
                $slotData = $this->getSlotsForDate($service, $provider, $dateStr, $timezone);
                $availableCount = $slotData['available_count'] ?? 0;
                $item['available_slots'] = $availableCount;

                if ($availableCount === 0) {
                    $item['status'] = 'full';
                } elseif ($availableCount <= 3) {
                    $item['status'] = 'few_left';
                } else {
                    $item['status'] = 'available';
                }
            }

            $summary[$dateStr] = $item;
        }

        return $summary;
    }
}
