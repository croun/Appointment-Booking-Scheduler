# Appointment Booking & Scheduler

A modern, full-stack appointment scheduling and provider management web application built with **Laravel 12 (PHP 8.3)**, **Tailwind CSS v4**, and **Alpine.js**.

---

## ✨ Features

### 👤 Client Booking View
- **Multi-Service Selection**: Multiple appointment services with customizable duration (15m to 90m), buffer times, and pricing.
- **Provider Choice & Availability**: Filter availability by specific provider or choose "Any Available Specialist".
- **Real-Time Calendar**: Visual month calendar showing available vs. fully booked days, past date disabling, and national holiday gray-out.
- **Dynamic Slot Generation**:
  - Automatically calculates bookable time slots based on provider working hours, service duration, and required buffer times.
  - Enforces a minimum 2-hour lead time (past slots collapsed).
  - Race-condition and double-booking prevention using database-level locking (`lockForUpdate`).
- **Immediate Booking Summary & Confirmation**:
  - Live summary card keeping track of selected service, provider, date, and slot.
  - Generates a unique reference code (`APT-XXXXXX`).
  - Google Calendar direct link (`calendar.google.com`) and downloadable iCalendar (`.ics`) file.
- **Manage Bookings**: Self-service appointment cancellation and rescheduling governed by a strict 24-hour lead rule.
- **Recurring Bookings**: Optional weekly recurring slot reservation.
- **Waitlist Registration**: Join priority waitlist when dates or slots are full.
- **Simulated Communications**: Real-time simulated SMS and transactional email drawer for client confirmations.

### 🛡️ Admin & Provider Portal
- **Daily Schedule Dashboard**: View real-time schedule timeline grouped by provider and time.
- **Key Performance Indicators (KPIs)**: Total appointments, capacity utilization rate, revenue, completed, and no-show metrics.
- **Time Block-Outs**: Block off vacations, emergencies, or staff meetings to prevent client bookings.
- **Status Workflows**: Seamlessly transition appointments across `Pending` ➔ `Confirmed` ➔ `Completed` / `Cancelled` / `No-Show`.
- **Waitlist Review**: View and manage queued waitlist requests with customer contact information.

---

## 🛠️ Tech Stack

- **Backend**: Laravel 12, PHP 8.3, SQLite / MySQL
- **Frontend**: Blade, Tailwind CSS 4.0, Alpine.js 3.x
- **Build Tool**: Vite 8.3
- **Testing**: PHPUnit / Laravel Test Suite (10 feature tests, 45 assertions)
- **Formatting**: Laravel Pint

---

## 🚀 Getting Started

### Prerequisites

- PHP 8.3+
- Composer
- Node.js 18+ and npm

### Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/croun/Appointment-Booking-Scheduler.git
   cd Appointment-Booking-Scheduler
   ```

2. **Install PHP dependencies**:
   ```bash
   composer install
   ```

3. **Install JavaScript dependencies**:
   ```bash
   npm install
   ```

4. **Environment Setup**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Database Migration & Seeding**:
   ```bash
   php artisan migrate:fresh --seed
   ```

6. **Build Frontend Assets**:
   ```bash
   npm run build
   ```

7. **Run the Development Server**:
   ```bash
   php artisan serve
   ```
   Open `http://localhost:8000` in your browser.

---

## 🧪 Testing

Run the automated test suite:
```bash
php artisan test
```

Run code formatting checks:
```bash
vendor/bin/pint
```

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).
