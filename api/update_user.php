<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';
header('Content-Type: application/json; charset=utf-8');

require_edit_api('admin_panel');

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit();
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : null;
$role = $_POST['role'] ?? '';
$password = $_POST['password'] ?? '';
$email = isset($_POST['email']) ? trim($_POST['email']) : null;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Invalid user id']);
    exit();
}

// Fetch current user row to decide if we need to generate a password when adding an email
$currentEmail = null;
$currentPassword = null;
$currentName = null;
$fetchUser = $conn->prepare("SELECT email, password, name FROM users WHERE id = ? LIMIT 1");
$fetchUser->bind_param('i', $id);
$fetchUser->execute();
$resUser = $fetchUser->get_result();
if ($resUser && $resUser->num_rows) {
    $urow = $resUser->fetch_assoc();
    $currentEmail = $urow['email'] ?? null;
    $currentPassword = $urow['password'] ?? null;
    $currentName = $urow['name'] ?? null;
} else {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'User not found']);
    $fetchUser->close();
    exit();
}
$fetchUser->close();

$allowed_roles = ['admin','projectmanager','estimator','accounting','superintendent','foreman','mechanic','operator','laborer','developer','data_entry'];
if (!in_array($role, $allowed_roles, true)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Invalid role']);
    exit();
}

// Verify the requested role exists in the database ENUM to avoid MySQL silently storing an empty string
$colRes = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($colRes) {
    $col = $colRes->fetch_assoc();
    if (isset($col['Type']) && preg_match("/^enum\\((.*)\\)$/i", $col['Type'], $m)) {
        preg_match_all("/'([^']*)'/", $m[1], $matches);
        $enum_vals = $matches[1] ?? [];
        if (!in_array($role, $enum_vals, true)) {
            http_response_code(400);
            echo json_encode(['success'=>false,'error'=>"Role '{$role}' is not supported by the database. Run the migration to add this role."]);
            $conn->close();
            exit();
        }
    } else {
        // Could not parse ENUM definition; fail safe
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>'Unable to verify role type in database.']);
        $conn->close();
        exit();
    }
} else {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Unable to read database schema for role column.']);
    $conn->close();
    exit();
}

// Prevent changing own role to non-admin? We'll allow editing but prevent deleting self elsewhere.

// Build update depending on provided fields. We allow role updates alone.
$params = [];
$types = '';
$sets = [];

// Only update name if provided (non-empty and not null)
if ($name !== null && $name !== '') {
    $sets[] = 'name = ?';
    $types .= 's';
    $params[] = $name;
}

// Role is required for this endpoint (validated above)
$sets[] = 'role = ?';
$types .= 's';
$params[] = $role;

if ($password !== '') {
    if (strlen($password) < 8 || !preg_match('/[0-9]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[!@#$%^&*()_+\-=[\]{};:\'"\\|,.<>\/\?]/', $password)) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'Password must be at least 8 chars, include number, uppercase and special char']);
        exit();
    }
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $sets[] = 'password = ?';
    $types .= 's';
    $params[] = $hashed;
}

// If email is provided, validate and ensure uniqueness
if ($email !== null && $email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'Invalid email address']);
        exit();
    }
    // Check uniqueness
    $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $chk->bind_param('si', $email, $id);
    $chk->execute();
    $resChk = $chk->get_result();
    if ($resChk && $resChk->num_rows) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'Email already in use by another user']);
        $chk->close();
        exit();
    }
    $chk->close();
    $sets[] = 'email = ?';
    $types .= 's';
    $params[] = $email;
}

// If no password was provided but an email is being added to a user who previously had no password,
// generate a random password, save the hashed version and send it by email below.
$generatedPlain = null;
if ($password === '') {
    // If email was provided (or user previously had none) and there is no existing password, generate one
    if ($email !== null && $email !== '' && (empty($currentPassword) || $currentPassword === null || $currentPassword === '')) {
        // generate a compliant password
        function generate_random_password($length = 12) {
            $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $lower = 'abcdefghijklmnopqrstuvwxyz';
            $nums = '0123456789';
            $specials = '!@#$%^&*()_+-={}[]|:;<>,.?/';
            $all = $upper . $lower . $nums . $specials;
            $pwd = '';
            $pwd .= $upper[random_int(0, strlen($upper)-1)];
            $pwd .= $nums[random_int(0, strlen($nums)-1)];
            $pwd .= $specials[random_int(0, strlen($specials)-1)];
            for ($i = 3; $i < $length; $i++) {
                $pwd .= $all[random_int(0, strlen($all)-1)];
            }
            // shuffle
            $pwd = str_shuffle($pwd);
            return $pwd;
        }

        $generatedPlain = generate_random_password(12);
        $hashed = password_hash($generatedPlain, PASSWORD_DEFAULT);
        $sets[] = 'password = ?';
        $types .= 's';
        $params[] = $hashed;
    }
}

