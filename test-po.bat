@echo off
cd /d "c:\Users\janni\Local Sites\yogaibiza\app\public\wp-content\plugins\ltl-bookings"
php -r "$po=file_get_contents('languages/de_DE.po'); preg_match_all('/msgid \"([^\"]*)\"\s*msgstr \"([^\"]*)\"/', $po, $m); $t=[]; foreach($m[1] as $i=>$k){if($k!=='' && $m[2][$i]!=='') $t[$k]=$m[2][$i];} echo 'Found: '.count($t).' translations\n'; file_put_contents('dict-count.txt', count($t));"
pause
