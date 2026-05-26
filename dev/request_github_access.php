<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Ensure user is logged in
if (!isset($_SESSION['email'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Validate POST
$github = isset($_POST['github_username']) ? trim($_POST['github_username']) : '';
$railway = isset($_POST['railway_email']) ? trim($_POST['railway_email']) : '';

if ($github === '' && $railway === '') {
    $_SESSION['flash'] = 'Please provide GitHub username or Railway email.';
    header('Location: getting-started.php');
    exit();
}

// Lookup requester name/email
$requesterEmail = $_SESSION['email'];
$stmt = $conn->prepare('SELECT id, name, email FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $requesterEmail);
$stmt->execute();
$res = $stmt->get_result();
$requester = $res->fetch_assoc();
$stmt->close();

$requesterName = $requester['name'] ?? $requesterEmail;

// Build grant link (admin/dev will click this)
$params = http_build_query([
    'requester_email' => $requesterEmail,
    'requester_name' => $requesterName,
    'github_username' => $github,
    'railway_email' => $railway,
]);
$grantUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/access-resources.php?' . $params;

// Find all developer users to notify
$devs = [];
$dstmt = $conn->prepare("SELECT name, email FROM users WHERE role = 'developer'");
if ($dstmt) {
    $dstmt->execute();
    $dres = $dstmt->get_result();
    while ($row = $dres->fetch_assoc()) {
        $devs[] = $row;
    }
    $dstmt->close();
}

// Compose email
$subject = 'Access request: ' . htmlspecialchars($requesterName);
$body = "<p><strong>" . htmlspecialchars($requesterName) . "</strong> (" . htmlspecialchars($requesterEmail) . ") has requested access to the GitHub repository and Railway deployment.</p>";
$body .= "<p>GitHub username: " . htmlspecialchars($github) . "<br>Railway email: " . htmlspecialchars($railway) . "</p>";
$body .= "<p><a href=\"" . htmlspecialchars($grantUrl) . "\" style=\"display:inline-block;padding:10px 16px;background:#1d4ed8;color:#fff;border-radius:6px;text-decoration:none;\">Grant access</a></p>";
// Prefer existing mail helpers (Mailjet/PHPMailer) if available
$mailerCandidates = [
    __DIR__ . '/../auth/mailjet_helper.php',
    __DIR__ . '/../partials/mailer.php',
    __DIR__ . '/../partials/mailer_helper.php',
    __DIR__ . '/../config/email_config.php'
];
foreach ($mailerCandidates as $cand) {
    if (file_exists($cand)) { require_once $cand; break; }
}

// Normalize to sendMail($to,$subject,$text,$html)
if (!function_exists('sendMail')) {
    if (function_exists('send_mailjet')) {
        function sendMail($to, $subject, $text, $html) { return send_mailjet($to, $subject, $text, $html); }
    } elseif (function_exists('sendMailjet')) {
        function sendMail($to, $subject, $text, $html) { return sendMailjet($to, $subject, $text, $html); }
    } elseif (function_exists('send_email')) {
        function sendMail($to, $subject, $text, $html) { return send_email($to, $subject, $text, $html); }
    } elseif (function_exists('sendEmail')) {
        function sendMail($to, $subject, $text, $html) { $ok = sendEmail($to, $subject, $html, true); return $ok ? ['success'=>true] : ['success'=>false,'error'=>'sendEmail failed']; }
    } else {
        // Fallback to PHP mail()
        function sendMail($to, $subject, $text, $html) {
            $headers = "MIME-Version: 1.0\r\n" .
                "Content-type:text/html;charset=UTF-8\r\n";
            $from = 'no-reply@' . $_SERVER['SERVER_NAME'];
            $headers .= 'From: ' . "DarkHorse <" . $from . ">\r\n";
            $sent = @mail($to, $subject, $html, $headers);
            return $sent ? ['success'=>true] : ['success'=>false,'error'=>'php-mail-failed'];
        }
    }
}

$plain = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $body));
$sentAny = false;
$lastError = '';
foreach ($devs as $d) {
    $to = $d['email'];
    $res = sendMail($to, $subject, $plain, $body);
    if (is_array($res)) {
        if (!empty($res['success'])) { $sentAny = true; }
        elseif (!empty($res['error'])) { $lastError = $res['error']; }
    } elseif ($res === true) {
        $sentAny = true;
    }
}

if ($sentAny) {
    $_SESSION['flash'] = 'Request sent to developer(s).';
} else {
    $_SESSION['flash'] = 'Unable to send email — check mail configuration. ' . ($lastError ? $lastError : 'Request details saved.');
}

// Redirect back
header('Location: getting-started.php');
exit();

?>
