<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

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

// Validate required fields
if (empty($clientName)) {
    echo json_encode(['success' => false, 'message' => 'Client Name is required']);
    exit;
}

// Insert into database
$stmt = $conn->prepare("INSERT INTO clients (client_name, client_number, client_type, union_status, contact_phone, client_email, client_address, city, state, website, notes, family_details, current_employer, previous_employment, past_projects) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('sssssssssssssss', $clientName, $clientNumber, $clientType, $unionStatus, $contactPhone, $clientEmail, $clientAddress, $city, $state, $website, $notes, $familyDetails, $currentEmployer, $previousEmployment, $pastProjects);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Client added successfully', 'id' => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
?>
