-- CI-Youths: add optional Cycling Ireland licence number.
-- Apply AFTER a database backup and BEFORE deploying updated registration PHP.
-- Existing licence_number column stores CLUB NAMES; do not rename or overwrite it.
ALTER TABLE CIY_spin_registrations
    ADD COLUMN ci_licence_number VARCHAR(50) NULL DEFAULT NULL AFTER licence_number;
