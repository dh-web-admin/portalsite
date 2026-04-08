<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

// Return aggregated distinct items grouped by detail
$res = $conn->query("SELECT detail, items FROM employee_details WHERE items IS NOT NULL AND TRIM(items) != '' ORDER BY detail ASC, items ASC");
$data = ['operating'=>[], 'life'=>[], 'certs'=>[], 'background'=>[]];
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $d = strtolower(trim($row['detail']));
    $it = trim($row['items']);
    if ($d === '') continue;
    // Map common titles to our keys
    if (strpos($d, 'operat') !== false) $key = 'operating';
    elseif (strpos($d, 'life') !== false) $key = 'life';
    elseif (strpos($d, 'cert') !== false) $key = 'certs';
    elseif (strpos($d, 'background') !== false) $key = 'background';
    else $key = $d;
    if (!isset($data[$key])) $data[$key] = [];
    if ($it !== '' && !in_array($it, $data[$key])) $data[$key][] = $it;
  }
}

echo json_encode($data);
