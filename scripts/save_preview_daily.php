<?php
// Generates an HTML preview file for the daily summary email (first recipient)
$dailyPath = __DIR__ . '/../api/send_daily_bid_summaries.php';
$dst = __DIR__ . '/preview_daily.html';
$src = file_get_contents($dailyPath);
if ($src === false) { echo "Cannot read $dailyPath\n"; exit(1); }
// Remove mail helper require to avoid re-declares
$src = preg_replace("/require_once.*auth\\/mailjet_helper.php.*;/", "// mail helper skipped for preview", $src, 1);
// Replace sendMail with writer that saves first HTML
$replacement = <<<'PHP'
function sendMail($to, $subject, $text, $html) {
    static $written = false;
    if (!$written) {
        file_put_contents(__DIR__ . '/preview_daily.html', "<!-- Subject: $subject -->\n" . $html);
        $written = true;
    }
    return ['success'=>true];
}
PHP;
// Prepend our override and eval
// Remove any opening PHP tag from source before eval
$src = preg_replace('/^\s*<\?php/', '', $src, 1);
$evalSrc = $replacement . "\n" . $src;
eval($evalSrc);
echo "Wrote preview to scripts/preview_daily.html\n";
