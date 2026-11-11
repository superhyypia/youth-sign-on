# CI-Youths database setup

This copy must use its own MySQL database. Do not use the Bray Wheelers database.

1. In your hosting control panel, create a new MySQL database and a new database user, then grant that user full privileges on the new database.
2. Import `database/setup.sql` into that new database (phpMyAdmin -> Import is fine).
3. Edit `db_config.php` and replace `CHANGE_ME_DB_USER`, `CHANGE_ME_DB_PASSWORD`, and `CHANGE_ME_DB_NAME`. Leave `localhost` unless your host gives you a different MySQL host.
4. Upload the CI-Youths files. `db_config_admin.php` automatically uses the same isolated database.
5. Open `db_test.php` to verify the connection, then remove or restrict `db_test.php` after testing.

The database starts empty. An admin account must then be created before using the admin area.
