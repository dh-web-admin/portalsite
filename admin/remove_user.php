<?php
require_once __DIR__ . '/../session_init.php';
require_once '../config/config.php';

// Must be logged in
if (!isset($_SESSION['email'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Verify user is admin
$adminEmail = $_SESSION['email'];
$stmt = $conn->prepare("SELECT role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $adminEmail);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    header('Location: ../auth/login.php');
    exit();
}
$row = $res->fetch_assoc();
if ($row['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $error = 'Email is required';
    } elseif (!preg_match('/^[A-Za-z0-9._%+-]+@darkhorsespreader\.com$/i', $email)) {
        $error = 'Email must be a valid @darkhorsespreader.com address';
    } elseif ($email === $adminEmail) {
        $error = 'You cannot delete your own account';
    } else {
        $del = $conn->prepare("DELETE FROM users WHERE email = ? LIMIT 1");
        $del->bind_param('s', $email);
        if ($del->execute()) {
            if ($del->affected_rows > 0) {
                $message = "User with email $email has been removed.";
            } else {
                $error = "No user found with email $email.";
            }
        } else {
            $error = 'Error executing delete: ' . $conn->error;
        }
        $del->close();
    }
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Remove User</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/admin-layout.css">
    <link rel="stylesheet" href="../assets/css/remove-user.css">
</head>
<body class="admin-page">
    <div class="admin-container">
    <?php include __DIR__ . '/../partials/portalheader.php'; ?>

        <div class="admin-layout">
            <?php include __DIR__ . '/../partials/admin_sidebar.php'; ?>

            <main class="content-area">
                <div class="remove-container">
                    <a href="../pages/dashboard.php" class="back-btn">Back to Dashboard</a>
                    <h1>Remove User</h1>

                    <?php if ($error): ?>
                        <div class="error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($message): ?>
                        <div class="message"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" id="removeForm">
                        <div class="form-group">
                            <label for="email">User Email</label>
                            <input type="email" id="email" name="email" required placeholder="user@darkhorsespreader.com">
                            <small style="display:block; margin-top:4px; color:#666; font-size:12px;">Must be a @darkhorsespreader.com address</small>
                        </div>
                        <button type="submit" class="add-user-btn">Remove User</button>
                    </form>
                </div>
            </main>
        </div>
    </div>
    <script>
    (function(){
        var usersToggle = document.getElementById('usersToggle');
        var usersGroup = document.getElementById('usersGroup');
        if (usersToggle && usersGroup) {
            usersToggle.addEventListener('click', function(){
                usersGroup.classList.toggle('open');
            });
        }

        // Client-side email validation
        var form = document.getElementById('removeForm');
        var emailInput = document.getElementById('email');
        
        form.addEventListener('submit', function(e){
            var emailVal = emailInput.value.trim();
            if (!/^[A-Za-z0-9._%+-]+@darkhorsespreader\.com$/i.test(emailVal)) {
                e.preventDefault();
                alert('Email must be a valid @darkhorsespreader.com address.');
                emailInput.focus();
            }
        });
    })();
    </script>
</body>
</html>
