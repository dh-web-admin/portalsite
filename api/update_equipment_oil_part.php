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

require_edit_api('equipments');

// convert warnings/notices to exceptions so we can return JSON
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// shutdown handler for fatal errors
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_end_clean();
        http_response_code(500);
        $payload = ['success' => false, 'message' => 'Internal server error', 'fatal' => $err['message'], 'file' => $err['file'], 'line' => $err['line']];
        error_log('[update_equipment_oil_part] FATAL: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
        echo json_encode($payload);
    }
});

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
// Normalize numeric inputs to avoid strict SQL errors (DECIMAL columns reject empty strings)
$unit_cost = ($unit_cost !== null && $unit_cost !== '' && is_numeric($unit_cost)) ? $unit_cost : 0;
$total = ($total !== null && $total !== '' && is_numeric($total)) ? $total : 0;
$oil_life = ($oil_life !== null && $oil_life !== '' && is_numeric($oil_life)) ? (float)$oil_life : 0;

// Look up equipment and current_hours so we can persist oil_hours
$equipment_id = 0;
$part_current_hours = 0.0;
if ($meta = $conn->query('SELECT equipment_id, current_hours FROM equipment_oil_parts WHERE id=' . intval($id) . ' LIMIT 1')) {
    $metaRow = $meta->fetch_assoc();
    if ($metaRow) {
        $equipment_id = isset($metaRow['equipment_id']) ? (int)$metaRow['equipment_id'] : 0;
        $part_current_hours = isset($metaRow['current_hours']) ? (float)$metaRow['current_hours'] : 0.0;
    }
    $meta->free();
}

$oil_hours = 0.0;
if ($equipment_id > 0) {
    $eq_hours = 0.0;
    if ($q = $conn->query('SELECT COALESCE(current_hours,0) AS ch FROM equipments WHERE equipment_id=' . intval($equipment_id) . ' LIMIT 1')) {
        $er = $q->fetch_assoc();
        if ($er && isset($er['ch'])) { $eq_hours = (float)$er['ch']; }
        $q->free();
    }
    $oil_hours = $eq_hours - $part_current_hours;
    if (!is_numeric($oil_hours) || $oil_hours < 0) { $oil_hours = 0.0; }
}

try {
    $stmt = $conn->prepare('UPDATE equipment_oil_parts SET part=?, approx_capacity=?, fluid_type=?, weight=?, mfg=?, supplier=?, unit_cost=?, unit=?, total=?, notes=?, oil_life=?, oil_hours=?, updated_at=? WHERE id=?');
    if (!$stmt) { throw new Exception('DB prepare failed: ' . $conn->error); }
    $stmt->bind_param('ssssssssssddsi', $part, $approx_capacity, $fluid_type, $weight, $mfg, $supplier, $unit_cost, $unit, $total, $notes, $oil_life, $oil_hours, $now, $id);
    $ok = $stmt->execute();
    if (!$ok) { $e = $stmt->error; $stmt->close(); throw new Exception('Update failed: ' . $e); }
    $stmt->close();
} catch (Exception $ex) {
    error_log('[update_equipment_oil_part] Exception: ' . $ex->getMessage());
    json_exit(['success' => false, 'message' => 'Update failed', 'error' => $ex->getMessage()], 500);
}

// Fetch updated row
$qr = $conn->query('SELECT * FROM equipment_oil_parts WHERE id=' . intval($id) . ' LIMIT 1');
$row = $qr ? $qr->fetch_assoc() : null;

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

json_exit(['success'=>true,'row'=>$row], 200);

?>
