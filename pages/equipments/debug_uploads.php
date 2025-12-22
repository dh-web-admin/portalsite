<?php
require_once __DIR__ . '/../../session_init.php';
require_once __DIR__ . '/../../config/config.php';
$equipment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($equipment_id <= 0) { echo "Invalid equipment ID."; exit; }
$stmt = $conn->prepare("SELECT id, equipment_id, field, file_url, uploaded_at FROM equipment_uploads WHERE equipment_id = ? ORDER BY uploaded_at DESC");
$stmt->bind_param('i', $equipment_id);
$stmt->execute();
$res = $stmt->get_result();
echo "<h2>Uploads for Equipment #$equipment_id</h2>";
echo "<table border='1' cellpadding='6'><tr><th>ID</th><th>Field</th><th>File URL</th><th>Uploaded At</th></tr>";
while ($row = $res->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['field']) . "</td>";
    echo "<td>" . htmlspecialchars($row['file_url']) . "</td>";
    echo "<td>" . htmlspecialchars($row['uploaded_at']) . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
