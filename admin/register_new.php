<?php
require_once __DIR__ . '/../session_init.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/config.php';

// Get admin information
$email = $_SESSION['email'];
$query = "SELECT role FROM users WHERE email='$email'";
$result = $conn->query($query);
$user = $result->fetch_assoc();

// Verify user is admin
if ($user['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trim and sanitize inputs
    $name = trim($_POST['name'] ?? '');
    $email_input = trim($_POST['email'] ?? '');
    $password_input = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // Preserve values for redisplay on error
    $old = [
        'name' => htmlspecialchars($name, ENT_QUOTES),
        'email' => htmlspecialchars($email_input, ENT_QUOTES),
        'role' => htmlspecialchars($role, ENT_QUOTES)
    ];

    // Server-side validation
    // Email must end with @darkhorsespreader.com
    if (!preg_match('/^[A-Za-z0-9._%+-]+@darkhorsespreader\.com$/i', $email_input)) {
        $error = "Email must be a valid @darkhorsespreader.com address.";
    }

    // Password rules: at least 8 chars, 1 number, 1 uppercase, 1 special char
    if (empty($error)) {
        if (strlen($password_input) < 8
            || !preg_match('/[0-9]/', $password_input)
            || !preg_match('/[A-Z]/', $password_input)
            || !preg_match('/[!@#$%^&*()_+\-=[\]{};:\'"\\|,.<>\/\?]/', $password_input)
        ) {
            $error = "Password must be at least 8 characters and include at least one number, one uppercase letter, and one special character.";
        }
    }

    // Check role is one of allowed values
    $allowed_roles = ['admin','projectmanager','estimator','accounting','superintendent','foreman','mechanic','operator','laborer','developer'];
    if (empty($error) && !in_array($role, $allowed_roles, true)) {
        $error = "Invalid role selected.";
    }

    if (empty($error)) {
        // Check if email already exists (use prepared statement)
        $check_sql = "SELECT id FROM users WHERE email = ? LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('s', $email_input);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result && $check_result->num_rows > 0) {
            $error = "Email already exists";
        } else {
            // Insert new user with hashed password
            $hashed_password = password_hash($password_input, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $name, $email_input, $hashed_password, $role);
            
            if ($stmt->execute()) {
                $success = "User registered successfully";
                // clear old values on success
                $old = ['name'=>'','email'=>'','role'=>''];
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
                    <a href="../pages/dashboard/" class="back-btn">Back to Dashboard</a>
                    <h1>Register New User</h1>
                    
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
                            <input type="email" id="email" name="email" required value="<?php echo $old['email'] ?? ''; ?>">
                            <small class="hint">Must be a @darkhorsespreader.com address</small>
                        </div>

                        <div class="form-group" style="position:relative;">
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
                                    $roles = ['admin'=>'Admin','projectmanager'=>'Project Manager','estimator'=>'Estimator','accounting'=>'Accounting','superintendent'=>'Superintendent','foreman'=>'Foreman','mechanic'=>'Mechanic','operator'=>'Operator','laborer'=>'Laborer','developer'=>'Developer'];
                                    $selectedRole = $old['role'] ?? '';
                                    foreach ($roles as $k => $label) {
                                        $sel = ($k === $selectedRole) ? 'selected' : '';
                                        echo "<option value=\"$k\" $sel>$label</option>";
                                    }
                                ?>
                            </select>
                        </div>

                        <button type="submit" class="add-user-btn">Register User</button>
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

        form.addEventListener('submit', function(e){
            var errors = [];
            var emailVal = email.value.trim();
            var pwdVal = passwordHidden.value;

            if (!/^[A-Za-z0-9._%+-]+@darkhorsespreader\.com$/i.test(emailVal)) {
                errors.push('Email must be a valid @darkhorsespreader.com address.');
            }

            if (!pwdVal) {
                errors.push('Please generate a password before registering.');
            } else if (pwdVal.length < 8 || !/[0-9]/.test(pwdVal) || !/[A-Z]/.test(pwdVal) || !/[!@#$%^&*()_+\-=[\]{};:'"\\|,.<>\/\?]/.test(pwdVal)) {
                errors.push('Generated password does not meet requirements. Please regenerate.');
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
