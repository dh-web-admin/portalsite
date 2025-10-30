<?php
require_once __DIR__ . '/../session_init.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Include database configuration
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipments</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/admin-layout.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="admin-page">
    <div class="admin-container">
        <?php include __DIR__ . '/../partials/portalheader.php'; ?>
        <div class="admin-layout">
            <?php include __DIR__ . '/../partials/admin_sidebar.php'; ?>
            <main class="content-area">
                <div class="main-content">
                    <h1>Equipments</h1>
                    <!-- Equipment content will go here -->
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
    })();
    </script>
</body>
</html>
