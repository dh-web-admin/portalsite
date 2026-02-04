<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['email'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

$userEmail = $_SESSION['email'];

try {
    $stmt = $conn->prepare('SELECT id, opted_in, preferred_days FROM bids_email WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $userEmail);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($row && isset($row['id'])) {
        $preferred = [];
        $pd = $row['preferred_days'] ?? '';
        if (is_string($pd) && $pd !== '') {
            $try = json_decode($pd, true);
            if (is_array($try)) $preferred = array_values(array_map('intval', $try));
        }
        echo json_encode([
            'success' => true,
            'exists' => true,
            'opted_in' => (int)($row['opted_in'] ?? 0),
            'preferred_days' => $preferred
        ]);
        exit();
    }

    echo json_encode(['success' => true, 'exists' => false, 'opted_in' => 0, 'preferred_days' => []]);
    exit();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit();
}
