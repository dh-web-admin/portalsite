<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$clientId = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;

if (!$clientId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Client ID is required']);
    exit;
}

try {
    $stmt = $conn->prepare('DELETE FROM clients WHERE client_id = ?');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database prepare failed', 'error' => $conn->error]);
        exit;
    }
    
    $stmt->bind_param('i', $clientId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Client deleted successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Client not found']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Delete operation failed', 'error' => $stmt->error]);
    }
    
    $stmt->close();
} catch (Throwable $ex) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Exception occurred', 'error' => $ex->getMessage()]);
}
?>
