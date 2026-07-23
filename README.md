# PropSight
### A Financial Monitoring and Reporting System for Data-Driven Rental Property Management

PropSight is a PHP-based web application for managing apartment rentals and other small- to medium-scale rental properties. It provides a tenant-facing portal for browsing units and managing bookings, and a full admin back-office for properties, reservations, payments, maintenance, and financial reporting.

Rental property management in the Philippines is often handled manually or through fragmented tools, especially among small-scale landlords, leaving owners with little real-time visibility into payments, outstanding balances, and expenses. PropSight was built to centralize that financial data in one place, automate reporting, and support more data-driven decision-making for property owners.

Developed as a capstone project for the Bachelor of Science in Information Technology program (Business Analytics Management track), PHINMA University of Iloilo — College of Information Technology Education, Second Semester 2025–26.

PropSight is built primarily for property owners managing apartments, boarding houses, dormitories, and other small commercial rental setups, giving them a centralized view of their income, expenses, and overall financial health. Tenants also benefit indirectly, through clearer billing, payment tracking, and a more transparent booking process.

---

## Features

### Tenant / User Portal
- **Unit browsing** — Browse available units with details, photos, amenities, and reviews
- **Booking** — Submit booking requests with date selection and payment method choice
- **Payments** — Pay via GCash, Maya, Bank Transfer, Cash, or Card (PayMongo Checkout)
- **Booking management** — View active and past bookings, download receipts, cancel reservations, request refunds, extend stays
- **Unit reviews** — Submit and view reviews for booked units
- **Loyalty rewards** — Earn points per booking; redeem points for voucher discounts
- **Saved units** — Bookmark units for later
- **Messaging** — Message property management directly
- **Support tickets** — Submit and track support requests
- **Notifications** — In-app notifications for booking and payment updates
- **Profile & settings** — Edit profile, upload a government ID for verification, change password, manage email preferences, delete account
- **Nearby places** — View nearby points of interest for a unit

### Admin Back-Office
- **Dashboard** — Live KPI cards (occupancy, revenue, bookings), revenue vs. expenses chart, task summary with scrollable maintenance list, real-time activity feed, right-panel calendar with schedule and recent transactions
- **Properties & units** — Add, edit, and delete properties and rooms; manage amenities and per-unit pricing; upload property images
- **Reservations** — View all reservations, filter by status, approve or reject pending bookings
- **Calendar** — Visual monthly calendar with blocked date management
- **Check-in / Check-out** — Process tenant arrivals and departures; sync unit statuses automatically
- **Task summary** — Maintenance request board with priority sorting (urgent → high → medium → low) and status tracking (open, in progress, pending, completed, closed)
- **Invoices & billing** — Generate invoices, send via email (PHPMailer SMTP), track billing status; supports per-method payment buttons in emails
- **Payments** — Full payment ledger with CSV export
- **Transactions** — Income and expense transaction log
- **Refunds** — Review and process tenant refund requests via PayMongo
- **Expenses** — Log and categorise operational expenses per property
- **Financial reports** — Revenue summaries and year-over-year comparisons
- **Booking reports** — Booking trends, cancellation rates
- **Occupancy reports** — Unit occupancy rates by property and period
- **Analytics** — Aggregated property analytics
- **Guests & clients** — Tenant directory, guest blacklist management
- **ID verification** — Review and approve/reject tenant government ID uploads
- **Messages** — Two-way messaging with tenants
- **Support** — Manage and respond to tenant support tickets (with real-time badge count in sidebar)
- **Loyalty rewards** — Configure point earn rates, view tenant point balances, manage vouchers
- **Staff & roles** — Create and manage staff accounts with role-based access (admin, accounting, manager)
- **Notifications** — DB-backed admin notification system with dismissable dropdown and real-time polling
- **Settings** — Platform-wide configuration
- **Backup** — Database backup utility

---

## Technology Stack

| Layer | Technology |
|---|---|
| Language | PHP 8+ |
| Database | MySQL / MariaDB via MySQLi (prepared statements) |
| Frontend | HTML5, CSS3, Vanilla JavaScript, Chart.js |
| Payments | PayMongo (Checkout Sessions for card; payment links for GCash / Maya) |
| Email | PHPMailer (SMTP / Gmail TLS) |
| Environment | vlucas/phpdotenv |
| Auth | PHP sessions with role-based access control |
| Local server | XAMPP (Apache + MySQL) |

---

## Payment Methods

| Method | Provider | Notes |
|---|---|---|
| Card | PayMongo Checkout Session | Redirects to hosted checkout page |
| GCash | PayMongo Payment Link | Sends link via in-app flow |
| Maya | PayMongo Payment Link | Sends link via in-app flow |
| Bank Transfer | Manual | Recorded manually by admin |
| Cash | Manual | Recorded manually by admin |

---

## Security Notes

- All database queries use **prepared statements** — no raw user input in SQL.
- Admin endpoints enforce **`role = admin`** session checks.
- Credentials and API keys are stored in **`.env`** — never hardcoded.
- File uploads are validated by **MIME type**, not file extension.
- PayMongo webhooks validate **request signatures** before processing.
- Rate limiting is applied on sensitive endpoints via `includes/rate_limiter.php`.

---

## Disclaimer

This project was developed for academic purposes as a capstone project for the Bachelor of Science in Information Technology program. It is not intended for production use without a thorough security review and hardening.