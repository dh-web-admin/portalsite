<?php
// scripts/send_email_notifications.php

require_once __DIR__ . '/../config/config.php';      // $conn (mysqli)
require_once __DIR__ . '/../partials/mailer.php';    // sendMail($to,$subject,$text,$html)
require_once __DIR__ . '/../partials/logger.php';    // logit($msg)

// If you already enabled this elsewhere, it's fine to keep it here too.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function safe_json_array($s) {
    if (!$s) return [];
    $arr = json_decode($s, true);
    return (is_array($arr)) ? $arr : [];
}

try {
    logit('Cron run started');

    // IMPORTANT:
    // DO NOT compare DATE columns to '' (empty string). That can cause "Incorrect DATE value: ''".
    // Instead, filter using proper date conditions.
    $sql = "
        SELECT
            b.id AS bid_id,
            b.bid_date,
            b.dhss_project_number,
            n.email,
            n.preferred_days
        FROM bids b
        JOIN bids_email_notifications n ON n.bid_id = b.id
        WHERE
            n.enabled = 1
            AND b.bid_date IS NOT NULL
            AND b.bid_date >= CURDATE()
    ";

    $res = $conn->query($sql);

    $toSend = [];

    while ($r = $res->fetch_assoc()) {
        $bidDate = $r['bid_date'];
        if (!$bidDate) continue;

        // Days until bid date (0=today, 1=tomorrow, etc.)
        $today = new DateTime('today');
        $bidDt = new DateTime($bidDate);
        $days = (int)$today->diff($bidDt)->format('%r%a');

        // Only upcoming/today bids
        if ($days < 0) continue;

        $email = trim((string)$r['email']);
        if ($email === '') continue;

        $preferred = safe_json_array($r['preferred_days']);
        if (!count($preferred)) continue;

        $preferred = array_map('intval', $preferred);

        if (in_array($days, $preferred, true)) {
            $toSend[] = [
                'email' => $email,
                'bid_id' => (int)$r['bid_id'],
                'days_before' => $days,
                'bid_date' => $bidDate,
                'dhss_project_number' => $r['dhss_project_number'] ?? ''
            ];
        }
    }

    // Group by recipient email so we send one aggregated email per user
    $grouped = [];
    foreach ($toSend as $item) {
        $grouped[$item['email']][] = $item;
    }

    foreach ($grouped as $email => $items) {
        $lines = [];
        $toMark = [];

        foreach ($items as $item) {
            // ensure not already sent
            $chk = $conn->prepare("
                SELECT id
                FROM bids_email_sent
                WHERE email = ? AND bid_id = ? AND days_before = ?
                LIMIT 1
            ");
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
                $lines[] = $proj . ': Bid date in ' . $item['days_before'] . ' days';
            }

            $toMark[] = $item;
        }

        if (!count($lines)) continue;

        $subject = 'Bid reminder:';

        $text = implode("\n", $lines);
        $text .= "\n\n----------------------------------------\n";
        $text .= "To change your notification days, go to Bid Tracking -> Email Notifications\n";
        $text .= "If you do not want these reminders, you can turn them off in the Bid Tracking settings or contact your administrator.";

        $htmlLines = '';
        foreach ($lines as $ln) {
            $htmlLines .= '<p>' . htmlspecialchars($ln) . '</p>';
        }
        $html = '<div><h3>Bid reminder:</h3>' . $htmlLines .
            '<hr />' .
            '<p>To change your notification days, go to <strong>Bid Tracking &rarr; Email Notifications</strong></p>' .
            '<p>If you do not want these reminders, you can turn them off in the Bid Tracking settings or contact your administrator.</p>' .
            '</div>';

        $sent = sendMail($email, $subject, $text, $html);

        if ($sent && !empty($sent['success'])) {
            foreach ($toMark as $m) {
                $ins = $conn->prepare("
                    INSERT INTO bids_email_sent (email, bid_id, days_before)
                    VALUES (?, ?, ?)
                ");
                $ins->bind_param('sii', $email, $m['bid_id'], $m['days_before']);
                $ins->execute();
                $ins->close();

                logit("Sent to {$email} for bid {$m['bid_id']} days_before {$m['days_before']}");
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
