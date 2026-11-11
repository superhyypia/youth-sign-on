<?php
/*
 * CI-Youths ClubSpin Register
 * Copyright (c) 2026 Bray Wheelers Cycling Club. All rights reserved.
 */

// includes/db.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$config_path = '/home/braywhee/bwcc/db_config_admin.php';

if (!file_exists($config_path)) {
    die("Config file not found: " . $config_path);
}

include_once $config_path;

if (!function_exists('connect_to_database')) {
    die("connect_to_database() function not found in config file.");
}

$conn = connect_to_database();

if (!$conn) {
    die("Database connection failed.");
}

if ($conn->connect_error) {
    die("Database connection error: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>