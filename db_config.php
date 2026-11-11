<?php
/**
 * CI-Youths database configuration.
 * Replace the four CHANGE_ME values with the credentials for the
 * SEPARATE CI-Youths MySQL database created in your hosting control panel.
 */
function connect_to_database(): mysqli
{
    $host = 'localhost';
    $user = 'CHANGE_ME_DB_USER';
    $pass = 'CHANGE_ME_DB_PASSWORD';
    $name = 'CHANGE_ME_DB_NAME';

    if (str_starts_with($user, 'CHANGE_ME') || str_starts_with($pass, 'CHANGE_ME') || str_starts_with($name, 'CHANGE_ME')) {
        die('CI-Youths database is not configured yet. Edit db_config.php and enter the CI-Youths database name, username and password.');
    }

    $conn = new mysqli($host, $user, $pass, $name);
    if ($conn->connect_error) {
        die('CI-Youths database connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
