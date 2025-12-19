<?php
// events.php - SSE endpoint for real-time project checklist updates
// Uses: text/event-stream, disables output buffering, polls for updated project rows

require_once '../../config/config.php';
require_once '../../session_init.php';

// Auth check (reuse existing logic)
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // For nginx, disables buffering

// Disable PHP output buffering
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(1);

$projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
if ($projectId <= 0) {
    echo ": invalid project_id\n\n";
    exit;
}

// Track last sent update timestamp
$lastSent = isset($_GET['since']) ? $_GET['since'] : null;
if (!$lastSent) {
    $lastSent = date('Y-m-d H:i:s', time() - 60); // default: last 60s
}

$pollInterval = 2; // seconds
$maxDuration = 60 * 5; // 5 minutes per connection
$startTime = time();

// Use PDO from config.php
$db = $pdo;

function sendEvent($row) {
    echo "event: projectUpdate\n";
    echo "data: ".json_encode($row)."\n\n";
    @ob_flush();
    @flush();
}

while (true) {
    // Stop after max duration
    if ((time() - $startTime) > $maxDuration) {
        break;
    }

    // Query for updated project rows
    $stmt = $db->prepare("SELECT * FROM Projects WHERE id = :id AND updated_at > :since LIMIT 1");
    $stmt->execute([
        ':id' => $projectId,
        ':since' => $lastSent
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        sendEvent($row);
        $lastSent = $row['updated_at'];
    }

    // Heartbeat to keep connection alive
    echo ": heartbeat\n\n";
    @ob_flush();
    @flush();
    sleep($pollInterval);
}
