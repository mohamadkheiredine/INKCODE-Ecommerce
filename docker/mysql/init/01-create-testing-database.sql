-- Separate database for the automated test suite, so tests never touch demo data.
CREATE DATABASE IF NOT EXISTS `inkcode_testing` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `inkcode_testing`.* TO 'inkcode'@'%';
-- `php artisan test --parallel` gives each worker its own copy (inkcode_testing_test_N).
GRANT ALL PRIVILEGES ON `inkcode_testing_%`.* TO 'inkcode'@'%';
FLUSH PRIVILEGES;
