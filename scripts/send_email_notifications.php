<?php
// scripts/send_email_notifications.php

require_once __DIR__ . '/../config/config.php';      // $conn (mysqli)
require_once __DIR__ . '/../partials/mailer.php';    // sendMail($to,$subject,$text,$html)
require_once __DIR__ . '/../partials/logger.php';    // logit($msg)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function safe_json_array($s) {
    if (!$s) return [];
    $arr = json_decode($s, true);
    return is_array($arr) ? $arr : [];
}

try {
    logit('Cron run started');

    /**
     * Schema (confirmed):
     * - bids
     * - bids_email (global per-user notification preferences)
     * - bids_email_sent (dedupe log)
     *
     * Logic:
     * - For every upcoming bid
     * - For every opted-in email
     * - Check if (bid_date - today) matches preferred_days
     */
    $sql = "
        SELECT
            b.id AS bid_id,
            b.bid_date,
            b.dhss_project_number,
            be.email,
            be.preferred_days
        FROM bids b
        JOIN bids_email be ON be.opted_in = 1
        WHERE
            b.bid_date IS NOT NULL
            AND b.bid_date >= CURDATE()
    ";

    $res = $conn->query($sql);

    $toSend = [];

    while ($r = $res->fetch_assoc()) {
        $bidDate = $r['bid_date'];
        if (!$bidDate) continue;

        $today = new DateTime('today');
        $bidDt = new DateTime($bidDate);
        $days = (int)$today->diff($bidDt)->format('%r%a');

        // Skip past bids
        if ($days < 0) continue;

        $email = trim($r['email']);
        if ($email === '') continue;

        $preferred = safe_json_array($r['preferred_days']);
        if (!$preferred) continue;

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

    // Group reminders by recipient (one email per user)
    $grouped = [];
    foreach ($toSend as $item) {
        $grouped[$item['email']][] = $item;
    }

    foreach ($grouped as $email => $items) {
        $lines = [];
        $toMark = [];

        foreach ($items as $item) {
            // Check duplicate
            $chk = $conn->prepare(
                "SELECT id FROM bids_email_sent
                 WHERE email = ? AND bid_id = ? AND days_before = ?
                 LIMIT 1"
            );
            $chk->bind_param('sii', $email, $item['bid_id'], $item['days_before']);
            $chk->execute();
            $found = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($found) continue;

            $proj = $item['dhss_project_number'] ?: 'Project ' . $item['bid_id'];

            if ($item['days_before'] === 0) {
                $lines[] = "$proj: Bid Today";
            } elseif ($item['days_before'] === 1) {
                $lines[] = "$proj: Bid in 1 day";
            } else {
                $lines[] = "$proj: Bid in {$item['days_before']} days";
            }

            $toMark[] = $item;
        }

        if (!$lines) continue;

        $subject = 'Bid reminder(s)';
        $text = implode("\n", $lines) .
            "\n\n----------------------------------------\n" .
            "Manage notifications in Bid Tracking → Email Notifications.";

        $html = '<div><h3>Bid reminder(s)</h3>';
        foreach ($lines as $ln) {
            $html .= '<p>' . htmlspecialchars($ln) . '</p>';
        }
        $html .= '<hr><p>Manage notifications in <strong>Bid Tracking → Email Notifications</strong>.</p></div>';

        $sent = sendMail($email, $subject, $text, $html);

        if ($sent && !empty($sent['success'])) {
            foreach ($toMark as $m) {
                $ins = $conn->prepare(
                    "INSERT INTO bids_email_sent (email, bid_id, days_before)
                     VALUES (?, ?, ?)"
                );
                $ins->bind_param('sii', $email, $m['bid_id'], $m['days_before']);
                $ins->execute();
                $ins->close();
            }
            logit("Sent reminder to {$email}");
        } else {
            logit("Send failed for {$email}: " . json_encode($sent));
        }
    }

    logit('Cron run completed');
} catch (Throwable $e) {
    logit('Cron error: ' . $e->getMessage());
    throw $e;
}
