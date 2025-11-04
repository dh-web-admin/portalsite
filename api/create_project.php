<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

// Require login
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$projectName = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept both form-encoded and JSON
    if (isset($_POST['project_name'])) {
        $projectName = trim($_POST['project_name']);
    } else {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (is_array($data) && isset($data['project_name'])) {
            $projectName = trim($data['project_name']);
        }
    }

    if ($projectName === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Project name is required']);
        exit();
    }

    // Insert into Projects table. Only set Project_Name; other fields left NULL.
    $stmt = $conn->prepare('INSERT INTO Projects (Project_Name) VALUES (?)');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database prepare failed']);
        exit();
    }
    $stmt->bind_param('s', $projectName);
    $ok = $stmt->execute();
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
        $stmt->close();
        exit();
    }
    $insertId = $stmt->insert_id;
    $stmt->close();

    // Fetch inserted row to return
    $rstmt = $conn->prepare('SELECT * FROM Projects WHERE Project_ID = ? LIMIT 1');
    if ($rstmt) {
        $rstmt->bind_param('i', $insertId);
        $rstmt->execute();
        $res = $rstmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $rstmt->close();
        echo json_encode(['success' => true, 'project' => $row]);
        exit();
    }

    // Fallback: return insert id
    echo json_encode(['success' => true, 'project' => ['Project_ID' => $insertId, 'Project_Name' => $projectName]]);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
exit();
