<?php
// Script to be run by cron (recommended every minute) to send scheduled bid reminder emails at 09:30 server time
// Usage: php scripts/send_email_notifications.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/mailjet_helper.php';

// Set timezone if desired; else rely on server timezone
// date_default_timezone_set('America/New_York');

$now = new DateTime();
$hour = (int)$now->format('H');
$minute = (int)$now->format('i');
$today = $now->format('Y-m-d');

// Only proceed when it's 09:30
if (!($hour === 9 && $minute === 30)) {
    // exit silently
    exit(0);
}

$logFile = __DIR__ . '/../debug/email_notifications_send.log';
function logit($m) {
    global $logFile; @file_put_contents($logFile, '['.date('c').'] '.$m."\n", FILE_APPEND);
}

logit('Cron run triggered at 09:30');

// Fetch all bids where bid_date is in the future (or today) and compute days difference
// We will join with bids_email and check JSON contains preferred days

$sql = "SELECT b.bid_id, b.dhss_project_number, b.bid_date, be.email, be.preferred_days
        FROM bids b
        JOIN bids_email be ON be.opted_in = 1
        WHERE b.bid_date IS NOT NULL";

$res = $conn->query($sql);
if (!$res) { logit('DB query failed: '.$conn->error); exit(0); }

$toSend = [];
while ($r = $res->fetch_assoc()) {
    $bidDate = $r['bid_date'];
    if (!$bidDate) continue;
    $bidDt = new DateTime($bidDate);
    $interval = $bidDt->diff(new DateTime());
    // days difference (bidDate - now). Use floor of days; if negative skip
    $days = (int)$interval->format('%r%a');
    if ($days < 0) continue;
    $email = $r['email'];
    $preferred = $r['preferred_days'] ? json_decode($r['preferred_days'], true) : [];
    if (!is_array($preferred) || !count($preferred)) continue;
    // check if days is in preferred
    if (in_array($days, array_map('intval', $preferred))) {
        $toSend[] = ['email' => $email, 'bid_id' => $r['bid_id'], 'days_before' => $days, 'bid_date' => $bidDate, 'dhss_project_number' => $r['dhss_project_number']];
    }
}

foreach ($toSend as $item) {
    // ensure not already sent
    $chk = $conn->prepare('SELECT id FROM bids_email_sent WHERE email = ? AND bid_id = ? AND days_before = ? LIMIT 1');
    $chk->bind_param('sii', $item['email'], $item['bid_id'], $item['days_before']);
    $chk->execute(); $cres = $chk->get_result(); $found = $cres ? $cres->fetch_assoc() : null; $chk->close();
    if ($found && isset($found['id'])) { logit('Already sent to '.$item['email'].' for bid '.$item['bid_id'].' days_before '.$item['days_before']); continue; }

    // Build email
    $subject = 'Bid Reminder: project ' . ($item['dhss_project_number'] ?: $item['bid_id']);
    $text = "This is a reminder that a bid (project " . ($item['dhss_project_number'] ?: $item['bid_id']) . ") is scheduled for " . $item['bid_date'] . ".\nYou requested a reminder " . $item['days_before'] . " day(s) before.";
    $html = "<p>This is a reminder that a bid (project <strong>" . htmlspecialchars($item['dhss_project_number']) . "</strong>) is scheduled for <strong>" . htmlspecialchars($item['bid_date']) . "</strong>.</p><p>You requested a reminder <strong>" . intval($item['days_before']) . "</strong> day(s) before.</p>";

    $sent = sendMail($item['email'], $subject, $text, $html);
    if ($sent && $sent['success']) {
        $ins = $conn->prepare('INSERT INTO bids_email_sent (email,bid_id,days_before) VALUES (?,?,?)');
        $ins->bind_param('sii', $item['email'], $item['bid_id'], $item['days_before']);
        $ins->execute(); $ins->close();
        logit('Sent to '.$item['email'].' for bid '.$item['bid_id'].' days_before '.$item['days_before']);
    } else {
        logit('Send failed for '.$item['email'].' bid '.$item['bid_id'].' err: '.json_encode($sent));
    }
}

logit('Cron run completed');

?>
