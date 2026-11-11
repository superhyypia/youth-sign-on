-- CI-Youths: optional Google Maps link for meeting points
-- Run once against the same database that contains the CIY_* tables.

ALTER TABLE CIY_spins
    ADD COLUMN maps_url VARCHAR(1000) NULL AFTER meeting_point;

ALTER TABLE CIY_spin_templates
    ADD COLUMN maps_url VARCHAR(1000) NULL AFTER meeting_point;
