<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json');
// Ensure PHP notices/warnings do not break JSON responses
@ini_set('display_errors', '0');

if (!function_exists('save_addhours_send_json')) {
    function save_addhours_send_json(array $payload) {
        if (ob_get_length()) {
            ob_clean();
        }
        echo json_encode($payload);
        exit();
    }
}

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    save_addhours_send_json(['success' => false, 'error' => 'Unauthorized']);
}

$email = $_SESSION['email'];
$roleStmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$roleStmt->bind_param('s', $email);
$roleStmt->execute();
$roleRes = $roleStmt->get_result();
$user = $roleRes ? $roleRes->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$roleStmt->close();

if (!can_access($role, 'equipments')) {
    save_addhours_send_json(['success' => false, 'error' => 'Forbidden']);
}

$raw = isset($_POST['payload']) ? $_POST['payload'] : '';
if ($raw === '') {
    save_addhours_send_json(['success' => false, 'error' => 'Missing payload']);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    save_addhours_send_json(['success' => false, 'error' => 'Invalid JSON payload']);
}

$filters = isset($data['filters']) && is_array($data['filters']) ? $data['filters'] : [];
$fluids  = isset($data['fluids']) && is_array($data['fluids']) ? $data['fluids'] : [];

// Start transaction (fallback to autocommit off if begin_transaction is unavailable)
try {
    if (method_exists($conn, 'begin_transaction')) {
        $conn->begin_transaction();
    } else {
        $conn->autocommit(false);
    }

    // Update filters: filter_info.filter_date, filter_info.hours, and recompute filter_hours
    if (!empty($filters)) {
        $filterSelectStmt = $conn->prepare('SELECT equipment_id FROM filter_info WHERE filter_id = ? LIMIT 1');
        if (!$filterSelectStmt) {
            throw new Exception('Failed to prepare filter select: ' . $conn->error);
        }
        $equipHoursStmt   = $conn->prepare('SELECT current_hours FROM equipments WHERE equipment_id = ? LIMIT 1');
        if (!$equipHoursStmt) {
            throw new Exception('Failed to prepare equipment hours select (filters): ' . $conn->error);
        }
        $filterUpdateStmt = $conn->prepare('UPDATE filter_info SET filter_date = ?, hours = ?, filter_hours = ? WHERE filter_id = ?');
        if (!$filterUpdateStmt) {
            throw new Exception('Failed to prepare filter update: ' . $conn->error);
        }

        foreach ($filters as $item) {
            if (!isset($item['filter_id'])) continue;
            $filterId = (int)$item['filter_id'];
            if ($filterId <= 0) continue;

            $filterDate = isset($item['filter_date']) ? trim((string)$item['filter_date']) : '';
            $hoursStr   = isset($item['hours']) ? trim((string)$item['hours']) : '';
            if ($filterDate === '' || $hoursStr === '') continue; // require both

            $hours = (float)$hoursStr;

            $filterSelectStmt->bind_param('i', $filterId);
            $filterSelectStmt->execute();
            $filterRes = $filterSelectStmt->get_result();
            $filterRow = $filterRes ? $filterRes->fetch_assoc() : null;
            $filterRes && $filterRes->free();
            $equipmentId = $filterRow ? (int)$filterRow['equipment_id'] : 0;

            $equipHours = 0.0;
            if ($equipmentId > 0) {
                $equipHoursStmt->bind_param('i', $equipmentId);
                $equipHoursStmt->execute();
                $ehRes = $equipHoursStmt->get_result();
                $ehRow = $ehRes ? $ehRes->fetch_assoc() : null;
                $ehRes && $ehRes->free();
                if ($ehRow && $ehRow['current_hours'] !== null) {
                    $equipHours = (float)$ehRow['current_hours'];
                }
            }

            $filterHours = max(0.0, $equipHours - $hours);

            $filterUpdateStmt->bind_param('sddi', $filterDate, $hours, $filterHours, $filterId);
            if (!$filterUpdateStmt->execute()) {
                throw new Exception('Failed to update filter_info: ' . $filterUpdateStmt->error);
            }
        }

        $filterSelectStmt->close();
        $equipHoursStmt->close();
        $filterUpdateStmt->close();
    }

    // Update fluids: equipment_oil_parts.reset_at, current_hours, and recompute oil_hours
    if (!empty($fluids)) {
        $fluidSelectStmt = $conn->prepare('SELECT equipment_id FROM equipment_oil_parts WHERE id = ? LIMIT 1');
        if (!$fluidSelectStmt) {
            throw new Exception('Failed to prepare fluid select: ' . $conn->error);
        }
        $equipHoursStmt2 = $conn->prepare('SELECT current_hours FROM equipments WHERE equipment_id = ? LIMIT 1');
        if (!$equipHoursStmt2) {
            throw new Exception('Failed to prepare equipment hours select (fluids): ' . $conn->error);
        }
        $fluidUpdateStmt = $conn->prepare('UPDATE equipment_oil_parts SET reset_at = ?, current_hours = ?, oil_hours = ? WHERE id = ?');
        if (!$fluidUpdateStmt) {
            throw new Exception('Failed to prepare fluid update: ' . $conn->error);
        }

        foreach ($fluids as $item) {
            if (!isset($item['id'])) continue;
            $id = (int)$item['id'];
            if ($id <= 0) continue;

            $resetAt = isset($item['reset_at']) ? trim((string)$item['reset_at']) : '';
            $hoursStr = isset($item['current_hours']) ? trim((string)$item['current_hours']) : '';
            if ($resetAt === '' || $hoursStr === '') continue; // require both

            // If resetAt is date-only, normalise to datetime
            if (strpos($resetAt, ' ') === false) {
                $resetAt .= ' 00:00:00';
            }

            $currentHours = (float)$hoursStr;

            $fluidSelectStmt->bind_param('i', $id);
            $fluidSelectStmt->execute();
            $fluidRes = $fluidSelectStmt->get_result();
            $fluidRow = $fluidRes ? $fluidRes->fetch_assoc() : null;
            $fluidRes && $fluidRes->free();
            $equipmentId = $fluidRow ? (int)$fluidRow['equipment_id'] : 0;

            $equipHours2 = 0.0;
            if ($equipmentId > 0) {
                $equipHoursStmt2->bind_param('i', $equipmentId);
                $equipHoursStmt2->execute();
                $ehRes2 = $equipHoursStmt2->get_result();
                $ehRow2 = $ehRes2 ? $ehRes2->fetch_assoc() : null;
                $ehRes2 && $ehRes2->free();
                if ($ehRow2 && $ehRow2['current_hours'] !== null) {
                    $equipHours2 = (float)$ehRow2['current_hours'];
                }
            }

            $oilHours = max(0.0, $equipHours2 - $currentHours);

            $fluidUpdateStmt->bind_param('sddi', $resetAt, $currentHours, $oilHours, $id);
            if (!$fluidUpdateStmt->execute()) {
                throw new Exception('Failed to update equipment_oil_parts: ' . $fluidUpdateStmt->error);
            }
        }

        $fluidSelectStmt->close();
        $equipHoursStmt2->close();
        $fluidUpdateStmt->close();
    }
    // Commit or restore autocommit
    if (method_exists($conn, 'commit')) {
        $conn->commit();
    } else {
        $conn->autocommit(true);
    }
    save_addhours_send_json(['success' => true]);
} catch (Throwable $e) {
    if (method_exists($conn, 'rollback')) {
        $conn->rollback();
    } else {
        $conn->autocommit(true);
    }
    error_log('[save_addhours_changes] ' . $e->getMessage());
    save_addhours_send_json(['success' => false, 'error' => $e->getMessage()]);
}
