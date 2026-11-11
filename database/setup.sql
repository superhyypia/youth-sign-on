SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ICE_spin_users (
  user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  ice_name VARCHAR(255) DEFAULT NULL,
  ice_phone VARCHAR(50) DEFAULT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'leader',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_spin_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ICE_spins (
  spin_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  spin_date DATE NOT NULL,
  start_time TIME NOT NULL,
  meeting_point VARCHAR(255) DEFAULT NULL,
  leader_name VARCHAR(255) DEFAULT NULL,
  leader_phone VARCHAR(50) DEFAULT NULL,
  distance_km DECIMAL(6,1) DEFAULT NULL,
  pace_level VARCHAR(20) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  strava_url TEXT DEFAULT NULL,
  max_riders INT NOT NULL DEFAULT 12,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  created_by INT UNSIGNED DEFAULT NULL,
  leader_user_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (spin_id),
  KEY idx_spins_date (spin_date),
  KEY idx_spins_status (status),
  KEY idx_spins_leader (leader_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ICE_spin_registrations (
  registration_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  spin_id INT UNSIGNED NOT NULL,
  first_name VARCHAR(120) NOT NULL,
  last_name VARCHAR(120) NOT NULL,
  mobile VARCHAR(50) DEFAULT NULL,
  email VARCHAR(255) NOT NULL,
  licence_number VARCHAR(100) DEFAULT NULL,
  emergency_contact_name VARCHAR(255) DEFAULT NULL,
  emergency_contact_phone VARCHAR(50) DEFAULT NULL,
  medical_notes TEXT DEFAULT NULL,
  bw_member_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  consent_given TINYINT(1) NOT NULL DEFAULT 0,
  registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (registration_id),
  UNIQUE KEY uq_registration_spin_email (spin_id, email),
  KEY idx_registration_spin (spin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ICE_spin_templates (
  template_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) DEFAULT NULL,
  meeting_point VARCHAR(255) DEFAULT NULL,
  distance_km DECIMAL(6,1) DEFAULT NULL,
  pace_level VARCHAR(20) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  strava_url TEXT DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
