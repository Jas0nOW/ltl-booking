<?php
// Correct path to wp-load.php
// Current dir: C:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings\
// Target: C:\Users\janni\Local Sites\yogaibiza\app\public\wp-load.php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/public/wp-load.php';

require_once __DIR__ . '/Includes/DB/Schema.php';
require_once __DIR__ . '/Includes/DB/Migrator.php';
require_once __DIR__ . '/Includes/Util/LockManager.php';
require_once __DIR__ . '/Includes/Util/Time.php';

echo "Running migrations...\n";
try {
    LTLB_DB_Migrator::migrate();
    echo "Migrations finished successfully.\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}