# PropSight — Property Management & Booking Platform

PropSight is a full-stack web application for property owners and managers to list, manage, and accept bookings for rental units. It provides a guest-facing booking experience alongside a comprehensive admin back-office for managing properties, reservations, payments, staff, and reporting.

Developed as a capstone project for the Bachelor of Science in Information Technology program.

---

## Features

### Guest / User Portal
- **Unit browsing & booking** — Search available units, view details, and submit booking requests
- **Booking management** — View upcoming and past bookings, cancel reservations, download receipts
- **Payments** — Pay for bookings, manage saved payment methods
- **Loyalty rewards** — Earn and redeem points across bookings
- **Messaging & support** — Contact property staff and submit support tickets
- **Profile & settings** — Edit personal info, upload ID for verification, manage email preferences
- **Saved units** — Bookmark favourite units for later

### Admin Back-Office
- **Dashboard** — Occupancy overview, recent activity, real-time polling updates
- **Properties & units** — Add/edit/delete properties and rooms; upload images, set amenities and pricing
- **Reservations & calendar** — View all reservations, block dates, manage check-in / check-out
- **Guests & clients** — Guest list, blacklist management
- **Payments & transactions** — Track all payments, export CSV reports
- **Invoices & billing** — Generate and email invoices via PHPMailer (SMTP)
- **Expenses** — Log operational expenses per property
- **Financial & occupancy reports** — Revenue summaries, occupancy rates, booking analytics
- **Staff & roles** — Manage staff accounts and role-based access (admin / accounting / manager)
- **Messages** — Internal messaging between admin and guests
- **Settings** — Platform-wide configuration

---

## Technology Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, JavaScript, Chart.js |
| Backend | PHP 8+ |
| Database | MySQL (via MySQLi with prepared statements) |
| Mail | PHPMailer (SMTP / Gmail TLS) |
| Environment | vlucas/phpdotenv (.env file) |
| Auth | PHP sessions with role-based access control |

---

## Setup

### Requirements
- PHP 8.0+
- MySQL 5.7+ or MariaDB
- A web server (Apache with `mod_rewrite`, or Nginx)
- Composer (for PHPMailer and dotenv)

### Installation

1. **Clone the repository**
   ```bash
   git clone <repo-url>
   cd propsight
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   ```
   Then edit `.env` and fill in your database credentials and mail settings:
   ```
   DB_SERVER=localhost
   DB_USERNAME=your_db_user
   DB_PASSWORD=your_db_password
   DB_NAME=propsight

   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=your@gmail.com
   MAIL_PASSWORD=your_app_password
   MAIL_FROM_NAME=PropSight
   MAIL_ENCRYPTION=tls
   ```
   > **Never commit `.env` to version control.** It is in `.gitignore`.

4. **Import the database schema**
   ```bash
   mysql -u root -p < database/propsight.sql
   ```

5. **Set upload directory permissions**
   ```bash
   chmod -R 755 uploads/
   ```

6. **Configure your web server** to point to the project root. If using Apache, the included `.htaccess` handles routing.

7. **Visit the app** in your browser and log in with your admin credentials.

---

## Security Notes

- All database queries use **prepared statements** (no raw `$_GET`/`$_POST` in queries).
- Admin API endpoints enforce **`role = admin`** session checks before executing any action.
- Database credentials are loaded from **`.env`** via Dotenv — never hardcoded.
- File uploads are validated by **MIME type** (not just extension).
- CSRF token protection is implemented in `includes/session.php` and required on destructive POST endpoints.
- Session cookie hardening (`HttpOnly`, `Secure`, `SameSite=Lax`) should be configured in `session_set_cookie_params()` inside `config.php` before production deployment.

---

## Project Structure

```
propsight/
├── api/
│   ├── admin/          # Admin-only API endpoints (role-gated)
│   └── user/           # Authenticated user API endpoints
├── assets/
│   ├── css/            # Stylesheets (admin + user)
│   └── js/             # JavaScript modules
├── includes/           # Shared PHP includes (session, db, layout)
├── pages/
│   ├── admin/          # Admin back-office pages
│   └── user/           # Guest-facing portal pages
├── uploads/            # User-uploaded files (images, IDs)
├── vendor/             # Composer dependencies
├── config.php          # App configuration (loads .env)
├── .env.example        # Environment template
└── index.php           # Login / entry point
```

---

## Disclaimer

This project was developed for academic purposes as part of a capstone project in the Bachelor of Science in Information Technology program. It is not intended for production use without a full security review.
