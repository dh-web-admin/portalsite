<?php
require_once __DIR__ . '/auth_check.php'; // includes config and developer-only guard

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

// Prevent running on production
if (!empty($isProduction)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not allowed in production']);
    exit;
}

header('Content-Type: application/json');

// Helper: detect is_dummy column
function hasIsDummyColumn($conn) {
    $res = $conn->query("SHOW COLUMNS FROM users LIKE 'is_dummy'");
    return ($res && $res->num_rows > 0);
}

$seedUsers = [
    ['Developer Tester', 'developer+dev@example.test', 'developer'],
    ['Foreman Tester', 'foreman+dev@example.test', 'foreman'],
    ['Operator Tester', 'operator+dev@example.test', 'operator'],
    ['Laborer Tester', 'laborer+dev@example.test', 'laborer'],
];

$plainPassword = 'Test12345';
$hash = password_hash($plainPassword, PASSWORD_DEFAULT);

$created = [];
$skipped = [];

$useIsDummy = hasIsDummyColumn($conn);

foreach ($seedUsers as $u) {
    [$name, $email, $role] = $u;

    // skip if email exists
    $check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $check->bind_param('s', $email);
    $check->execute();
    $r = $check->get_result();
    if ($r && $r->num_rows > 0) {
        $skipped[] = $email;
        $check->close();
        continue;
    }
    $check->close();

    if ($useIsDummy) {
        $stmt = $conn->prepare('INSERT INTO users (name,email,password,role,is_dummy,created_at,updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())');
        $stmt->bind_param('ssss', $name, $email, $hash, $role);
    } else {
        $stmt = $conn->prepare('INSERT INTO users (name,email,password,role,created_at,updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
        $stmt->bind_param('ssss', $name, $email, $hash, $role);
    }

    if ($stmt && $stmt->execute()) {
        $created[] = $email;
    } else {
        $skipped[] = $email;
    }
    if ($stmt) $stmt->close();
}

echo json_encode(['success' => true, 'created' => $created, 'skipped' => $skipped]);
