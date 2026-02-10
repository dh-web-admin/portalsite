<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

require_edit_api('Bid_tracking');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (is_array($data)) $input = $data;
}

$items = [];
if (isset($input['clients']) && is_array($input['clients'])) {
    $items = $input['clients'];
} elseif (isset($input['clients'])) {
    $decoded = json_decode((string)$input['clients'], true);
    if (is_array($decoded)) $items = $decoded;
}

if (empty($items)) {
    echo json_encode(['success' => true, 'inserted' => 0, 'skipped' => 0]);
    exit;
}

function norm($v) {
    return trim((string)$v);
}

function normalize_union($v) {
    $s = strtolower(trim((string)$v));
    if ($s === '') return '';
    if ($s === '1' || $s === 'true' || $s === 'yes' || $s === 'union') return 'Union';
    if ($s === '0' || $s === 'false' || $s === 'no' || $s === 'non-union' || $s === 'nonunion') return 'Non-Union';
    if ($s === 'union') return 'Union';
    if ($s === 'non-union') return 'Non-Union';
    return '';
}

$checkStmt = $conn->prepare('SELECT client_id, past_projects FROM clients WHERE LOWER(client_name) = LOWER(?) LIMIT 1');
$insertStmt = $conn->prepare('INSERT INTO clients (client_name, client_type, union_status, contact_phone, client_email, client_address, current_employer, past_projects) VALUES (?,?,?,?,?,?,?,?)');
$updateStmt = $conn->prepare('UPDATE clients SET past_projects = ? WHERE client_id = ?');

if (!$checkStmt || !$insertStmt || !$updateStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
    exit;
}

$inserted = 0;
$skipped = 0;
$updated = 0;

foreach ($items as $c) {
    if (!is_array($c)) { $skipped++; continue; }

    $gcName = norm($c['general_contractor_name'] ?? '');
    $gcCompany = norm($c['general_contractor'] ?? '');
    $clientName = $gcName !== '' ? $gcName : $gcCompany;
    if ($clientName === '') { $skipped++; continue; }

    $checkStmt->bind_param('s', $clientName);
    $checkStmt->execute();
    $res = $checkStmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $clientId = isset($row['client_id']) ? (int)$row['client_id'] : 0;
        $pastProjects = isset($row['past_projects']) ? (string)$row['past_projects'] : '';
        $proj = norm($c['dhss_project_number'] ?? '');

        if ($clientId > 0 && $proj !== '') {
            $parts = array_filter(array_map('trim', explode(',', $pastProjects)), function($v){ return $v !== ''; });
            if (!in_array($proj, $parts, true)) {
                $parts[] = $proj;
                $newPast = implode(', ', $parts);
                $updateStmt->bind_param('si', $newPast, $clientId);
                if ($updateStmt->execute()) {
                    $updated++;
                }
            }
        }

        $skipped++;
        continue;
    }

    $clientType = 'General Contractor';
    $unionStatus = normalize_union($c['is_union'] ?? ($c['union'] ?? ''));
    $contactPhone = norm($c['general_contractor_number'] ?? '');
    $clientEmail = norm($c['general_contractor_email'] ?? '');
    $clientAddress = norm($c['general_contractor_address'] ?? '');
    $currentEmployer = $gcCompany;
    $pastProjects = norm($c['dhss_project_number'] ?? '');

    $contactPhone = $contactPhone !== '' ? $contactPhone : null;
    $clientEmail = $clientEmail !== '' ? $clientEmail : null;
    $clientAddress = $clientAddress !== '' ? $clientAddress : null;
    $unionStatus = $unionStatus !== '' ? $unionStatus : null;
    $currentEmployer = $currentEmployer !== '' ? $currentEmployer : null;
    $pastProjects = $pastProjects !== '' ? $pastProjects : null;

    $insertStmt->bind_param(
        'ssssssss',
        $clientName,
        $clientType,
        $unionStatus,
        $contactPhone,
        $clientEmail,
        $clientAddress,
        $currentEmployer,
        $pastProjects
    );

    if ($insertStmt->execute()) {
        $inserted++;
    } else {
        $skipped++;
    }
}

$checkStmt->close();
$insertStmt->close();
$updateStmt->close();

echo json_encode(['success' => true, 'inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped]);
