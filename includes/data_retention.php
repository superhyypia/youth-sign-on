<?php
/*
 * CI-Youths ClubSpin Register
 * Copyright (c) 2026 Bray Wheelers Cycling Club. All rights reserved.
 *
 * Registration data retention:
 * Five calendar days after the ride date, operational personal data is
 * removed. Rider name, club, spin reference and registration timestamp
 * remain for limited historical/statistical use.
 */

function purge_expired_registration_data(mysqli $conn): int
{
    $sql = "
        UPDATE CIY_spin_registrations r
        INNER JOIN CIY_spins s
            ON s.spin_id = r.spin_id
        SET
            r.mobile = '',
            r.email = '',
            r.emergency_contact_name = '',
            r.emergency_contact_phone = '',
            r.medical_notes = '',
            r.bw_member_confirmed = 0,
            r.consent_given = 0
        WHERE DATE_ADD(s.spin_date, INTERVAL 5 DAY) <= CURDATE()
          AND (
                COALESCE(r.mobile, '') <> ''
             OR COALESCE(r.email, '') <> ''
             OR COALESCE(r.emergency_contact_name, '') <> ''
             OR COALESCE(r.emergency_contact_phone, '') <> ''
             OR COALESCE(r.medical_notes, '') <> ''
             OR COALESCE(r.bw_member_confirmed, 0) <> 0
             OR COALESCE(r.consent_given, 0) <> 0
          )
    ";

    if (!$conn->query($sql)) {
        error_log('CI-Youths registration purge failed: ' . $conn->error);
        return 0;
    }

    return (int) $conn->affected_rows;
}
