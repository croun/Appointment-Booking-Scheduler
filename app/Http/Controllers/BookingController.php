<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Holiday;
use App\Models\Provider;
use App\Models\Service;
use App\Services\BookingAvailabilityService;
use App\Services\BookingManagerService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        protected BookingAvailabilityService $availabilityService,
        protected BookingManagerService $managerService
    ) {}

    /**
     * Show scheduler view.
     */
    public function index(): View
    {
        $services = Service::where('is_active', true)->with('providers')->get();
        $providers = Provider::where('is_active', true)->with('services')->get();
        $holidays = Holiday::orderBy('date')->get();

        return view('scheduler', [
            'services' => $services,
            'providers' => $providers,
            'holidays' => $holidays,
            'currentDate' => Carbon::now()->format('Y-m-d'),
            'defaultTimezone' => config('app.timezone', 'UTC'),
        ]);
    }

    /**
     * Get monthly calendar availability summary.
     */
    public function getCalendarSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'provider_id' => 'nullable|exists:providers,id',
            'year' => 'required|integer|min:2025|max:2035',
            'month' => 'required|integer|min:1|max:12',
            'timezone' => 'nullable|string',
        ]);

        $service = Service::findOrFail($validated['service_id']);
        $provider = ! empty($validated['provider_id']) ? Provider::find($validated['provider_id']) : null;
        $timezone = $validated['timezone'] ?? 'UTC';

        $summary = $this->availabilityService->getMonthCalendarSummary(
            $service,
            $provider,
            (int) $validated['year'],
            (int) $validated['month'],
            $timezone
        );

        return response()->json([
            'success' => true,
            'year' => (int) $validated['year'],
            'month' => (int) $validated['month'],
            'summary' => $summary,
        ]);
    }

    /**
     * Get slots for a date.
     */
    public function getSlots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'provider_id' => 'nullable|exists:providers,id',
            'date' => 'required|date_format:Y-m-d',
            'timezone' => 'nullable|string',
        ]);

        $service = Service::findOrFail($validated['service_id']);
        $provider = ! empty($validated['provider_id']) ? Provider::find($validated['provider_id']) : null;
        $timezone = $validated['timezone'] ?? 'UTC';

        $slotData = $this->availabilityService->getSlotsForDate(
            $service,
            $provider,
            $validated['date'],
            $timezone
        );

        return response()->json([
            'success' => true,
            'data' => $slotData,
        ]);
    }

    /**
     * Store new appointment.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'provider_id' => 'required|exists:providers,id',
            'starts_at' => 'required|date',
            'customer_name' => 'required|string|max:150',
            'customer_email' => 'required|email|max:150',
            'customer_phone' => 'required|string|max:50',
            'customer_notes' => 'nullable|string|max:1000',
            'timezone' => 'nullable|string',
            'is_recurring' => 'nullable|boolean',
            'recurring_weeks' => 'nullable|integer|min:1|max:4',
        ]);

        try {
            $appointment = $this->managerService->createAppointment($validated);
            $appointment->load(['service', 'provider']);

            return response()->json([
                'success' => true,
                'message' => 'Appointment scheduled successfully!',
                'appointment' => $appointment,
                'google_calendar_url' => $this->managerService->getGoogleCalendarUrl($appointment),
                'can_be_modified' => $appointment->canBeModified(),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Show appointment by reference code.
     */
    public function show(string $referenceCode): JsonResponse
    {
        $appointment = Appointment::with(['service', 'provider'])
            ->where('reference_code', strtoupper(trim($referenceCode)))
            ->first();

        if (! $appointment) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment reference code not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'appointment' => $appointment,
            'can_be_modified' => $appointment->canBeModified(),
            'google_calendar_url' => $this->managerService->getGoogleCalendarUrl($appointment),
        ]);
    }

    /**
     * Reschedule appointment.
     */
    public function reschedule(Request $request, string $referenceCode): JsonResponse
    {
        $validated = $request->validate([
            'starts_at' => 'required|date',
        ]);

        $appointment = Appointment::where('reference_code', strtoupper(trim($referenceCode)))->firstOrFail();

        try {
            $updated = $this->managerService->rescheduleAppointment($appointment, $validated['starts_at']);
            $updated->load(['service', 'provider']);

            return response()->json([
                'success' => true,
                'message' => 'Appointment successfully rescheduled.',
                'appointment' => $updated,
                'google_calendar_url' => $this->managerService->getGoogleCalendarUrl($updated),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel appointment.
     */
    public function cancel(Request $request, string $referenceCode): JsonResponse
    {
        $validated = $request->validate([
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $appointment = Appointment::where('reference_code', strtoupper(trim($referenceCode)))->firstOrFail();

        try {
            $updated = $this->managerService->cancelAppointment($appointment, $validated['cancellation_reason'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Appointment has been cancelled.',
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
     * Download iCalendar file (.ics).
     */
    public function downloadIcs(string $referenceCode): Response
    {
        $appointment = Appointment::with(['service', 'provider'])
            ->where('reference_code', strtoupper(trim($referenceCode)))
            ->firstOrFail();

        $ics = $this->managerService->generateIcs($appointment);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="appointment-'.$appointment->reference_code.'.ics"',
        ]);
    }

    /**
     * Submit waitlist entry.
     */
    public function submitWaitlist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'provider_id' => 'nullable|exists:providers,id',
            'requested_date' => 'required|date_format:Y-m-d',
            'preferred_time' => 'required|in:morning,afternoon,anytime',
            'customer_name' => 'required|string|max:150',
            'customer_email' => 'required|email|max:150',
            'customer_phone' => 'required|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $entry = $this->managerService->addToWaitlist($validated);

            return response()->json([
                'success' => true,
                'message' => 'You have been added to the priority waitlist! We will notify you promptly if a slot becomes available.',
                'waitlist' => $entry,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
