<?php

use App\Http\Controllers\AdminBookingController;
use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Route;

// Main Single-Page Scheduler Application
Route::get('/', [BookingController::class, 'index'])->name('scheduler.index');

// Guest Manage Booking Page (No Login Required)
Route::get('/manage/{token}', [BookingController::class, 'managePage'])->name('booking.manage.page');

// Public Booking & Guest Workflow APIs
Route::prefix('api')->group(function () {
    Route::get('/calendar-summary', [BookingController::class, 'getCalendarSummary'])->name('api.calendar.summary');
    Route::get('/slots', [BookingController::class, 'getSlots'])->name('api.slots');

    // Guest OTP verification (No Login identity check)
    Route::post('/guest/otp/send', [BookingController::class, 'sendOtp'])->name('api.guest.otp.send');
    Route::post('/guest/otp/verify', [BookingController::class, 'verifyOtp'])->name('api.guest.otp.verify');

    // Appointment Booking & Lookup
    Route::post('/appointments', [BookingController::class, 'store'])->name('api.appointments.store');
    Route::post('/appointments/lookup', [BookingController::class, 'lookupBooking'])->name('api.appointments.lookup');
    Route::get('/appointments/{code}', [BookingController::class, 'show'])->name('api.appointments.show');
    Route::post('/appointments/{code}/reschedule', [BookingController::class, 'reschedule'])->name('api.appointments.reschedule');
    Route::post('/appointments/{code}/cancel', [BookingController::class, 'cancel'])->name('api.appointments.cancel');
    Route::get('/appointments/{code}/ics', [BookingController::class, 'downloadIcs'])->name('api.appointments.ics');

    // Guest Self-Service Management (Via token, No Login required)
    Route::get('/manage/{token}', [BookingController::class, 'getManageAppointment'])->name('api.manage.show');
    Route::post('/manage/{token}/reschedule', [BookingController::class, 'rescheduleByManageToken'])->name('api.manage.reschedule');
    Route::post('/manage/{token}/cancel', [BookingController::class, 'cancelByManageToken'])->name('api.manage.cancel');

    // Public "Now Serving" Live Queue (Privacy-Safe)
    Route::get('/queue/now-serving', [BookingController::class, 'getNowServing'])->name('api.queue.now_serving');

    // Priority Waitlist
    Route::post('/waitlist', [BookingController::class, 'submitWaitlist'])->name('api.waitlist.store');

    // Admin & Provider APIs
    Route::prefix('admin')->group(function () {
        Route::get('/stats', [AdminBookingController::class, 'getStats'])->name('api.admin.stats');
        Route::get('/appointments', [AdminBookingController::class, 'getAppointments'])->name('api.admin.appointments');
        Route::post('/appointments/{id}/status', [AdminBookingController::class, 'updateStatus'])->name('api.admin.appointments.status');
        Route::get('/blocked-times', [AdminBookingController::class, 'getBlockedTimes'])->name('api.admin.blocked_times');
        Route::post('/blocked-times', [AdminBookingController::class, 'blockTime'])->name('api.admin.blocked_times.store');
        Route::delete('/blocked-times/{id}', [AdminBookingController::class, 'removeBlockedTime'])->name('api.admin.blocked_times.destroy');
        Route::get('/waitlist', [AdminBookingController::class, 'getWaitlist'])->name('api.admin.waitlist');
        Route::post('/waitlist/{id}/status', [AdminBookingController::class, 'updateWaitlistStatus'])->name('api.admin.waitlist.status');
    });
});
