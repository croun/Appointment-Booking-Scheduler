# Appointment Booking & Scheduler (Node.js & Express)

A modern, high-performance appointment booking and queue management system built with **Node.js (v24)**, **Express**, **MySQL (XAMPP / MariaDB)**, **Tailwind CSS**, and **Alpine.js**.

---

## Key Features

### 1. Guest Booking Workflow (No Login Required)
- **Direct Access**: Guests book schedules directly without creating an account or logging in.
- **Service Selection**: Services with prices in **Philippine Peso (`₱` / PHP)** (₱500, ₱1,200, ₱2,000, ₱3,000).
- **Interactive Calendar**: Visual month calendar showing available vs. few slots vs. fully booked days, past date disabling, and holiday detection. No dead space.
- **Dynamic Slot Generation**:
  - Automatically calculates bookable time slots based on provider working hours, duration, and buffer times.
  - Enforces minimum 2-hour lead time notice.
  - Double-booking prevention via database transaction row locks (`FOR UPDATE`).
- **Direct Contact Submission**: Full Name, Email, Phone, and optional notes (Direct guest confirmation, OTP removed).
- **Atomic Daily Sequential Queue Numbering**:
  - Sequential queue counter generated atomically per day and per service (`A-001`, `A-002`, `B-001`).
  - Resets daily.
- **Self-Service Manage Link**:
  - Unguessable private token link (`/manage/:token`) allowing guests to view, reschedule, or cancel anytime without login.
- **Lost Booking Lookup**:
  - Guests can retrieve their appointment anytime using their Reference Code (`APT-XXXXXX`) and Email/Phone.
- **Calendar Integrations**:
  - Instant Google Calendar event link and downloadable `.ics` iCalendar file.
- **Anti-Spam & Duplicate Booking Protections**:
  - Honeypot spam bot traps.
  - 1 active booking per contact per day policy.

### 2. Live "Now Serving" Public Queue Board
- Privacy-safe queue display for clinic TVs and waiting areas (`/api/queue/now-serving`).
- Displays serving tickets and next-in-line tickets while concealing sensitive customer contact details.

### 3. Admin & Provider Operations
- **Daily Queue & Schedule Dashboard**: Filterable by date, provider, and status (`Confirmed`, `Serving`, `Completed`, `Cancelled`, `No-Show`).
- **Key Performance Indicators (KPIs)**: Total bookings, capacity usage %, completed count, waitlist count, and Estimated Daily Revenue in **Philippine Peso (`₱`)**.
- **Time Block-Outs**: Block off vacations or maintenance windows.
- **Priority Waitlist**: Waitlist management for fully booked days.

---

## Tech Stack

- **Backend Runtime**: Node.js v24.x
- **Framework**: Express 5.x
- **Database**: MySQL 8.0+ / MariaDB (XAMPP phpMyAdmin) using `mysql2/promise` connection pooling
- **Template & Views**: EJS, HTML5
- **Styling**: Tailwind CSS
- **Interactivity**: Alpine.js 3.x
- **Date & Calendar**: JavaScript standard UTC & Intl API, iCalendar RFC 5545 generator

---

## Project Structure

```
appointment-booking-scheduler/
├── server.js                      # Server entry point (starts Express on port 8000)
├── server/
│   ├── app.js                     # Express app configuration & middlewares
│   ├── config/
│   │   └── db.js                  # MySQL pool configuration (reads .env)
│   ├── controllers/
│   │   ├── bookingController.js   # Guest booking, slots, lookup, manage, ics
│   │   └── adminBookingController.js # Admin schedule, stats, block times, waitlist
│   ├── services/
│   │   ├── bookingAvailabilityService.js # Calendar summary & slot computation
│   │   └── bookingManagerService.js      # Atomic queue numbering, locking, transactions
│   ├── routes/
│   │   ├── api.js                 # API endpoints (/api/*)
│   │   └── web.js                 # Web page routes (/, /manage/:token)
│   └── utils/
│       └── icsGenerator.js        # iCalendar (.ics) and Google Calendar generator
├── views/
│   └── scheduler.ejs              # Main responsive scheduler UI
├── public/                        # Static assets (Tailwind CSS, fonts, icons)
├── database/
│   └── database.sql               # Full MySQL database schema & initial seed data
├── .env                           # Environment configuration
└── package.json                   # Dependencies and scripts
```

---

## Quick Start

### 1. Database Setup (XAMPP MySQL)
1. Ensure MySQL is running in XAMPP on port `3306`.
2. Access phpMyAdmin at:
   `http://localhost/phpmyadmin/index.php?route=/database/structure&db=appointment_booking_scheduler`
3. If not already present, import `database/database.sql`.

### 2. Environment Configuration (`.env`)
```ini
PORT=8000
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=appointment_booking_scheduler
DB_USERNAME=root
DB_PASSWORD=
APP_TIMEZONE=UTC
```

### 3. Run the Node.js Server
```bash
# Start server
npm start

# Or directly:
node server.js
```

Open your browser at:
**[http://localhost:8000](http://localhost:8000)**
