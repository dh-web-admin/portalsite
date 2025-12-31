<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
// capture any unexpected output (styles/html) so we can return JSON safely
ob_start();
require_once __DIR__ . '/../session_init.php';
// mark API context so permissions partial won't emit UI scripts/styles
if (!defined('IS_API')) define('IS_API', true);

function json_exit($arr, $status = 200){
    http_response_code($status);
    $buf = ob_get_clean();
    if ($buf && is_string($buf) && trim($buf) !== '') {
        $arr['raw'] = $buf;
    }
    echo json_encode($arr);
    exit();
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    json_exit(['success' => false, 'message' => 'Unauthorized'], 401);
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
    json_exit(['success' => false, 'message' => 'Forbidden'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit(['success' => false, 'message' => 'Method not allowed'], 405);
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) { json_exit(['success'=>false,'message'=>'Invalid id'], 400); }

$part = isset($_POST['part']) ? trim($_POST['part']) : '';
$approx_capacity = isset($_POST['approx_capacity']) ? trim($_POST['approx_capacity']) : null;
$fluid_type = isset($_POST['fluid_type']) ? trim($_POST['fluid_type']) : null;
$oil_life = isset($_POST['oil_life']) ? trim($_POST['oil_life']) : 0;
$weight = isset($_POST['weight']) ? trim($_POST['weight']) : null;
$mfg = isset($_POST['mfg']) ? trim($_POST['mfg']) : null;
$supplier = isset($_POST['supplier']) ? trim($_POST['supplier']) : null;
$unit_cost = isset($_POST['unit_cost']) ? trim($_POST['unit_cost']) : null;
$unit = isset($_POST['unit']) ? trim($_POST['unit']) : null;
$total = isset($_POST['total']) ? trim($_POST['total']) : null;
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;

$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare('UPDATE equipment_oil_parts SET part=?, approx_capacity=?, fluid_type=?, weight=?, mfg=?, supplier=?, unit_cost=?, unit=?, total=?, notes=?, oil_life=?, updated_at=? WHERE id=?');
if (!$stmt) { json_exit(['success'=>false,'message'=>'DB prepare failed'], 500); }
$stmt->bind_param('ssssssssssdsi', $part, $approx_capacity, $fluid_type, $weight, $mfg, $supplier, $unit_cost, $unit, $total, $notes, $oil_life, $now, $id);
$ok = $stmt->execute();
$err = null;
if (!$ok) { $err = $stmt->error; $stmt->close(); json_exit(['success'=>false,'message'=>'Update failed: '.$err], 500); }
$stmt->close();

$qr = $conn->query('SELECT * FROM equipment_oil_parts WHERE id=' . intval($id) . ' LIMIT 1');
 $row = $qr ? $qr->fetch_assoc() : null;
json_exit(['success'=>true,'row'=>$row], 200);

// After update, if part belongs to an equipment, recalc equipment oil_status
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
    if ($stmt3 = $conn->prepare('UPDATE equipments SET oil_status=? WHERE equipment_id=?')) {
        $stmt3->bind_param('si', $status, $equipment_id);
        $stmt3->execute();
        $stmt3->close();
    }
}

?>
