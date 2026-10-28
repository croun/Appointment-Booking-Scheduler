<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BlockedTime;
use App\Models\Provider;
use App\Models\Service;
use App\Models\Waitlist;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected Service $service;

    protected Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = Service::create([
            'name' => 'Physical Therapy Standard',
            'slug' => 'physical-therapy-standard',
            'description' => 'Targeted therapeutic treatment',
            'duration_minutes' => 30,
            'buffer_minutes' => 15,
            'price' => 85.00,
            'color' => '#0d9488',
            'is_active' => true,
        ]);

        $this->provider = Provider::create([
            'name' => 'Dr. Alex Mercer',
            'email' => 'alex.mercer@scheduler.test',
            'working_hours' => [
                'monday' => ['start' => '09:00', 'end' => '17:00'],
                'tuesday' => ['start' => '09:00', 'end' => '17:00'],
                'wednesday' => ['start' => '09:00', 'end' => '17:00'],
                'thursday' => ['start' => '09:00', 'end' => '17:00'],
                'friday' => ['start' => '09:00', 'end' => '17:00'],
                'saturday' => ['start' => '10:00', 'end' => '14:00'],
                'sunday' => null,
            ],
            'daily_capacity' => 8,
            'is_active' => true,
        ]);

        $this->provider->services()->attach($this->service->id);
    }

    public function test_scheduler_page_loads_successfully(): void
    {
        $response = $this->get(route('scheduler.index'));

        $response->assertStatus(200);
        $response->assertSee('Lumina Health');
        $response->assertSee('Physical Therapy Standard');
        $response->assertSee('Dr. Alex Mercer');
    }

    public function test_calendar_summary_returns_monthly_overview(): void
    {
        $response = $this->getJson(route('api.calendar.summary', [
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'year' => 2026,
            'month' => 11,
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'year',
            'month',
            'summary',
        ]);
    }

    public function test_slots_api_respects_working_hours_and_buffers(): void
    {
        // Monday Nov 9, 2026
        $targetDate = '2026-11-09';

        $response = $this->getJson(route('api.slots', [
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'date' => $targetDate,
            'timezone' => 'UTC',
        ]));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $slots = $response->json('data.slots');
        $this->assertNotEmpty($slots);
        $this->assertEquals('09:00', $slots[0]['time']);
    }

    public function test_can_book_appointment_and_generate_reference_code(): void
    {
        $bookingTime = '2026-11-09 10:00:00';

        $response = $this->postJson(route('api.appointments.store'), [
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'starts_at' => $bookingTime,
            'customer_name' => 'Jordan Reed',
            'customer_email' => 'jordan@example.com',
            'customer_phone' => '+1 (555) 321-4321',
            'customer_notes' => 'Knee discomfort',
            'timezone' => 'UTC',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $refCode = $response->json('appointment.reference_code');
        $this->assertNotNull($refCode);
        $this->assertStringStartsWith('APT-', $refCode);

        $this->assertDatabaseHas('appointments', [
            'reference_code' => $refCode,
            'customer_name' => 'Jordan Reed',
            'customer_email' => 'jordan@example.com',
            'status' => 'confirmed',
        ]);
    }

    public function test_conflict_prevention_prevents_double_booking(): void
    {
        $bookingTime = '2026-11-09 10:00:00';

        // 1. First booking succeeds
        $first = $this->postJson(route('api.appointments.store'), [
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'starts_at' => $bookingTime,
            'customer_name' => 'Customer One',
            'customer_email' => 'one@example.com',
            'customer_phone' => '+1 (555) 111-1111',
            'timezone' => 'UTC',
        ]);
        $first->assertStatus(201);

        // 2. Second booking on the exact same slot must fail
        $second = $this->postJson(route('api.appointments.store'), [
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'starts_at' => $bookingTime,
            'customer_name' => 'Customer Two',
            'customer_email' => 'two@example.com',
            'customer_phone' => '+1 (555) 222-2222',
            'timezone' => 'UTC',
        ]);

        $second->assertStatus(422);
        $second->assertJsonPath('success', false);
        $this->assertStringContainsString('Selected slot was just booked by someone else', $second->json('message'));
    }

    public function test_twenty_four_hour_rule_for_cancellation_and_rescheduling(): void
    {
        // 1. Appointment far in future (>24 hours) can be cancelled
        $futureAppointment = Appointment::create([
            'reference_code' => 'APT-FUTURE',
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'customer_name' => 'Future Patient',
            'customer_email' => 'future@example.com',
            'customer_phone' => '+1 (555) 000-1111',
            'starts_at' => Carbon::now()->addDays(5),
            'ends_at' => Carbon::now()->addDays(5)->addMinutes(30),
            'timezone' => 'UTC',
            'status' => 'confirmed',
        ]);

        $cancelRes = $this->postJson(route('api.appointments.cancel', ['code' => 'APT-FUTURE']), [
            'cancellation_reason' => 'Schedule conflict',
        ]);
        $cancelRes->assertStatus(200);
        $this->assertEquals('cancelled', $futureAppointment->fresh()->status);

        // 2. Appointment within 24 hours cannot be cancelled by client
        $imminentAppointment = Appointment::create([
            'reference_code' => 'APT-IMMINENT',
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'customer_name' => 'Imminent Patient',
            'customer_email' => 'imminent@example.com',
            'customer_phone' => '+1 (555) 000-2222',
            'starts_at' => Carbon::now()->addHours(6),
            'ends_at' => Carbon::now()->addHours(6)->addMinutes(30),
            'timezone' => 'UTC',
            'status' => 'confirmed',
        ]);

        $imminentCancelRes = $this->postJson(route('api.appointments.cancel', ['code' => 'APT-IMMINENT']), [
            'cancellation_reason' => 'Last minute change',
        ]);
        $imminentCancelRes->assertStatus(422);
        $imminentCancelRes->assertJsonPath('success', false);
        $this->assertEquals('confirmed', $imminentAppointment->fresh()->status);
    }

    public function test_blocked_time_prevents_slot_booking(): void
    {
        $blockDate = '2026-11-12';
        $blockStart = "{$blockDate} 11:00:00";
        $blockEnd = "{$blockDate} 13:00:00";

        // Admin blocks off time
        BlockedTime::create([
            'provider_id' => $this->provider->id,
            'title' => 'Clinical Training',
            'starts_at' => $blockStart,
            'ends_at' => $blockEnd,
        ]);

        // Attempting to book during blocked window fails
        $response = $this->postJson(route('api.appointments.store'), [
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'starts_at' => "{$blockDate} 11:30:00",
            'customer_name' => 'Blocked Tester',
            'customer_email' => 'blocked@example.com',
            'customer_phone' => '+1 555-999-0000',
            'timezone' => 'UTC',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('blocked', strtolower($response->json('message')));
    }

    public function test_admin_can_update_status_and_read_kpi_stats(): void
    {
        $appointment = Appointment::create([
            'reference_code' => 'APT-ADMINTEST',
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'customer_name' => 'Admin Test Client',
            'customer_email' => 'admintest@example.com',
            'customer_phone' => '+1 555-888-7777',
            'starts_at' => Carbon::today()->setTime(14, 0),
            'ends_at' => Carbon::today()->setTime(14, 30),
            'timezone' => 'UTC',
            'status' => 'confirmed',
        ]);

        // Mark completed
        $updateRes = $this->postJson(route('api.admin.appointments.status', ['id' => $appointment->id]), [
            'status' => 'completed',
        ]);
        $updateRes->assertStatus(200);
        $this->assertEquals('completed', $appointment->fresh()->status);

        // Fetch stats
        $statsRes = $this->getJson(route('api.admin.stats', ['date' => Carbon::today()->format('Y-m-d')]));
        $statsRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $statsRes->json('completed'));
    }

    public function test_can_join_waitlist_and_admin_can_update_status(): void
    {
        $response = $this->postJson(route('api.waitlist.store'), [
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'requested_date' => '2026-11-20',
            'preferred_time' => 'morning',
            'customer_name' => 'Waitlist Client',
            'customer_email' => 'waitlist@example.com',
            'customer_phone' => '+1 555-432-1234',
        ]);

        $response->assertStatus(201);
        $waitlistId = $response->json('waitlist.id');
        $this->assertDatabaseHas('waitlists', [
            'id' => $waitlistId,
            'customer_name' => 'Waitlist Client',
            'status' => 'waiting',
        ]);

        // Admin marks notified
        $adminRes = $this->postJson(route('api.admin.waitlist.status', ['id' => $waitlistId]), [
            'status' => 'notified',
        ]);
        $adminRes->assertStatus(200);
        $this->assertEquals('notified', Waitlist::find($waitlistId)->status);
    }

    public function test_ics_calendar_invite_download(): void
    {
        $appointment = Appointment::create([
            'reference_code' => 'APT-ICSTEST',
            'service_id' => $this->service->id,
            'provider_id' => $this->provider->id,
            'customer_name' => 'ICS Patient',
            'customer_email' => 'ics@example.com',
            'customer_phone' => '+1 555-111-2222',
            'starts_at' => Carbon::parse('2026-11-15 10:00:00'),
            'ends_at' => Carbon::parse('2026-11-15 10:30:00'),
            'timezone' => 'UTC',
            'status' => 'confirmed',
        ]);

        $response = $this->get(route('api.appointments.ics', ['code' => 'APT-ICSTEST']));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
        $this->assertStringContainsString('BEGIN:VEVENT', $response->getContent());
        $this->assertStringContainsString('APT-ICSTEST', $response->getContent());
    }
}
