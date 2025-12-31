<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ob_start();
require_once __DIR__ . '/../session_init.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

function json_exit_r($arr, $status = 200){
    http_response_code($status);
    $buf = ob_get_clean();
    if ($buf && is_string($buf) && trim($buf) !== '') $arr['raw'] = $buf;
    echo json_encode($arr);
    exit();
}

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    json_exit_r(['success' => false, 'message' => 'Unauthorized'], 401);
}

$role = 'laborer';
$email = $_SESSION['email'];
if ($stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1')) {
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $role = $user && isset($user['role']) ? $user['role'] : 'laborer';
    $stmt->close();
}

if (!can_access($role, 'equipments')) {
    json_exit_r(['success' => false, 'message' => 'Forbidden'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit_r(['success' => false, 'message' => 'Method not allowed'], 405);
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) json_exit_r(['success'=>false,'message'=>'Invalid id'], 400);

// find equipment id for this part
$qr = $conn->query('SELECT equipment_id FROM equipment_oil_parts WHERE id=' . intval($id) . ' LIMIT 1');
$prow = $qr ? $qr->fetch_assoc() : null;
if (!$prow) json_exit_r(['success'=>false,'message'=>'Part not found'], 404);
$equipment_id = intval($prow['equipment_id']);

// get equipment current hours
$equip_hours = 0;
if ($equipment_id) {
    $q2 = $conn->query('SELECT COALESCE(current_hours,0) AS ch FROM equipments WHERE equipment_id=' . intval($equipment_id) . ' LIMIT 1');
    if ($q2) { $r2 = $q2->fetch_assoc(); $equip_hours = isset($r2['ch']) ? $r2['ch'] : 0; $q2->free(); }
}

$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare('UPDATE equipment_oil_parts SET current_hours=?, reset_at=?, updated_at=? WHERE id=?');
if (!$stmt) json_exit_r(['success'=>false,'message'=>'DB prepare failed'], 500);
$stmt->bind_param('dssi', $equip_hours, $now, $now, $id);
$ok = $stmt->execute();
if (!$ok) { $err = $stmt->error; $stmt->close(); json_exit_r(['success'=>false,'message'=>'Update failed: '.$err], 500); }
$stmt->close();

$qr3 = $conn->query('SELECT * FROM equipment_oil_parts WHERE id=' . intval($id) . ' LIMIT 1');
$row = $qr3 ? $qr3->fetch_assoc() : null;
json_exit_r(['success'=>true,'row'=>$row], 200);

// After reset, recalc equipment oil_status for this equipment
if ($row && isset($row['equipment_id'])) {
    $equipment_id = intval($row['equipment_id']);
    $eq_hours = 0;
    $q = $conn->query('SELECT COALESCE(current_hours,0) AS ch FROM equipments WHERE equipment_id=' . intval($equipment_id) . ' LIMIT 1');
    if ($q) { $r = $q->fetch_assoc(); $eq_hours = isset($r['ch']) ? (float)$r['ch'] : 0; $q->free(); }

    $qr2 = $conn->query('SELECT current_hours, oil_life FROM equipment_oil_parts WHERE equipment_id=' . intval($equipment_id));
    $status = 'green';
    if ($qr2) {
        while ($p = $qr2->fetch_assoc()) {
            $p_ch = isset($p['current_hours']) ? (float)$p['current_hours'] : 0;
            $p_life = isset($p['oil_life']) ? (float)$p['oil_life'] : 0;
            $diff = $eq_hours - $p_ch; if ($diff < 0) $diff = 0;
            if ($p_life > 0) {
                $cond = round(100 - (($diff / $p_life) * 100));
                if ($cond <= 0) { $status = 'red'; break; }
                if ($cond < 20 && $status !== 'red') { $status = 'yellow'; }
            }
        }
        $qr2->free();
    }
    if ($stmt4 = $conn->prepare('UPDATE equipments SET oil_status=? WHERE equipment_id=?')) {
        $stmt4->bind_param('si', $status, $equipment_id);
        $stmt4->execute();
        $stmt4->close();
    }
}

?>
