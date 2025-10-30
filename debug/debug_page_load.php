<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../session_init.php';

echo "Step 1: Session loaded<br>";
echo "Session ID: " . session_id() . "<br>";
echo "Session email: " . (isset($_SESSION['email']) ? $_SESSION['email'] : 'NOT SET') . "<br>";
echo "Session name: " . (isset($_SESSION['name']) ? $_SESSION['name'] : 'NOT SET') . "<br><br>";

// Check if user is logged in and is admin
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    echo "REDIRECT: Not logged in, redirecting to login page...<br>";
    // header("Location: ../auth/login.php");
    // exit();
}

echo "Step 2: User is logged in<br><br>";

// Include database configuration
try {
    require_once __DIR__ . '/../config/config.php';
    echo "Step 3: Config loaded successfully<br>";
    // Avoid deprecated ping() usage; assume connected if $conn is mysqli and no connect_error
    $connected = (isset($conn) && $conn instanceof mysqli && !$conn->connect_error);
    echo "Database connected: " . ($connected ? "YES" : "NO") . "<br><br>";
} catch (Exception $e) {
    echo "Step 3 ERROR: " . $e->getMessage() . "<br>";
    die();
}

// Get admin information
$email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
echo "Step 4: Querying for user: " . htmlspecialchars($email) . "<br>";

$query = "SELECT role FROM users WHERE email='$email'";
$result = $conn->query($query);

if (!$result) {
    echo "Query failed: " . $conn->error . "<br>";
    die();
}

echo "Query rows: " . $result->num_rows . "<br>";

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "User role: " . $user['role'] . "<br><br>";
    
    // Verify user is admin
    if ($user['role'] !== 'admin') {
        echo "REDIRECT: User is not admin, redirecting...<br>";
        // header("Location: ../auth/login.php");
        // exit();
    } else {
        echo "Step 5: User is admin - Page would load normally<br>";
    }
} else {
    echo "ERROR: No user found with email: $email<br>";
}
?>
