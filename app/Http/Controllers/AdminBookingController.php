<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\BlockedTime;
use App\Models\Provider;
use App\Models\Waitlist;
use App\Services\BookingManagerService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    public function __construct(
        protected BookingManagerService $managerService
    ) {}

    /**
     * Get appointments list.
     */
    public function getAppointments(Request $request): JsonResponse
    {
        $query = Appointment::with(['service', 'provider']);

        if ($request->filled('date')) {
            $query->whereDate('starts_at', $request->input('date'));
        }

        if ($request->filled('provider_id')) {
            $query->where('provider_id', $request->input('provider_id'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $appointments = $query->orderBy('starts_at')->get();

        return response()->json([
            'success' => true,
            'appointments' => $appointments,
        ]);
    }

    /**
     * Update appointment status.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,serving,completed,cancelled,no_show',
        ]);

        $appointment = Appointment::findOrFail($id);

        try {
            $updated = $this->managerService->updateStatus($appointment, $validated['status']);
            $updated->load(['service', 'provider']);

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully.',
                'appointment' => $updated,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Block time window.
     */
    public function blockTime(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => 'nullable|exists:providers,id',
            'title' => 'required|string|max:150',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $blocked = $this->managerService->blockTime($validated);
            $blocked->load('provider');

            return response()->json([
                'success' => true,
                'message' => 'Time period successfully blocked.',
                'blocked_time' => $blocked,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get blocked times.
     */
    public function getBlockedTimes(Request $request): JsonResponse
    {
        $query = BlockedTime::with('provider')->orderBy('starts_at', 'desc');

        if ($request->filled('date')) {
            $query->whereDate('starts_at', $request->input('date'));
        }

        return response()->json([
            'success' => true,
            'blocked_times' => $query->get(),
        ]);
    }

    /**
     * Remove blocked time.
     */
    public function removeBlockedTime(int $id): JsonResponse
    {
        $blocked = BlockedTime::findOrFail($id);
        $blocked->delete();

        return response()->json([
            'success' => true,
            'message' => 'Blocked time period removed.',
        ]);
    }

    /**
     * Get operations statistics.
     */
    public function getStats(Request $request): JsonResponse
    {
        $date = $request->input('date', Carbon::today()->format('Y-m-d'));

        $todayAppointments = Appointment::whereDate('starts_at', $date)->get();
        $totalBookingsToday = $todayAppointments->count();
        $completedToday = $todayAppointments->where('status', 'completed')->count();
        $confirmedToday = $todayAppointments->where('status', 'confirmed')->count();
        $cancelledToday = $todayAppointments->where('status', 'cancelled')->count();
        $noShowToday = $todayAppointments->where('status', 'no_show')->count();

        $totalCapacity = Provider::where('is_active', true)->sum('daily_capacity');
        $capacityPercentage = $totalCapacity > 0 ? round(($totalBookingsToday / $totalCapacity) * 100) : 0;

        $revenueToday = $todayAppointments->whereIn('status', ['confirmed', 'completed'])
            ->sum(function ($apt) {
                return $apt->service->price ?? 0;
            });

        $waitlistCount = Waitlist::where('status', 'waiting')->count();

        return response()->json([
            'success' => true,
            'date' => $date,
            'total_bookings' => $totalBookingsToday,
            'confirmed' => $confirmedToday,
            'completed' => $completedToday,
            'cancelled' => $cancelledToday,
            'no_show' => $noShowToday,
            'capacity_percentage' => min($capacityPercentage, 100),
            'total_capacity' => $totalCapacity,
            'revenue_today' => $revenueToday,
            'waitlist_count' => $waitlistCount,
        ]);
    }

    /**
     * Get waitlist records.
     */
    public function getWaitlist(Request $request): JsonResponse
    {
        $query = Waitlist::with(['service', 'provider'])->orderBy('requested_date');

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'success' => true,
            'waitlist' => $query->get(),
        ]);
    }

    /**
     * Update waitlist status.
     */
    public function updateWaitlistStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:waiting,notified,converted,cancelled',
        ]);

        $waitlist = Waitlist::findOrFail($id);
        $waitlist->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Waitlist status updated.',
            'waitlist' => $waitlist,
        ]);
    }
}
