<?php
// Delete old and create new I18n.php
$old = __DIR__ . '/Util/I18n.php';
$new = __DIR__ . '/Util/I18n-new.php';

if (file_exists($old)) {
    unlink($old);
    echo "Deleted old I18n.php\n";
}

if (file_exists($new)) {
    rename($new, $old);
    echo "Renamed I18n-new.php to I18n.php\n";
} else {
    echo "I18n-new.php not found\n";
}
