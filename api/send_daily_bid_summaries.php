<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/mailjet_helper.php';

// Script intended to be run via cron (no auth required when run from server)
// It composes a 6-day summary (today + next 5 days) and emails users who opted in for daily updates.

// Set timezone
date_default_timezone_set(@date_default_timezone_get() ?: 'UTC');
$today = new DateTimeImmutable('today');
$dates = [];
for ($i = 0; $i < 6; $i++) {
    $d = $today->add(new DateInterval('P' . $i . 'D'));
    $dates[] = $d->format('Y-m-d');
}

try {
    // Fetch all users with emails
    $usersRes = $conn->query("SELECT id, email, name FROM users WHERE email IS NOT NULL AND email != ''");
    $users = [];
    if ($usersRes) {
        while ($u = $usersRes->fetch_assoc()) $users[] = $u;
    }

    // Preload bids in the next 6 days by bid_date (assuming column name contains 'date' like bid_date)
    $placeholders = implode(',', array_fill(0, count($dates), '?'));
    $sql = "SELECT * FROM bids WHERE DATE(bid_date) IN ($placeholders) ORDER BY bid_date ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
    $types = str_repeat('s', count($dates));
    $bind = array_merge([$types], $dates);
    $refs = [];
    foreach ($bind as $k => $v) $refs[$k] = &$bind[$k];
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $res = $stmt->get_result();
    $bids = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $bidDate = (isset($r['bid_date']) && $r['bid_date']) ? substr($r['bid_date'],0,10) : null;
            if ($bidDate) {
                if (!isset($bids[$bidDate])) $bids[$bidDate] = [];
                $bids[$bidDate][] = $r;
            }
        }
    }
    $stmt->close();

    // For each user, check daily_opted and send email
    foreach ($users as $u) {
        $to = $u['email'];
        $name = !empty($u['name']) ? $u['name'] : 'user';

        // load preference
        $daily_opted = 1;
        try {
            $pstmt = $conn->prepare('SELECT preferred_days FROM bids_email WHERE email = ? LIMIT 1');
            if ($pstmt) {
                $pstmt->bind_param('s', $to);
                $pstmt->execute();
                $pres = $pstmt->get_result();
                $prow = $pres ? $pres->fetch_assoc() : null;
                $pstmt->close();
                if ($prow && isset($prow['preferred_days'])) {
                    $pd = $prow['preferred_days'];
                    $decoded = json_decode($pd, true);
                    if (is_array($decoded) && isset($decoded['daily_opted'])) {
                        $daily_opted = intval($decoded['daily_opted']) ? 1 : 0;
                    } else {
                        $daily_opted = 1; // default
                    }
                }
            }
        } catch (Throwable $e) {
            $daily_opted = 1;
        }

        if (!$daily_opted) continue;

        // Build sections: today, tomorrow, next 4 days
        $todayKey = $dates[0];
        $tomorrowKey = $dates[1];

        $todayBids = isset($bids[$todayKey]) ? $bids[$todayKey] : [];
        $tomorrowBids = isset($bids[$tomorrowKey]) ? $bids[$tomorrowKey] : [];

        $more = [];
        for ($i = 2; $i < 6; $i++) {
            $key = $dates[$i];
            if (isset($bids[$key]) && count($bids[$key])) {
                foreach ($bids[$key] as $bb) $more[] = ['date' => $key, 'bid' => $bb];
            }
        }

        // Compose HTML
        $html = "<div style='font-family: Arial, sans-serif; max-width:700px; color:#0f172a; border:1px solid #eef2f6; border-radius:8px; overflow:hidden;'>";
        $html .= "<div style='background:#0b76ef;color:#fff;padding:16px 20px;'><h2 style='margin:0;font-size:20px;'>Daily Bid Summary</h2></div>";
        $html .= "<div style='padding:16px;background:#fff;'>";
        $html .= "<div style='font-size:15px;margin-bottom:6px;'>Good morning " . htmlspecialchars($name) . ",</div>";
        $html .= "<div style='font-size:15px;margin-bottom:12px;color:#334155;'>Here is your daily bids summary</div>";

        // Today
        if (!empty($todayBids)) {
            $b = $todayBids[0];
            $proj = htmlspecialchars($b['project_name'] ?? '');
            $addrParts = [];
            if (!empty($b['project_address'])) $addrParts[] = $b['project_address'];
            if (!empty($b['project_city'])) $addrParts[] = $b['project_city'];
            if (!empty($b['project_county'])) $addrParts[] = $b['project_county'];
            if (!empty($b['project_state'])) $addrParts[] = $b['project_state'];
            $addr = htmlspecialchars(implode(', ', $addrParts));
            $gc = '';
            if (!empty($b['client_winner'])) {
                $cw = $b['client_winner'];
                if (is_numeric($cw)) {
                    $gq = $conn->prepare('SELECT COALESCE(general_contractor_name, general_contractor) AS name FROM general_contractor WHERE id = ? LIMIT 1');
                    if ($gq) { $gq->bind_param('i', $cw); $gq->execute(); $gr = $gq->get_result(); $grow = $gr ? $gr->fetch_assoc() : null; if ($grow) $gc = $grow['name']; $gq->close(); }
                } else { $gc = $cw; }
            }
            $html .= "<div style='background:#f1f8ff;border-left:4px solid #0b76ef;padding:12px;border-radius:6px;margin-bottom:12px;'>";
            $html .= "<div style='font-size:14px;color:#0b1726;font-weight:600;'>Due Today</div>";
            $html .= "<div style='font-size:16px;color:#0b1726;margin-top:6px;'>";
            $html .= ($proj ? $proj : '-');
            $html .= "</div>";
            $html .= "<div style='color:#475569;margin-top:8px;line-height:1.45;'>";
            if ($proj) $html .= "<div><strong>Project:</strong> " . $proj . "</div>";
            if ($addr) $html .= "<div><strong>Address:</strong> " . $addr . "</div>";
            if ($gc) $html .= "<div><strong>General Contractor:</strong> " . htmlspecialchars($gc) . "</div>";
            $html .= "</div></div>";
            // add extra spacing after today
            $html .= "<div style='height:12px;'></div>";
        } else {
            $html .= "<div style='background:#f8fafc;border-left:4px solid #94a3b8;padding:14px;border-radius:8px;margin-bottom:14px;color:#475569;'>No bids due today</div>";
            $html .= "<div style='height:12px;'></div>";
        }

        // Tomorrow
        if (!empty($tomorrowBids)) {
            $b = $tomorrowBids[0];
            $proj = htmlspecialchars($b['project_name'] ?? '');
            $addrParts = [];
            if (!empty($b['project_address'])) $addrParts[] = $b['project_address'];
            if (!empty($b['project_city'])) $addrParts[] = $b['project_city'];
            if (!empty($b['project_county'])) $addrParts[] = $b['project_county'];
            if (!empty($b['project_state'])) $addrParts[] = $b['project_state'];
            $addr = htmlspecialchars(implode(', ', $addrParts));
            $gc = '';
            if (!empty($b['client_winner'])) {
                $cw = $b['client_winner'];
                if (is_numeric($cw)) {
                    $gq = $conn->prepare('SELECT COALESCE(general_contractor_name, general_contractor) AS name FROM general_contractor WHERE id = ? LIMIT 1');
                    if ($gq) { $gq->bind_param('i', $cw); $gq->execute(); $gr = $gq->get_result(); $grow = $gr ? $gr->fetch_assoc() : null; if ($grow) $gc = $grow['name']; $gq->close(); }
                } else { $gc = $cw; }
            }
            // Use the same boxed layout as today's entry, with labels for Project and Address
            $html .= "<div style='background:#fff7ed;border-left:4px solid #ff9800;padding:12px;border-radius:6px;margin-bottom:14px;'>";
            $html .= "<div style='font-size:14px;color:#b45309;'>Due Tomorrow</div>";
            $html .= "<div style='font-size:16px;color:#0b1726;font-weight:600;margin-top:6px;'>";
            $html .= ($proj ? $proj : '-');
            $html .= "</div>";
            $html .= "<div style='color:#475569;margin-top:8px;line-height:1.45;'>";
            if ($proj) $html .= "<div><strong>Project:</strong> " . $proj . "</div>";
            if ($addr) $html .= "<div><strong>Address:</strong> " . $addr . "</div>";
            if ($gc) $html .= "<div><strong>General Contractor:</strong> " . htmlspecialchars($gc) . "</div>";
            $html .= "</div></div>";
            $html .= "<div style='height:12px;'></div>";
        } else {
            $html .= "<div style='background:#fffaf0;border-left:4px solid #f59e0b;padding:14px;border-radius:8px;margin-bottom:14px;color:#475569;'>No bids due tomorrow</div>";
            $html .= "<div style='height:12px;'></div>";
        }

        // More updates in its own bubble
        $html .= "<div style='background:#eef2ff;border-left:4px solid #3b82f6;padding:14px;border-radius:8px;margin-top:8px;color:#0f172a;'>";
        $html .= "<div style='font-weight:600;margin-bottom:8px;'>More updates</div>";
        if (!empty($more)) {
            $html .= "<ul style='padding-left:20px;margin:0;color:#334155;line-height:1.8;'>";
            foreach ($more as $m) {
                $d = $m['date'];
                $bb = $m['bid'];
                $proj = htmlspecialchars($bb['project_name'] ?? '');
                $addrParts = [];
                if (!empty($bb['project_address'])) $addrParts[] = $bb['project_address'];
                if (!empty($bb['project_city'])) $addrParts[] = $bb['project_city'];
                if (!empty($bb['project_county'])) $addrParts[] = $bb['project_county'];
                if (!empty($bb['project_state'])) $addrParts[] = $bb['project_state'];
                $addr = htmlspecialchars(implode(', ', $addrParts));
                $html .= "<li style='margin-bottom:6px;'>" . htmlspecialchars($d) . " - " . ($proj ? $proj . " - " . $addr : ($addr ? $addr : '')) . "</li>";
            }
            $html .= "</ul>";
        } else {
            $html .= "<div style='color:#475569;'>No bids due this week</div>";
        }
        $html .= "</div>";

        $html .= "<hr style='margin:16px 0;border:none;border-top:1px solid #eef2f6' />";
        $html .= "<div style='font-size:13px;color:#6b7280;'>Want fewer emails? Open the Bid Tracking bell icon → Email Notifications to turn off daily or project-win notifications.</div>";
        $html .= "</div>"; // container

        // Plain text
        $text = "Daily Bid Summary\n\nGood morning " . $name . ",\n\nHere is your daily bids summary\n\n";
        if (!empty($todayBids)) {
            $b = $todayBids[0];
            $text .= "DUE TODAY\n- " . ($b['project_name'] ?? '') . "\n";
            if (!empty($b['project_address'])) $text .= "  Address: " . $b['project_address'] . "\n";
            if (!empty($b['client_winner'])) $text .= "  GC: " . $b['client_winner'] . "\n";
            $text .= "\n";
        } else {
            $text .= "No bids due today\n\n";
        }
        if (!empty($tomorrowBids)) {
            $b = $tomorrowBids[0];
            $text .= "DUE TOMORROW\n- " . ($b['project_name'] ?? '') . "\n";
            if (!empty($b['project_address'])) $text .= "  Address: " . $b['project_address'] . "\n";
            if (!empty($b['client_winner'])) $text .= "  GC: " . $b['client_winner'] . "\n";
            $text .= "\n";
        } else {
            $text .= "No bids due tomorrow\n\n";
        }
        $text .= "MORE UPDATES\n";
        if (!empty($more)) {
            foreach ($more as $m) {
                $d = $m['date']; $bb = $m['bid'];
                $text .= "- " . $d . " — " . ($bb['project_name'] ?? '') . "\n";
            }
        } else {
            $text .= "No bids due this week\n";
        }

        $text .= "\nWant fewer emails? Open the Bid Tracking bell icon → Email Notifications to change your preferences.\n";

        // Send
        try {
            $res = sendMail($to, 'Daily Bid Summary — 6 Day Outlook', $text, $html);
            @file_put_contents(__DIR__ . '/daily_send.log', date('c') . " SENT to: $to result: " . json_encode($res) . "\n", FILE_APPEND);
        } catch (Throwable $e) {
            @file_put_contents(__DIR__ . '/daily_send.log', date('c') . " SEND_ERROR to: $to " . $e->getMessage() . "\n", FILE_APPEND);
        }

    }

    echo json_encode(['success'=>true]);
    exit();

} catch (Throwable $e) {
    @file_put_contents(__DIR__ . '/daily_send.log', date('c') . " SCRIPT_ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    exit();
}
