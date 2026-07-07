<?php
// Test runner for daily summary email formatting.
// This overrides sendMail() to print the email payload instead of sending.

function sendMail($to, $subject, $text, $html) {
    echo "--- TO: $to ---\n";
    echo "SUBJECT: $subject\n\n";
    echo "---- TEXT ----\n";
    echo $text . "\n\n";
    echo "---- HTML ----\n";
    echo substr($html, 0, 2000) . "\n\n";
    return ['success' => true];
}

// Load the daily summary script source and run it with mail helper removed to avoid real sends
$path = __DIR__ . '/../api/send_daily_bid_summaries.php';
$src = file_get_contents($path);
if ($src === false) {
    echo "Could not read $path\n";
    exit(1);
}

// Remove the require_once line that pulls in the mail helper (prevent redeclare)
$src = preg_replace("/require_once.*auth\\/mailjet_helper.php.*;/", "// mail helper skipped for test", $src, 1);

// Also avoid sending headers or echoing JSON at the end by trimming closing php tag and final echoes
// Execute the modified script in the current scope so our sendMail() is used.
eval('?>' . $src);

echo "\nTest run completed.\n";
