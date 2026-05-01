<?php
/**
 * PropSight API — Endpoint Directory
 * All files in this /api folder are JSON endpoints consumed by JS fetch() calls.
 * Authentication: all endpoints require a valid PHP session (role checked per endpoint).
 *
 * BASE PATH: /api/<file>.php
 *
 * ┌──────────────────────────────────────────────────────────┐
 * │  ADMIN ENDPOINTS                                         │
 * ├──────────────────────────────────────────────────────────┤
 * │  GET  reservations.php        List/filter bookings       │
 * │  POST reservations.php        Create/update/cancel       │
 * │  GET  payments.php            List payments              │
 * │  POST payments.php            Add/edit/delete payment    │
 * │  GET  transactions.php        List transactions          │
 * │  POST transactions.php        Add/delete transaction     │
 * │  GET  expenses.php            List expenses              │
 * │  POST expenses.php            Add/edit/delete expense    │
 * │  GET  analytics.php           Dashboard KPI data         │
 * │  GET  financial_report.php    Financial summary data     │
 * │  GET  occupancy_report.php    Occupancy data             │
 * │  GET  booking_report.php      Booking stats              │
 * │  POST checkin.php             Process check-in/out       │
 * │  POST booking_status.php      Update booking status      │
 * │  GET  guests.php              Guest list                 │
 * │  POST blacklist.php           Blacklist/unblacklist user │
 * │  GET  staff.php               Staff list                 │
 * │  POST staff.php               Add/edit/delete staff      │
 * │  GET  messages.php            List messages              │
 * │  POST messages.php            Send/reply message         │
 * │  GET  settings.php            Get admin settings         │
 * │  POST settings.php            Update admin settings      │
 * ├──────────────────────────────────────────────────────────┤
 * │  USER ENDPOINTS                                          │
 * ├──────────────────────────────────────────────────────────┤
 * │  GET  user/bookings.php       User booking list          │
 * │  POST user/cancel_booking.php Cancel a booking           │
 * │  GET  user/saved.php          Saved units list           │
 * │  POST user/save_toggle.php    Save/unsave a unit         │
 * │  GET  user/loyalty.php        Points balance & history   │
 * │  POST user/redeem.php         Redeem loyalty reward      │
 * │  GET  user/payment_methods.php Stored payment methods    │
 * │  POST user/payment_methods.php Add/remove payment method │
 * │  GET  user/settings.php       User preferences           │
 * │  POST user/settings.php       Update user preferences    │
 * │  POST user/support.php        Submit support ticket      │
 * │  GET  user/notifications.php  Get notifications          │
 * │  POST user/notifications.php  Mark notifications read    │
 * └──────────────────────────────────────────────────────────┘
 */
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'message' => 'PropSight API v1.0', 'docs' => 'See /api/index.php']);
