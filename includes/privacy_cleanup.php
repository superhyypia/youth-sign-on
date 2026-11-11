<?php
/*
 * CI-Youths ClubSpin Register
 * Copyright (c) 2026 Bray Wheelers Cycling Club. All rights reserved.
 *
 * Privacy housekeeping
 *
 * Sensitive rider information is removed from
 * registration records once the relevant spin
 * reaches the seven-day retention point.
 *
 * Retained:
 * - First name
 * - Last name
 * - Club
 * - Spin participation
 * - Registration date
 *
 * Removed:
 * - Mobile
 * - Email
 * - Emergency contact name
 * - Emergency contact phone
 * - Medical notes
 */

function cleanup_old_ciy_registration_data(mysqli $conn): void
{
    /*
     * IMPORTANT:
     * CI-Youths privacy housekeeping must operate
     * ONLY on CIY_* tables.
     */

    $sql = "
        UPDATE CIY_spin_registrations AS r

        INNER JOIN CIY_spins AS s
            ON s.spin_id = r.spin_id

        SET
            r.mobile = '',
            r.email = '',
            r.emergency_contact_name = '',
            r.emergency_contact_phone = '',
            r.medical_notes = ''

        WHERE
            s.spin_date <= DATE_SUB(CURDATE(), INTERVAL 7 DAY)

            AND (
                COALESCE(r.mobile, '') <> ''
                OR COALESCE(r.email, '') <> ''
                OR COALESCE(r.emergency_contact_name, '') <> ''
                OR COALESCE(r.emergency_contact_phone, '') <> ''
                OR COALESCE(r.medical_notes, '') <> ''
            )
    ";

    if (!$conn->query($sql)) {
        error_log(
            'CI-Youths privacy cleanup failed: ' .
            $conn->error
        );
    }
}
?>