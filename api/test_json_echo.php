<?php
// Test endpoint to debug JSON output issues
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');
$input = file_get_contents('php://input');
echo json_encode([
    'raw_input' => $input,
    'json_decoded' => json_decode($input, true),
    'php_errors' => error_get_last()
]);
// No closing PHP tag
