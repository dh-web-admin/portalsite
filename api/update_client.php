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
$clientEmail = isset($_POST['client_email']) ? trim($_POST['client_email']) : '';
$clientAddress = isset($_POST['client_address']) ? trim($_POST['client_address']) : '';
$city = isset($_POST['city']) ? trim($_POST['city']) : '';
$state = isset($_POST['state']) ? trim($_POST['state']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
$familyDetails = isset($_POST['family_details']) ? trim($_POST['family_details']) : '';
$currentRole = isset($_POST['current_role']) ? trim($_POST['current_role']) : '';
$previousEmployment = isset($_POST['previous_employment']) ? trim($_POST['previous_employment']) : '';
$pastProjects = isset($_POST['past_projects']) ? trim($_POST['past_projects']) : '';

// Validate required fields
if (empty($clientId) || empty($clientName)) {
    echo json_encode(['success' => false, 'message' => 'Client ID and Name are required']);
    exit;
}

// Update database
$stmt = $conn->prepare("UPDATE clients SET client_name = ?, client_number = ?, client_email = ?, client_address = ?, city = ?, state = ?, notes = ?, family_details = ?, `current_role` = ?, previous_employment = ?, past_projects = ? WHERE client_id = ?");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('sssssssssssi', $clientName, $clientNumber, $clientEmail, $clientAddress, $city, $state, $notes, $familyDetails, $currentRole, $previousEmployment, $pastProjects, $clientId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Client updated successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
?>
