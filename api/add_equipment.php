<?php
define('IS_API', true);
// Debug: Log errors to a file for troubleshooting
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../uploads/equipment/upload_debug.log');
error_reporting(E_ALL);

// Debug: Output file upload errors if any
function debug_upload_error($file, $field) {
    if ($file && isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
        $errCodes = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
        ];
        $msg = isset($errCodes[$file['error']]) ? $errCodes[$file['error']] : 'Unknown upload error.';
        error_log("Upload error for $field: $msg");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Upload error for $field: $msg"]);
        exit();
    }
}

// These variables are set later, but we must not call debug_upload_error before they exist
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

require_edit_api('equipments');

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

$dhss_equipment_number = isset($_POST['dhss_equipment_number']) ? trim($_POST['dhss_equipment_number']) : '';
$equipment_number = $dhss_equipment_number;
$type = isset($_POST['type']) ? trim($_POST['type']) : '';
$operating_condition = isset($_POST['operating_condition']) ? trim($_POST['operating_condition']) : '';
$location = isset($_POST['location']) ? trim($_POST['location']) : '';
$current_hours_raw = isset($_POST['current_hours']) ? trim($_POST['current_hours']) : '';
$oil_status = isset($_POST['oil_status']) ? trim($_POST['oil_status']) : '';
$air_filters = isset($_POST['air_filters']) ? strtolower(trim($_POST['air_filters'])) : '';
if (!in_array($air_filters, ['green','yellow','red'], true)) {
    $air_filters = '';
}

// File upload field
$warranty_file = $_FILES['warranty'] ?? null;

if ($dhss_equipment_number === '' || $type === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'DHSS Equipment number and type are required']);
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

$stmt = $conn->prepare('INSERT INTO equipments (equipment_number, dhss_equipment_number, type, operating_condition, location, current_hours, oil_status, air_filters) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database prepare failed']);
    exit();
}
$stmt->bind_param(
    'sssssdss',
    $equipment_number,
    $dhss_equipment_number,
    $type,
    $operating_condition,
    $location,
    $current_hours,
    $oil_status,
    $air_filters
);
$ok = $stmt->execute();
if (!$ok) {
    $err = $stmt->error;
    $stmt->close();
    if (stripos($err, 'Duplicate') !== false) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'DHSS Equipment number already exists']);
        exit();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $err]);
    exit();
}
$insertId = $stmt->insert_id;
$stmt->close();

// Handle file uploads and insert into equipment_uploads
$upload_dir = realpath(__DIR__ . '/../uploads/equipment');
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

function handle_upload($file, $equipment_id, $field, $conn, $upload_dir) {
    if ($file && isset($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
        // Extra debug: check directory is writable
        if (!is_writable($upload_dir)) {
            error_log('Upload directory not writable: ' . $upload_dir);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Upload directory is not writable.']);
            exit();
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safe_name = uniqid($field . '_') . '.' . $ext;
        $target = $upload_dir . DIRECTORY_SEPARATOR . $safe_name;
        if (move_uploaded_file($file['tmp_name'], $target)) {
            $url = 'uploads/equipment/' . $safe_name;
            $stmt = $conn->prepare('INSERT INTO equipment_uploads (equipment_id, field, file_url) VALUES (?, ?, ?)');
            $stmt->bind_param('iss', $equipment_id, $field, $url);
            $stmt->execute();
            $stmt->close();
            return $url;
        }
    }
    return null;
}

handle_upload($warranty_file, $insertId, 'warranty', $conn, $upload_dir);

echo json_encode(['success' => true, 'equipment_id' => $insertId]);
exit();
