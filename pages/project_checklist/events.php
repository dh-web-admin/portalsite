<?php
// /pages/project_checklist/events.php
// Short-lived SSE endpoint (Railway + XAMPP safe)

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../session_init.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Disable buffering
while (ob_get_level() > 0) ob_end_flush();
ob_implicit_flush(true);

global $conn;

// Validate project_id
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if ($projectId <= 0) {
    echo "event: error\n";
    echo "data: invalid_project\n\n";
    flush();
    exit;
}

// Track updates since timestamp
$since = isset($_GET['since']) && is_numeric($_GET['since'])
    ? (int)$_GET['since']
    : (time() - 60);

// Tell browser retry delay
echo "retry: 3000\n\n";
flush();

// Fetch up to 10 updates for THIS project
$sql = "
    SELECT *,
           UNIX_TIMESTAMP(updated_at) AS updated_ts
    FROM Projects
    WHERE Project_ID = ?
      AND updated_at > FROM_UNIXTIME(?)
    ORDER BY updated_at ASC
    LIMIT 10
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $projectId, $since);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $payload = [
        'project_id' => (int)$row['Project_ID'],
        'updated_at' => (int)$row['updated_ts'],
        'row'        => $row
    ];

    echo "event: projectUpdate\n";
    echo "data: " . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

// Named heartbeat event
echo "event: heartbeat\n";
echo "data: ping\n\n";
flush();

// IMPORTANT: exit so Railway doesn't kill the process
exit;
