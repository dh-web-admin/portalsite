<?php
require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

if (!empty($isProduction)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not allowed in production']);
    exit;
}

header('Content-Type: application/json');

$role = trim($_POST['role'] ?? 'laborer');
if ($role === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'role required']);
    exit;
}

// sanitize role to safe chars
$roleSafe = preg_replace('/[^a-z0-9_-]/i', '', strtolower($role));

// Disallow creation of admin accounts via dummy generator
if ($roleSafe === 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'creating admin accounts via this tool is not allowed']);
    exit;
}

// fixed dev password per request
$plain = 'Test12345';
$hash = password_hash($plain, PASSWORD_DEFAULT);

// generate base names
$basePrimary = 'dummy-' . $roleSafe; // e.g. dummy-laborer
$primaryEmail = $basePrimary . '@dummy.com';

// check for existing and if needed generate alternate like role-dummy-1
function emailExists($conn, $email) {
    $chk = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $chk->bind_param('s', $email);
    $chk->execute();
    $r = $chk->get_result();
    $exists = ($r && $r->num_rows > 0);
    $chk->close();
    return $exists;
}

$nameToUse = $basePrimary;
$emailToUse = $primaryEmail;
$attempt = 0;
while (emailExists($conn, $emailToUse)) {
    $attempt++;
    $nameToUse = $roleSafe . '-dummy-' . $attempt; // e.g. laborer-dummy-1
    $emailToUse = $nameToUse . '@dummy.com';
    if ($attempt > 200) break; // safety
}

// detect availability of columns and build INSERT accordingly
$hasDummy = ($conn->query("SHOW COLUMNS FROM users LIKE 'is_dummy'") && $conn->query("SHOW COLUMNS FROM users LIKE 'is_dummy'")->num_rows > 0);
$hasCreated = ($conn->query("SHOW COLUMNS FROM users LIKE 'created_at'") && $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'")->num_rows > 0) || ($conn->query("SHOW COLUMNS FROM users LIKE 'created'") && $conn->query("SHOW COLUMNS FROM users LIKE 'created'")->num_rows > 0);

if ($hasDummy && $hasCreated) {
    $stmt = $conn->prepare('INSERT INTO users (name,email,password,role,is_dummy,created_at,updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())');
    $stmt->bind_param('ssss', $nameToUse, $emailToUse, $hash, $role);
} elseif ($hasCreated) {
    $stmt = $conn->prepare('INSERT INTO users (name,email,password,role,created_at,updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
    $stmt->bind_param('ssss', $nameToUse, $emailToUse, $hash, $role);
} elseif ($hasDummy) {
    $stmt = $conn->prepare('INSERT INTO users (name,email,password,role,is_dummy) VALUES (?, ?, ?, ?, 1)');
    $stmt->bind_param('ssss', $nameToUse, $emailToUse, $hash, $role);
} else {
    $stmt = $conn->prepare('INSERT INTO users (name,email,password,role) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $nameToUse, $emailToUse, $hash, $role);
}

if ($stmt && $stmt->execute()) {
    echo json_encode(['success' => true, 'name' => $nameToUse, 'email' => $emailToUse, 'role' => $role]);
} else {
    http_response_code(500);
    $errorMsg = $stmt ? $stmt->error : 'prepare failed';
    // return any DB error in JSON (avoid HTML output)
    echo json_encode(['success' => false, 'error' => $errorMsg]);
}
