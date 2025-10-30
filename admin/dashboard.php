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
    <title>Admin Dashboard</title>
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
                    <div class="tiles">
                    <!-- Merged all tiles into a single container -->
                    <a href="../pages/equipments.php" class="tile">
                        <h2>Equipments</h2>
                    </a>
                    <a href="Bid_tracking.php" class="tile">
                        <h2>Bid Tracking</h2>
                    </a>
                    <a href="scheduling.php" class="tile">
                        <h2>Scheduling</h2>
                    </a>
                    <a href="engineering.php" class="tile">
                        <h2>Engineering</h2>
                    </a>

                    <a href="employee_information.php" class="tile">
                        <h2>Employee Information</h2>
                    </a>
                    <a href="for_sale.php" class="tile">
                        <h2>For Sale</h2>
                    </a>
                    <a href="project_checklist.php" class="tile">
                        <h2>Project Checklist</h2>
                    </a>
                    <a href="pictures.php" class="tile">
                        <h2>Pictures</h2>
                    </a>
                    <a href="forms.php" class="tile">
                        <h2>Forms</h2>
                    </a>
                    <a href="manuals.php" class="tile">
                        <h2>Manuals</h2>
                    </a>
                    <a href="videos.php" class="tile">
                        <h2>Videos</h2>
                    </a>
                    <a href="maps.php" class="tile">
                        <h2>Maps</h2>
                    </a>
                </div>
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
