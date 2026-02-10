<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
// Indicate this is an API endpoint to prevent UI injection from permissions helper
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

// Require permission to edit the Project Checklist page
require_edit_api('project_checklist');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Accept both form-encoded and JSON
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (is_array($data)) $input = $data;
}

$projectName = isset($input['project_name']) ? trim($input['project_name']) : '';

if ($projectName === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Project name is required']);
    exit;
}

// Check if Projects table has a Status column; if so, default new rows to 'Ongoing'
$hasStatus = false;
try {
    $colRes = $conn->query("SHOW COLUMNS FROM `Projects` LIKE 'Status'");
    if ($colRes && $colRes->num_rows > 0) {
        $hasStatus = true;
    }
} catch (Exception $e) {
    $hasStatus = false;
}

try {
    if ($hasStatus) {
        $status = 'Ongoing';
        $stmt = $conn->prepare('INSERT INTO `Projects` (`Project_Name`, `Status`) VALUES (?, ?)');
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('ss', $projectName, $status);
    } else {
        $stmt = $conn->prepare('INSERT INTO `Projects` (`Project_Name`) VALUES (?)');
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('s', $projectName);
    }

    if (!$stmt->execute()) {
        $msg = $stmt->error ?: 'Insert failed';
        $stmt->close();
        throw new Exception($msg);
    }

    $newId = $stmt->insert_id;
    $stmt->close();

    echo json_encode(['success' => true, 'project_id' => $newId, 'project_name' => $projectName]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
