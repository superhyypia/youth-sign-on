<?php
/*
 * CI-Youths ClubSpin Register
 * Copyright (c) 2026 Bray Wheelers Cycling Club. All rights reserved.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once '/home/braywhee/bwcc/db_config.php';

$conn = connect_to_database();

$result = $conn->query("SELECT USER() AS mysql_user, DATABASE() AS db_name");
$row = $result->fetch_assoc();

echo "<pre>";
print_r($row);
echo "</pre>";
?>