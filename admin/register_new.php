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
    $allowed_roles = ['admin','projectmanager','estimator','accounting','superintendent','foreman','mechanic','operator','laborer'];
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
                    <a href="../pages/dashboard.php" class="back-btn">Back to Dashboard</a>
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
                            <label for="password">Password:</label>
                            <input type="password" id="password" name="password" required style="padding-right:40px;">
                            <button type="button" id="togglePassword" aria-label="Show password" title="Show password" style="position:absolute; right:8px; top:34px; background:none; border:none; cursor:pointer; padding:4px; font-weight:600;">
                                <span id="toggleIcon">Show</span>
                            </button>
                            <small class="hint">At least 8 chars, 1 number, 1 uppercase, 1 special character</small>
                        </div>

                        <div class="form-group">
                            <label for="role">Role:</label>
                            <select id="role" name="role" required>
                                <option value="" disabled selected>Select role</option>
                                <?php
                                    $roles = ['admin'=>'Admin','projectmanager'=>'Project Manager','estimator'=>'Estimator','accounting'=>'Accounting','superintendent'=>'Superintendent','foreman'=>'Foreman','mechanic'=>'Mechanic','operator'=>'Operator','laborer'=>'Laborer'];
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
        var password = document.getElementById('password');
        var successMsg = document.getElementById('successMsg');

        if (successMsg) {
            successMsg.style.fontSize = '1.5rem';
            successMsg.style.fontWeight = '700';
        }

        form.addEventListener('submit', function(e){
            var errors = [];
            var emailVal = email.value.trim();
            var pwdVal = password.value;

            if (!/^[A-Za-z0-9._%+-]+@darkhorsespreader\.com$/i.test(emailVal)) {
                errors.push('Email must be a valid @darkhorsespreader.com address.');
            }

            if (pwdVal.length < 8 || !/[0-9]/.test(pwdVal) || !/[A-Z]/.test(pwdVal) || !/[!@#$%^&*()_+\-=[\]{};:\'"\\|,.<>\/\?]/.test(pwdVal)) {
                errors.push('Password must be at least 8 characters and include at least one number, one uppercase letter, and one special character.');
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

        var toggle = document.getElementById('togglePassword');
        var toggleIcon = document.getElementById('toggleIcon');
        toggle.addEventListener('click', function(){
            if (password.type === 'password') {
                password.type = 'text';
                toggleIcon.textContent = 'Hide';
                toggle.setAttribute('aria-label','Hide password');
                toggle.title = 'Hide password';
            } else {
                password.type = 'password';
                toggleIcon.textContent = 'Show';
                toggle.setAttribute('aria-label','Show password');
                toggle.title = 'Show password';
            }
            password.focus();
        });
    })();
    </script>
</body>
</html>
