<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\BlockedTime;
use App\Models\Holiday;
use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use App\Models\Waitlist;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed database with initial data.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@scheduler.test'],
            [
                'name' => 'Clinic Administrator',
                'password' => bcrypt('password'),
            ]
        );

        $servicesData = [
            [
                'name' => 'Quick Consultation',
                'slug' => 'quick-consultation',
                'description' => 'A focused 15-minute introductory consultation or follow-up check-in to assess progress and answer immediate questions.',
                'duration_minutes' => 15,
                'buffer_minutes' => 10,
                'price' => 45.00,
                'color' => '#0ea5e9',
                'badge' => 'Quick Assessment',
                'max_daily_bookings' => 12,
                'is_active' => true,
            ],
            [
                'name' => 'Standard Therapy Session',
                'slug' => 'standard-therapy-session',
                'description' => 'Our classic 30-minute one-on-one session covering examination, personalized manual therapy, and guided exercise routines.',
                'duration_minutes' => 30,
                'buffer_minutes' => 15,
                'price' => 85.00,
                'color' => '#0d9488',
                'badge' => 'Most Popular',
                'max_daily_bookings' => 8,
                'is_active' => true,
            ],
            [
                'name' => 'Comprehensive Treatment & Rehab',
                'slug' => 'comprehensive-treatment-rehab',
                'description' => 'An in-depth 60-minute diagnostic and rehabilitation appointment with multimodal therapy, biomechanical analysis, and plan adaptation.',
                'duration_minutes' => 60,
                'buffer_minutes' => 15,
                'price' => 150.00,
                'color' => '#6366f1',
                'badge' => 'Recommended for New Patients',
                'max_daily_bookings' => 6,
                'is_active' => true,
            ],
            [
                'name' => 'Specialist Extended Evaluation',
                'slug' => 'specialist-extended-evaluation',
                'description' => 'A dedicated 90-minute senior specialist evaluation for complex clinical cases, custom orthotics, or post-operative recovery roadmaps.',
                'duration_minutes' => 90,
                'buffer_minutes' => 20,
                'price' => 220.00,
                'color' => '#8b5cf6',
                'badge' => 'Specialist Care',
                'max_daily_bookings' => 4,
                'is_active' => true,
            ],
        ];

        $createdServices = [];
        foreach ($servicesData as $s) {
            $createdServices[] = Service::updateOrCreate(['slug' => $s['slug']], $s);
        }

        $defaultHours = [
            'monday' => ['start' => '09:00', 'end' => '17:00'],
            'tuesday' => ['start' => '09:00', 'end' => '17:00'],
            'wednesday' => ['start' => '09:00', 'end' => '17:00'],
            'thursday' => ['start' => '09:00', 'end' => '17:00'],
            'friday' => ['start' => '09:00', 'end' => '17:00'],
            'saturday' => ['start' => '10:00', 'end' => '15:00'],
            'sunday' => null,
        ];

        $providersData = [
            [
                'name' => 'Dr. Sarah Jenkins, PT, DPT',
                'title' => 'Lead Physical Therapist & Specialist',
                'email' => 'sarah.jenkins@scheduler.test',
                'phone' => '+1 (555) 234-5678',
                'bio' => 'Over 12 years of clinical excellence in musculoskeletal rehabilitation and sports injury recovery.',
                'avatar_url' => 'https://images.unsplash.com/photo-1594824813511-57d605175eef?auto=format&fit=crop&w=256&q=80',
                'working_hours' => $defaultHours,
                'daily_capacity' => 8,
                'is_active' => true,
            ],
            [
                'name' => 'Marcus Vance, MS, CSCS',
                'title' => 'Clinical Exercise Physiologist',
                'email' => 'marcus.vance@scheduler.test',
                'phone' => '+1 (555) 345-6789',
                'bio' => 'Specializes in post-surgical mobility re-education and functional movement screening.',
                'avatar_url' => 'https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&w=256&q=80',
                'working_hours' => $defaultHours,
                'daily_capacity' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'Elena Rostova, LMT, CMP',
                'title' => 'Therapeutic Bodywork & Recovery Specialist',
                'email' => 'elena.rostova@scheduler.test',
                'phone' => '+1 (555) 456-7890',
                'bio' => 'Expert in myofascial trigger point release, active recovery, and chronic pain alleviation.',
                'avatar_url' => 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&w=256&q=80',
                'working_hours' => array_merge($defaultHours, [
                    'monday' => null,
                    'saturday' => ['start' => '09:00', 'end' => '16:00'],
                ]),
                'daily_capacity' => 6,
                'is_active' => true,
            ],
        ];

        $createdProviders = [];
        foreach ($providersData as $p) {
            $provider = Provider::updateOrCreate(['email' => $p['email']], $p);
            $provider->services()->sync(collect($createdServices)->pluck('id'));
            $createdProviders[] = $provider;
        }

        $holidays = [
            ['name' => 'Veterans Day', 'date' => '2026-11-11', 'description' => 'Clinic closed.'],
            ['name' => 'Thanksgiving Day', 'date' => '2026-11-26', 'description' => 'Clinic closed.'],
            ['name' => 'Day after Thanksgiving', 'date' => '2026-11-27', 'description' => 'Clinic closed.'],
            ['name' => 'Christmas Day', 'date' => '2026-12-25', 'description' => 'Clinic closed.'],
            ['name' => 'New Year’s Day', 'date' => '2027-01-01', 'description' => 'Clinic closed.'],
        ];

        foreach ($holidays as $h) {
            Holiday::whereDate('date', $h['date'])->delete();
            Holiday::create($h);
        }

        $tomorrow = Carbon::parse('2026-10-29');
        $afterTomorrow = Carbon::parse('2026-10-30');

        $appointmentsData = [
            [
                'reference_code' => 'APT-7X9K2M',
                'service_id' => $createdServices[1]->id,
                'provider_id' => $createdProviders[0]->id,
                'customer_name' => 'James Harrison',
                'customer_email' => 'james.h@example.com',
                'customer_phone' => '+1 (555) 777-8899',
                'customer_notes' => 'Lower back tightness following marathon training.',
                'starts_at' => $tomorrow->copy()->setTime(10, 0),
                'ends_at' => $tomorrow->copy()->setTime(10, 30),
                'status' => 'confirmed',
            ],
            [
                'reference_code' => 'APT-4W2N8P',
                'service_id' => $createdServices[2]->id,
                'provider_id' => $createdProviders[0]->id,
                'customer_name' => 'Claire Chen',
                'customer_email' => 'claire.chen@example.com',
                'customer_phone' => '+1 (555) 333-2211',
                'customer_notes' => 'Post-ACL surgery 8-week mobility reassessment.',
                'starts_at' => $tomorrow->copy()->setTime(13, 0),
                'ends_at' => $tomorrow->copy()->setTime(14, 0),
                'status' => 'confirmed',
            ],
            [
                'reference_code' => 'APT-8M3Q5L',
                'service_id' => $createdServices[0]->id,
                'provider_id' => $createdProviders[1]->id,
                'customer_name' => 'David Miller',
                'customer_email' => 'david.m@example.com',
                'customer_phone' => '+1 (555) 444-9988',
                'customer_notes' => 'Initial inquiry about biomechanical cycling fit.',
                'starts_at' => $afterTomorrow->copy()->setTime(11, 0),
                'ends_at' => $afterTomorrow->copy()->setTime(11, 15),
                'status' => 'confirmed',
            ],
        ];

        foreach ($appointmentsData as $apt) {
            Appointment::updateOrCreate(['reference_code' => $apt['reference_code']], $apt);
        }

        BlockedTime::updateOrCreate(
            ['title' => 'Weekly Team Clinical Briefing', 'starts_at' => $tomorrow->copy()->setTime(12, 0)],
            [
                'provider_id' => null,
                'ends_at' => $tomorrow->copy()->setTime(13, 0),
                'reason' => 'All providers mandatory case review meeting.',
            ]
        );

        BlockedTime::updateOrCreate(
            ['title' => 'Specialist Equipment Maintenance', 'starts_at' => $afterTomorrow->copy()->setTime(15, 0)],
            [
                'provider_id' => $createdProviders[1]->id,
                'ends_at' => $afterTomorrow->copy()->setTime(16, 30),
                'reason' => 'Isokinetic testing dynamometer calibration.',
            ]
        );

        Waitlist::updateOrCreate(
            ['customer_email' => 'sophia.taylor@example.com', 'requested_date' => $tomorrow->format('Y-m-d')],
            [
                'service_id' => $createdServices[1]->id,
                'provider_id' => $createdProviders[0]->id,
                'preferred_time' => 'morning',
                'customer_name' => 'Sophia Taylor',
                'customer_phone' => '+1 (555) 999-1234',
                'notes' => 'Flexible if any morning cancellation opens up.',
                'status' => 'waiting',
            ]
        );
    }
}
