<?php
// File: test_list_root.php
session_start();
$_SESSION['user_id'] = 3; // Mock user ID for Laura García Pérez

// Capture output
ob_start();
require __DIR__ . '/views/patient/api/surgery_list.php';
$output = ob_get_clean();

echo "<pre>";
echo "JSON Output:\n";
echo $output;
echo "\n\nDecoded:\n";
print_r(json_decode($output, true));
echo "</pre>";
?>