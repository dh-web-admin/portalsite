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

// Group items by recipient email so we send a single aggregated message per user
$grouped = [];
foreach ($toSend as $item) {
    $grouped[$item['email']][] = $item;
}

foreach ($grouped as $email => $items) {
    // For this recipient, filter out items already sent and build lines
    $lines = [];
    $toMark = [];
    foreach ($items as $item) {
        // ensure not already sent
        $chk = $conn->prepare('SELECT id FROM bids_email_sent WHERE email = ? AND bid_id = ? AND days_before = ? LIMIT 1');
        $chk->bind_param('sii', $email, $item['bid_id'], $item['days_before']);
        $chk->execute(); $cres = $chk->get_result(); $found = $cres ? $cres->fetch_assoc() : null; $chk->close();
        if ($found && isset($found['id'])) {
            logit('Already sent to '.$email.' for bid '.$item['bid_id'].' days_before '.$item['days_before']);
            continue;
        }

        $proj = $item['dhss_project_number'] ?: 'Project ' . $item['bid_id'];
        if ($item['days_before'] === 0) {
            $textLine = $proj . ': Bid Today';
        } elseif ($item['days_before'] === 1) {
            $textLine = $proj . ': Bid date in 1 day';
        } else {
            $textLine = $proj . ': Bid date in ' . intval($item['days_before']) . ' days';
        }
        $lines[] = $textLine;
        $toMark[] = $item; // mark after successful send
    }

    if (!count($lines)) continue; // nothing to send for this recipient

    // Build aggregated email
    $subject = 'Bid reminder:';
    $text = "";
    foreach ($lines as $ln) { $text .= $ln . "\n"; }
    $text .= "\n----------------------------------------\nTo change your notification days, go to Bid Tracking -> Email Notifications\nIf you do not want these reminders, you can turn them off in the Bid Tracking settings or contact your administrator.";

    $htmlLines = '';
    foreach ($lines as $ln) { $htmlLines .= '<p>' . htmlspecialchars($ln) . '</p>'; }
    $html = '<div><h3>Bid reminder:</h3>' . $htmlLines . '<hr /><p>To change your notification days, go to <strong>Bid Tracking &rarr; Email Notifications</strong></p><p>If you do not want these reminders, you can turn them off in the Bid Tracking settings or contact your administrator.</p></div>';

    $sent = sendMail($email, $subject, $text, $html);
    if ($sent && isset($sent['success']) && $sent['success']) {
        // mark each bid as sent for this recipient
        foreach ($toMark as $m) {
            $ins = $conn->prepare('INSERT INTO bids_email_sent (email,bid_id,days_before) VALUES (?,?,?)');
            $ins->bind_param('sii', $email, $m['bid_id'], $m['days_before']);
            $ins->execute(); $ins->close();
            logit('Sent to '.$email.' for bid '.$m['bid_id'].' days_before '.$m['days_before']);
        }
    } else {
        logit('Send failed for '.$email.' err: '.json_encode($sent));
    }
}
while ($r = $res->fetch_assoc()) {
    $bidDate = $r['bid_date'];
    if (!$bidDate) continue;
    $bidDt = new DateTime($bidDate);
$interval = (new DateTime())->diff($bidDt);
$days = (int)$interval->format('%r%a');
if ($days < 0) continue; // now correctly means "bid already passed"

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
