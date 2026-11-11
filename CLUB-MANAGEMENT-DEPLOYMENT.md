# CI-Youths — Manage Clubs deployment

1. Back up the live database and application files.
2. Run `database/2026-11-11-manage-clubs.sql` **once** against the CI-Youths database. Confirm the four clubs were seeded.
3. Deploy the updated `register.php`, `register_submit.php`, `admin/index.php`, and new `admin/manage_clubs.php` together. Keep all other files unchanged.
4. Log in as an admin, open **Manage Clubs**, add a test club, and confirm it appears in a participant registration dropdown. Deactivate it and confirm it disappears from new registrations.
5. Check that the Other option works, a leader cannot access Manage Clubs, and existing registrations/statistics remain visible.

## Compatibility
The selected club name is still stored in the existing `CIY_spin_registrations.licence_number` field. No existing registrations are migrated. Renaming a club changes future dropdown choices but does **not** rewrite historical names; older names may therefore remain in historical statistics as separate labels. This is intentional to preserve historical records. A rider with a saved, now-inactive club must select an active club or Other before submitting.

## Rollback
Restore the four modified PHP files from the backup. The new table may be left in place unused. Do not drop it if administrators have added clubs that you may need later.
