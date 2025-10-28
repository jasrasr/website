<?php
echo "PHP is working.";
?>
<?php
file_put_contents("logs/test.txt", "Test log at " . date('c') . "\n", FILE_APPEND);
echo "✅ Write test complete.";
?>
