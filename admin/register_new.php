<?php
require_once __DIR__ . '/../session_init.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

// Get admin information
$email = $_SESSION['email'];
$query = "SELECT role FROM users WHERE email='$email'";
$result = $conn->query($query);
$user = $result->fetch_assoc();

// Determine role and require Admin Panel access
$role = (string)($user['role'] ?? 'laborer');
if (!function_exists('can_access') || !can_access((string)$role, 'admin_panel')) {
    header('Location: ../pages/dashboard/');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trim and sanitize inputs
    $name = trim($_POST['name'] ?? '');
    $email_input = trim($_POST['email'] ?? '');
    $password_input = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $non_user = isset($_POST['non_user_employee']) && $_POST['non_user_employee'] == '1';

    // Preserve values for redisplay on error
    $old = [
        'name' => htmlspecialchars($name, ENT_QUOTES),
        'email' => htmlspecialchars($email_input, ENT_QUOTES),
        'role' => htmlspecialchars($role, ENT_QUOTES)
    ];
    if ($non_user) $old['non_user'] = true;

    // Server-side validation
    // Accept any valid email address (no domain restriction) unless this is a non-user employee
    if (!$non_user) {
        if (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        }
    }

    // Password rules: at least 8 chars, 1 number, 1 uppercase, 1 special char (only for real users)
    if (empty($error) && !$non_user) {
        if (strlen($password_input) < 8
            || !preg_match('/[0-9]/', $password_input)
            || !preg_match('/[A-Z]/', $password_input)
            || !preg_match('/[!@#$%^&*()_+\-=[\]{};:\'"\\|,.<>\/\?]/', $password_input)
        ) {
            $error = "Password must be at least 8 characters and include at least one number, one uppercase letter, and one special character.";
        }
    }

    // Check role is one of allowed values
    $allowed_roles = ['admin','projectmanager','estimator','accounting','superintendent','foreman','mechanic','operator','laborer','developer','data_entry'];
    if (empty($error) && !in_array($role, $allowed_roles, true)) {
        $error = "Invalid role selected.";
    }

        // Verify the requested role exists in the DB ENUM to avoid silent truncation
        if (empty($error)) {
            $colRes = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
            if ($colRes) {
                $col = $colRes->fetch_assoc();
                if (isset($col['Type']) && preg_match("/^enum\\((.*)\\)$/i", $col['Type'], $m)) {
                    preg_match_all("/'([^']*)'/", $m[1], $matches);
                    $enum_vals = $matches[1] ?? [];
                    if (!in_array($role, $enum_vals, true)) {
                        $error = "Selected role is not supported by the database. Please run the migration to add this role.";
                    }
                } else {
                    $error = "Unable to verify role support in database.";
                }
            } else {
                $error = "Unable to read database schema to verify role support.";
            }
        }

    if (empty($error)) {
        if (!$non_user) {
            // Check if email already exists (use prepared statement)
            $check_sql = "SELECT id FROM users WHERE email = ? LIMIT 1";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param('s', $email_input);
            try {
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result && $check_result->num_rows > 0) {
                    $error = "Email already exists";
                }
            } catch (mysqli_sql_exception $dbex) {
                error_log('register_new.php email check DB error: ' . $dbex->getMessage());
                $error = "Database error while validating email";
            }
        }

        if (empty($error)) {
            if ($non_user) {
                // Insert a non-user employee (no email/password)
                $sql = "INSERT INTO users (name, role) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $name, $role);
            } else {
                // Insert new user with hashed password
                $hashed_password = password_hash($password_input, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssss", $name, $email_input, $hashed_password, $role);
            }

            try {
                $execOk = $stmt->execute();
            } catch (mysqli_sql_exception $dbex) {
                error_log('register_new.php insert DB error: ' . $dbex->getMessage());
                $execOk = false;
                $error = "Error registering user: database error";
            }

            if ($execOk) {
                // clear old values on success
                $old = ['name'=>'','email'=>'','role'=>''];
                if ($non_user) {
                    $success = "User registration successful.";
                } else {
                    // Send notification email to the newly created user with their credentials
                    // Use the same mail helper used by the daily bid notifications (Mailjet/PHPMailer wrapper)
                    try {
                        $to = $email_input;
                        $creator = (isset($_SESSION['name']) && $_SESSION['name']) ? $_SESSION['name'] : (isset($_SESSION['email']) ? $_SESSION['email'] : 'Admin');
                        $subject = 'Your DarkHorse account';
                        $plainPassword = $password_input;

                        $text = "Hello " . $name . "\n\n" .
                            "Your DarkHorse Login has been successfully created. You can go to https://app.darkhorsespreader.com to access your employee portal.\n\n" .
                            "email: " . $email_input . "\n" .
                            "password: " . $plainPassword . "\n\n" .
                            "Once you login to your account, please go ahead and change your password by navigating to Account Settings on the top right corner of the portal.\n\n" .
                            "Thanks,\n" . $creator;

                        $html = "<div style=\"font-family:Arial,sans-serif;color:#333;line-height:1.6;\">" .
                            "<p>Hello " . htmlspecialchars($name, ENT_QUOTES) . ",</p>" .
                            "<p>Your DarkHorse Login has been successfully created. You can go to <a href=\"https://app.darkhorsespreader.com\">app.darkhorsespreader.com</a> to access your employee portal.</p>" .
                            "<p><strong>email:</strong> " . htmlspecialchars($email_input, ENT_QUOTES) . "<br/>" .
                            "<strong>password:</strong> " . htmlspecialchars($plainPassword, ENT_QUOTES) . "</p>" .
                            "<p><strong>Once you login to your account, please go ahead and change your password by navigating to Account Settings on the top right corner of the portal.</strong></p>" .
                            "<p>Thanks,<br/>" . htmlspecialchars($creator, ENT_QUOTES) . "</p>" .
                            "</div>";

                        // Load mailer helper (reuse the same candidate list as the cron script)
                        $mailerCandidates = [
                            __DIR__ . '/../auth/mailjet_helper.php',
                            __DIR__ . '/../partials/mailer.php',
                            __DIR__ . '/../partials/mailer_helper.php',
                            __DIR__ . '/../config/email_config.php'
                        ];
                        foreach ($mailerCandidates as $cand) {
                            if (file_exists($cand)) { require_once $cand; break; }
                        }

                        // Normalize to sendMail($to,$subject,$text,$html) if possible
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

                        if ($mailOk) {
                            $success = "User registration successful.<br/>An email with login credentials has been sent to the user";
                        } else {
                            $success = "User registration successful.<br/>Email delivery failed — user created but notification email was not sent.";
                        }
                    } catch (Throwable $ex) {
                        $success = "User registration successful.<br/>User created; email not sent.";
                    }
                }
            } else {
                $error = "Error registering user: " . $conn->error;
            }
        }
        if (isset($check_stmt) && $check_stmt) $check_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New User</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/admin-layout.css">
    <link rel="stylesheet" href="../assets/css/register-user.css">
    
</head>
<body class="admin-page">
    <div class="admin-container">
    <?php include __DIR__ . '/../partials/portalheader.php'; ?>
    
  

        <div class="admin-layout">
            <?php include __DIR__ . '/../partials/sidebar.php'; ?>

            <main class="content-area">
                <div class="register-container">
                    <h1>Register New User</h1>
                    <div class="non-user-top" style="margin:18px 0 6px 0;padding:12px 14px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:8px;text-align:center;font-weight:600;">
                        <label style="cursor:pointer;"><input type="checkbox" id="nonUserTopCheckbox" <?php echo (!empty($old['non_user']) || (isset($_POST['non_user_employee']) && $_POST['non_user_employee'])) ? 'checked' : ''; ?> style="margin-right:8px;transform:scale(1.05);"> Non-user employee (no login)</label>
                        <div class="hint" style="font-weight:400;margin-top:6px;">If checked, no email or password is required; only name and role will be saved.</div>
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="success" id="successMsg"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" id="registerForm" novalidate>
                        <div class="form-group">
                            <label for="name">Name:</label>
                            <input type="text" id="name" name="name" required value="<?php echo $old['name'] ?? ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="email">Email:</label>
                            <div class="email-fields">
                                <input type="email" id="email" name="email" required value="<?php echo $old['email'] ?? ''; ?>">
                                <small class="hint">We'll email the user their credentials</small>
                            </div>
                        </div>

                        <input type="hidden" id="nonUserHidden" name="non_user_employee" value="<?php echo (!empty($old['non_user']) ? '1' : '0'); ?>">

                        <div id="passwordSection" class="form-group" style="position:relative;">
                            <label>Password:</label>
                            <input type="hidden" id="passwordHidden" name="password" />
                            <button type="button" id="generatePasswordBtn" class="add-user-btn" style="margin-bottom:6px;">Auto Generate Password</button>
                            <div id="passwordGeneratedMsg" style="display:none;color:#059669;font-weight:600;font-size:13px;margin-bottom:8px;">Password generated</div>
                            <div id="generatedPasswordWrap" style="display:none; background:#f8fafc; border:1px solid #e2e8f0; padding:12px 14px; border-radius:8px; font-family:monospace; font-size:15px; position:relative;">
                                <span id="generatedPassword" style="word-break:break-all;"></span>
                                <button type="button" id="copyPasswordBtn" style="position:absolute; top:8px; right:8px; background:#ffffff; border:1px solid #cbd5e1; padding:4px 8px; font-size:12px; border-radius:6px; cursor:pointer;">Copy</button>
                            </div>
                            <small class="hint">Password will be generated: ≥8 chars, includes uppercase, number, and special character</small>
                        </div>

                        <div class="form-group">
                            <label for="role">Role:</label>
                            <select id="role" name="role" required>
                                <option value="" disabled selected>Select role</option>
                                <?php
                                    $roles = ['admin'=>'Admin','projectmanager'=>'Project Manager','estimator'=>'Estimator','accounting'=>'Accounting','superintendent'=>'Superintendent','foreman'=>'Foreman','mechanic'=>'Mechanic','operator'=>'Operator','laborer'=>'Laborer','developer'=>'Developer','data_entry'=>'Data Entry'];
                                    $selectedRole = $old['role'] ?? '';
                                    foreach ($roles as $k => $label) {
                                        $sel = ($k === $selectedRole) ? 'selected' : '';
                                        echo "<option value=\"$k\" $sel>$label</option>";
                                    }
                                ?>
                            </select>
                        </div>

                        <button type="submit" id="submitBtn" class="add-user-btn">Register User</button>
                    </form>
                </div>
            </main>
        </div>
    </div>
    <script>
    (function(){
        // Toggle users sub-nav
        var usersToggle = document.getElementById('usersToggle');
        var usersGroup = document.getElementById('usersGroup');
        if (usersToggle && usersGroup) {
            usersToggle.addEventListener('click', function(){
                usersGroup.classList.toggle('open');
            });
        }

        // Client-side validation and password toggle keep working below
        var form = document.getElementById('registerForm');
        var email = document.getElementById('email');
        var nonUserCheckbox = document.getElementById('nonUserTopCheckbox');
        var nonUserHidden = document.getElementById('nonUserHidden');
        var passwordSection = document.getElementById('passwordSection');
    var passwordHidden = document.getElementById('passwordHidden');
    var generateBtn = document.getElementById('generatePasswordBtn');
    var generatedWrap = document.getElementById('generatedPasswordWrap');
    var generatedSpan = document.getElementById('generatedPassword');
    var copyBtn = document.getElementById('copyPasswordBtn');
    var generatedMsg = document.getElementById('passwordGeneratedMsg');
    var isGenerating = false; // guard against rapid double-clicks
        var successMsg = document.getElementById('successMsg');

        if (successMsg) {
            successMsg.style.fontSize = '1.5rem';
            successMsg.style.fontWeight = '700';
        }

        // initialize visibility based on checkbox
        var heading = document.querySelector('.register-container h1');
        var submitBtn = document.getElementById('submitBtn');
        function updateVisibility() {
            var checked = nonUserCheckbox && nonUserCheckbox.checked;
            var emailGroup = email ? email.closest('.form-group') : null;
            var emailFields = document.querySelector('.email-fields');
            if (checked) {
                if (emailGroup) emailGroup.style.display = 'none';
                if (emailFields) emailFields.style.display = 'none';
                if (passwordSection) passwordSection.style.display = 'none';
                if (heading) heading.textContent = 'Register New Employee';
                if (submitBtn) submitBtn.textContent = 'Register Employee';
                if (nonUserHidden) nonUserHidden.value = '1';
            } else {
                if (emailGroup) emailGroup.style.display = '';
                if (emailFields) emailFields.style.display = '';
                if (passwordSection) passwordSection.style.display = '';
                if (heading) heading.textContent = 'Register New User';
                if (submitBtn) submitBtn.textContent = 'Register User';
                if (nonUserHidden) nonUserHidden.value = '0';
            }
        }
        if (nonUserCheckbox) {
            nonUserCheckbox.addEventListener('change', function(){
                updateVisibility();
            });
            updateVisibility();
        }

        form.addEventListener('submit', function(e){
            var errors = [];
            var emailVal = email ? email.value.trim() : '';
            var pwdVal = passwordHidden.value;
            var nonUser = nonUserCheckbox && nonUserCheckbox.checked;

            if (!nonUser) {
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
                    errors.push('Please enter a valid email address.');
                }

                if (!pwdVal) {
                    errors.push('Please generate a password before registering.');
                } else if (pwdVal.length < 8 || !/[0-9]/.test(pwdVal) || !/[A-Z]/.test(pwdVal) || !/[!@#$%^&*()_+\-=[\]{};:'"\\|,.<>\/\?]/.test(pwdVal)) {
                    errors.push('Generated password does not meet requirements. Please regenerate.');
                }
            }

            if (errors.length) {
                e.preventDefault();
                var existing = document.querySelector('.error');
                if (existing) existing.remove();
                var div = document.createElement('div');
                div.className = 'error';
                div.textContent = errors[0];
                form.parentNode.insertBefore(div, form);
                window.scrollTo({top: 0, behavior: 'smooth'});
            }
        });

        function generatePassword() {
            const length = 12; // stronger default length
            const upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            const lower = 'abcdefghijklmnopqrstuvwxyz';
            const nums = '0123456789';
            const specials = '!@#$%^&*()_+-={}[]|:;<>,.?/';
            const all = upper + lower + nums + specials;
            let pwd = '';
            // Ensure at least one of each required set
            pwd += upper[Math.floor(Math.random()*upper.length)];
            pwd += nums[Math.floor(Math.random()*nums.length)];
            pwd += specials[Math.floor(Math.random()*specials.length)];
            // Fill remaining
            for (let i = pwd.length; i < length; i++) {
                pwd += all[Math.floor(Math.random()*all.length)];
            }
            // Shuffle characters
            pwd = pwd.split('').sort(()=>Math.random()-0.5).join('');
            return pwd;
        }

        generateBtn.addEventListener('click', function(){
            if (isGenerating) return;
            isGenerating = true;
            const newPwd = generatePassword();
            passwordHidden.value = newPwd;
            generatedSpan.textContent = newPwd;
            generatedWrap.style.display = 'block';
            // Disable button (keep original label) and show confirmation text
            generateBtn.disabled = true; // CSS :disabled handles styling globally
            generateBtn.setAttribute('disabled', 'disabled'); // ensure attribute present in DOM
            generateBtn.classList.add('btn-disabled'); // extra class as safety for styling
            if (generatedMsg) generatedMsg.style.display = 'block';
        });

        copyBtn.addEventListener('click', function(){
            const text = generatedSpan.textContent;
            if (!text) return;
            navigator.clipboard.writeText(text).then(function(){
                copyBtn.textContent = 'Copied';
                setTimeout(()=>{ copyBtn.textContent = 'Copy'; }, 1800);
            }).catch(function(){
                copyBtn.textContent = 'Failed';
                setTimeout(()=>{ copyBtn.textContent = 'Copy'; }, 1800);
            });
        });
    })();
    </script>
</body>
</html>
