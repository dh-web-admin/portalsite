<?php
// scripts/send_email_notifications.php

require_once __DIR__ . '/../config/config.php'; // $conn (mysqli)

// ----------------------------
// Logger (use yours if exists, else fallback)
// ----------------------------
$loggerPath = __DIR__ . '/../partials/logger.php';
$logFile = __DIR__ . '/../debug/bids_email_cron.log';

if (file_exists($loggerPath)) {
    require_once $loggerPath; // expects logit($msg)
} else {
    function logit($msg) {
        global $logFile;
        @file_put_contents($logFile, '[' . date('c') . '] ' . $msg . PHP_EOL, FILE_APPEND);
    }
}

// Ensure debug directory exists
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

// ----------------------------
// Mailer (Mailjet) - your project uses auth/mailjet_helper.php
// ----------------------------
$mailerCandidates = [
    __DIR__ . '/../auth/mailjet_helper.php',
    __DIR__ . '/../partials/mailer.php',
    __DIR__ . '/../partials/mailer_helper.php'
];

$mailerPath = null;
foreach ($mailerCandidates as $cand) {
    if (file_exists($cand)) { $mailerPath = $cand; break; }
}

if (!$mailerPath) {
    logit('Cron error: mailer helper not found in expected locations: ' . implode(', ', $mailerCandidates));
    // Don't crash silently—throw so Railway logs show it
    throw new RuntimeException('Mailer helper missing; looked for: ' . implode(', ', $mailerCandidates));
}

require_once $mailerPath;

/**
 * Normalize to a single function name: sendMail($to, $subject, $text, $html)
 * Your mailjet helper might expose a different name — we detect and wrap it.
 */
if (!function_exists('sendMail')) {
    if (function_exists('send_mailjet')) {
        function sendMail($to, $subject, $text, $html) { return send_mailjet($to, $subject, $text, $html); }
    } elseif (function_exists('sendMailjet')) {
        function sendMail($to, $subject, $text, $html) { return sendMailjet($to, $subject, $text, $html); }
    } elseif (function_exists('send_email')) {
        function sendMail($to, $subject, $text, $html) { return send_email($to, $subject, $text, $html); }
    } else {
        logit('Cron error: No recognized mail function found in mailer helper. Expected sendMail/send_mailjet/sendMailjet/send_email');
        throw new RuntimeException('No mail function found in mailer helper');
    }
}

// Fail fast on mysqli errors (so you see real errors)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function safe_json_array($s) {
    if (!$s) return [];
    $arr = json_decode($s, true);
    return (is_array($arr)) ? $arr : [];
}

