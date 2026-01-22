<?php
// Recalculate equipment.operating_condition from equipment_history rows for a given equipment_id
require_once __DIR__ . '/../config/config.php';
if ($argc < 2) {
    echo "Usage: php recalc_equipment_condition.php <equipment_id>\n";
    exit(1);
}
$equipmentId = (int)$argv[1];
if ($equipmentId <= 0) {
    echo "Invalid equipment_id\n";
    exit(1);
}

$conn->set_charset('utf8mb4');
// Detect if column exists
$afterColRes = $conn->query("SHOW COLUMNS FROM equipment_history LIKE 'condition_after_repair'");
$afterColExists = ($afterColRes && $afterColRes->num_rows > 0);

if ($afterColExists) {
    $rowWorstSql = "SELECT MAX(CASE WHEN (LOWER(operating_condition) LIKE '%red%' OR LOWER(IFNULL(condition_after_repair,'')) LIKE '%red%' OR LOWER(operating_condition) LIKE '%inoperable%' OR LOWER(IFNULL(condition_after_repair,'')) LIKE '%inoperable%') THEN 3 WHEN (LOWER(operating_condition) LIKE '%yellow%' OR LOWER(IFNULL(condition_after_repair,'')) LIKE '%yellow%' OR LOWER(operating_condition) LIKE '%minor%' OR LOWER(IFNULL(condition_after_repair,'')) LIKE '%minor%') THEN 2 WHEN (LOWER(operating_condition) LIKE '%green%' OR LOWER(IFNULL(condition_after_repair,'')) LIKE '%green%' OR LOWER(operating_condition) LIKE '%fully%' OR LOWER(IFNULL(condition_after_repair,'')) LIKE '%fully%') THEN 1 ELSE 0 END) AS worst_all FROM equipment_history eh WHERE eh.equipment_id = ? AND NOT EXISTS (SELECT 1 FROM equipment_history eh2 WHERE eh2.original_issue_id = eh.id)";
} else {
    $rowWorstSql = "SELECT MAX(CASE WHEN (LOWER(operating_condition) LIKE '%red%' OR LOWER(operating_condition) LIKE '%inoperable%') THEN 3 WHEN (LOWER(operating_condition) LIKE '%yellow%' OR LOWER(operating_condition) LIKE '%minor%') THEN 2 WHEN (LOWER(operating_condition) LIKE '%green%' OR LOWER(operating_condition) LIKE '%fully%') THEN 1 ELSE 0 END) AS worst_all FROM equipment_history eh WHERE eh.equipment_id = ? AND NOT EXISTS (SELECT 1 FROM equipment_history eh2 WHERE eh2.original_issue_id = eh.id)";
}

$stmt = $conn->prepare($rowWorstSql);
$combinedWorst = 0;
if ($stmt) {
    $stmt->bind_param('i', $equipmentId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($r = $res->fetch_assoc())) {
        $combinedWorst = (int)($r['worst_all'] ?? 0);
    }
    $stmt->close();
}

$map = [3 => 'red', 2 => 'yellow', 1 => 'green', 0 => null];
$final = $map[$combinedWorst] ?? null;

$stmtOld = $conn->prepare('SELECT operating_condition FROM equipments WHERE equipment_id = ? LIMIT 1');
$old = null;
if ($stmtOld) {
    $stmtOld->bind_param('i', $equipmentId);
    $stmtOld->execute();
    $resOld = $stmtOld->get_result();
    if ($resOld && ($r = $resOld->fetch_assoc())) $old = $r['operating_condition'];
    $stmtOld->close();
}

echo "Equipment {$equipmentId} - old operating_condition: " . ($old ?? '(null)') . "\n";
echo "Computed worst severity code: {$combinedWorst} => final: " . ($final ?? '(none)') . "\n";

if ($final !== null) {
    $stmtUpd = $conn->prepare('UPDATE equipments SET operating_condition = ? WHERE equipment_id = ?');
    if ($stmtUpd) {
        $stmtUpd->bind_param('si', $final, $equipmentId);
        $stmtUpd->execute();
        $stmtUpd->close();
        echo "Updated equipment.operating_condition to {$final}\n";
    } else {
        echo "Failed to prepare update statement\n";
    }
} else {
    echo "No severity determined; not updating.\n";
}

echo "Done.\n";

?>