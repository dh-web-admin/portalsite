<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/mailjet_helper.php';
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Require user to be logged in
if (!isset($_SESSION['email'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

$userEmail = $_SESSION['email'];

$raw = $_POST;
$opted = isset($raw['opted_in']) ? (intval($raw['opted_in']) ? 1 : 0) : 1;
$preferred = [];
if (isset($raw['preferred_days'])) {
    // expected JSON array or comma-separated
    $pd = $raw['preferred_days'];
    if (is_string($pd)) {
        $try = json_decode($pd, true);
        if (is_array($try)) $preferred = array_values(array_map('intval', $try));
        else {
            // csv
            $parts = array_filter(array_map('trim', explode(',', $pd)));
            $preferred = array_values(array_map('intval', $parts));
        }
    } else if (is_array($pd)) {
        $preferred = array_values(array_map('intval', $pd));
    }
}

// limit to 1-5 values and max 5 entries
$preferred = array_values(array_filter(array_unique($preferred), function($v){ return $v >=1 && $v <=5; }));
if (count($preferred) > 5) $preferred = array_slice($preferred, 0, 5);

// upsert into bids_email
try {
    $stmt = $conn->prepare('SELECT id FROM bids_email WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $userEmail);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($row && isset($row['id'])) {
        $id = intval($row['id']);
        $up = $conn->prepare('UPDATE bids_email SET opted_in = ?, preferred_days = ? WHERE id = ?');
        $pd_json = json_encode($preferred);
        $up->bind_param('isi', $opted, $pd_json, $id);
        $ok = $up->execute();
        $up->close();
    } else {
        $ins = $conn->prepare('INSERT INTO bids_email (`email`,`opted_in`,`preferred_days`) VALUES (?,?,?)');
        $pd_json = json_encode($preferred);
        $ins->bind_param('sis', $userEmail, $opted, $pd_json);
        $ok = $ins->execute();
        $ins->close();
    }

    // Always send a confirmation email (report success/failure in response)
    $email_sent = false;
    try {
        $subject = 'Bid email notification settings updated';

        // Build human-readable lines for selected days
        if (!empty($preferred) && is_array($preferred)) {
            $dayLines = array_map(function($d){ $n = intval($d); return $n . ' day' . ($n === 1 ? '' : 's') . ' before bid'; }, $preferred);
            $days_text = implode(', ', $preferred);
            $days_block_text = implode("\n", $dayLines);
            $days_block_html = '<ul>' . implode('', array_map(function($l){ return '<li>' . htmlspecialchars($l) . '</li>'; }, $dayLines)) . '</ul>';
        } else {
            $days_text = 'none';
            $days_block_text = 'none';
            $days_block_html = '<p>none</p>';
        }

        $text = "You have changed/updated your email notification for bids.\n\nYou will be reminded on these days for bids:\n" . $days_block_text . "\n\nx y .. are the selected reminder dates";

        $html = "<p>You have changed/updated your email notification for bids.</p>" .
                "<p>You will be reminded on these days for bids:</p>" .
                $days_block_html .
                "<p>Thank you</p>";
        $sent = sendMail($userEmail, $subject, $text, $html);
        if ($sent && isset($sent['success']) && $sent['success']) {
            $email_sent = true;
        } else {
            @file_put_contents(__DIR__ . '/../debug/bids_email_send.log', date('c') . " CONFIRM_SEND_FAILED " . json_encode($sent) . "\n", FILE_APPEND);
        }
    } catch (Throwable $e) {
        @file_put_contents(__DIR__ . '/../debug/bids_email_send.log', date('c') . " CONFIRM_EXCEPTION " . $e->getMessage() . "\n", FILE_APPEND);
    }

    echo json_encode(['success' => true, 'preferred_days' => $preferred, 'opted_in' => $opted, 'email_sent' => $email_sent]);
    exit();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error', 'error' => $e->getMessage()]);
    exit();
}
