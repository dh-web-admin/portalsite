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
        $daily_opted = 1;
        $win_opted = 1;
        $pd = $row['preferred_days'] ?? '';
        if (is_string($pd) && $pd !== '') {
            $try = json_decode($pd, true);
            if (is_array($try)) {
                // support legacy array and new object format
                if (isset($try['days']) && is_array($try['days'])) {
                    $preferred = array_values(array_map('intval', $try['days']));
                    $daily_opted = isset($try['daily_opted']) ? intval($try['daily_opted']) : 1;
                    $win_opted = isset($try['win_opted']) ? intval($try['win_opted']) : 1;
                } else {
                    $preferred = array_values(array_map('intval', $try));
                    $daily_opted = 1;
                    $win_opted = 1;
                }
            }
        }
        echo json_encode([
            'success' => true,
            'exists' => true,
            'opted_in' => (int)($row['opted_in'] ?? 0),
            'preferred_days' => $preferred,
            'daily_opted' => $daily_opted,
            'win_opted' => $win_opted
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
