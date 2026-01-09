<?php
// Suppress all error output except for JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Ensure permissions.php does not echo UI CSS/JS in API context
if (!defined('IS_API')) {
    define('IS_API', true);
}

require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';
header('Content-Type: application/json');
while (ob_get_level()) ob_end_clean();

if (isset($conn)) $GLOBALS['conn'] = $conn;

require_edit_api('equipments');

$tire_id = isset($_POST['tire_id']) ? (int)$_POST['tire_id'] : 0;
$equipment_id = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
$steer_tire_make = isset($_POST['steer_tire_make']) ? trim($_POST['steer_tire_make']) : null;
$steer_tire_model = isset($_POST['steer_tire_model']) ? trim($_POST['steer_tire_model']) : null;
$steer_tire_size = isset($_POST['steer_tire_size']) ? trim($_POST['steer_tire_size']) : null;
$drive_tire_make = isset($_POST['drive_tire_make']) ? trim($_POST['drive_tire_make']) : null;
$drive_tire_model = isset($_POST['drive_tire_model']) ? trim($_POST['drive_tire_model']) : null;
$drive_tire_size = isset($_POST['drive_tire_size']) ? trim($_POST['drive_tire_size']) : null;

// Allow callers that don't yet have a tire_id (no tire_info row exists)
if ($tire_id <= 0) {
    if ($equipment_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing tire_id']);
        exit;
    }

    // Try to find an existing row for this equipment first
    $findStmt = $conn->prepare('SELECT tire_id FROM tire_info WHERE equipment_id = ? ORDER BY tire_id ASC LIMIT 1');
    if ($findStmt) {
        $findStmt->bind_param('i', $equipment_id);
        $findStmt->execute();
        $findRes = $findStmt->get_result();
        $found = $findRes ? $findRes->fetch_assoc() : null;
        $findStmt->close();
        if (!empty($found['tire_id'])) {
            $tire_id = (int)$found['tire_id'];
        }
    }

    // If still missing, create the row
    if ($tire_id <= 0) {
        $insStmt = $conn->prepare('INSERT INTO tire_info (equipment_id, steer_tire_make, steer_tire_model, steer_tire_size, drive_tire_make, drive_tire_model, drive_tire_size) VALUES (?, ?, ?, ?, ?, ?, ?)');
        if (!$insStmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
            exit;
        }
        $insStmt->bind_param('issssss', $equipment_id, $steer_tire_make, $steer_tire_model, $steer_tire_size, $drive_tire_make, $drive_tire_model, $drive_tire_size);
        if (!$insStmt->execute()) {
            $err = $insStmt->error ?: $conn->error;
            $insStmt->close();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $err]);
            exit;
        }
        $insStmt->close();
        $tire_id = (int)$conn->insert_id;

        echo json_encode(['success' => true, 'tire_id' => $tire_id]);
        exit;
    }
}

$stmt = $conn->prepare('UPDATE tire_info SET steer_tire_make = ?, steer_tire_model = ?, steer_tire_size = ?, drive_tire_make = ?, drive_tire_model = ?, drive_tire_size = ? WHERE tire_id = ?');
$stmt->bind_param('ssssssi', $steer_tire_make, $steer_tire_model, $steer_tire_size, $drive_tire_make, $drive_tire_model, $drive_tire_size, $tire_id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    echo json_encode(['success' => true, 'tire_id' => $tire_id]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

exit;