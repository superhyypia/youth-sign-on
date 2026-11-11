# CI-Youths database isolation

CI-Youths shares the existing Bray MySQL connection configuration but **not Bray's application tables**.

CI-Youths tables:
- CIY_spins
- CIY_spin_registrations
- CIY_spin_templates
- CIY_spin_users

Bray tables remain ICE_* and must never be referenced by this CI-Youths codebase.

Shared connection files expected on production:
- /home/braywhee/bwcc/db_config.php
- /home/braywhee/bwcc/db_config_admin.php

The `CIY_TABLE_PREFIX` constant in `includes/ciy_config.php` documents the required namespace for future CI-Youths development.


## Google Maps meeting-point link (27 Sep 2026)
Run `database/2026-09-27-add-google-maps.sql` once before deploying this version.
It adds an optional `maps_url` field to `CIY_spins` and `CIY_spin_templates`. Existing rows remain valid.

## Registration data retention (27 Sep 2026)
After a successful admin/leader login, the app checks registrations against the actual spin date.
Once five calendar days have elapsed, it removes mobile, email, emergency-contact details, medical notes and operational consent/confirmation flags.
It retains first name, last name, club (currently stored in `licence_number`), `spin_id`, `registration_id` and `registered_at`.
Example: a spin on 11 October is eligible for purge on 16 October; if there is no login that day, it is purged on the next successful login.
