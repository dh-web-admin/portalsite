<?php
// Diagnostic-friendly output
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', '1');
// Start session for optional admin auth check
if (session_status() === PHP_SESSION_NONE) session_start();
// Avoid mysqli throwing exceptions that kill the script; we'll handle errors manually
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

echo "create_admin start\n";

// Ensure mysqli is available early to avoid fatal white-screen
if (!function_exists('mysqli_connect') || !class_exists('mysqli')) {
    http_response_code(500);
    echo "FATAL: mysqli extension not loaded.\n";
    echo "Hint: set PHP_EXTENSIONS='mysqli pdo_mysql' on the web service and redeploy.\n";
    exit;
}

// Include config and validate connection
require_once __DIR__ . '/../config/config.php';

// // ----- Access control -----
// // Allow if current user is logged in and is an admin
// $allow = false;
// if (isset($_SESSION['email'])) {
//     $check = $conn->prepare("SELECT role FROM users WHERE email = ? LIMIT 1");
//     if ($check) {
//         $check->bind_param('s', $_SESSION['email']);
//         $check->execute();
//         $r = $check->get_result();
//         $u = $r ? $r->fetch_assoc() : null;
//         if ($u && isset($u['role']) && in_array($u['role'], ['admin','developer'])) $allow = true;
//         $check->close();
//     }
// }

// // If not logged-in admin, allow only from localhost + matching secret token
// if (!$allow) {
//     $remote = $_SERVER['REMOTE_ADDR'] ?? '';
//     $isLocal = in_array($remote, ['127.0.0.1','::1','localhost'], true);
//     $secret = getenv('CREATE_ADMIN_SECRET') ?: null;
//     $provided = $_GET['token'] ?? $_POST['token'] ?? $_SERVER['HTTP_X_CREATE_ADMIN_TOKEN'] ?? null;

//     if ($isLocal && $secret && $provided && hash_equals((string)$secret, (string)$provided)) {
//         $allow = true;
//     } else {
//         echo "Unauthorized: create_admin is restricted.\n";
//         echo "Requirements: either be logged in as an admin, or run from localhost with a valid token.\n";
//         if (!$isLocal) echo "Note: your IP ($remote) is not localhost.\n";
//         if (!$secret) echo "Note: server has no CREATE_ADMIN_SECRET configured — set an env var to enable localhost+token access.\n";
//         exit;
//     }
// }
// // ----- end access control -----

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo "FATAL: DB connection (\$conn) is not initialized.\n";
    exit;
}

echo "DB connected. Host: " . ($conn->host_info ?? 'n/a') . "\n";

// Ensure the users table exists (idempotent)
$createSql = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
SQL;

echo "Ensuring users table...\n";
if (!$conn->query($createSql)) {
    http_response_code(500);
    echo "Failed to ensure users table exists: " . $conn->error . "\n";
    exit;
}

// Repair schema if table exists but id is not AUTO_INCREMENT/PRIMARY KEY
echo "Verifying users.id is AUTO_INCREMENT PRIMARY KEY...\n";

// Check if id has AUTO_INCREMENT
$autoSql = "SELECT LOWER(EXTRA) AS extra FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'id'";
$autoRes = $conn->query($autoSql);
$hasAuto = false;
if ($autoRes && ($row = $autoRes->fetch_assoc())) {
    $hasAuto = (strpos($row['extra'] ?? '', 'auto_increment') !== false);
}

// Check if a PRIMARY KEY exists and which columns it covers
$pkSql = "SELECT GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION) AS pk_cols\n          FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS t\n          JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE k\n            ON t.CONSTRAINT_NAME = k.CONSTRAINT_NAME\n           AND t.TABLE_SCHEMA = k.TABLE_SCHEMA\n           AND t.TABLE_NAME = k.TABLE_NAME\n         WHERE t.TABLE_SCHEMA = DATABASE()\n           AND t.TABLE_NAME = 'users'\n           AND t.CONSTRAINT_TYPE = 'PRIMARY KEY'";
$pkRes = $conn->query($pkSql);
$pkCols = '';
if ($pkRes && ($row = $pkRes->fetch_assoc())) {
    $pkCols = (string)($row['pk_cols'] ?? '');
}

