# CI-Youths — Youth Spin Sign-On

Mobile-friendly PHP/MySQL application for registering youth cyclists on organised group spins. Adapted from Bray Wheelers ClubSpin Register.

## Features

- Public list of upcoming spins, route links, weather indicators and WhatsApp sharing.
- Youth ride categories: **U14 (23–25 km/h)** and **U16 (25–28 km/h)**.
- Registration captures rider name, phone, email, **club (required)**, **Cycling Ireland licence number (optional)**, emergency contact details, optional medical notes, membership/invitation confirmation and consent.
- Duplicate-registration checks, confirmation screen and optional 30-day remember-my-details cookie.
- Admin/leader login, spin creation and editing, templates, rider lists, printable attendance sheets and administration.
- **Manage Clubs**: admins can add, rename, activate or deactivate clubs; the public dropdown shows active clubs plus a permanent **Other** option.
- Green-and-mint CI-Youths theme; mobile-friendly interface and PWA assets.

## Technical stack

PHP, MySQL (mysqli), HTML, CSS and vanilla JavaScript. Intended for traditional PHP hosting. Authentication uses PHP sessions with admin and leader roles.

## Database and deployment

The application uses its own `CIY_*` tables within a shared database. The connection is loaded by `includes/db_loader.php` from a server-side configuration file **outside this repository**. Do not commit credentials, production data, or rider information.

Core tables referenced by the application include `CIY_spins`, `CIY_spin_registrations`, `CIY_spin_templates`, `CIY_spin_users` and `CIY_clubs`.

**Important historical compatibility:** the existing `CIY_spin_registrations.licence_number` column stores the rider's **club name**, despite its misleading legacy column name. Do not overwrite or repurpose it. The actual optional Cycling Ireland licence number is stored separately in `ci_licence_number`.

### Database migrations

On an existing installation, back up the database and apply migrations before deploying the corresponding PHP changes:

1. `database/2026-11-11-manage-clubs.sql` — adds the managed club list.
2. `database/2026-11-11-optional-ci-licence.sql` — adds the optional licence number column.

These migrations do not rewrite historical club registrations. Existing registrations have a NULL CI licence number.

### Registration and rider lists

- `register.php` — participant form.
- `register_submit.php` — server-side validation, club validation and insertion.
- `admin/riders.php` — leader/admin rider details and printable attendance sheet, including optional CI licence.
- `admin/manage_clubs.php` — admin-only club management.

## Privacy and security

This application handles information about minors, including emergency contacts and optional medical notes. Keep the repository free of real rider data, passwords, secrets and database exports. Restrict access to the deployed application and sensitive rider lists.

The existing 30-day remember-me cookie may contain rider contact and emergency/medical details. **Review this mechanism before broader deployment**: avoid retaining sensitive information in client-side cookies and ensure retention/deletion controls are tested. This README does not certify GDPR compliance or a completed security audit.

Some pages still contain legacy `/clubspins/` asset/cookie paths and old naming; verify these against the deployment location. The legacy `database/setup.sql` may not represent the live CIY schema; use reviewed migrations rather than applying it blindly.

## Development workflow

Create a branch, make changes, review a pull request, then merge into `main`. Back up the live database before applying SQL. Deploy database migrations **before** code that requires new columns.

## Ownership

© 2026 Bray Wheelers Cycling Club. All rights reserved. Participating clubs retain ownership of their names, logos and branding.
