ClubSpin Register

Overview

ClubSpin Register is a mobile-friendly ride registration and emergency contact platform designed for Bray Wheelers Cycling Club.

The system allows:

* Riders to browse upcoming spins
* Riders to register for spins
* Leaders to create and manage spins
* Admins to manage users and platform settings
* Emergency contact information (ICE) to be stored securely for ride leaders
* Repeat registrations to be simplified with a 14-day remember-me feature
* Ride templates to be reused by spin leaders

The platform is designed primarily for mobile devices and Progressive Web App (PWA) usage.

⸻

Core Features

Rider Features

Upcoming Spins

Riders can:

* Browse upcoming spins
* View:
    * Spin title
    * Date/time
    * Leader
    * Distance
    * Spin colour / pace
    * Weather indicator
    * Strava route
* Share spins via WhatsApp
* Register or cancel registration

Only spins with status:

* final
* cancelled

are visible publicly.

Draft spins remain hidden.

⸻

Rider Registration

Riders can register with:

* Name
* Phone
* Emergency contact name
* Emergency contact phone
* Optional notes

The registration system:

* Prevents duplicate registrations
* Remembers rider details for 14 days
* Remembers previously registered spins
* Hides the Register button for already-registered spins
* Shows “Already Registered”

Cookies are used for the remember-me functionality.

⸻

Spin Colours

The platform uses standard Bray Wheelers pace colours.

Value	Display
28+	Blue — 28 km/h+
26-28	Green — 26-28 km/h
24-26	Yellow — 24-26 km/h
22-24	Orange — 22-24 km/h
20-22	Red — 20-22 km/h

These values are enforced via dropdown selection.

⸻

Weather Indicators

Each spin displays an expected weather condition indicator:

* Green
* Orange
* Red

Weather data is generated through:

/includes/weather.php

⸻

Admin Features

Admin Dashboard

File:

/admin/index.php

Features:

* View all spins
* Rider counts
* Pace colour badges
* Edit spins
* Delete spins
* View rider lists
* Access ride templates
* Manage users (admins only)
* Danger Zone (admins only)

⸻

Create Spin

File:

/admin/create_spin.php

Features:

* Create draft/final/cancelled spins
* Auto-fill leader details from logged-in user
* Pace colour dropdown
* Distance
* Strava route
* Rider limits
* Description
* Default meeting point
* Ride template support

Spin leaders and admins can create spins.

⸻

Edit Spin

File:

/admin/edit_spin.php

Features:

* Edit all spin details
* Change spin status
* Modify pace colour
* Update route links
* Update descriptions
* Cancel spins

⸻

Delete Spin

File:

/admin/delete_spin.php

Features:

* Deletes a spin
* Deletes associated registrations
* Confirmation prompt

⸻

Ride Templates

File:

/admin/templates.php

Purpose:

Allows leaders to:

* Save commonly used spins
* Reuse routes
* Recreate recurring spins quickly

Templates can include:

* Title
* Distance
* Pace
* Route link
* Description
* Meeting point

⸻

Rider Lists

File:

/admin/riders.php

Features:

* View registered riders
* View ICE contacts
* Export/copy emergency information
* Leader emergency management

⸻

User Management

File:

/admin/admin_manage_users.php

Admin-only functionality.

Features:

* Create leaders/admins
* Edit users
* Reset passwords
* Activate/deactivate accounts
* Delete users
* Store ICE information for leaders

⸻

Authentication

Files:

/admin/login.php
/admin/logout.php
/admin/auth_check.php

Features:

* Session-based authentication
* 14-day login persistence
* Role-based access control
* Admin vs Leader permissions

⸻

User Roles

Admin

Admins can:

* Create/edit/delete spins
* Manage users
* Access danger zone
* View all riders
* Create templates

⸻

Leader

Leaders can:

* Create spins
* Edit their spins
* View riders
* Use templates

Leaders cannot:

* Manage users
* Access danger zone

⸻

Database Schema

Table: ICE_spins

Stores spin information.