// If PK exists but not on id, drop it first (fresh schema safety)
if ($pkCols !== '' && strtolower(trim($pkCols)) !== 'id') {
    echo "Primary key exists on ($pkCols); switching to id...\n";
    if (!$conn->query("ALTER TABLE users DROP PRIMARY KEY")) {
        echo "Note: DROP PRIMARY KEY failed: " . $conn->error . "\n";
    }
    $pkCols = '';
}

// Ensure id is NOT NULL
if (!$conn->query("ALTER TABLE users MODIFY COLUMN id INT NOT NULL")) {
    echo "Note: MODIFY id NOT NULL may have failed: " . $conn->error . "\n";
}

// Ensure PK on id if missing
if ($pkCols === '') {
    if (!$conn->query("ALTER TABLE users ADD PRIMARY KEY (id)")) {
        echo "Note: ADD PRIMARY KEY (id) failed or already exists: " . $conn->error . "\n";
    }
}

// Ensure AUTO_INCREMENT on id
if (!$hasAuto) {
    if (!$conn->query("ALTER TABLE users MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT")) {
        echo "Note: ADD AUTO_INCREMENT failed: " . $conn->error . "\n";
    }
}

echo "Clearing existing admin (if any)...\n";
if (!$conn->query("DELETE FROM users WHERE email='admin'")) {
    http_response_code(500);
    echo "Failed to delete existing admin: " . $conn->error . "\n";
    exit;
}

// Create password hash
$password = 'admin';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
if ($hashed_password === false) {
    http_response_code(500);
    echo "Failed to generate password hash.\n";
    exit;
}

// Insert new admin user
$name = 'admin';
$email = 'admin';
$role = 'admin';

echo "Inserting admin...\n";
// First try normal insert (works when id is AUTO_INCREMENT)
$sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo "Failed to prepare statement: " . $conn->error . "\n";
    exit;
}
$stmt->bind_param("ssss", $name, $email, $hashed_password, $role);

if ($stmt->execute()) {
    echo "Admin user created successfully!\n";
    echo "Email: admin\n";
    echo "Password: admin\n";
    echo "Generated hash: " . $hashed_password . "\n";
} else {
    $err = $stmt->error;
    $errno = $stmt->errno;
    echo "Primary insert failed (errno=$errno): $err\n";
    // If failure due to id missing default (strict mode, no AUTO_INCREMENT), compute next id and insert explicitly
    if ($errno === 1364 || stripos($err, "doesn't have a default value") !== false) {
        echo "Falling back to explicit id insert...\n";
        $nextId = 1;
        $rid = $conn->query("SELECT COALESCE(MAX(id)+1,1) AS next_id FROM users");
        if ($rid && ($r = $rid->fetch_assoc())) {
            $nextId = (int)$r['next_id'];
        }
        $rid && $rid->free();
        $stmt->close();
        $sql2 = "INSERT INTO users (id, name, email, password, role) VALUES (?, ?, ?, ?, ?)";
        $stmt2 = $conn->prepare($sql2);
        if (!$stmt2) {
            http_response_code(500);
            echo "Failed to prepare fallback statement: " . $conn->error . "\n";
            exit;
        }
        $stmt2->bind_param("issss", $nextId, $name, $email, $hashed_password, $role);
        if ($stmt2->execute()) {
            echo "Admin user created successfully (explicit id=$nextId)!\n";
            echo "Email: admin\n";
            echo "Password: admin\n";
            echo "Generated hash: " . $hashed_password . "\n";
        } else {
            http_response_code(500);
            echo "Fallback insert failed: " . $stmt2->error . "\n";
            $stmt2->close();
            $conn->close();
            exit;
        }
        $stmt2->close();
    } else {
        http_response_code(500);
        echo "Error creating admin user: $err\n";
        $stmt->close();
        $conn->close();
        exit;
    }
}

// Safely close statement if it still exists (fallback path may have closed it already)
if (isset($stmt) && $stmt) {
    @ $stmt->close();
}
$conn->close();

echo "Done.\n";
?>
