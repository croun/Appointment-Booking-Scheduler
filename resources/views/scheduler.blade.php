<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50 text-slate-900 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Lumina Health & Performance | Appointment Scheduler</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-screen flex flex-col overflow-hidden bg-slate-50 text-slate-800"
      x-data="schedulerApp()"
      x-init="initApp()">

    <!-- Header (h-12 / 48px) -->
    <header class="h-12 shrink-0 bg-white/95 backdrop-blur border-b border-slate-200 z-30">
        <div class="h-full max-w-7xl mx-auto px-3 flex items-center justify-between">
            
            <!-- Brand -->
            <div class="flex items-center space-x-2.5">
                <div class="w-7 h-7 rounded-lg bg-teal-600 flex items-center justify-center text-white shadow-xs">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <div class="flex items-center space-x-1.5">
                    <span class="font-bold text-sm tracking-tight text-slate-900">Lumina Health</span>
                    <span class="text-[10px] font-semibold px-1.5 py-0.2 rounded bg-teal-50 text-teal-700 border border-teal-200">Scheduler</span>
                </div>
            </div>

            <!-- View toggle -->
            <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200">
                <button type="button"
                        @click="switchView('client')"
                        :class="activeView === 'client' ? 'bg-white text-teal-700 shadow-xs font-semibold' : 'text-slate-600 hover:text-slate-900 font-medium'"
                        class="px-2.5 py-1 rounded-md text-xs transition flex items-center space-x-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span>Client Booking</span>
                </button>
                <button type="button"
                        @click="switchView('admin')"
                        :class="activeView === 'admin' ? 'bg-white text-teal-700 shadow-xs font-semibold' : 'text-slate-600 hover:text-slate-900 font-medium'"
                        class="px-2.5 py-1 rounded-md text-xs transition flex items-center space-x-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span>Admin Portal</span>
                </button>
            </div>

            <!-- Header actions -->
            <div class="flex items-center space-x-2">
                <div class="relative hidden sm:flex items-center text-[11px] text-slate-600 bg-slate-50 border border-slate-200 rounded-md px-2 py-1">
                    <svg class="w-3 h-3 mr-1 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <select x-model="selectedTimezone" @change="onTimezoneChange()" class="bg-transparent border-0 text-[11px] text-slate-700 font-medium focus:ring-0 cursor-pointer p-0">
                        <option value="UTC">UTC</option>
                        <option value="America/New_York">US Eastern (ET)</option>
                        <option value="America/Chicago">US Central (CT)</option>
                        <option value="America/Los_Angeles">US Pacific (PT)</option>
                        <option value="Europe/London">London (GMT/BST)</option>
                        <option value="Asia/Tokyo">Tokyo (JST)</option>
                        <option value="Asia/Shanghai">Beijing / Shanghai (CST)</option>
                        <option value="Australia/Sydney">Sydney (AEST)</option>
                    </select>
                </div>

                <button type="button"
                        @click="openManageModal()"
                        class="px-2.5 py-1 text-xs font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 transition shadow-2xs flex items-center space-x-1">
                    <svg class="w-3 h-3 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <span>Lookup</span>
                </button>

                <button type="button"
                        @click="isNotificationDrawerOpen = true"
                        title="Simulated Alerts"
                        class="relative p-1.5 text-slate-500 hover:text-slate-800 bg-white border border-slate-200 rounded-md hover:bg-slate-50 transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span x-show="notifications.length > 0"
                          x-text="notifications.length"
                          class="absolute -top-1 -right-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-teal-600 text-[8px] font-bold text-white ring-1 ring-white"></span>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Full-Viewport Grid (p-3 / 12px, gap-3 / 12px) -->
    <main class="flex-1 overflow-hidden max-w-7xl w-full mx-auto p-3">

        <!-- Client view -->
        <div x-show="activeView === 'client'" class="h-full grid grid-cols-1 lg:grid-cols-12 gap-3 items-stretch">
            
            <!-- Left column: Services & Providers (Fits comfortably without scrolling) -->
            <section class="lg:col-span-4 h-full flex flex-col justify-between overflow-y-auto bg-white rounded-xl border border-slate-200 p-3 space-y-2.5">
                
                <!-- Services -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between mb-1">
                        <div class="flex items-center space-x-1.5">
                            <span class="w-5 h-5 rounded-full bg-teal-50 text-teal-700 text-[11px] font-bold inline-flex items-center justify-center border border-teal-200">1</span>
                            <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Select Service</h2>
                        </div>
                        <span class="text-[10px] text-slate-500 font-medium" x-text="services.length + ' available'"></span>
                    </div>

                    <!-- Compact rows: Full name visible, badge moved to expand area -->
                    <template x-for="service in services" :key="service.id">
                        <div @click="selectService(service)"
                             :class="selectedService?.id === service.id ? 'border-teal-600 bg-teal-50/40 ring-1 ring-teal-500' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50/60'"
                             class="p-2 rounded-lg border transition-all cursor-pointer">
                            
                            <!-- Single-line compact row with complete title -->
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center space-x-2 min-w-0 pr-2">
                                    <span class="w-2 h-2 rounded-full shrink-0" :style="'background-color:' + (service.color || '#0d9488')"></span>
                                    <span class="font-bold text-slate-900 truncate" x-text="service.name"></span>
                                </div>
                                <div class="flex items-center space-x-2 shrink-0">
                                    <span class="text-[11px] text-slate-500 font-medium" x-text="service.duration_minutes + 'm'"></span>
                                    <span class="font-extrabold text-slate-900" x-text="'$' + parseFloat(service.price).toFixed(2)"></span>
                                </div>
                            </div>

                            <!-- Expand area on select with badge and description -->
                            <div x-show="selectedService?.id === service.id" x-transition class="mt-1.5 pt-1.5 border-t border-slate-200/60 text-[11px] text-slate-600 leading-relaxed">
                                <div class="flex items-center justify-between mb-1">
                                    <template x-if="service.badge">
                                        <span class="text-[9px] font-semibold uppercase px-1.5 py-0.2 rounded bg-teal-100 text-teal-800" x-text="service.badge"></span>
                                    </template>
                                    <span class="text-[10px] text-teal-800 font-medium">+<span x-text="service.buffer_minutes"></span>m buffer included</span>
                                </div>
                                <p x-text="service.description"></p>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Provider selection with reliable initials fallback -->
                <div class="pt-2 border-t border-slate-100 space-y-1.5">
                    <div class="flex items-center space-x-1.5 mb-1">
                        <span class="w-5 h-5 rounded-full bg-teal-50 text-teal-700 text-[11px] font-bold inline-flex items-center justify-center border border-teal-200">2</span>
                        <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Select Specialist</h2>
                    </div>

                    <!-- Any specialist -->
                    <div @click="selectProvider(null)"
                         :class="selectedProvider === null ? 'border-teal-600 bg-teal-50/40 ring-1 ring-teal-500 font-bold' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50/60'"
                         class="p-1.5 px-2 rounded-lg border transition-all cursor-pointer flex items-center justify-between text-xs">
                        <div class="flex items-center space-x-2">
                            <div class="w-6 h-6 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center text-[10px] font-bold">⚡</div>
                            <span class="font-semibold text-slate-900">Any Available Specialist</span>
                        </div>
                        <span x-show="selectedProvider === null" class="text-teal-700 font-bold text-xs">✓</span>
                    </div>

                    <!-- Specific providers with clean initials avatars -->
                    <template x-for="provider in providers" :key="provider.id">
                        <div @click="selectProvider(provider)"
                             :class="selectedProvider?.id === provider.id ? 'border-teal-600 bg-teal-50/40 ring-1 ring-teal-500 font-bold' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50/60'"
                             class="p-1.5 px-2 rounded-lg border transition-all cursor-pointer flex items-center justify-between text-xs">
                            <div class="flex items-center space-x-2 min-w-0 pr-2">
                                <div class="w-6 h-6 rounded-full bg-teal-100 text-teal-800 font-bold text-[10px] flex items-center justify-center shrink-0 border border-teal-200"
                                     x-text="getInitials(provider.name)"></div>
                                <div class="truncate">
                                    <span class="font-semibold text-slate-900 block truncate" x-text="provider.name"></span>
                                </div>
                            </div>
                            <span x-show="selectedProvider?.id === provider.id" class="text-teal-700 font-bold text-xs shrink-0">✓</span>
                        </div>
                    </template>
                </div>

                <!-- Recurring option (compact, fits in column) -->
                <div class="pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-slate-700">Repeat weekly:</span>
                        <input type="checkbox" x-model="isRecurring" class="rounded text-teal-600 focus:ring-teal-500">
                    </div>
                    <div x-show="isRecurring" x-transition class="mt-1 flex items-center justify-between text-xs">
                        <span class="text-slate-500 text-[11px]">Sessions:</span>
                        <select x-model="recurringWeeks" class="bg-slate-50 border border-slate-200 text-xs rounded px-2 py-0.5 font-medium">
                            <option value="2">2 weeks</option>
                            <option value="3">3 weeks</option>
                            <option value="4">4 weeks</option>
                        </select>
                    </div>
                </div>

            </section>


            <!-- Center column: Full Month Calendar (Top) + Booking Summary & Form (Bottom) -->
            <section class="lg:col-span-4 h-full flex flex-col justify-between overflow-y-auto bg-white rounded-xl border border-slate-200 p-3 space-y-3">
                
                <!-- Full Month Calendar Area -->
                <div>
                    <!-- Calendar header -->
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center space-x-1.5">
                            <span class="w-5 h-5 rounded-full bg-teal-50 text-teal-700 text-[11px] font-bold inline-flex items-center justify-center border border-teal-200">3</span>
                            <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider" x-text="calendarMonthName + ' ' + calendarYear"></h2>
                        </div>

                        <div class="flex items-center space-x-1">
                            <button type="button"
                                    @click="prevMonth()"
                                    :disabled="isPrevMonthDisabled()"
                                    :class="isPrevMonthDisabled() ? 'opacity-30 cursor-not-allowed' : 'hover:bg-slate-100 text-slate-700'"
                                    class="p-1 rounded text-slate-500 transition">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <button type="button"
                                    @click="nextMonth()"
                                    class="p-1 rounded text-slate-500 hover:bg-slate-100 transition">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Weekday headers -->
                    <div class="grid grid-cols-7 text-center text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
                        <span>Su</span>
                        <span>Mo</span>
                        <span>Tu</span>
                        <span>We</span>
                        <span>Th</span>
                        <span>Fr</span>
                        <span>Sa</span>
                    </div>

                    <!-- Full Month Grid with past dates dimmed, filling the space -->
                    <div class="relative">
                        <div x-show="isCalendarLoading" class="absolute inset-0 bg-white/70 backdrop-blur-[1px] flex items-center justify-center z-10">
                            <div class="w-5 h-5 border-2 border-teal-600 border-t-transparent rounded-full animate-spin"></div>
                        </div>

                        <div class="grid grid-cols-7 gap-1">
                            <template x-for="(dayItem, idx) in fullMonthDays" :key="dayItem.date || ('blank-' + idx)">
                                <div class="w-full">
                                    <div x-show="dayItem.isBlank" class="h-7 sm:h-8"></div>
                                    <button x-show="!dayItem.isBlank"
                                            type="button"
                                            @click="selectDate(dayItem)"
                                            :disabled="dayItem.status === 'past' || dayItem.status === 'holiday' || dayItem.status === 'closed'"
                                            :title="getDayTitle(dayItem)"
                                            :class="getDayClass(dayItem)"
                                            class="w-full h-7 sm:h-8 rounded-lg flex items-center justify-center space-x-1 text-xs font-semibold transition px-0.5">
                                        <span x-text="dayItem.day"></span>
                                        <span x-show="dayItem.status === 'few_left' && selectedDate !== dayItem.date" class="w-1.5 h-1.5 rounded-full bg-amber-400" title="Few slots left"></span>
                                        <span x-show="dayItem.status === 'holiday'" class="w-1.5 h-1.5 rounded-full bg-rose-400" title="Holiday"></span>
                                        <span x-show="dayItem.status === 'full'" class="text-[7px] font-bold text-slate-400">Full</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Calendar legend without duplicate date -->
                    <div class="mt-2 pt-1.5 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-500">
                        <div class="flex items-center space-x-2">
                            <span class="flex items-center space-x-1">
                                <span class="w-2 h-2 rounded-full bg-teal-600 inline-block"></span>
                                <span>Selected</span>
                            </span>
                            <span class="flex items-center space-x-1">
                                <span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>
                                <span>Few slots</span>
                            </span>
                            <span class="flex items-center space-x-1">
                                <span class="w-2 h-2 rounded-full bg-rose-400 inline-block"></span>
                                <span>Holiday</span>
                            </span>
                        </div>
                        <span class="text-[10px] text-slate-400">Dimmed = Past / Closed</span>
                    </div>
                </div>

                <!-- Booking Summary & Form (Moved under calendar in middle column) -->
                <div class="pt-2 border-t border-slate-200">
                    
                    <!-- Summary Card -->
                    <div class="text-xs space-y-1 mb-2 bg-slate-50 p-2.5 rounded-lg border border-slate-200 shadow-2xs">
                        <div class="flex justify-between text-slate-600">
                            <span>Service:</span>
                            <span class="font-bold text-slate-900" x-text="selectedService?.name || '—'"></span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Schedule:</span>
                            <span class="font-bold text-teal-700" x-text="(selectedSlot ? selectedSlot.formatted_time : 'Pick a time slot') + ' • ' + (selectedDate ? formattedSelectedDate : '')"></span>
                        </div>
                        <div class="flex justify-between text-slate-800 pt-1 border-t border-slate-200/60 font-bold">
                            <span>Total Due:</span>
                            <span>$<span x-text="selectedService ? parseFloat(selectedService.price).toFixed(2) : '0.00'"></span></span>
                        </div>
                    </div>

                    <!-- Details Form -->
                    <div x-show="selectedSlot" x-transition class="space-y-2">
                        <form @submit.prevent="submitBooking()" class="space-y-2">
                            <input type="text"
                                   x-model="form.customer_name"
                                   required
                                   placeholder="Full Name *"
                                   class="w-full text-xs rounded-md border-slate-300 py-1.5 focus:border-teal-500 focus:ring-teal-500">
                            
                            <div class="grid grid-cols-2 gap-2">
                                <input type="email"
                                       x-model="form.customer_email"
                                       required
                                       placeholder="Email *"
                                       class="w-full text-xs rounded-md border-slate-300 py-1.5 focus:border-teal-500 focus:ring-teal-500">
                                <input type="tel"
                                       x-model="form.customer_phone"
                                       required
                                       placeholder="Phone *"
                                       class="w-full text-xs rounded-md border-slate-300 py-1.5 focus:border-teal-500 focus:ring-teal-500">
                            </div>

                            <input type="text"
                                   x-model="form.customer_notes"
                                   placeholder="Optional notes or reason for visit..."
                                   class="w-full text-xs rounded-md border-slate-300 py-1.5 focus:border-teal-500 focus:ring-teal-500">

                            <template x-if="bookingErrorMessage">
                                <div class="p-1.5 text-xs text-rose-700 bg-rose-50 border border-rose-200 rounded" x-text="bookingErrorMessage"></div>
                            </template>

                            <button type="submit"
                                    :disabled="isSubmittingBooking"
                                    class="w-full py-2 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-lg transition shadow-xs flex items-center justify-center space-x-1.5">
                                <span x-show="!isSubmittingBooking">Confirm Appointment</span>
                                <span x-show="isSubmittingBooking">Reserving...</span>
                            </button>
                        </form>
                    </div>

                    <div x-show="!selectedSlot" class="text-center text-[11px] text-slate-400 py-1">
                        Select a time slot on the right to enter your details
                    </div>
                </div>

            </section>


            <!-- Right column: Holds ONLY time slots in a 4-column grid (No scrolling, no dead space) -->
            <section class="lg:col-span-4 h-full flex flex-col overflow-y-auto bg-white rounded-xl border border-slate-200 p-3 space-y-2.5">
                
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-1.5">
                        <span class="w-5 h-5 rounded-full bg-teal-50 text-teal-700 text-[11px] font-bold inline-flex items-center justify-center border border-teal-200">4</span>
                        <h2 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Available Slots</h2>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-teal-50 text-teal-700 border border-teal-200"
                          x-text="availableSlotsCount + ' open'"></span>
                </div>

                <!-- Filter tabs -->
                <div class="flex items-center space-x-1 bg-slate-100 p-0.5 rounded-lg text-[11px]">
                    <button type="button" @click="slotFilter = 'all'" :class="slotFilter === 'all' ? 'bg-white text-slate-900 font-bold shadow-2xs' : 'text-slate-500'" class="flex-1 py-1 rounded transition text-center">All</button>
                    <button type="button" @click="slotFilter = 'Morning'" :class="slotFilter === 'Morning' ? 'bg-white text-slate-900 font-bold shadow-2xs' : 'text-slate-500'" class="flex-1 py-1 rounded transition text-center">Morn</button>
                    <button type="button" @click="slotFilter = 'Afternoon'" :class="slotFilter === 'Afternoon' ? 'bg-white text-slate-900 font-bold shadow-2xs' : 'text-slate-500'" class="flex-1 py-1 rounded transition text-center">Aft</button>
                    <button type="button" @click="slotFilter = 'Evening'" :class="slotFilter === 'Evening' ? 'bg-white text-slate-900 font-bold shadow-2xs' : 'text-slate-500'" class="flex-1 py-1 rounded transition text-center">Eve</button>
                </div>

                <!-- Earlier past-lead-time slots collapsed bar -->
                <template x-if="pastLeadTimeSlotsCount > 0">
                    <button type="button"
                            @click="showPastSlots = !showPastSlots"
                            class="w-full text-center py-1 text-[10px] text-slate-500 hover:text-slate-800 bg-slate-50 rounded border border-dashed border-slate-200 transition flex items-center justify-center space-x-1">
                        <span x-text="pastLeadTimeSlotsCount + ' earlier slots unavailable (passed)'"></span>
                        <span class="text-[9px]" x-text="showPastSlots ? '▲ Hide' : '▼ Show'"></span>
                    </button>
                </template>

                <!-- 4-Column Grid of Slots (Clean, compact, no repeated Afternoon label) -->
                <div class="relative flex-1">
                    <div x-show="isSlotsLoading" class="absolute inset-0 bg-white/70 flex items-center justify-center z-10">
                        <div class="w-5 h-5 border-2 border-teal-600 border-t-transparent rounded-full animate-spin"></div>
                    </div>

                    <div class="grid grid-cols-4 gap-1.5">
                        <template x-for="slot in displaySlots" :key="slot.datetime">
                            <button type="button"
                                    @click="selectSlot(slot)"
                                    :disabled="!slot.available"
                                    :class="getSlotClass(slot)"
                                    class="h-9 rounded-lg text-xs font-bold transition flex items-center justify-center">
                                <span x-text="slot.formatted_time"></span>
                            </button>
                        </template>
                    </div>

                    <!-- No slots / waitlist -->
                    <template x-if="slots.length === 0 || availableSlotsCount === 0">
                        <div class="py-6 text-center bg-amber-50/70 border border-amber-200 rounded-lg p-3 mt-2">
                            <p class="text-xs font-bold text-amber-900">No open slots on this date</p>
                            <p class="text-[11px] text-amber-700 mt-0.5">Join the priority waitlist to be alerted if a slot opens up.</p>
                            <button type="button" @click="openWaitlistModal()" class="mt-3 px-3 py-1.5 bg-amber-600 text-white rounded text-xs font-semibold hover:bg-amber-700 transition">
                                Join Priority Waitlist
                            </button>
                        </div>
                    </template>
                </div>

            </section>

        </div>


        <!-- Admin Portal view (scrolls internally) -->
        <div x-show="activeView === 'admin'" class="h-full overflow-y-auto space-y-3 pr-1">
            
            <div class="bg-white rounded-xl border border-slate-200 shadow-2xs p-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 class="text-base font-bold text-slate-900">Clinical Operations & Schedule</h1>
                    <p class="text-xs text-slate-500">Manage daily appointments, capacity, and provider blockouts</p>
                </div>

                <div class="flex items-center space-x-2">
                    <input type="date" x-model="adminDate" @change="loadAdminData()" class="text-xs rounded-lg border-slate-200 bg-slate-50 py-1 px-2 font-semibold">
                    <select x-model="adminProviderId" @change="loadAdminData()" class="text-xs rounded-lg border-slate-200 bg-slate-50 py-1 px-2 font-medium">
                        <option value="">All Providers</option>
                        <template x-for="p in providers" :key="p.id">
                            <option :value="p.id" x-text="p.name"></option>
                        </template>
                    </select>
                    <button type="button" @click="isBlockTimeModalOpen = true" class="px-3 py-1 bg-slate-900 text-white rounded-lg text-xs font-semibold hover:bg-slate-800 transition">
                        + Block Time
                    </button>
                </div>
            </div>

            <!-- Metrics -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                <div class="bg-white p-3 rounded-xl border border-slate-200">
                    <span class="text-[11px] font-semibold text-slate-500">Total Bookings</span>
                    <div class="text-xl font-bold text-slate-900 mt-1" x-text="adminStats.total_bookings"></div>
                </div>
                <div class="bg-white p-3 rounded-xl border border-slate-200">
                    <span class="text-[11px] font-semibold text-slate-500">Capacity Usage</span>
                    <div class="text-xl font-bold text-slate-900 mt-1" x-text="adminStats.capacity_percentage + '%'"></div>
                </div>
                <div class="bg-white p-3 rounded-xl border border-slate-200">
                    <span class="text-[11px] font-semibold text-slate-500">Completed</span>
                    <div class="text-xl font-bold text-emerald-600 mt-1" x-text="adminStats.completed"></div>
                </div>
                <div class="bg-white p-3 rounded-xl border border-slate-200">
                    <span class="text-[11px] font-semibold text-slate-500">Est. Revenue</span>
                    <div class="text-xl font-bold text-slate-900 mt-1" x-text="'$' + parseFloat(adminStats.revenue_today || 0).toFixed(2)"></div>
                </div>
                <div class="bg-white p-3 rounded-xl border border-slate-200">
                    <span class="text-[11px] font-semibold text-slate-500">Waitlist Queue</span>
                    <div class="text-xl font-bold text-amber-600 mt-1" x-text="adminStats.waitlist_count"></div>
                </div>
            </div>

            <!-- Schedule table -->
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="p-3 border-b border-slate-100 flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-800">Appointments Schedule</span>
                    <input type="text" x-model="adminSearchQuery" @input.debounce.300ms="loadAdminData()" placeholder="Filter..." class="text-xs rounded-md border-slate-200 py-1 px-2 w-48">
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500 font-semibold uppercase text-[10px] border-b border-slate-200">
                            <tr>
                                <th class="py-2 px-3">Ref</th>
                                <th class="py-2 px-3">Time</th>
                                <th class="py-2 px-3">Client</th>
                                <th class="py-2 px-3">Service</th>
                                <th class="py-2 px-3">Provider</th>
                                <th class="py-2 px-3">Status</th>
                                <th class="py-2 px-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="apt in adminAppointments" :key="apt.id">
                                <tr class="hover:bg-slate-50/80">
                                    <td class="py-2 px-3 font-mono font-bold" x-text="apt.reference_code"></td>
                                    <td class="py-2 px-3 font-semibold" x-text="formatTimeWindow(apt.starts_at, apt.ends_at)"></td>
                                    <td class="py-2 px-3" x-text="apt.customer_name"></td>
                                    <td class="py-2 px-3" x-text="apt.service?.name"></td>
                                    <td class="py-2 px-3" x-text="apt.provider?.name"></td>
                                    <td class="py-2 px-3">
                                        <span :class="getStatusBadgeClass(apt.status)" class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase" x-text="apt.status"></span>
                                    </td>
                                    <td class="py-2 px-3 text-right space-x-1">
                                        <button type="button" x-show="apt.status === 'confirmed'" @click="updateAppointmentStatus(apt.id, 'completed')" class="text-emerald-600 font-semibold">Done</button>
                                        <button type="button" x-show="apt.status !== 'cancelled' && apt.status !== 'completed'" @click="updateAppointmentStatus(apt.id, 'cancelled')" class="text-rose-600 font-semibold">Cancel</button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="adminAppointments.length === 0">
                                <tr>
                                    <td colspan="7" class="py-6 text-center text-slate-400">No appointments found.</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </main>


    <!-- Confirmation modal -->
    <div x-show="isConfirmationModalOpen"
         x-transition
         class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        
        <div @click.away="isConfirmationModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl text-center">
            <div class="w-12 h-12 bg-teal-100 text-teal-700 rounded-xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <h3 class="text-base font-bold text-slate-900">Appointment Confirmed</h3>
            <p class="text-xs text-slate-500 mt-0.5">Reference: <strong class="font-mono text-teal-700" x-text="confirmedAppointment?.reference_code"></strong></p>

            <div class="mt-4 p-3 bg-slate-50 rounded-xl text-left text-xs space-y-1.5 border border-slate-200">
                <div><strong>Service:</strong> <span x-text="confirmedAppointment?.service?.name"></span></div>
                <div><strong>Provider:</strong> <span x-text="confirmedAppointment?.provider?.name"></span></div>
                <div><strong>Date & Time:</strong> <span x-text="formatDateTime(confirmedAppointment?.starts_at)"></span></div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2">
                <a :href="confirmedAppointmentGoogleUrl" target="_blank" class="py-2 bg-slate-100 text-slate-800 rounded-lg text-xs font-semibold hover:bg-slate-200 transition text-center">Google Calendar</a>
                <a :href="'/api/appointments/' + confirmedAppointment?.reference_code + '/ics'" download class="py-2 bg-slate-100 text-slate-800 rounded-lg text-xs font-semibold hover:bg-slate-200 transition text-center">Download .ICS</a>
            </div>

            <button type="button" @click="isConfirmationModalOpen = false; resetBookingFlow()" class="w-full mt-3 py-2 bg-teal-600 text-white rounded-lg text-xs font-bold hover:bg-teal-700 transition">
                Done
            </button>
        </div>
    </div>


    <!-- Lookup modal -->
    <div x-show="isManageModalOpen" x-transition class="fixed inset-0 z-50 bg-slate-900/60 flex items-center justify-center p-4">
        <div @click.away="isManageModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-5 shadow-2xl">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-sm font-bold text-slate-900">Lookup Booking</h3>
                <button type="button" @click="isManageModalOpen = false" class="text-slate-400">✕</button>
            </div>

            <div class="flex space-x-2">
                <input type="text" x-model="manageSearchCode" placeholder="Reference code (e.g. APT-7X9K2M)" class="flex-1 uppercase font-mono text-xs rounded-lg border-slate-300">
                <button type="button" @click="lookupAppointment()" class="px-3 py-1.5 bg-slate-900 text-white text-xs font-bold rounded-lg">Find</button>
            </div>

            <template x-if="managedAppointment">
                <div class="mt-4 p-3 bg-slate-50 rounded-xl text-xs space-y-1.5 border border-slate-200">
                    <div class="flex justify-between">
                        <span class="font-mono font-bold" x-text="managedAppointment.reference_code"></span>
                        <span :class="getStatusBadgeClass(managedAppointment.status)" class="px-1.5 py-0.2 rounded text-[9px] uppercase font-bold" x-text="managedAppointment.status"></span>
                    </div>
                    <div><strong>Service:</strong> <span x-text="managedAppointment.service?.name"></span></div>
                    <div><strong>Time:</strong> <span x-text="formatDateTime(managedAppointment.starts_at)"></span></div>
                    
                    <template x-if="managedAppointmentCanBeModified && managedAppointment.status === 'confirmed'">
                        <button type="button" @click="cancelManagedBooking()" class="mt-2 text-rose-600 font-bold hover:underline">Cancel Appointment</button>
                    </template>
                </div>
            </template>
        </div>
    </div>


    <!-- Priority Waitlist modal -->
    <div x-show="isWaitlistModalOpen" x-transition class="fixed inset-0 z-50 bg-slate-900/60 flex items-center justify-center p-4">
        <div @click.away="isWaitlistModalOpen = false" class="bg-white rounded-2xl max-w-sm w-full p-5 shadow-2xl">
            <h3 class="text-sm font-bold text-slate-900">Join Priority Waitlist</h3>
            <form @submit.prevent="submitWaitlist()" class="mt-3 space-y-2 text-xs">
                <div>
                    <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Preferred Time</label>
                    <select x-model="waitlistForm.preferred_time" class="w-full text-xs rounded border-slate-300 py-1">
                        <option value="anytime">Any Time</option>
                        <option value="morning">Morning</option>
                        <option value="afternoon">Afternoon</option>
                    </select>
                </div>
                <input type="text" x-model="waitlistForm.customer_name" required placeholder="Name *" class="w-full text-xs rounded border-slate-300 py-1">
                <input type="email" x-model="waitlistForm.customer_email" required placeholder="Email *" class="w-full text-xs rounded border-slate-300 py-1">
                <input type="tel" x-model="waitlistForm.customer_phone" required placeholder="Phone *" class="w-full text-xs rounded border-slate-300 py-1">
                
                <div class="flex space-x-2 pt-1">
                    <button type="submit" class="flex-1 py-1.5 bg-amber-600 text-white font-bold rounded">Join Waitlist</button>
                    <button type="button" @click="isWaitlistModalOpen = false" class="px-3 py-1.5 bg-slate-100 rounded">Cancel</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Admin Block time modal -->
    <div x-show="isBlockTimeModalOpen" x-transition class="fixed inset-0 z-50 bg-slate-900/60 flex items-center justify-center p-4">
        <div @click.away="isBlockTimeModalOpen = false" class="bg-white rounded-2xl max-w-sm w-full p-5 shadow-2xl">
            <h3 class="text-sm font-bold text-slate-900">Block Time Window</h3>
            <form @submit.prevent="submitBlockTime()" class="mt-3 space-y-2 text-xs">
                <input type="text" x-model="blockTimeForm.title" required placeholder="Reason / Title *" class="w-full text-xs rounded border-slate-300 py-1">
                <div class="grid grid-cols-2 gap-2">
                    <input type="datetime-local" x-model="blockTimeForm.starts_at" required class="w-full text-xs rounded border-slate-300 py-1">
                    <input type="datetime-local" x-model="blockTimeForm.ends_at" required class="w-full text-xs rounded border-slate-300 py-1">
                </div>
                <div class="flex space-x-2 pt-1">
                    <button type="submit" class="flex-1 py-1.5 bg-slate-900 text-white font-bold rounded">Save</button>
                    <button type="button" @click="isBlockTimeModalOpen = false" class="px-3 py-1.5 bg-slate-100 rounded">Cancel</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Notifications drawer -->
    <div x-show="isNotificationDrawerOpen" x-transition class="fixed inset-0 z-50 bg-slate-900/40 flex justify-end">
        <div @click.away="isNotificationDrawerOpen = false" class="w-full max-w-sm bg-white h-full shadow-2xl p-4 flex flex-col justify-between overflow-y-auto">
            <div>
                <div class="flex justify-between items-center pb-2 border-b border-slate-100 mb-3">
                    <span class="text-sm font-bold text-slate-900">Simulated Alerts</span>
                    <button type="button" @click="isNotificationDrawerOpen = false" class="text-slate-400">✕</button>
                </div>
                <div class="space-y-2">
                    <template x-for="(notif, idx) in notifications" :key="idx">
                        <div class="p-2.5 rounded-lg border text-xs" :class="notif.channel === 'sms' ? 'bg-sky-50/50 border-sky-200' : 'bg-slate-50 border-slate-200'">
                            <div class="font-bold text-[10px] uppercase text-slate-500 mb-0.5" x-text="notif.channel === 'sms' ? '📱 SMS' : '✉️ Email'"></div>
                            <div class="font-semibold text-slate-900" x-text="notif.subject"></div>
                            <div class="text-[11px] text-slate-600 font-mono mt-1 whitespace-pre-line" x-text="notif.body"></div>
                        </div>
                    </template>
                </div>
            </div>
            <button type="button" @click="notifications = []" class="w-full py-1.5 bg-slate-100 text-slate-700 text-xs font-semibold rounded-lg">Clear Alerts</button>
        </div>
    </div>


    <!-- Client state logic -->
    <script>
        function schedulerApp() {
            return {
                activeView: 'client',
                
                services: @json($services),
                providers: @json($providers),
                holidays: @json($holidays),
                selectedTimezone: '{{ $defaultTimezone }}',

                selectedService: null,
                selectedProvider: null,
                selectedDate: null,
                formattedSelectedDate: '',
                selectedSlot: null,
                isRecurring: false,
                recurringWeeks: 2,

                calendarYear: {{ (int) \Carbon\Carbon::now()->format('Y') }},
                calendarMonth: {{ (int) \Carbon\Carbon::now()->format('n') }},
                calendarMonthName: '',
                calendarDays: {},
                calendarFirstDayOfWeek: 0,
                isCalendarLoading: false,

                slots: [],
                slotFilter: 'all',
                isSlotsLoading: false,
                availableSlotsCount: 0,
                showPastSlots: false,

                form: {
                    customer_name: '',
                    customer_email: '',
                    customer_phone: '',
                    customer_notes: ''
                },
                isSubmittingBooking: false,
                bookingErrorMessage: null,

                isConfirmationModalOpen: false,
                isNotificationDrawerOpen: false,
                confirmedAppointment: null,
                confirmedAppointmentGoogleUrl: '',

                isManageModalOpen: false,
                manageSearchCode: '',
                managedAppointment: null,
                managedAppointmentCanBeModified: false,

                isWaitlistModalOpen: false,
                waitlistForm: {
                    preferred_time: 'anytime',
                    customer_name: '',
                    customer_email: '',
                    customer_phone: ''
                },

                adminDate: '{{ $currentDate }}',
                adminProviderId: '',
                adminSearchQuery: '',
                adminAppointments: [],
                adminStats: {
                    total_bookings: 0,
                    completed: 0,
                    capacity_percentage: 0,
                    revenue_today: 0,
                    waitlist_count: 0
                },
                isBlockTimeModalOpen: false,
                blockTimeForm: {
                    provider_id: '',
                    title: '',
                    starts_at: '',
                    ends_at: ''
                },

                notifications: [
                    {
                        channel: 'email',
                        subject: 'Scheduler Active',
                        body: 'System ready. Select a service and slot to book.',
                        time: 'Just now'
                    }
                ],

                getInitials(name) {
                    if (!name) return 'SP';
                    return name.replace(/^Dr\.\s*/i, '')
                               .split(' ')
                               .filter(Boolean)
                               .map(n => n[0])
                               .slice(0, 2)
                               .join('')
                               .toUpperCase();
                },

                async initApp() {
                    if (this.services.length > 0) {
                        this.selectedService = this.services[1] || this.services[0];
                    }
                    this.updateMonthName();
                    await this.loadCalendarSummary();
                },

                switchView(view) {
                    this.activeView = view;
                    if (view === 'admin') {
                        this.loadAdminData();
                    }
                },

                onTimezoneChange() {
                    this.loadCalendarSummary();
                },

                selectService(service) {
                    this.selectedService = service;
                    this.selectedSlot = null;
                    this.loadCalendarSummary();
                },

                selectProvider(provider) {
                    this.selectedProvider = provider;
                    this.selectedSlot = null;
                    this.loadCalendarSummary();
                },

                updateMonthName() {
                    const d = new Date(this.calendarYear, this.calendarMonth - 1, 1);
                    this.calendarMonthName = d.toLocaleString('en-US', { month: 'long' });
                    this.calendarFirstDayOfWeek = d.getDay();
                },

                prevMonth() {
                    if (this.isPrevMonthDisabled()) return;
                    if (this.calendarMonth === 1) {
                        this.calendarMonth = 12;
                        this.calendarYear--;
                    } else {
                        this.calendarMonth--;
                    }
                    this.updateMonthName();
                    this.loadCalendarSummary();
                },

                nextMonth() {
                    if (this.calendarMonth === 12) {
                        this.calendarMonth = 1;
                        this.calendarYear++;
                    } else {
                        this.calendarMonth++;
                    }
                    this.updateMonthName();
                    this.loadCalendarSummary();
                },

                isPrevMonthDisabled() {
                    const nowYear = {{ (int) \Carbon\Carbon::now()->format('Y') }};
                    const nowMonth = {{ (int) \Carbon\Carbon::now()->format('n') }};
                    return this.calendarYear === nowYear && this.calendarMonth <= nowMonth;
                },

                // Full month grid: All days with past dates dimmed, filling the calendar area
                get fullMonthDays() {
                    const allDays = Object.values(this.calendarDays);
                    if (!allDays.length) return [];

                    const days = [];
                    // Pad initial blanks
                    for (let i = 0; i < this.calendarFirstDayOfWeek; i++) {
                        days.push({ isBlank: true, is_past: true });
                    }

                    for (const day of allDays) {
                        days.push({ isBlank: false, ...day });
                    }

                    return days;
                },

                async loadCalendarSummary() {
                    if (!this.selectedService) return;
                    this.isCalendarLoading = true;
                    try {
                        const params = new URLSearchParams({
                            service_id: this.selectedService.id,
                            year: this.calendarYear,
                            month: this.calendarMonth,
                            timezone: this.selectedTimezone
                        });
                        if (this.selectedProvider) {
                            params.append('provider_id', this.selectedProvider.id);
                        }

                        const res = await fetch(`/api/calendar-summary?${params.toString()}`);
                        const data = await res.json();
                        if (data.success) {
                            this.calendarDays = data.summary;

                            // Pre-select first available date so slots fill immediately
                            const availableDay = Object.values(this.calendarDays).find(d => !d.is_past && (d.status === 'available' || d.status === 'few_left'));
                            if (availableDay) {
                                this.selectDate(availableDay);
                            }
                        }
                    } catch (e) {
                        console.error('Failed to load calendar summary', e);
                    } finally {
                        this.isCalendarLoading = false;
                    }
                },

                getDayTitle(day) {
                    if (day.status === 'holiday') return `Holiday: ${day.holiday_name}`;
                    if (day.status === 'past') return 'Past date';
                    if (day.status === 'closed') return 'Closed on Sundays';
                    if (day.status === 'full') return 'Fully booked (Waitlist available)';
                    return `${day.available_slots} slots available`;
                },

                getDayClass(day) {
                    if (this.selectedDate === day.date) {
                        return 'bg-teal-600 text-white font-bold ring-2 ring-teal-600 shadow-xs';
                    }
                    if (day.status === 'past') {
                        return 'text-slate-300 bg-slate-50 cursor-not-allowed opacity-60';
                    }
                    if (day.status === 'holiday') {
                        return 'text-rose-400 bg-rose-50/50 cursor-not-allowed';
                    }
                    if (day.status === 'closed') {
                        return 'text-slate-300 bg-slate-50/50 cursor-not-allowed';
                    }
                    if (day.status === 'full') {
                        return 'text-slate-400 bg-slate-100 hover:bg-slate-200 cursor-pointer';
                    }
                    if (day.status === 'few_left') {
                        return 'text-slate-900 bg-amber-50 hover:bg-amber-100 border border-amber-200 cursor-pointer font-semibold';
                    }
                    return 'text-slate-800 bg-white hover:bg-teal-50 hover:text-teal-900 border border-slate-200 cursor-pointer';
                },

                selectDate(dayInfo) {
                    this.selectedDate = dayInfo.date;
                    const d = new Date(dayInfo.date + 'T00:00:00');
                    this.formattedSelectedDate = d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
                    this.selectedSlot = null;
                    this.loadSlots();
                },

                async loadSlots() {
                    if (!this.selectedService || !this.selectedDate) return;
                    this.isSlotsLoading = true;
                    try {
                        const params = new URLSearchParams({
                            service_id: this.selectedService.id,
                            date: this.selectedDate,
                            timezone: this.selectedTimezone
                        });
                        if (this.selectedProvider) {
                            params.append('provider_id', this.selectedProvider.id);
                        }

                        const res = await fetch(`/api/slots?${params.toString()}`);
                        const data = await res.json();
                        if (data.success) {
                            this.slots = data.data.slots || [];
                            this.availableSlotsCount = data.data.available_count || 0;
                        }
                    } catch (e) {
                        console.error('Failed to load slots', e);
                    } finally {
                        this.isSlotsLoading = false;
                    }
                },

                // Past-lead-time count
                get pastLeadTimeSlotsCount() {
                    return this.slots.filter(s => !s.available && s.reason && s.reason.includes('lead time')).length;
                },

                // Display slots with option to hide earlier passed slots
                get displaySlots() {
                    let list = this.slots;
                    if (this.slotFilter !== 'all') {
                        list = list.filter(s => s.period === this.slotFilter);
                    }
                    if (!this.showPastSlots) {
                        list = list.filter(s => !(s.reason && s.reason.includes('lead time')));
                    }
                    return list;
                },

                getSlotClass(slot) {
                    if (this.selectedSlot?.datetime === slot.datetime) {
                        return 'bg-teal-600 text-white font-bold ring-2 ring-teal-500 shadow-xs';
                    }
                    if (!slot.available) {
                        return 'bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed line-through text-[11px]';
                    }
                    return 'bg-white hover:bg-teal-50 hover:border-teal-400 text-slate-800 border border-slate-200 cursor-pointer shadow-2xs';
                },

                selectSlot(slot) {
                    if (!slot.available) return;
                    this.selectedSlot = slot;
                    this.bookingErrorMessage = null;
                },

                async submitBooking() {
                    this.isSubmittingBooking = true;
                    this.bookingErrorMessage = null;
                    try {
                        const payload = {
                            service_id: this.selectedService.id,
                            provider_id: this.selectedSlot.provider_id,
                            starts_at: this.selectedSlot.datetime,
                            customer_name: this.form.customer_name,
                            customer_email: this.form.customer_email,
                            customer_phone: this.form.customer_phone,
                            customer_notes: this.form.customer_notes,
                            timezone: this.selectedTimezone,
                            is_recurring: this.isRecurring,
                            recurring_weeks: this.recurringWeeks
                        };

                        const res = await fetch('/api/appointments', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });

                        const data = await res.json();
                        if (!res.ok || !data.success) {
                            this.bookingErrorMessage = data.message || 'Unable to schedule appointment. Please try another slot.';
                            return;
                        }

                        this.confirmedAppointment = data.appointment;
                        this.confirmedAppointmentGoogleUrl = data.google_calendar_url;
                        this.isConfirmationModalOpen = true;

                        this.pushSimulatedNotifications(data.appointment);
                        this.loadSlots();
                    } catch (e) {
                        this.bookingErrorMessage = 'Network error occurred while reserving slot.';
                    } finally {
                        this.isSubmittingBooking = false;
                    }
                },

                pushSimulatedNotifications(apt) {
                    const formattedDate = new Date(apt.starts_at).toLocaleString();
                    this.notifications.unshift({
                        channel: 'email',
                        subject: `Confirmed: ${apt.reference_code}`,
                        body: `${apt.customer_name}, your ${apt.service.name} appointment is confirmed for ${formattedDate}.`,
                        time: 'Just now'
                    });
                },

                resetBookingFlow() {
                    this.selectedSlot = null;
                    this.form.customer_notes = '';
                },

                openManageModal() {
                    this.isManageModalOpen = true;
                    this.managedAppointment = null;
                },

                async lookupAppointment() {
                    if (!this.manageSearchCode.trim()) return;
                    try {
                        const res = await fetch(`/api/appointments/${encodeURIComponent(this.manageSearchCode.trim())}`);
                        const data = await res.json();
                        if (data.success) {
                            this.managedAppointment = data.appointment;
                            this.managedAppointmentCanBeModified = data.can_be_modified;
                        }
                    } catch (e) {
                        alert('Error locating booking.');
                    }
                },

                async cancelManagedBooking() {
                    if (!confirm('Cancel this appointment?')) return;
                    try {
                        const res = await fetch(`/api/appointments/${this.managedAppointment.reference_code}/cancel`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            }
                        });
                        const data = await res.json();
                        if (data.success) {
                            alert('Appointment cancelled.');
                            this.managedAppointment.status = 'cancelled';
                            this.loadSlots();
                        }
                    } catch (e) {
                        alert('Cancellation failed.');
                    }
                },

                openWaitlistModal() {
                    this.isWaitlistModalOpen = true;
                },

                async submitWaitlist() {
                    try {
                        const res = await fetch('/api/waitlist', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                service_id: this.selectedService.id,
                                provider_id: this.selectedProvider?.id || null,
                                requested_date: this.selectedDate,
                                preferred_time: this.waitlistForm.preferred_time,
                                customer_name: this.waitlistForm.customer_name,
                                customer_email: this.waitlistForm.customer_email,
                                customer_phone: this.waitlistForm.customer_phone
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            alert('Added to waitlist!');
                            this.isWaitlistModalOpen = false;
                        }
                    } catch (e) {
                        alert('Error joining waitlist.');
                    }
                },

                async loadAdminData() {
                    try {
                        const statsRes = await fetch(`/api/admin/stats?date=${this.adminDate}`);
                        const statsData = await statsRes.json();
                        if (statsData.success) {
                            this.adminStats = statsData;
                        }

                        const aptParams = new URLSearchParams({ date: this.adminDate });
                        if (this.adminProviderId) aptParams.append('provider_id', this.adminProviderId);
                        if (this.adminSearchQuery) aptParams.append('search', this.adminSearchQuery);

                        const aptRes = await fetch(`/api/admin/appointments?${aptParams.toString()}`);
                        const aptData = await aptRes.json();
                        if (aptData.success) {
                            this.adminAppointments = aptData.appointments;
                        }
                    } catch (e) {
                        console.error('Failed to load admin data', e);
                    }
                },

                async updateAppointmentStatus(id, status) {
                    try {
                        const res = await fetch(`/api/admin/appointments/${id}/status`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ status })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.loadAdminData();
                        }
                    } catch (e) {
                        alert('Error updating status.');
                    }
                },

                async submitBlockTime() {
                    try {
                        const res = await fetch('/api/admin/blocked-times', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.blockTimeForm)
                        });
                        const data = await res.json();
                        if (data.success) {
                            alert('Time blocked.');
                            this.isBlockTimeModalOpen = false;
                            this.loadAdminData();
                            this.loadSlots();
                        }
                    } catch (e) {
                        alert('Error blocking time.');
                    }
                },

                formatTimeWindow(start, end) {
                    if (!start || !end) return '';
                    const s = new Date(start);
                    const e = new Date(end);
                    return s.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) + ' - ' + e.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                },

                formatDateTime(dateStr) {
                    if (!dateStr) return '';
                    const d = new Date(dateStr);
                    return d.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) + ' at ' + d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                },

                getStatusBadgeClass(status) {
                    switch (status) {
                        case 'confirmed':
                            return 'bg-teal-100 text-teal-800';
                        case 'completed':
                            return 'bg-emerald-100 text-emerald-800';
                        case 'cancelled':
                            return 'bg-rose-100 text-rose-800';
                        case 'no_show':
                            return 'bg-amber-100 text-amber-800';
                        default:
                            return 'bg-slate-100 text-slate-700';
                    }
                }
            };
        }
    </script>
</body>
</html>
