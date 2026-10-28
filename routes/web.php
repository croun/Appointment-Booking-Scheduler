<?php

use App\Http\Controllers\AdminBookingController;
use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Route;

// Main Single-Page Scheduler Application
Route::get('/', [BookingController::class, 'index'])->name('scheduler.index');

// Public Booking APIs
Route::prefix('api')->group(function () {
    Route::get('/calendar-summary', [BookingController::class, 'getCalendarSummary'])->name('api.calendar.summary');
    Route::get('/slots', [BookingController::class, 'getSlots'])->name('api.slots');
    Route::post('/appointments', [BookingController::class, 'store'])->name('api.appointments.store');
    Route::get('/appointments/{code}', [BookingController::class, 'show'])->name('api.appointments.show');
    Route::post('/appointments/{code}/reschedule', [BookingController::class, 'reschedule'])->name('api.appointments.reschedule');
    Route::post('/appointments/{code}/cancel', [BookingController::class, 'cancel'])->name('api.appointments.cancel');
    Route::get('/appointments/{code}/ics', [BookingController::class, 'downloadIcs'])->name('api.appointments.ics');
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
