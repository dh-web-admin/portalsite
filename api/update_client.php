<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$clientId = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
$clientName = isset($_POST['client_name']) ? trim($_POST['client_name']) : '';
$clientNumber = isset($_POST['client_number']) ? trim($_POST['client_number']) : '';
$clientType = isset($_POST['client_type']) ? trim($_POST['client_type']) : '';
$unionStatus = isset($_POST['union_status']) ? trim($_POST['union_status']) : '';
$contactPhone = isset($_POST['contact_phone']) ? trim($_POST['contact_phone']) : '';
$clientEmail = isset($_POST['client_email']) ? trim($_POST['client_email']) : '';
$clientAddress = isset($_POST['client_address']) ? trim($_POST['client_address']) : '';
$city = isset($_POST['city']) ? trim($_POST['city']) : '';
$state = isset($_POST['state']) ? trim($_POST['state']) : '';
$website = isset($_POST['website']) ? trim($_POST['website']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
$familyDetails = isset($_POST['family_details']) ? trim($_POST['family_details']) : '';
$currentEmployer = isset($_POST['current_employer']) ? trim($_POST['current_employer']) : '';
$previousEmployment = isset($_POST['previous_employment']) ? trim($_POST['previous_employment']) : '';
$pastProjects = isset($_POST['past_projects']) ? trim($_POST['past_projects']) : '';

function normalize_union_status($value) {
    $s = strtolower(trim((string)$value));
    if ($s === '') {
        return null;
    }
    if ($s === '1' || $s === 'true' || $s === 'yes' || $s === 'union') {
        return 1;
    }
    if ($s === '0' || $s === 'false' || $s === 'no' || $s === 'non-union' || $s === 'nonunion') {
        return 0;
    }
    return null;
}

// Validate required fields
if (empty($clientId) || empty($clientName)) {
    echo json_encode(['success' => false, 'message' => 'Client ID and Name are required']);
    exit;
}

$existingClientName = '';
$existingClientType = '';
$existingStmt = $conn->prepare('SELECT client_name, client_type FROM clients WHERE client_id = ? LIMIT 1');
if ($existingStmt) {
    $existingStmt->bind_param('i', $clientId);
    if ($existingStmt->execute()) {
        $res = $existingStmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $existingClientName = isset($row['client_name']) ? (string)$row['client_name'] : '';
            $existingClientType = isset($row['client_type']) ? (string)$row['client_type'] : '';
        }
    }
    $existingStmt->close();
}

// Update database
$stmt = $conn->prepare("UPDATE clients SET client_name = ?, client_number = ?, client_type = ?, union_status = ?, contact_phone = ?, client_email = ?, client_address = ?, city = ?, state = ?, website = ?, notes = ?, family_details = ?, current_employer = ?, previous_employment = ?, past_projects = ? WHERE client_id = ?");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('sssssssssssssssi', $clientName, $clientNumber, $clientType, $unionStatus, $contactPhone, $clientEmail, $clientAddress, $city, $state, $website, $notes, $familyDetails, $currentEmployer, $previousEmployment, $pastProjects, $clientId);

if ($stmt->execute()) {
    $gcUpdated = 0;
    $partsUpdated = 0;
    $isGcNow = strtolower($clientType) === 'general contractor';
    $wasGc = strtolower($existingClientType) === 'general contractor';
    $shouldSyncGc = $isGcNow || $wasGc;
    $lookupName = $existingClientName !== '' ? $existingClientName : $clientName;

    if ($shouldSyncGc && $lookupName !== '') {
        $gcStmt = $conn->prepare(
            'UPDATE general_contractor SET general_contractor_name = ?, general_contractor = ?, general_contractor_number = ?, general_contractor_email = ?, general_contractor_address = ?, is_union = ? WHERE LOWER(general_contractor_name) = LOWER(?)'
        );
        if ($gcStmt) {
            $isUnion = normalize_union_status($unionStatus);
            $gcStmt->bind_param(
                'sssssis',
                $clientName,
                $currentEmployer,
                $contactPhone,
                $clientEmail,
                $clientAddress,
                $isUnion,
                $lookupName
            );
            if ($gcStmt->execute()) {
                $gcUpdated = $gcStmt->affected_rows;
            }
            $gcStmt->close();
        }
    }

    $isPartsNow = strtolower($clientType) === 'parts supplier';
    $wasParts = strtolower($existingClientType) === 'parts supplier';
    $shouldSyncParts = $isPartsNow || $wasParts;

    if ($shouldSyncParts && $lookupName !== '') {
        $partsStmt = $conn->prepare(
            'UPDATE part_specifications SET supplier_name = ?, supplier = ?, supplier_number = ?, supplier_email = ?, supplier_address = ? WHERE LOWER(supplier_name) = LOWER(?)'
        );
        if ($partsStmt) {
            $partsStmt->bind_param(
                'ssssss',
                $clientName,
                $currentEmployer,
                $contactPhone,
                $clientEmail,
                $clientAddress,
                $lookupName
            );
            if ($partsStmt->execute()) {
                $partsUpdated = $partsStmt->affected_rows;
            }
            $partsStmt->close();
        }
    }

    echo json_encode(['success' => true, 'message' => 'Client updated successfully', 'gc_updated' => $gcUpdated, 'parts_updated' => $partsUpdated]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
?>