try {
    logit('Cron run started');
    logit('Running script: ' . (realpath(__FILE__) ?: __FILE__));

    // ----------------------------
    // Pull upcoming bids
    // ----------------------------
    // NOTE: We intentionally avoid comparing bid_date to '' or '0000-00-00' (can error in strict sql_mode).
    // Since bid_date is a DATE column, NULL is the only "empty" value that can safely exist.
    $bids = [];
    $bres = $conn->query("
        SELECT bid_id, bid_date, dhss_project_number
        FROM bids
        WHERE bid_date IS NOT NULL
          AND bid_date >= CURDATE()
    ");
    while ($br = $bres->fetch_assoc()) {
        $bids[] = $br;
    }

    if (!count($bids)) {
        logit('No upcoming bids found. Cron run completed.');
        exit(0);
    }

    // ----------------------------
    // Pull opted-in users
    // ----------------------------
    $users = [];
    $ures = $conn->query("
        SELECT email, preferred_days
        FROM bids_email
        WHERE opted_in = 1
    ");
    while ($u = $ures->fetch_assoc()) {
        $email = trim((string)$u['email']);
        if ($email === '') continue;

        $preferred = safe_json_array($u['preferred_days']);
        if (!is_array($preferred) || !count($preferred)) continue;

        $preferred = array_map('intval', $preferred);

        $users[] = [
            'email' => $email,
            'preferred' => $preferred,
        ];
    }

    if (!count($users)) {
        logit('No opted-in users found. Cron run completed.');
        exit(0);
    }

    // ----------------------------
    // Build send list (user x bids)
    // ----------------------------
    $toSend = [];
    $today = new DateTime('today');

    foreach ($users as $usr) {
        foreach ($bids as $br) {
            if (empty($br['bid_date'])) continue;

            $bidDt = new DateTime($br['bid_date']);
            $days = (int)$today->diff($bidDt)->format('%r%a');
            if ($days < 0) continue;

            if (in_array($days, $usr['preferred'], true)) {
                $toSend[] = [
                    'email' => $usr['email'],
                    'bid_id' => (int)$br['bid_id'],
                    'days_before' => $days,
                    'bid_date' => $br['bid_date'],
                    'dhss_project_number' => $br['dhss_project_number'] ?? ''
                ];
            }
        }
    }

    if (!count($toSend)) {
        logit('No notifications match preferred days. Cron run completed.');
        exit(0);
    }

    // ----------------------------
    // Group by recipient for one email per user
    // ----------------------------
    $grouped = [];
    foreach ($toSend as $item) {
        $grouped[$item['email']][] = $item;
    }

    foreach ($grouped as $email => $items) {
        $lines = [];
        $toMark = [];

        // Skip already-sent items
        foreach ($items as $item) {
            $chk = $conn->prepare(
                "SELECT id FROM bids_email_sent WHERE email = ? AND bid_id = ? AND days_before = ? LIMIT 1"
            );
            $chk->bind_param('sii', $email, $item['bid_id'], $item['days_before']);
            $chk->execute();
            $found = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($found && isset($found['id'])) {
                logit("Already sent to {$email} for bid {$item['bid_id']} days_before {$item['days_before']}");
                continue;
            }

            $proj = $item['dhss_project_number'] ?: ('Project ' . $item['bid_id']);

            if ($item['days_before'] === 0) {
                $lines[] = $proj . ': Bid Today';
            } elseif ($item['days_before'] === 1) {
                $lines[] = $proj . ': Bid date in 1 day';
            } else {
                $lines[] = $proj . ': Bid date in ' . (int)$item['days_before'] . ' days';
            }

            $toMark[] = $item;
        }

        if (!count($lines)) {
            // Everything for this user was already sent
            continue;
        }

        $subject = 'Bid reminder:';

        $text = implode("\n", $lines);
        $text .= "\n\n----------------------------------------\n";
        $text .= "To change your notification days, go to Bid Tracking -> Email Notifications\n";
        $text .= "If you do not want these reminders, you can turn them off in the Bid Tracking settings or contact your administrator.";

        $htmlLines = '';
        foreach ($lines as $ln) {
            $htmlLines .= '<p>' . htmlspecialchars($ln, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        $html =
            '<div>' .
                '<h3>Bid reminder:</h3>' .
                $htmlLines .
                '<hr />' .
                '<p>To change your notification days, go to <strong>Bid Tracking &rarr; Email Notifications</strong></p>' .
                '<p>If you do not want these reminders, you can turn them off in the Bid Tracking settings or contact your administrator.</p>' .
            '</div>';

        // Send the email
        $sent = sendMail($email, $subject, $text, $html);

        if (is_array($sent) && !empty($sent['success'])) {
            // Mark as sent (avoid duplicates)
            foreach ($toMark as $m) {
                $ins = $conn->prepare(
                    "INSERT IGNORE INTO bids_email_sent (email, bid_id, days_before) VALUES (?, ?, ?)"
                );
                $ins->bind_param('sii', $email, $m['bid_id'], $m['days_before']);

                try {
                    $ins->execute();
                    if ($ins->affected_rows > 0) {
                        logit("Sent to {$email} for bid {$m['bid_id']} days_before {$m['days_before']}");
                    } else {
                        logit("Insert ignored (duplicate) for {$email} bid {$m['bid_id']} days_before {$m['days_before']}");
                    }
                } catch (Throwable $e) {
                    logit("Insert error for {$email} bid {$m['bid_id']} days_before {$m['days_before']}: " . $e->getMessage());
                }

                $ins->close();
            }
        } else {
            logit("Send failed for {$email} err: " . json_encode($sent));
        }
    }

    logit('Cron run completed');
} catch (Throwable $e) {
    logit('Cron error: ' . $e->getMessage());
    throw $e;
}
