<?php
// Touches the session so a "Stay Logged In" click (assets/js/idle-timeout.js)
// resets the server-side inactivity clock, not just the client-side one.
require_once __DIR__ . '/../session_init.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit();
}

echo json_encode(['ok' => true, 'idle_limit_seconds' => SESSION_IDLE_LIMIT_SECONDS]);
