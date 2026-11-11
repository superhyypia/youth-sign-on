<?php
/*
 * CI-Youths ClubSpin Register
 * Copyright (c) 2026 Bray Wheelers Cycling Club. All rights reserved.
 */

// CI-Youths deliberately shares Bray's database CONNECTION only.
// CI-Youths application data is isolated in CIY_* tables.
$prod_path = '/home/braywhee/bwcc/db_config.php';

if (!file_exists($prod_path)) {
    die('Error: Shared database configuration file not found: ' . $prod_path);
}

include_once $prod_path;
?>
