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
        SELECT bid_id, bid_date, dhss_project_number, project_name
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
        SELECT u.email, b.preferred_days, u.name
        FROM bids_email b
        LEFT JOIN users u ON u.email COLLATE utf8mb4_0900_ai_ci = b.email COLLATE utf8mb4_0900_ai_ci
        WHERE b.opted_in = 1
    ");
    while ($u = $ures->fetch_assoc()) {
        $email = trim((string)$u['email']);
        if ($email === '') continue;

        $preferred = safe_json_array($u['preferred_days']);
        if (!is_array($preferred) || !count($preferred)) continue;

        $preferred = array_map('intval', $preferred);

        $users[] = [
            'email' => $email,
            'name' => $u['name'] ?? 'User',
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
                    'name' => $usr['name'],
                    'bid_id' => (int)$br['bid_id'],
                    'days_before' => $days,
                    'bid_date' => $br['bid_date'],
                    'dhss_project_number' => $br['dhss_project_number'] ?? '',
                    'project_name' => $br['project_name'] ?? ''
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
        $userName = '';
        $bidsByDate = [];
        $toMark = [];

        // Skip already-sent items and group by date
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

            // Capture user name from first item
            if ($userName === '') {
                $userName = $item['name'] ?: 'User';
            }

            // Group by date
            $bidsByDate[$item['bid_date']][] = $item;
            $toMark[] = $item;
        }

        if (!count($toMark)) {
            // Everything for this user was already sent
            continue;
        }

        // Sort dates chronologically
        ksort($bidsByDate);

        // Build plain text email
        $textLines = [];
        $textLines[] = 'Good morning ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ',';
        $textLines[] = '';
        $textLines[] = 'Daily Bid Updates:';
        $textLines[] = '';

        $today = new DateTime('today');
        foreach ($bidsByDate as $bidDate => $bidItems) {
            $dateObj = new DateTime($bidDate);
            $days = (int)$today->diff($dateObj)->format('%r%a');
            $dateStr = $dateObj->format('M j, Y');

            if ($days === 0) {
                $textLines[] = 'Today: ' . $dateStr;
            } elseif ($days === 1) {
                $textLines[] = 'Tomorrow: ' . $dateObj->format('l') . ' | ' . $dateStr;
            } else {
                $textLines[] = $dateObj->format('l') . ' | ' . $dateStr;
            }

            foreach ($bidItems as $bid) {
                $projNum = $bid['dhss_project_number'] ?: ('Bid ' . $bid['bid_id']);
                $projName = $bid['project_name'] ? ' – ' . $bid['project_name'] : '';
                $textLines[] = $projNum . $projName;
            }

            $textLines[] = '';
        }

        $textLines[] = '----------------------------------------';
        $textLines[] = 'To change your notification days, go to Bid Tracking -> Email Notifications';
        $textLines[] = 'If you do not want these reminders, you can turn them off in the Bid Tracking settings or contact your administrator.';

        $text = implode("\n", $textLines);

        // Build HTML email
        $htmlBidsContent = '';
        foreach ($bidsByDate as $bidDate => $bidItems) {
            $dateObj = new DateTime($bidDate);
            $days = (int)$today->diff($dateObj)->format('%r%a');
            $dateStr = $dateObj->format('M j, Y');

            if ($days === 0) {
                $dayLabel = 'Today: ' . $dateStr;
            } elseif ($days === 1) {
                $dayLabel = 'Tomorrow: ' . $dateObj->format('l') . ' | ' . $dateStr;
            } else {
                $dayLabel = $dateObj->format('l') . ' | ' . $dateStr;
            }

            $htmlBidsContent .= '<p><strong>' . htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8') . '</strong></p>';

            foreach ($bidItems as $bid) {
                $projNum = $bid['dhss_project_number'] ?: ('Bid ' . $bid['bid_id']);
                $projName = $bid['project_name'] ? ' – ' . $bid['project_name'] : '';
                $htmlBidsContent .= '<p style="margin-left:16px;">' . htmlspecialchars($projNum . $projName, ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }

        $html = '<div style="font-family:Arial,sans-serif;color:#333;line-height:1.6;">' .
            '<p>Good morning ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ',</p>' .
            '<h3 style="color:#0f172a;margin-top:20px;margin-bottom:12px;">Daily Bid Updates:</h3>' .
            $htmlBidsContent .
            '<hr style="margin-top:20px;margin-bottom:20px;" />' .
            '<p>To change your notification days, go to <strong>Bid Tracking &rarr; Email Notifications</strong></p>' .
            '<p>If you do not want these reminders, you can turn them off in the Bid Tracking settings or contact your administrator.</p>' .
            '</div>';

        $subject = 'Daily Bid Updates';

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
