-- Run once before deploying the updated PHP files.
CREATE TABLE IF NOT EXISTS CIY_clubs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    club_name VARCHAR(150) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ciy_club_name (club_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO CIY_clubs (club_name) VALUES
('Bray Wheelers Cycling Club'),
('Lucan Cycling Road Club'),
('Orwell Wheelers'),
('Dublin Bay Cycling');
-- 'Other' is a permanent registration choice, not a database club.
