<?php
/**
 * scripts/send_email_notifications.php
 *
 * Cron-safe email reminder sender for bid notifications.
 * - Reads users' preferred_days from bids_email
 * - Checks bids.bid_date and sends reminders when (bid_date - today) matches a preferred day
 * - Sends ONE aggregated email per recipient per run
 * - Prevents duplicates using bids_email_sent (email, bid_id, days_before)
 */

require_once __DIR__ . '/../session_init.php';          // ok if your project expects it
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/mailjet_helper.php';

date_default_timezone_set('America/New_York'); // adjust if needed

// --------------------------
// Simple logger
// --------------------------
function logit($msg) {
  $path = __DIR__ . '/../debug/bids_email_cron.log';
  @file_put_contents($path, date('c') . ' ' . $msg . "\n", FILE_APPEND);
}

// --------------------------
// Helpers
// --------------------------
function parsePreferredDays($raw) {
  if (!$raw) return [];
  $arr = json_decode($raw, true);
  if (!is_array($arr)) return [];
  $arr = array_values(array_filter(array_unique(array_map('intval', $arr)), function($v){
    return $v >= 0 && $v <= 365; // allow 0 ("today") too
  }));
  return $arr;
}

function safeDateTimeFromYmd($ymd) {
  // supports "YYYY-MM-DD" and other mysql formats, returns DateTimeImmutable or null
  if (!$ymd) return null;
  try {
    // If it's YYYY-MM-DD, force midnight local
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
      return new DateTimeImmutable($ymd . ' 00:00:00');
    }
    return new DateTimeImmutable($ymd);
  } catch (Throwable $e) {
    return null;
  }
}

// --------------------------
// Main
// --------------------------
try {
  logit('Cron run started');

  // IMPORTANT:
  // This query assumes:
  // - bids table has: bid_id, bid_date, dhss_project_number
  // - bids_email has: email, opted_in, preferred_days
  //
  // If your schema differs, adjust the column names here.
  $sql = "
    SELECT
      be.email,
      be.preferred_days,
      b.bid_id,
      b.bid_date,
      b.dhss_project_number
    FROM bids_email be
    JOIN bids b ON 1=1
    WHERE be.opted_in = 1
      AND be.preferred_days IS NOT NULL
      AND be.preferred_days <> '[]'
      AND b.bid_date IS NOT NULL
      AND b.bid_date <> ''
  ";

  $res = $conn->query($sql);
  if (!$res) {
    throw new RuntimeException('Query failed: ' . $conn->error);
  }

  $now = new DateTimeImmutable('today'); // midnight today

  // Build candidate reminders
  $toSend = [];
  while ($r = $res->fetch_assoc()) {
    $email   = $r['email'] ?? '';
    $bidId   = isset($r['bid_id']) ? (int)$r['bid_id'] : 0;
    $bidDate = $r['bid_date'] ?? '';
    if (!$email || !$bidId || !$bidDate) continue;

    $bidDt = safeDateTimeFromYmd($bidDate);
    if (!$bidDt) continue;

    // ✅ Correct: days = (bid_date - today). Future bids => positive days.
    $days = (int)$now->diff($bidDt)->format('%r%a');

    // Skip bids already passed
    if ($days < 0) continue;

    $preferred = parsePreferredDays($r['preferred_days'] ?? '');
    if (!$preferred) continue;

    // If user picked this day count, queue it
    if (in_array($days, $preferred, true)) {
      $toSend[] = [
        'email' => $email,
        'bid_id' => $bidId,
        'days_before' => $days,
        'bid_date' => $bidDt->format('Y-m-d'),
        'dhss_project_number' => ($r['dhss_project_number'] ?? '')
      ];
    }
  }

  if (!count($toSend)) {
    logit('No reminders to send');
    logit('Cron run completed');
    exit;
  }

  // Group by recipient
  $grouped = [];
  foreach ($toSend as $item) {
    $grouped[$item['email']][] = $item;
  }

  // Prepared statement to check duplicates
  $chk = $conn->prepare('SELECT id FROM bids_email_sent WHERE email = ? AND bid_id = ? AND days_before = ? LIMIT 1');
  if (!$chk) throw new RuntimeException('Prepare failed (chk): ' . $conn->error);

  // Prepared statement to mark sent
  // TIP: If you add a UNIQUE key on (email,bid_id,days_before) you can switch to INSERT IGNORE.
  $ins = $conn->prepare('INSERT INTO bids_email_sent (email, bid_id, days_before) VALUES (?,?,?)');
  if (!$ins) throw new RuntimeException('Prepare failed (ins): ' . $conn->error);

  foreach ($grouped as $email => $items) {
    // Filter out items already sent, build lines for email body
    $lines = [];
    $toMark = [];

    // Sort by soonest first (optional but nice)
    usort($items, function($a,$b){
      return ($a['days_before'] <=> $b['days_before']) ?: (($a['bid_id'] ?? 0) <=> ($b['bid_id'] ?? 0));
    });

    foreach ($items as $item) {
      $bidId = (int)$item['bid_id'];
      $days  = (int)$item['days_before'];

      $chk->bind_param('sii', $email, $bidId, $days);
      $chk->execute();
      $cres = $chk->get_result();
      $found = $cres ? $cres->fetch_assoc() : null;

      if ($found && isset($found['id'])) {
        logit("Already sent to {$email} for bid {$bidId} days_before {$days}");
        continue;
      }

      $proj = $item['dhss_project_number'] ?: ('Project ' . $bidId);

      if ($days === 0) {
        $textLine = "{$proj}: Bid Today ({$item['bid_date']})";
      } elseif ($days === 1) {
        $textLine = "{$proj}: Bid date in 1 day ({$item['bid_date']})";
      } else {
        $textLine = "{$proj}: Bid date in {$days} days ({$item['bid_date']})";
      }

      $lines[] = $textLine;
      $toMark[] = $item;
    }

    if (!count($lines)) continue;

    // Build aggregated email
    $subject = 'Bid reminder(s)';
    $text = implode("\n", $lines)
      . "\n\n----------------------------------------\n"
      . "To change your notification days, go to Bid Tracking -> Email Notifications.\n"
      . "If you do not want these reminders, you can turn them off in Bid Tracking settings or contact your administrator.";

    $htmlLines = '';
    foreach ($lines as $ln) {
      $htmlLines .= '<p>' . htmlspecialchars($ln) . '</p>';
    }
    $html =
      '<div>'
      . '<h3>Bid reminder(s)</h3>'
      . $htmlLines
      . '<hr />'
      . '<p>To change your notification days, go to <strong>Bid Tracking &rarr; Email Notifications</strong>.</p>'
      . '<p>If you do not want these reminders, you can turn them off in Bid Tracking settings or contact your administrator.</p>'
      . '</div>';

    $sent = sendMail($email, $subject, $text, $html);

    if ($sent && isset($sent['success']) && $sent['success']) {
      // Mark each sent item
      foreach ($toMark as $m) {
        $bidId = (int)$m['bid_id'];
        $days  = (int)$m['days_before'];
        $ins->bind_param('sii', $email, $bidId, $days);
        $ins->execute();
        logit("Sent to {$email} for bid {$bidId} days_before {$days}");
      }
    } else {
      logit('Send failed for ' . $email . ' err: ' . json_encode($sent));
    }
  }

  $chk->close();
  $ins->close();

  logit('Cron run completed');
} catch (Throwable $e) {
  logit('Cron error: ' . $e->getMessage());
  // If running in CLI, you might want to echo too:
  // echo "Cron error: " . $e->getMessage() . PHP_EOL;
}
