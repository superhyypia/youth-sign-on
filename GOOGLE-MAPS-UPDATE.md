# Google Maps meeting-point update

## Deployment order
1. Back up the CIY database tables.
2. Run `database/2026-09-27-add-google-maps.sql` once.
3. Deploy the PHP files from this package.

## What changed
- Create Spin: optional Google Maps Link field.
- Edit Spin: optional Google Maps Link field.
- Ride Templates: Maps links are saved and restored with templates.
- Public upcoming spins and registration pages: meeting point can open directly in Google Maps.
- Admin spin/rider/QR/reassignment views: map shortcut shown when present.
- WhatsApp sharing: map link included when present.
- Validation accepts HTTPS Google Maps share URLs (`maps.app.goo.gl`, `goo.gl/maps`, and Google `/maps` URLs).

Existing spins and templates do not need a Maps link and continue to work with `maps_url` NULL.