CREATE TABLE ICE_spins (
    spin_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    spin_date DATE NOT NULL,
    start_time TIME NOT NULL,
    meeting_point VARCHAR(255),
    leader_name VARCHAR(255),
    leader_phone VARCHAR(50),
    distance_km DECIMAL(5,1),
    pace_level VARCHAR(20),
    description TEXT,
    strava_url TEXT,
    max_riders INT DEFAULT 12,
    status VARCHAR(20) DEFAULT 'draft',
    created_by INT,
    leader_user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

⸻

Table: ICE_spin_registrations

Stores rider registrations.

CREATE TABLE ICE_spin_registrations (
    registration_id INT AUTO_INCREMENT PRIMARY KEY,
    spin_id INT NOT NULL,
    rider_name VARCHAR(255) NOT NULL,
    rider_phone VARCHAR(50),
    emergency_contact_name VARCHAR(255),
    emergency_contact_phone VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

⸻

Table: ICE_spin_users

Stores leaders/admins.

CREATE TABLE ICE_spin_users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    ice_name VARCHAR(255),
    ice_phone VARCHAR(50),
    role VARCHAR(20) DEFAULT 'leader',
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

⸻

Table: ICE_spin_templates

Stores reusable ride templates.

CREATE TABLE ICE_spin_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    meeting_point VARCHAR(255),
    distance_km DECIMAL(5,1),
    pace_level VARCHAR(20),
    description TEXT,
    strava_url TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

⸻

Public File Structure

/clubspins/
│
├── index.php
├── register.php
├── register_submit.php
├── cancel_registration.php
├── manifest.json
├── service-worker.js
│
├── /includes/
│   └── weather.php
│
├── /assets/
│   ├── /css/
│   ├── /images/
│   └── /icons/
│
└── /admin/
    ├── index.php
    ├── login.php
    ├── logout.php
    ├── auth_check.php
    ├── create_spin.php
    ├── edit_spin.php
    ├── delete_spin.php
    ├── riders.php
    ├── templates.php
    ├── admin_manage_users.php
    └── danger_zone.php

⸻

Progressive Web App (PWA)

The system supports:

* Install to Home Screen
* Offline caching
* Mobile-first layout
* iPhone support
* App icons
* Theme colours

Core files:

manifest.json
service-worker.js

⸻

Session & Remember Me Strategy

Admins / Leaders

* Session persistence: 14 days
* Reduced login frequency
* Session regeneration on login

Riders

Rider registration details are stored for:

* 14 days

Stored locally:

* Name
* Phone
* ICE details
* Previously registered spins

⸻

Security Notes

The system currently includes:

* Prepared SQL statements
* Password hashing via password_hash()
* Session-based authentication
* Role-based access control

Recommended future improvements:

* CSRF protection
* Rate limiting
* Audit logging
* Email verification
* HTTPS-only cookies
* Admin activity history

⸻

Future Roadmap

Phase 1 Complete

* Rider registration
* Admin portal
* User management
* Templates
* Remember me
* Mobile UI
* PWA support

⸻

Suggested Future Enhancements

Rider Accounts

Optional rider login system.

WhatsApp Integration

Deep linking into colour-specific WhatsApp groups.

Push Notifications

* Spin cancellations
* Route changes
* Weather warnings

GPX Uploads

Native GPX support.

Check-In System

Ride departure attendance tracking.

Emergency Mode

Quick-access ICE export screen for leaders.

Leader Ownership

Restrict leaders to editing only their own spins.

Analytics

* Popular rides
* Registration trends
* Leader participation

⸻

Branding & Design Principles

The platform uses:

* Bray Wheelers purple theme
* Rounded card UI
* Mobile-first layouts
* Large touch targets
* Clean typography
* Minimal admin friction

⸻

Technical Stack

Component	Technology
Backend	PHP
Database	MySQL
Frontend	HTML/CSS/Vanilla JS
Hosting	Shared Hosting
Authentication	PHP Sessions
Mobile Support	PWA

⸻

Notes

This project was designed specifically for:

* Bray Wheelers club spins
* Mobile usability
* Emergency safety management
* Minimal admin overhead
* Fast rider registration

The platform is intentionally lightweight and hosting-friendly for traditional PHP hosting environments.

## Ownership

CI-Youths ClubSpin Register software is © 2026 Bray Wheelers Cycling Club. All rights reserved. Participating clubs retain ownership of their respective names, logos and branding.


## CI-Youths registration club field
The existing `CIY_spin_registrations.licence_number` column is intentionally reused by the CI-Youths fork to store the rider's **Club**. The public registration field is mandatory and free-form. Do not relabel it as a Cycling Ireland licence number in this fork.


## Session cookie portability
Admin sessions use cookie path `/`, so deployments do not depend on a hard-coded `/clubspins/` or `/ci-youths/` directory.
