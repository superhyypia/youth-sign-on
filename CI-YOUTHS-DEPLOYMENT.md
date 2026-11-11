# CI-Youths ClubSpin clone

Baseline created from the working Bray Wheelers ClubSpin code on 24 September 2026.

## Separation from Bray

This copy is intended to run independently of the Bray Wheelers installation.
It does **not** point at the Bray database configuration paths.

Production database configuration paths expected by this clone:

- `/home/braywhee/ci-youths/db_config.php`
- `/home/braywhee/ci-youths/db_config_admin.php`

Create those files with credentials for a **separate CI-Youths database** before deployment.
Do not copy the Bray database credentials into this instance unless deliberately migrating test data.

## Branding

The UI now references:

- `assets/images/ci-youth.png`

The supplied baseline logo is the 2x2 four-club graphic. It can be replaced by dropping a new file at the same path/name.

Visible Bray-specific branding has been removed. The logo itself does not contain the text `CI-Youths`.

## Functional baseline

No intentional changes have been made to the core ride/spin, registration, leader, route, QR, rider, admin or statistics workflows. Club-specific functional changes should be made in this fork only.

The Bray-specific default meeting point has been changed from `Kilmac Roundabout` to `To be confirmed` so a new CI-Youths spin does not accidentally inherit the Bray meeting point.
