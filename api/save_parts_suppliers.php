<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

require_edit_api('equipments');

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
if (isset($input['suppliers']) && is_array($input['suppliers'])) {
  $items = $input['suppliers'];
} elseif (isset($input['suppliers'])) {
  $decoded = json_decode((string)$input['suppliers'], true);
  if (is_array($decoded)) $items = $decoded;
}

if (empty($items)) {
  echo json_encode(['success' => true, 'inserted' => 0, 'updated' => 0, 'skipped' => 0]);
  exit;
}

function norm($v) {
  return trim((string)$v);
}

$selectByNameCompany = $conn->prepare('SELECT client_id, client_name, current_employer, contact_phone, client_email, client_address, client_type, past_projects FROM clients WHERE LOWER(client_name) = LOWER(?) AND LOWER(current_employer) = LOWER(?) LIMIT 1');
$selectByName = $conn->prepare('SELECT client_id, client_name, current_employer, contact_phone, client_email, client_address, client_type, past_projects FROM clients WHERE LOWER(client_name) = LOWER(?) LIMIT 1');
$selectByCompany = $conn->prepare('SELECT client_id, client_name, current_employer, contact_phone, client_email, client_address, client_type, past_projects FROM clients WHERE LOWER(current_employer) = LOWER(?) LIMIT 1');
$selectByEmail = $conn->prepare('SELECT client_id, client_name, current_employer, contact_phone, client_email, client_address, client_type, past_projects FROM clients WHERE LOWER(client_email) = LOWER(?) LIMIT 1');

$insertStmt = $conn->prepare('INSERT INTO clients (client_name, client_type, contact_phone, client_email, client_address, current_employer, past_projects) VALUES (?,?,?,?,?,?,?)');
$updateStmt = $conn->prepare('UPDATE clients SET client_name = ?, client_type = ?, contact_phone = ?, client_email = ?, client_address = ?, current_employer = ?, past_projects = ? WHERE client_id = ?');

if (!$selectByNameCompany || !$selectByName || !$selectByCompany || !$selectByEmail || !$insertStmt || !$updateStmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
  exit;
}

$inserted = 0;
$updated = 0;
$skipped = 0;

foreach ($items as $c) {
  if (!is_array($c)) { $skipped++; continue; }

  $clientName = norm($c['supplier_name'] ?? '');
  $company = norm($c['supplier'] ?? '');
  $phone = norm($c['supplier_number'] ?? '');
  $email = norm($c['supplier_email'] ?? '');
  $address = norm($c['supplier_address'] ?? '');
  $partName = norm($c['part_name'] ?? '');
  $nsn = norm($c['nsn_number'] ?? '');

  if ($clientName === '' && $company === '' && $email === '') { $skipped++; continue; }

  $interaction = $partName;
  if ($nsn !== '') {
    $interaction = $interaction !== '' ? ($interaction . ' (' . $nsn . ')') : ('(' . $nsn . ')');
  }

  $row = null;
  if ($clientName !== '' && $company !== '') {
    $selectByNameCompany->bind_param('ss', $clientName, $company);
    $selectByNameCompany->execute();
    $res = $selectByNameCompany->get_result();
    if ($res && $res->num_rows > 0) $row = $res->fetch_assoc();
  }
  if (!$row && $clientName !== '') {
    $selectByName->bind_param('s', $clientName);
    $selectByName->execute();
    $res = $selectByName->get_result();
    if ($res && $res->num_rows > 0) $row = $res->fetch_assoc();
  }
  if (!$row && $company !== '') {
    $selectByCompany->bind_param('s', $company);
    $selectByCompany->execute();
    $res = $selectByCompany->get_result();
    if ($res && $res->num_rows > 0) $row = $res->fetch_assoc();
  }
  if (!$row && $email !== '') {
    $selectByEmail->bind_param('s', $email);
    $selectByEmail->execute();
    $res = $selectByEmail->get_result();
    if ($res && $res->num_rows > 0) $row = $res->fetch_assoc();
  }

  if ($row) {
    $clientId = (int)($row['client_id'] ?? 0);
    if ($clientId <= 0) { $skipped++; continue; }

    $past = isset($row['past_projects']) ? (string)$row['past_projects'] : '';
    if ($interaction !== '') {
      $parts = array_filter(array_map('trim', explode(',', $past)), function($v){ return $v !== ''; });
      if (!in_array($interaction, $parts, true)) {
        $parts[] = $interaction;
      }
      $past = implode(', ', $parts);
    }

    $newName = $clientName !== '' ? $clientName : (string)($row['client_name'] ?? '');
    $newCompany = $company !== '' ? $company : (string)($row['current_employer'] ?? '');
    $newPhone = $phone !== '' ? $phone : (string)($row['contact_phone'] ?? '');
    $newEmail = $email !== '' ? $email : (string)($row['client_email'] ?? '');
    $newAddress = $address !== '' ? $address : (string)($row['client_address'] ?? '');

    $clientType = 'Parts Supplier';

    $newName = $newName !== '' ? $newName : null;
    $newCompany = $newCompany !== '' ? $newCompany : null;
    $newPhone = $newPhone !== '' ? $newPhone : null;
    $newEmail = $newEmail !== '' ? $newEmail : null;
    $newAddress = $newAddress !== '' ? $newAddress : null;
    $past = $past !== '' ? $past : null;

    $updateStmt->bind_param('sssssssi', $newName, $clientType, $newPhone, $newEmail, $newAddress, $newCompany, $past, $clientId);
    if ($updateStmt->execute()) {
      $updated++;
    } else {
      $skipped++;
    }
    continue;
  }

  $clientType = 'Parts Supplier';
  $clientNameForInsert = $clientName !== '' ? $clientName : $company;
  if ($clientNameForInsert === '') { $skipped++; continue; }

  $currentEmployer = $company !== '' ? $company : null;
  $contactPhone = $phone !== '' ? $phone : null;
  $clientEmail = $email !== '' ? $email : null;
  $clientAddress = $address !== '' ? $address : null;
  $pastProjects = $interaction !== '' ? $interaction : null;

  $insertStmt->bind_param(
    'sssssss',
    $clientNameForInsert,
    $clientType,
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

$selectByNameCompany->close();
$selectByName->close();
$selectByCompany->close();
$selectByEmail->close();
$insertStmt->close();
$updateStmt->close();

echo json_encode(['success' => true, 'inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped]);