// Ensure we have something to update
if (empty($sets)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'No fields to update']);
    exit();
}

$types .= 'i';
$params[] = $id;

$sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?";
$update = $conn->prepare($sql);

// bind params dynamically
$update->bind_param($types, ...$params);

if ($update->execute()) {
    $affected = $update->affected_rows;
    // Fetch the current role from the DB to ensure we return what is actually stored
    $fetch = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $fetch->bind_param('i', $id);
    $fetch->execute();
    $res2 = $fetch->get_result();
    $dbRole = null;
    if ($res2 && $res2->num_rows) {
        $row2 = $res2->fetch_assoc();
        $dbRole = $row2['role'];
    }
    $fetch->close();
    // If we generated a password above, send the notification email like register_new.php does
    $emailSentOk = null;
    if (!empty($generatedPlain) && $email) {
        try {
            $to = $email;
            $creator = (isset($_SESSION['name']) && $_SESSION['name']) ? $_SESSION['name'] : (isset($_SESSION['email']) ? $_SESSION['email'] : 'Admin');
            $subject = 'Your DarkHorse account';
            $plainPassword = $generatedPlain;

            $displayName = $name ?? $currentName ?? '';

            $text = "Hello " . $displayName . "\n\n" .
                "Your DarkHorse Login has been created. You can go to https://app.darkhorsespreader.com to access your employee portal.\n\n" .
                "email: " . $email . "\n" .
                "password: " . $plainPassword . "\n\n" .
                "Once you login to your account, please go ahead and change your password by navigating to Account Settings on the top right corner of the portal.\n\n" .
                "Thanks,\n" . $creator;

            $html = "<div style=\"font-family:Arial,sans-serif;color:#333;line-height:1.6;\">" .
                "<p>Hello " . htmlspecialchars($displayName, ENT_QUOTES) . ",</p>" .
                "<p>Your DarkHorse Login has been created. You can go to <a href=\"https://app.darkhorsespreader.com\">app.darkhorsespreader.com</a> to access your employee portal.</p>" .
                "<p><strong>email:</strong> " . htmlspecialchars($email, ENT_QUOTES) . "<br/>" .
                "<strong>password:</strong> " . htmlspecialchars($plainPassword, ENT_QUOTES) . "</p>" .
                "<p><strong>Once you login to your account, please go ahead and change your password by navigating to Account Settings on the top right corner of the portal.</strong></p>" .
                "<p>Thanks,<br/>" . htmlspecialchars($creator, ENT_QUOTES) . "</p>" .
                "</div>";

            // Load mailer helper candidates
            $mailerCandidates = [
                __DIR__ . '/../auth/mailjet_helper.php',
                __DIR__ . '/../partials/mailer.php',
                __DIR__ . '/../partials/mailer_helper.php',
                __DIR__ . '/../config/email_config.php'
            ];
            foreach ($mailerCandidates as $cand) {
                if (file_exists($cand)) { require_once $cand; break; }
            }

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
                    function sendMail($to, $subject, $text, $html) {
                        $headers = "MIME-Version: 1.0\r\n" .
                            "Content-type:text/html;charset=UTF-8\r\n";
                        $from = isset($_SESSION['email']) && $_SESSION['email'] ? $_SESSION['email'] : 'no-reply@darkhorsespreader.com';
                        $headers .= 'From: ' . (isset($_SESSION['name'])?$_SESSION['name']:'Admin') . " <" . $from . ">\r\n";
                        $sent = @mail($to, $subject, $html, $headers);
                        return $sent ? ['success'=>true] : ['success'=>false,'error'=>'php-mail-failed'];
                    }
                }
            }

            $sent = sendMail($to, $subject, $text, $html);
            $mailOk = false;
            if (is_array($sent)) {
                $mailOk = !empty($sent['success']);
            } elseif (is_bool($sent)) {
                $mailOk = $sent;
            }
            $emailSentOk = $mailOk;
        } catch (Throwable $ex) {
            $emailSentOk = false;
        }
    }

    $out = ['success'=>true,'message'=>'User updated','affected_rows'=>$affected,'role'=> $dbRole];
    if ($generatedPlain !== null) $out['email_sent'] = $emailSentOk ? true : false;
    echo json_encode($out);
} else {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Update failed: '.$conn->error]);
}
$update->close();
$conn->close();
?>
