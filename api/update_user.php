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

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Invalid user id']);
    exit();
}

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

    echo json_encode(['success'=>true,'message'=>'User updated','affected_rows'=>$affected,'role'=> $dbRole]);
} else {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Update failed: '.$conn->error]);
}
$update->close();
$conn->close();
?>
