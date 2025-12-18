<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

// Require login
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Resolve role
$role = 'laborer';
$email = $_SESSION['email'];
if ($stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1')) {
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $role = $user && isset($user['role']) ? $user['role'] : 'laborer';
    $stmt->close();
}

if (!can_access($role, 'equipments')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$equipment_number = isset($_POST['equipment_number']) ? trim($_POST['equipment_number']) : '';
$type = isset($_POST['type']) ? trim($_POST['type']) : '';
$operating_condition = isset($_POST['operating_condition']) ? trim($_POST['operating_condition']) : '';
$location = isset($_POST['location']) ? trim($_POST['location']) : '';
$current_hours_raw = isset($_POST['current_hours']) ? trim($_POST['current_hours']) : '';
$oil_status = isset($_POST['oil_status']) ? trim($_POST['oil_status']) : '';
$air_filters = isset($_POST['air_filters']) ? trim($_POST['air_filters']) : '';
$warranty = isset($_POST['warranty']) ? trim($_POST['warranty']) : '';
$tires = isset($_POST['tires']) ? trim($_POST['tires']) : '';

if ($equipment_number === '' || $type === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Equipment number and type are required']);
    exit();
}

$current_hours = 0.0;
if ($current_hours_raw !== '') {
    if (!is_numeric($current_hours_raw) || floatval($current_hours_raw) < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current hours must be a non-negative number']);
        exit();
    }
    $current_hours = floatval($current_hours_raw);
}

$warrantyParam = null;
if ($warranty !== '') {
    // Expect YYYY-MM-DD
    $ts = strtotime($warranty);
    if ($ts === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Warranty date is invalid']);
        exit();
    }
    $warrantyParam = date('Y-m-d', $ts);
}

$stmt = $conn->prepare('INSERT INTO equipments (equipment_number, type, operating_condition, location, current_hours, oil_status, air_filters, warranty, tires) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database prepare failed']);
    exit();
}

// bind warranty as string (or null)
$stmt->bind_param(
    'ssssdssss',
    $equipment_number,
    $type,
    $operating_condition,
    $location,
    $current_hours,
    $oil_status,
    $air_filters,
    $warrantyParam,
    $tires
);

$ok = $stmt->execute();
if (!$ok) {
    $err = $stmt->error;
    $stmt->close();

    // Friendly unique constraint message
    if (stripos($err, 'Duplicate') !== false) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Equipment number already exists']);
        exit();
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $err]);
    exit();
}

$insertId = $stmt->insert_id;
$stmt->close();

echo json_encode(['success' => true, 'equipment_id' => $insertId]);
exit();
