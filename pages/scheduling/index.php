<?php
require_once __DIR__ . '/../../session_init.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../partials/permissions.php';

$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$stmt->close();

if (!can_access($role, 'scheduling')) {
  header('Location: /pages/dashboard/');
  exit();
}

$formError = '';

$conn->query("CREATE TABLE IF NOT EXISTS scheduled_projects (
  project_id INT AUTO_INCREMENT PRIMARY KEY,
  project_name VARCHAR(255) NOT NULL,
  `start` DATETIME NOT NULL,
  `end` DATETIME NOT NULL,
  exclude_weekends TINYINT(1) NOT NULL DEFAULT 0,
  location VARCHAR(255) DEFAULT ''
  ,details TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query('CREATE TABLE IF NOT EXISTS scheduled_project_details (
  project_id INT NOT NULL,
  `day` DATE NOT NULL,
  equipments TEXT NULL,
  personnel TEXT NULL,
  PRIMARY KEY (project_id, `day`),
  CONSTRAINT fk_scheduled_project_details_project FOREIGN KEY (project_id)
    REFERENCES scheduled_projects(project_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

require_once __DIR__ . '/../../partials/project_uploads.php';
project_uploads_ensure_schema($conn);
// Images staged in the "Add Project" modal hang on this key until the project exists.
$noteUploadDraftKey = bin2hex(random_bytes(12));
project_uploads_purge_stale_drafts($conn);

// URL prefix the app is mounted on ('' in production, '/portalsite' under XAMPP),
// so /api and /uploads links resolve in both environments.
$appBaseUrl = preg_replace('#/pages/scheduling/[^/]*$#', '', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
if ($appBaseUrl === '/') { $appBaseUrl = ''; }

$startColumnSql = '`start`';
$endColumnSql = '`end`';
$projectColumns = [];
$colsRes = $conn->query('SHOW COLUMNS FROM scheduled_projects');
if ($colsRes) {
  while ($col = $colsRes->fetch_assoc()) {
    if (!empty($col['Field'])) {
      $projectColumns[$col['Field']] = true;
    }
  }
}
if (!isset($projectColumns['start']) && isset($projectColumns['start_datetime'])) {
  $startColumnSql = '`start_datetime`';
}
if (!isset($projectColumns['end']) && isset($projectColumns['end_datetime'])) {
  $endColumnSql = '`end_datetime`';
}
// Ensure exclude_weekends column exists for older schemas
if (!isset($projectColumns['exclude_weekends'])) {
  $conn->query("ALTER TABLE scheduled_projects ADD COLUMN exclude_weekends TINYINT(1) NOT NULL DEFAULT 0");
  $projectColumns['exclude_weekends'] = true;
}
// Ensure location column exists for older schemas
if (!isset($projectColumns['location'])) {
  $conn->query("ALTER TABLE scheduled_projects ADD COLUMN location VARCHAR(255) DEFAULT ''");
  $projectColumns['location'] = true;
}
// Ensure details column exists for older schemas
if (!isset($projectColumns['details'])) {
  $conn->query("ALTER TABLE scheduled_projects ADD COLUMN details TEXT NULL");
  $projectColumns['details'] = true;
}

// Optional project-detail columns (Project Information / Owner / Accommodation /
// General Contractor). Only the ones that exist in the table are read/written.
$projectMetaTextColumns = array_values(array_filter([
  'project_address', 'project_city', 'project_state',
  'hotel_name', 'hotel_address', 'hotel_confirmation', 'hotel_phone',
  'owner_name', 'owner_email', 'owner_address', 'owner_contact_name', 'owner_phone',
  'contractor_name', 'contractor_email', 'contractor_address', 'contractor_contact_name', 'contractor_phone',
], function ($c) use ($projectColumns) { return isset($projectColumns[$c]); }));
$projectMetaIntColumns = array_values(array_filter(['hotel_rooms'], function ($c) use ($projectColumns) { return isset($projectColumns[$c]); }));
$projectMetaBoolColumns = array_values(array_filter(['taxable', 'certified', 'permit'], function ($c) use ($projectColumns) { return isset($projectColumns[$c]); }));
$projectMetaAllColumns = array_merge($projectMetaTextColumns, $projectMetaIntColumns, $projectMetaBoolColumns);

// Normalize a POSTed value for one meta column.
function scheduling_meta_value_for($col, $raw, $intCols, $boolCols) {
  $s = trim((string)$raw);
  if (in_array($col, $boolCols, true)) {
    $lc = strtolower($s);
    if ($lc === 'yes' || $s === '1') return 1;
    if ($lc === 'no' || $s === '0') return 0;
    return null;
  }
  if (in_array($col, $intCols, true)) {
    return ($s === '' || !is_numeric($s)) ? null : (int)$s;
  }
  return ($s === '') ? null : $s;
}

// If the table existed previously without a `day` column, add it and migrate existing rows
$colsRes = $conn->query("SHOW COLUMNS FROM scheduled_project_details LIKE 'day'");
if ($colsRes && $colsRes->num_rows === 0) {
  // add the day column with a temporary default
  $conn->query("ALTER TABLE scheduled_project_details ADD COLUMN `day` DATE NOT NULL DEFAULT '1970-01-01'");
  // populate day with the project's start date where possible
  $conn->query('UPDATE scheduled_project_details spd JOIN scheduled_projects sp ON spd.project_id = sp.project_id SET spd.`day` = DATE(sp.' . $startColumnSql . ')');
  // change primary key to (project_id, day)
  $conn->query('ALTER TABLE scheduled_project_details DROP PRIMARY KEY, ADD PRIMARY KEY (project_id, `day`)');
  // remove temporary default (set to no default)
  $conn->query("ALTER TABLE scheduled_project_details MODIFY COLUMN `day` DATE NOT NULL");
}

// Bulk save handler to persist client-side per-day details in one request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'bulk_save_project_details') {
  header('Content-Type: application/json; charset=utf-8');
  try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['entries']) || !is_array($data['entries'])) {
      http_response_code(400);
      echo json_encode(['success' => false, 'message' => 'Invalid payload']);
      exit();
    }

    $stmt = $conn->prepare('INSERT INTO scheduled_project_details (project_id, `day`, equipments, personnel) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE equipments = VALUES(equipments), personnel = VALUES(personnel)');
    if (!$stmt) {
      throw new Exception('Unable to prepare statement');
    }

    foreach ($data['entries'] as $entry) {
      $projectId = isset($entry['project_id']) ? (int)$entry['project_id'] : 0;
      $day = isset($entry['day']) ? trim((string)$entry['day']) : '';
      $equipments = isset($entry['equipments']) ? (string)$entry['equipments'] : '';
      $personnel = isset($entry['personnel']) ? (string)$entry['personnel'] : '';

      if ($projectId <= 0 || $day === '') {
        continue;
      }

      $stmt->bind_param('isss', $projectId, $day, $equipments, $personnel);
      $stmt->execute();
    }
    $stmt->close();

    echo json_encode(['success' => true]);
    exit();
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save details']);
    exit();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_project_requirement') {
  header('Content-Type: application/json; charset=utf-8');

  $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
  $kind = trim((string)($_POST['kind'] ?? ''));
  $value = trim((string)($_POST['value'] ?? ''));

  if ($projectId <= 0 || ($kind !== 'equipments' && $kind !== 'personnel') || $value === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
  }

  try {
    $existsStmt = $conn->prepare('SELECT project_id FROM scheduled_projects WHERE project_id = ? LIMIT 1');
    if (!$existsStmt) {
      throw new Exception('Prepare failed');
    }
    $existsStmt->bind_param('i', $projectId);
    $existsStmt->execute();
    $existsRes = $existsStmt->get_result();
    $projectExists = $existsRes && $existsRes->fetch_assoc();
    $existsStmt->close();

    if (!$projectExists) {
      http_response_code(404);
      echo json_encode(['success' => false, 'message' => 'Project not found']);
      exit();
    }

    // determine target day (optional POST param 'day'), otherwise use project's start date
    $day = isset($_POST['day']) ? trim($_POST['day']) : null;
    if ($day === null || $day === '') {
      $projStmt = $conn->prepare('SELECT ' . $startColumnSql . ' AS start FROM scheduled_projects WHERE project_id = ? LIMIT 1');
      $projStmt->bind_param('i', $projectId);
      $projStmt->execute();
      $projRes = $projStmt->get_result();
      $projRow = $projRes ? $projRes->fetch_assoc() : null;
      $projStmt->close();
      $day = $projRow && !empty($projRow['start']) ? date('Y-m-d', strtotime($projRow['start'])) : date('Y-m-d');
    }

    // A requirement belongs to the selected day only; assignments on other project days stay unchanged.
    $projectDays = [$day];

    if (empty($projectDays)) {
      $projRangeStmt = $conn->prepare('SELECT ' . $startColumnSql . ' AS start, ' . $endColumnSql . ' AS end FROM scheduled_projects WHERE project_id = ? LIMIT 1');
      if (!$projRangeStmt) {
        throw new Exception('Unable to fetch project date range');
      }
      $projRangeStmt->bind_param('i', $projectId);
      $projRangeStmt->execute();
      $projRangeRes = $projRangeStmt->get_result();
      $projRangeRow = $projRangeRes ? $projRangeRes->fetch_assoc() : null;
      $projRangeStmt->close();

      if ($projRangeRow && !empty($projRangeRow['start']) && !empty($projRangeRow['end'])) {
        $cursor = new DateTimeImmutable(date('Y-m-d', strtotime($projRangeRow['start'])));
        $end = new DateTimeImmutable(date('Y-m-d', strtotime($projRangeRow['end'])));
        while ($cursor <= $end) {
          $projectDays[] = $cursor->format('Y-m-d');
          $cursor = $cursor->modify('+1 day');
        }
      }
    }

    if (empty($projectDays)) {
      $projectDays = [$day];
    }

    $ensureStmt = $conn->prepare('INSERT INTO scheduled_project_details (project_id, `day`, equipments, personnel) VALUES (?, ?, "", "") ON DUPLICATE KEY UPDATE project_id = project_id');
    if (!$ensureStmt) {
      throw new Exception('Unable to ensure project details row');
    }
    foreach ($projectDays as $projectDay) {
      $ensureStmt->bind_param('is', $projectId, $projectDay);
      $ensureStmt->execute();
    }
    $ensureStmt->close();

    $dayResults = [];
    foreach ($projectDays as $projectDay) {
      $detailsStmt = $conn->prepare('SELECT COALESCE(equipments, "") AS equipments, COALESCE(personnel, "") AS personnel FROM scheduled_project_details WHERE project_id = ? AND `day` = ? LIMIT 1');
      if (!$detailsStmt) {
        throw new Exception('Unable to fetch project details');
      }
      $detailsStmt->bind_param('is', $projectId, $projectDay);
      $detailsStmt->execute();
      $detailsRes = $detailsStmt->get_result();
      $details = $detailsRes ? $detailsRes->fetch_assoc() : ['equipments' => '', 'personnel' => ''];
      $detailsStmt->close();

      $currentCsv = isset($details[$kind]) ? (string)$details[$kind] : '';
      $items = array_values(array_filter(array_map('trim', explode(',', $currentCsv)), function ($item) {
        return $item !== '';
      }));

      if (!in_array($value, $items, true)) {
        $items[] = $value;
      }
      $updatedCsv = implode(', ', $items);

      if ($kind === 'equipments') {
        $updateStmt = $conn->prepare('UPDATE scheduled_project_details SET equipments = ? WHERE project_id = ? AND `day` = ?');
      } else {
        $updateStmt = $conn->prepare('UPDATE scheduled_project_details SET personnel = ? WHERE project_id = ? AND `day` = ?');
      }
      if (!$updateStmt) {
        throw new Exception('Unable to update project details');
      }
      $updateStmt->bind_param('sis', $updatedCsv, $projectId, $projectDay);
      $updateStmt->execute();
      $updateStmt->close();
      $details[$kind] = $updatedCsv;

      $dayResults[] = [
        'day' => $projectDay,
        'equipments' => (string)($details['equipments'] ?? ''),
        'personnel' => (string)($details['personnel'] ?? '')
      ];
    }

    $selectedFinalStmt = $conn->prepare('SELECT COALESCE(equipments, "") AS equipments, COALESCE(personnel, "") AS personnel FROM scheduled_project_details WHERE project_id = ? AND `day` = ? LIMIT 1');
    if (!$selectedFinalStmt) {
      throw new Exception('Unable to fetch updated details');
    }
    $selectedFinalStmt->bind_param('is', $projectId, $day);
    $selectedFinalStmt->execute();
    $selectedFinalRes = $selectedFinalStmt->get_result();
    $selectedFinalDetails = $selectedFinalRes ? $selectedFinalRes->fetch_assoc() : ['equipments' => '', 'personnel' => ''];
    $selectedFinalStmt->close();

    echo json_encode([
      'success' => true,
      'equipments' => (string)($selectedFinalDetails['equipments'] ?? ''),
      'personnel' => (string)($selectedFinalDetails['personnel'] ?? ''),
      'days' => $dayResults
    ]);
    exit();
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update requirement']);
    exit();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_project_requirement') {
  header('Content-Type: application/json; charset=utf-8');

  $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
  $kind = trim((string)($_POST['kind'] ?? ''));
  $value = trim((string)($_POST['value'] ?? ''));

  if ($projectId <= 0 || ($kind !== 'equipments' && $kind !== 'personnel') || $value === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
  }

  try {
    // determine target day (optional POST param 'day'), otherwise use project's start date
    $day = isset($_POST['day']) ? trim($_POST['day']) : null;
    if ($day === null || $day === '') {
      $projStmt = $conn->prepare('SELECT ' . $startColumnSql . ' AS start FROM scheduled_projects WHERE project_id = ? LIMIT 1');
      $projStmt->bind_param('i', $projectId);
      $projStmt->execute();
      $projRes = $projStmt->get_result();
      $projRow = $projRes ? $projRes->fetch_assoc() : null;
      $projStmt->close();
      $day = $projRow && !empty($projRow['start']) ? date('Y-m-d', strtotime($projRow['start'])) : date('Y-m-d');
    }

    $detailsStmt = $conn->prepare('SELECT COALESCE(equipments, "") AS equipments, COALESCE(personnel, "") AS personnel FROM scheduled_project_details WHERE project_id = ? AND `day` = ? LIMIT 1');
    if (!$detailsStmt) {
      throw new Exception('Unable to fetch project details');
    }
    $detailsStmt->bind_param('is', $projectId, $day);
    $detailsStmt->execute();
    $detailsRes = $detailsStmt->get_result();
    $details = $detailsRes ? $detailsRes->fetch_assoc() : null;
    $detailsStmt->close();

    if (!$details) {
      http_response_code(404);
      echo json_encode(['success' => false, 'message' => 'Project details not found']);
      exit();
    }

    $currentCsv = isset($details[$kind]) ? (string)$details[$kind] : '';
    $items = array_values(array_filter(array_map('trim', explode(',', $currentCsv)), function ($item) {
      return $item !== '';
    }));

    $items = array_values(array_filter($items, function ($item) use ($value) {
      return $item !== $value;
    }));

    $updatedCsv = implode(', ', $items);
    if ($kind === 'equipments') {
      $updateStmt = $conn->prepare('UPDATE scheduled_project_details SET equipments = ? WHERE project_id = ? AND `day` = ?');
    } else {
      $updateStmt = $conn->prepare('UPDATE scheduled_project_details SET personnel = ? WHERE project_id = ? AND `day` = ?');
    }
    if (!$updateStmt) {
      throw new Exception('Unable to update project details');
    }
    $updateStmt->bind_param('sis', $updatedCsv, $projectId, $day);
    $updateStmt->execute();
    $updateStmt->close();

    $finalStmt = $conn->prepare('SELECT COALESCE(equipments, "") AS equipments, COALESCE(personnel, "") AS personnel FROM scheduled_project_details WHERE project_id = ? AND `day` = ? LIMIT 1');
    if (!$finalStmt) {
      throw new Exception('Unable to fetch updated details');
    }
    $finalStmt->bind_param('is', $projectId, $day);
    $finalStmt->execute();
    $finalRes = $finalStmt->get_result();
    $finalDetails = $finalRes ? $finalRes->fetch_assoc() : ['equipments' => '', 'personnel' => ''];
    $finalStmt->close();

    echo json_encode([
      'success' => true,
      'equipments' => (string)($finalDetails['equipments'] ?? ''),
      'personnel' => (string)($finalDetails['personnel'] ?? '')
    ]);
    exit();
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to remove requirement']);
    exit();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_scheduled_project') {
  header('Content-Type: application/json; charset=utf-8');

  $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
  if ($projectId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid project id']);
    exit();
  }

  try {
    // The upload rows go with the project (FK cascade); drop their files too.
    foreach (project_uploads_list($conn, $projectId) as $noteImage) {
      project_uploads_unlink((string)$noteImage['filename']);
    }

    $stmtDelete = $conn->prepare('DELETE FROM scheduled_projects WHERE project_id = ? LIMIT 1');
    if (!$stmtDelete) {
      throw new Exception('Unable to prepare delete');
    }
    $stmtDelete->bind_param('i', $projectId);
    $stmtDelete->execute();
    $deleted = $stmtDelete->affected_rows > 0;
    $stmtDelete->close();

    if (!$deleted) {
      http_response_code(404);
      echo json_encode(['success' => false, 'message' => 'Project not found']);
      exit();
    }

    echo json_encode(['success' => true]);
    exit();
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to delete project']);
    exit();
  }
}

// Delete a single scheduled_project_details row for a project on a specific day
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_project_day') {
  header('Content-Type: application/json; charset=utf-8');
  $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
  $day = isset($_POST['day']) ? trim((string)$_POST['day']) : '';
  if ($projectId <= 0 || $day === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
  }

  // basic YYYY-MM-DD validation
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date']);
    exit();
  }

  try {
    $delStmt = $conn->prepare('DELETE FROM scheduled_project_details WHERE project_id = ? AND `day` = ? LIMIT 1');
    if (!$delStmt) {
      throw new Exception('Unable to prepare delete');
    }
    $delStmt->bind_param('is', $projectId, $day);
    $delStmt->execute();
    $deleted = $delStmt->affected_rows > 0;
    $delStmt->close();

    if (!$deleted) {
      http_response_code(404);
      echo json_encode(['success' => false, 'message' => 'Day not found']);
      exit();
    }

    echo json_encode(['success' => true]);
    exit();
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to delete day']);
    exit();
  }
}

// Update project meta (location shared across all days)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_project_meta') {
  header('Content-Type: application/json; charset=utf-8');
  $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
  $location = isset($_POST['location']) ? trim((string)$_POST['location']) : '';
  $details = isset($_POST['details']) ? trim((string)$_POST['details']) : '';
  if ($projectId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid project id']);
    exit();
  }

  try {
    $up = $conn->prepare('UPDATE scheduled_projects SET location = ?, details = ? WHERE project_id = ? LIMIT 1');
    if (!$up) throw new Exception('Unable to prepare update');
    $up->bind_param('ssi', $location, $details, $projectId);
    $up->execute();
    $ok = $up->affected_rows >= 0; // 0 may mean no change but request succeeded
    $up->close();
    if (!$ok) {
      http_response_code(500);
      echo json_encode(['success' => false, 'message' => 'Unable to update']);
      exit();
    }

    echo json_encode(['success' => true, 'location' => $location, 'details' => $details]);
    exit();
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update project']);
    exit();
  }
}

// Replace the full set of scheduled days for a project (calendar edit in the
// Project Details modal). Adds detail rows for newly-selected days, removes rows
// for unselected days, and keeps the legacy start/end range columns in sync.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_project_days') {
  header('Content-Type: application/json; charset=utf-8');

  $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
  $daysRaw = json_decode((string)($_POST['days'] ?? '[]'), true);
  if (!is_array($daysRaw)) {
    $daysRaw = [];
  }

  $daySet = [];
  foreach ($daysRaw as $candidate) {
    if (!is_string($candidate)) {
      continue;
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $candidate);
    if ($parsed && $parsed->format('Y-m-d') === $candidate) {
      $daySet[$candidate] = true;
    }
  }
  $days = array_keys($daySet);
  sort($days);

  if ($projectId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid project id']);
    exit();
  }
  if (empty($days)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A project must keep at least one scheduled day']);
    exit();
  }

  try {
    $existsStmt = $conn->prepare('SELECT project_id FROM scheduled_projects WHERE project_id = ? LIMIT 1');
    if (!$existsStmt) {
      throw new Exception('Prepare failed');
    }
    $existsStmt->bind_param('i', $projectId);
    $existsStmt->execute();
    $projectExists = ($existsRes = $existsStmt->get_result()) && $existsRes->fetch_assoc();
    $existsStmt->close();
    if (!$projectExists) {
      http_response_code(404);
      echo json_encode(['success' => false, 'message' => 'Project not found']);
      exit();
    }

    $existingDays = [];
    $existingStmt = $conn->prepare('SELECT `day` FROM scheduled_project_details WHERE project_id = ?');
    if (!$existingStmt) {
      throw new Exception('Prepare failed');
    }
    $existingStmt->bind_param('i', $projectId);
    $existingStmt->execute();
    $existingRes = $existingStmt->get_result();
    while ($existingRes && ($existingRow = $existingRes->fetch_assoc())) {
      $existingDays[] = (string)$existingRow['day'];
    }
    $existingStmt->close();

    $daysToRemove = array_values(array_filter($existingDays, function ($day) use ($daySet) {
      return !isset($daySet[$day]);
    }));

    $conn->begin_transaction();
    try {
      $ensureStmt = $conn->prepare('INSERT INTO scheduled_project_details (project_id, `day`, equipments, personnel) VALUES (?, ?, "", "") ON DUPLICATE KEY UPDATE project_id = project_id');
      if (!$ensureStmt) {
        throw new Exception('Prepare failed');
      }
      foreach ($days as $day) {
        $ensureStmt->bind_param('is', $projectId, $day);
        $ensureStmt->execute();
      }
      $ensureStmt->close();

      if (!empty($daysToRemove)) {
        $removeStmt = $conn->prepare('DELETE FROM scheduled_project_details WHERE project_id = ? AND `day` = ?');
        if (!$removeStmt) {
          throw new Exception('Prepare failed');
        }
        foreach ($daysToRemove as $day) {
          $removeStmt->bind_param('is', $projectId, $day);
          $removeStmt->execute();
        }
        $removeStmt->close();
      }

      $startDb = $days[0] . ' 07:00:00';
      $endDb = $days[count($days) - 1] . ' 18:00:00';
      $rangeStmt = $conn->prepare('UPDATE scheduled_projects SET ' . $startColumnSql . ' = ?, ' . $endColumnSql . ' = ? WHERE project_id = ?');
      if (!$rangeStmt) {
        throw new Exception('Prepare failed');
      }
      $rangeStmt->bind_param('ssi', $startDb, $endDb, $projectId);
      $rangeStmt->execute();
      $rangeStmt->close();

      $conn->commit();
    } catch (Throwable $inner) {
      $conn->rollback();
      throw $inner;
    }

    $rows = [];
    $rowsStmt = $conn->prepare('SELECT `day`, COALESCE(equipments, "") AS equipments, COALESCE(personnel, "") AS personnel FROM scheduled_project_details WHERE project_id = ? ORDER BY `day` ASC');
    $rowsStmt->bind_param('i', $projectId);
    $rowsStmt->execute();
    $rowsRes = $rowsStmt->get_result();
    while ($rowsRes && ($row = $rowsRes->fetch_assoc())) {
      $rows[] = [
        'day' => (string)$row['day'],
        'equipments' => (string)$row['equipments'],
        'personnel' => (string)$row['personnel']
      ];
    }
    $rowsStmt->close();

    echo json_encode([
      'success' => true,
      'days' => $rows,
      'start' => $startDb,
      'end' => $endDb
    ]);
    exit();
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update project days']);
    exit();
  }
}

// When "Add Project" pulls from an existing project, gather Project Information +
// General Contractor details from Bid Tracking (bids + winning general_contractor)
// and the Project Checklist (Projects), flagging fields where the two disagree.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'import_existing_project') {
  header('Content-Type: application/json; charset=utf-8');
  try {
    $checklistId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
    $rawName = isset($_GET['project_name']) ? trim((string)$_GET['project_name']) : '';

    $tableExists = function ($t) use ($conn) {
      $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
      return $r && $r->num_rows > 0;
    };
    $lower = function ($row) { return is_array($row) ? array_change_key_case($row, CASE_LOWER) : []; };

    // --- Project Checklist row ---
    $checklist = null;
    if ($checklistId > 0 && $tableExists('Projects')) {
      $st = $conn->prepare('SELECT * FROM Projects WHERE Project_ID = ? LIMIT 1');
      if ($st) {
        $st->bind_param('i', $checklistId);
        $st->execute();
        $rs = $st->get_result();
        $checklist = $rs ? $rs->fetch_assoc() : null;
        $st->close();
      }
    }
    $cl = $lower($checklist);
    $checklistName = $checklist ? (string)($cl['project_name'] ?? '') : $rawName;

    // Checklist names are often stored as "260007 - Project Name".
    $dhss = '';
    $cleanName = trim($checklistName);
    if (preg_match('/^\s*([0-9]{3,})\s*[-\x{2013}\x{2014}]\s*(.+)$/u', $checklistName, $mm)) {
      $dhss = trim($mm[1]);
      $cleanName = trim($mm[2]);
    } elseif (preg_match('/\b([0-9]{5,6})\b/', $checklistName, $mm2)) {
      // Fallback: a 5-6 digit project number embedded anywhere in the name.
      $dhss = $mm2[1];
    }

    // --- Bid Tracking row ---
    $bid = null;
    if ($tableExists('bids')) {
      if ($dhss !== '') {
        $st = $conn->prepare('SELECT * FROM bids WHERE dhss_project_number = ? OR TRIM(LEADING "0" FROM dhss_project_number) = TRIM(LEADING "0" FROM ?) ORDER BY bid_id DESC LIMIT 1');
        if ($st) { $st->bind_param('ss', $dhss, $dhss); $st->execute(); $rs = $st->get_result(); $bid = $rs ? $rs->fetch_assoc() : null; $st->close(); }
      }
      if (!$bid && $cleanName !== '') {
        $st = $conn->prepare('SELECT * FROM bids WHERE LOWER(TRIM(project_name)) = LOWER(TRIM(?)) ORDER BY bid_id DESC LIMIT 1');
        if ($st) { $st->bind_param('s', $cleanName); $st->execute(); $rs = $st->get_result(); $bid = $rs ? $rs->fetch_assoc() : null; $st->close(); }
      }
    }
    $bl = $lower($bid);
    $bidDhss = $bid ? trim((string)($bl['dhss_project_number'] ?? '')) : $dhss;

    // --- Winning general contractor for that project ---
    $gc = null;
    if ($tableExists('general_contractor') && $bidDhss !== '') {
      $st = $conn->prepare('SELECT * FROM general_contractor WHERE dhss_project_number = ? OR TRIM(LEADING "0" FROM dhss_project_number) = TRIM(LEADING "0" FROM ?) ORDER BY (COALESCE(winner, 0) = 1) DESC, id ASC LIMIT 1');
      if ($st) { $st->bind_param('ss', $bidDhss, $bidDhss); $st->execute(); $rs = $st->get_result(); $gc = $rs ? $rs->fetch_assoc() : null; $st->close(); }
    }
    $gl = $lower($gc);

    // --- Merge candidate values per target field ---
    $normVal = function ($v) { return strtolower(trim(preg_replace('/\s+/', ' ', (string)$v))); };
    $mkField = function (array $candidates) use ($normVal) {
      $seen = [];
      $uniq = [];
      foreach ($candidates as $c) {
        $val = trim((string)($c['value'] ?? ''));
        if ($val === '') continue;
        $k = $normVal($val);
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $uniq[] = ['source' => (string)$c['source'], 'value' => $val];
      }
      if (count($uniq) === 0) return null;
      if (count($uniq) === 1) return ['value' => $uniq[0]['value'], 'source' => $uniq[0]['source'], 'conflict' => false];
      return ['conflict' => true, 'options' => $uniq];
    };

    $gcName = trim((string)($gl['general_contractor_name'] ?? ''));
    if ($gcName === '') $gcName = trim((string)($gl['general_contractor'] ?? ''));

    $fields = [];
    $fields['project_address']   = $mkField([['source' => 'Bid Tracking', 'value' => $bl['project_address'] ?? '']]);
    $fields['project_city']      = $mkField([
      ['source' => 'Bid Tracking', 'value' => $bl['project_city'] ?? ''],
      ['source' => 'Project Checklist', 'value' => $cl['city'] ?? ''],
    ]);
    $fields['project_state']     = $mkField([
      ['source' => 'Bid Tracking', 'value' => $bl['project_state'] ?? ''],
      ['source' => 'Project Checklist', 'value' => $cl['state'] ?? ''],
    ]);
    $fields['contractor_name']   = $mkField([
      ['source' => 'Bid Tracking (winning contractor)', 'value' => $gcName],
      ['source' => 'Project Checklist (Client)', 'value' => $cl['client'] ?? ''],
    ]);
    $fields['contractor_email']  = $mkField([['source' => 'Bid Tracking (winning contractor)', 'value' => $gl['general_contractor_email'] ?? '']]);
    $fields['contractor_address'] = $mkField([['source' => 'Bid Tracking (winning contractor)', 'value' => $gl['general_contractor_address'] ?? '']]);
    $fields['contractor_phone']  = $mkField([['source' => 'Bid Tracking (winning contractor)', 'value' => $gl['general_contractor_number'] ?? '']]);

    $fields = array_filter($fields, function ($f) { return $f !== null; });

    echo json_encode([
      'success' => true,
      'clean_name' => $cleanName,
      'dhss' => $bidDhss,
      'found' => [
        'checklist' => $checklist !== null,
        'bid' => $bid !== null,
        'gc' => $gc !== null,
      ],
      'fields' => (object) $fields,
    ]);
    exit();
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to import project info']);
    exit();
  }
}

// Save the Project Details modal's editable fields (name, notes, and every
// Project Information / Owner / Accommodation / General Contractor field).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'save_project_meta') {
  header('Content-Type: application/json; charset=utf-8');
  try {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) { $payload = $_POST; }

    $projectId = isset($payload['project_id']) ? (int)$payload['project_id'] : 0;
    if ($projectId <= 0) {
      http_response_code(400);
      echo json_encode(['success' => false, 'message' => 'Invalid project id']);
      exit();
    }

    $setCols = [];
    $setTypes = '';
    $setVals = [];

    if (array_key_exists('project_name', $payload)) {
      $pn = trim((string)$payload['project_name']);
      if ($pn !== '') {
        $setCols[] = 'project_name';
        $setTypes .= 's';
        $setVals[] = $pn;
      }
    }
    if (array_key_exists('details', $payload) && isset($projectColumns['details'])) {
      $setCols[] = 'details';
      $setTypes .= 's';
      $dv = trim((string)$payload['details']);
      $setVals[] = ($dv === '') ? null : $dv;
    }

    foreach ($projectMetaAllColumns as $mc) {
      if (!array_key_exists($mc, $payload)) continue;
      $setCols[] = $mc;
      $setTypes .= (in_array($mc, $projectMetaIntColumns, true) || in_array($mc, $projectMetaBoolColumns, true)) ? 'i' : 's';
      $setVals[] = scheduling_meta_value_for($mc, $payload[$mc], $projectMetaIntColumns, $projectMetaBoolColumns);
    }

    // Keep the legacy "location" string in sync with city/state so calendar tiles still show it.
    if (isset($projectColumns['location']) && (array_key_exists('project_city', $payload) || array_key_exists('project_state', $payload))) {
      $locParts = array_values(array_filter([
        trim((string)($payload['project_city'] ?? '')),
        trim((string)($payload['project_state'] ?? '')),
      ], function ($p) { return $p !== ''; }));
      $setCols[] = 'location';
      $setTypes .= 's';
      $setVals[] = implode(', ', $locParts);
    }

    if (empty($setCols)) {
      echo json_encode(['success' => true, 'updated' => 0]);
      exit();
    }

    $setSql = implode(', ', array_map(function ($c) { return '`' . $c . '` = ?'; }, $setCols));
    $stmt = $conn->prepare('UPDATE scheduled_projects SET ' . $setSql . ' WHERE project_id = ? LIMIT 1');
    if (!$stmt) { throw new Exception('Prepare failed'); }
    $setTypes .= 'i';
    $setVals[] = $projectId;
    $refs = [];
    $refs[] = &$setTypes;
    for ($i = 0; $i < count($setVals); $i++) { $refs[] = &$setVals[$i]; }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'updated' => count($setCols)]);
    exit();
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save project details']);
    exit();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_scheduled_project') {
  $projectName = trim($_POST['project_name'] ?? '');
  $selectedDates = json_decode((string)($_POST['selected_dates'] ?? '[]'), true);
  if (!is_array($selectedDates)) {
    $selectedDates = [];
  }
  $selectedDates = array_values(array_unique(array_filter($selectedDates, function ($date) {
    if (!is_string($date)) {
      return false;
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
  })));
  sort($selectedDates);

  if ($projectName === '' || empty($selectedDates)) {
    $formError = 'Project name and at least one scheduled date are required.';
  } else {
    // Keep the legacy range columns as compatibility metadata; selected dates are authoritative.
    $startDb = $selectedDates[0] . ' 07:00:00';
    $endDb = $selectedDates[count($selectedDates) - 1] . ' 18:00:00';

    $conn->begin_transaction();
    try {
      $excludeWeekendsInsert = 0;
      $detailsInsert = isset($_POST['project_details']) ? trim((string)$_POST['project_details'])
        : (isset($_POST['details']) ? trim((string)$_POST['details']) : '');

      // Derive the legacy single "location" string from the address/city/state inputs.
      $locationInsert = trim((string)($_POST['project_location'] ?? ''));
      if ($locationInsert === '') {
        $locParts = array_values(array_filter([
          trim((string)($_POST['project_city'] ?? '')),
          trim((string)($_POST['project_state'] ?? '')),
        ], function ($p) { return $p !== ''; }));
        $locationInsert = implode(', ', $locParts);
      }

      // Base columns + any project-detail columns that exist and were submitted.
      $insertCols = ['project_name', trim($startColumnSql, '`'), trim($endColumnSql, '`'), 'exclude_weekends', 'location', 'details'];
      $insertTypes = 'sssiss';
      $insertVals = [$projectName, $startDb, $endDb, $excludeWeekendsInsert, $locationInsert, $detailsInsert];

      foreach ($projectMetaAllColumns as $mc) {
        if (!array_key_exists($mc, $_POST)) continue;
        $val = scheduling_meta_value_for($mc, $_POST[$mc], $projectMetaIntColumns, $projectMetaBoolColumns);
        $insertCols[] = $mc;
        $insertTypes .= (in_array($mc, $projectMetaIntColumns, true) || in_array($mc, $projectMetaBoolColumns, true)) ? 'i' : 's';
        $insertVals[] = $val;
      }

      $placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
      $colList = '`' . implode('`, `', $insertCols) . '`';
      $projectStmt = $conn->prepare('INSERT INTO scheduled_projects (' . $colList . ') VALUES (' . $placeholders . ')');
      if (!$projectStmt) {
        throw new Exception('Unable to prepare project insert.');
      }
      $bindRefs = [];
      $bindRefs[] = &$insertTypes;
      for ($i = 0; $i < count($insertVals); $i++) { $bindRefs[] = &$insertVals[$i]; }
      call_user_func_array([$projectStmt, 'bind_param'], $bindRefs);
      if (!$projectStmt->execute()) {
        throw new Exception('Unable to save project.');
      }
      $projectId = (int)$projectStmt->insert_id;
      $projectStmt->close();

      // Create rows only for dates explicitly selected in the calendar.
      $detailsStmt = $conn->prepare('INSERT INTO scheduled_project_details (project_id, `day`, equipments, personnel) VALUES (?, ?, "", "") ON DUPLICATE KEY UPDATE project_id = project_id');
      if (!$detailsStmt) {
        throw new Exception('Unable to prepare project details insert.');
      }
      foreach ($selectedDates as $dayStr) {
        $detailsStmt->bind_param('is', $projectId, $dayStr);
        if (!$detailsStmt->execute()) {
          throw new Exception('Unable to save project details for ' . $dayStr);
        }
      }
      $detailsStmt->close();

      // Adopt any note images that were staged before the project existed.
      $draftKeyPosted = trim((string)($_POST['upload_draft_key'] ?? ''));
      if ($draftKeyPosted !== '') {
        project_uploads_claim_draft($conn, $draftKeyPosted, $projectId);
      }

      $conn->commit();
      header('Location: ' . $_SERVER['REQUEST_URI']);
      exit();
    } catch (Throwable $e) {
      $conn->rollback();
      $formError = 'Unable to add project right now. Please try again.';
    }
  }
}

$employees = [];
// include profile picture when available (either in user_details.profile_picture or legacy users.profile_image)
$hasProfile = false;
$c3 = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
if ($c3 && $c3->num_rows > 0) $hasProfile = true;

$empSql = 'SELECT u.id, name, COALESCE(role, "") AS role, ';
if ($hasProfile) {
  $empSql .= 'COALESCE(ud.profile_picture, u.profile_image) AS picture';
} else {
  $empSql .= 'ud.profile_picture AS picture';
}
$empSql .= ' FROM users u LEFT JOIN user_details ud ON ud.user_id = u.id WHERE name IS NOT NULL AND name <> "" ORDER BY name ASC';
$empStmt = $conn->prepare($empSql);
if ($empStmt) {
  $empStmt->execute();
  $empRes = $empStmt->get_result();
  if ($empRes) {
    while ($row = $empRes->fetch_assoc()) {
      $employees[] = $row;
    }
  }
  $empStmt->close();
}

$equipments = [];
$eqStmt = $conn->prepare('SELECT equipment_id, COALESCE(NULLIF(dhss_equipment_number, ""), NULLIF(equipment_number, ""), CONCAT("#", equipment_id)) AS equipment_label, COALESCE(NULLIF(type, ""), "Equipment") AS equipment_type, COALESCE(NULLIF(operating_condition, ""), "") AS operating_condition FROM equipments ORDER BY equipment_id ASC');
if ($eqStmt) {
  $eqStmt->execute();
  $eqRes = $eqStmt->get_result();
  if ($eqRes) {
    while ($row = $eqRes->fetch_assoc()) {
      $equipments[] = $row;
    }
  }
  $eqStmt->close();
}

$scheduledProjects = [];
$projectMetaSelectSql = '';
foreach ($projectMetaAllColumns as $mc) {
  $projectMetaSelectSql .= ', sp.`' . $mc . '` AS `' . $mc . '`';
}
 $projectsSql = 'SELECT sp.project_id, sp.project_name, sp.' . $startColumnSql . ' AS `start`, sp.' . $endColumnSql . ' AS `end`, COALESCE(spd.equipments, "") AS equipments, COALESCE(spd.personnel, "") AS personnel, COALESCE(sp.exclude_weekends, 0) AS exclude_weekends, COALESCE(sp.location, "") AS location, COALESCE(sp.details, "") AS details, COALESCE(sp.note_image_urls, "") AS note_image_urls' . $projectMetaSelectSql . ' FROM scheduled_projects sp LEFT JOIN scheduled_project_details spd ON spd.project_id = sp.project_id AND spd.`day` = DATE(sp.' . $startColumnSql . ') ORDER BY sp.' . $startColumnSql . ' ASC';
$projectsRes = $conn->query($projectsSql);
if ($projectsRes) {
  while ($row = $projectsRes->fetch_assoc()) {
    $scheduledProjects[] = $row;
  }
}

$existingChecklistProjects = [];
$projectChecklistTable = $conn->query("SHOW TABLES LIKE 'Projects'");
if ($projectChecklistTable && $projectChecklistTable->num_rows > 0) {
  $projectStatusColumn = $conn->query("SHOW COLUMNS FROM Projects LIKE 'Status'");
  $hasChecklistStatus = $projectStatusColumn && $projectStatusColumn->num_rows > 0;

  $projectListSql = $hasChecklistStatus
    ? 'SELECT Project_ID, Project_Name, Status FROM Projects ORDER BY CASE LOWER(COALESCE(Status, "")) WHEN "ongoing" THEN 1 WHEN "completed" THEN 2 WHEN "cancelled" THEN 3 ELSE 4 END, Project_Name ASC, Project_ID DESC'
    : 'SELECT Project_ID, Project_Name FROM Projects ORDER BY Project_Name ASC, Project_ID DESC';

  $projectListRes = $conn->query($projectListSql);
  if ($projectListRes) {
    while ($row = $projectListRes->fetch_assoc()) {
      $existingChecklistProjects[] = $row;
    }
  }
}

// Load per-day details for projects so client can initialize per-day view correctly
$perDayDetails = [];
$spdRes = $conn->query('SELECT project_id, `day`, COALESCE(equipments, "") AS equipments, COALESCE(personnel, "") AS personnel FROM scheduled_project_details');
if ($spdRes) {
  while ($r = $spdRes->fetch_assoc()) {
    $key = $r['project_id'] . '|' . $r['day'];
    $perDayDetails[$key] = ['equipments' => $r['equipments'], 'personnel' => $r['personnel']];
  }
}

$printIconPath = ((isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost') ? '/PortalSite' : '') . '/assets/images/print.svg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Scheduling</title>
  <link rel="stylesheet" href="../../assets/css/base.css" />
  <link rel="stylesheet" href="../../assets/css/admin-layout.css?v=20260323e" />
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="style.css?v=20260826-three-sections" />
  <style>
    /* Auto-save status indicator (replaces the old Save Changes button) */
    .autosave-indicator {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin-right: 8px;
      font-size: 0.8rem;
      font-weight: 700;
      color: #1e7a3e;
      opacity: 0;
      transition: opacity 200ms ease;
      white-space: nowrap;
    }
    .autosave-indicator.is-visible { opacity: 1; }
    .autosave-indicator.is-saving { color: #6a7992; }
    .autosave-indicator.is-error { color: #b42318; }
    /* Week range / today indicator */
    .week-range { display:inline-flex; align-items:center; gap:10px; }
    .today-indicator { font-size:0.95em; color:#666; padding:4px 8px; border-radius:6px; background:transparent; }
    .today-indicator.today-in-week { background:#e6f7ea; color:#1e7a3e; font-weight:700; border:1px solid #cdebd3; }
    /* Left crew/equipment sidebar removed - the scheduler uses the full width now */
    .scheduling-page .scheduling-shell { grid-template-columns: minmax(0, 1fr); }
    #resourceDataStore { display: none !important; }
  </style>
  <style>
    /* Project tiles: no hover effects at all - the whole tile is click-to-edit. */
    .scheduling-page .project-tile-name,
    .scheduling-page .project-tile-req { transition: none !important; }
    .scheduling-page .project-tile:hover .project-tile-name,
    .scheduling-page .project-tile:focus-within .project-tile-name,
    .scheduling-page .project-tile:hover .project-tile-req,
    .scheduling-page .project-tile:focus-within .project-tile-req {
      opacity: 0.92 !important;
      transform: none !important;
      pointer-events: auto !important;
    }
    .scheduling-page .project-tile:hover .project-tile-name,
    .scheduling-page .project-tile:focus-within .project-tile-name { opacity: 1 !important; }
    .scheduling-page .project-tile-actions { display: none !important; }
    .scheduling-page .project-tile { cursor: pointer; }

    /* Project tile requirement styling */
    .project-tile-req { white-space: pre-line; margin-top: 8px; font-size: 13px; color: rgba(255,255,255,0.9); }
    .project-tile-req .req-row { display:block; margin: 2px 0; }
    .project-tile-req .req-label { display:inline-block; font-weight:600; margin-right:6px; opacity:0.95; }
    .project-tile-req .personnel-value { color: #D0F3FF; }
    .project-tile-req .equipments-value { color: #FFF3B8; }
    /* Single solid status indicator for equipment (green/yellow/red/neutral) */
    .status-dot { display:inline-block; width:12px; height:12px; border-radius:50%; margin-right:10px; vertical-align:middle; background: #9ca3af; }
    .status-dot.good { background-color: #16a34a; }
    .status-dot.warn { background-color: #f59e0b; }
    .status-dot.bad { background-color: #ef4444; }
    .status-dot.neutral { background-color: #9ca3af; opacity:0.95; }
    .resource-link { color: inherit; text-decoration: none; }
    .resource-link:hover .resource-name { text-decoration: underline; }
    .decision-modal-message { margin: 0 0 8px; text-align: center; }
    .decision-modal-message a { display: inline-block; margin-top: 12px; background: #f0f7ff; color: #1e63d6; padding: 8px 14px; border-radius: 8px; text-decoration: none; font-weight: 700; }
    .decision-modal-message a:hover { background: #e6f0ff; text-decoration: underline; }
    #decisionModal .modal-head { text-align: center; }
    #decisionModal .decision-modal-actions { display:flex; justify-content:center; gap:16px; margin-top:18px; }
    #decisionModal .decision-modal-actions button { min-width:120px; }
    /* Red warning light */
    .modal-warning-light { display:inline-block; width:14px; height:14px; border-radius:50%; background:#ef4444; box-shadow: 0 0 10px rgba(239,68,68,0.28); vertical-align:middle; }
    .modal-warning-wrap { text-align:center; }
    .modal-warning-row { display:flex; align-items:center; gap:12px; justify-content:center; }
    .modal-warning-text { font-weight:600; }
    /* ===== Crew / Equipment modal (reuses the 3-column .add-project-card grid) ===== */
    #crewEquipmentModal .crew-equipment-modal .add-project-notes { background: #fbfcfe; }

    /* Right column header: Save & Go Back + X sit top-right, matching the project-details modal */
    #crewEquipmentModal .crew-resources-panel .modal-head {
      justify-content: flex-end;
      min-height: 40px;
      margin: 0 0 4px;
    }
    #crewEquipmentModal .modal-head-actions { display: flex; align-items: center; gap: 8px; }
    #crewEquipmentModal .crew-save-return-btn { white-space: nowrap; }

    /* Project Details / Add Project modals: header action buttons next to the X.
       This row must stay on a single line at every width — the buttons scale
       down with the viewport instead of wrapping the X onto a second row. */
    #projectDetailsModal .add-project-right .modal-head,
    #addProjectModal .add-project-right .modal-head {
      flex-wrap: nowrap;
      gap: 8px;
      justify-content: flex-end;
      min-height: 40px;
      /* Pinned to the top of this column so scrolling the fields below never
         moves the buttons. The shadow paints over the column's top padding,
         which a sticky element does not cover on its own. */
      position: sticky;
      top: 0;
      z-index: 3;
      background: #f7f9fc;
      padding-bottom: 10px;
      box-shadow: 0 -32px 0 #f7f9fc;
    }
    #projectDetailsModal .modal-head-actions,
    #addProjectModal .modal-head-actions {
      display: flex;
      align-items: center;
      gap: clamp(2px, 0.28vw, 8px);
      flex-wrap: nowrap;
      justify-content: flex-end;
      width: 100%;
      min-width: 0;
    }
    #projectDetailsModal .modal-head-actions .secondary-btn,
    #addProjectModal .modal-head-actions .secondary-btn {
      white-space: nowrap;
      min-width: 0;
      flex: 0 1 auto;
      /* Type and padding shrink with the viewport so the full labels keep fitting
         on one line; the 3-column layout leaves this column ~264px at its
         narrowest, which is what the lower bounds are sized for. */
      font-size: clamp(0.5rem, 0.64vw, 0.84rem);
      padding: 9px clamp(3px, 0.42vw, 12px);
      /* Last-resort guard so a long label can never force a wrap. */
      overflow: hidden;
      text-overflow: ellipsis;
    }
    /* The close button is a fixed 34px box by default, so it is the one item
       that cannot give back space when the row gets tight. */
    #projectDetailsModal .modal-head-actions .modal-close-btn,
    #addProjectModal .modal-head-actions .modal-close-btn {
      flex: 0 0 auto;
      width: clamp(26px, 2.2vw, 34px);
      height: clamp(26px, 2.2vw, 34px);
      padding: 0;
      font-size: clamp(0.56rem, 0.66vw, 0.86rem);
    }
    /* Tightest band: the 3-column grid is at its minimum track widths here, so
       this column is at its narrowest while the modal is still 3 columns wide.
       Trim spacing only here — wider screens keep the roomier defaults. */
    @media (min-width: 951px) and (max-width: 1200px) {
      #projectDetailsModal .modal-head-actions,
      #addProjectModal .modal-head-actions {
        gap: 2px;
      }
      #projectDetailsModal .modal-head-actions .secondary-btn,
      #addProjectModal .modal-head-actions .secondary-btn {
        padding-left: 3px;
        padding-right: 3px;
      }
    }
    /* Smallest phones: the single column is narrower than the row's resting size. */
    @media (max-width: 400px) {
      #projectDetailsModal .modal-head-actions,
      #addProjectModal .modal-head-actions {
        gap: 1px;
      }
      #projectDetailsModal .modal-head-actions .secondary-btn,
      #addProjectModal .modal-head-actions .secondary-btn {
        padding-left: 2px;
        padding-right: 2px;
      }
    }

    #crewEquipmentModal #crewEquipmentProjectName,
    #crewEquipmentModal .crew-assignments-panel > h3 { margin: 2px 0 0; font-size: 1.05rem; color: #1f2f49; }
    #crewEquipmentModal .crew-equipment-panel-label {
      margin: 16px 0 7px;
      color: #8a98af;
      font-size: 0.66rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.07em;
    }

    /* Scheduled-day pills (left column) */
    #crewEquipmentModal .crew-scheduled-days { display: flex; flex-wrap: wrap; gap: 8px; }
    #crewEquipmentModal .crew-scheduled-day {
      border: 1px solid #c9d7ef;
      background: #ffffff;
      color: #2f4161;
      border-radius: 999px;
      padding: 6px 12px;
      font-size: 0.78rem;
      font-weight: 700;
      cursor: pointer;
    }
    #crewEquipmentModal .crew-scheduled-day[aria-pressed="true"] {
      background: #2f68c5;
      border-color: #2b57a0;
      color: #ffffff;
    }

    /* Available resource list (right column) */
    #crewEquipmentModal .crew-available-resources {
      display: flex;
      flex-direction: column;
      gap: 7px;
      margin-bottom: 8px;
    }
    #crewEquipmentModal .crew-available-resource {
      display: flex;
      align-items: center;
      gap: 10px;
      width: 100%;
      text-align: left;
      border: 1px solid #d7dee9;
      border-radius: 9px;
      background: #ffffff;
      padding: 8px 10px;
      cursor: pointer;
      font: inherit;
    }
    #crewEquipmentModal .crew-available-resource:hover:not(:disabled) { background: #eef3fb; border-color: #b9c9e6; }
    #crewEquipmentModal .crew-available-resource.is-assigned,
    #crewEquipmentModal .crew-available-resource:disabled { opacity: 0.45; cursor: not-allowed; }
    #crewEquipmentModal .crew-available-resource .resource-texts { min-width: 0; display: flex; flex-direction: column; }
    #crewEquipmentModal .crew-available-resource .resource-name {
      font-weight: 700; font-size: 0.82rem; color: #1f2f49;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    #crewEquipmentModal .crew-available-resource .resource-sub {
      color: #7c8aa1; font-size: 0.68rem;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    /* Assignments (middle column) */
    #crewEquipmentModal .crew-selected-day { margin: 4px 0 14px; color: #637491; font-size: 0.8rem; font-weight: 700; }
    #crewEquipmentModal .crew-assignment-group { margin-top: 20px; }
    #crewEquipmentModal .crew-assignment-group h4 { margin: 0 0 10px; font-size: 0.82rem; color: #314a70; }
    #crewEquipmentModal .crew-assignment-row { display: flex; flex-wrap: wrap; gap: 9px 10px; }
    #crewEquipmentModal .crew-assignment-empty { color: #8a98af; font-size: 0.8rem; }
    #crewEquipmentModal .crew-assignment-chip {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      background: #e8eefb;
      border: 1px solid #cdd9f0;
      color: #26375a;
      border-radius: 999px;
      padding: 5px 7px 5px 13px;
      font-size: 0.78rem;
      font-weight: 700;
    }
    #crewEquipmentModal .crew-assignment-remove {
      border: 0;
      background: rgba(38, 55, 90, 0.12);
      color: #26375a;
      width: 18px;
      height: 18px;
      border-radius: 50%;
      line-height: 1;
      cursor: pointer;
      font-size: 0.8rem;
    }
    #crewEquipmentModal .crew-assignment-remove:hover { background: rgba(38, 55, 90, 0.24); }

    /* Apply-to-all-days button: green, lighter green on hover */
    #crewEquipmentModal .crew-apply-all-btn {
      display: inline-block;
      margin: 4px 0;
      padding: 9px 14px;
      font-size: 0.8rem;
      background: #2e9e5b;
      border: 1px solid #248049;
      color: #ffffff;
      border-radius: 10px;
      font-weight: 700;
      cursor: pointer;
      transition: background-color 150ms ease, border-color 150ms ease;
    }
    #crewEquipmentModal .crew-apply-all-btn:hover:not(:disabled),
    #crewEquipmentModal .crew-apply-all-btn:focus-visible:not(:disabled) {
      background: #57c583;
      border-color: #46b271;
      color: #ffffff;
    }
    #crewEquipmentModal .crew-apply-all-btn:disabled { cursor: not-allowed; opacity: 0.55; }

    /* Not-available (assigned elsewhere) collapsible section */
    #crewEquipmentModal .crew-unavailable { margin-top: 18px; border-top: 1px solid #e2e8f0; padding-top: 14px; }
    #crewEquipmentModal .crew-unavailable-toggle {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      width: 100%;
      border: 1px solid #d7dee9;
      background: #ffffff;
      color: #2f4161;
      border-radius: 9px;
      padding: 9px 12px;
      font: inherit;
      font-weight: 700;
      font-size: 0.8rem;
      cursor: pointer;
    }
    #crewEquipmentModal .crew-unavailable-toggle:hover:not(:disabled) { background: #eef3fb; border-color: #b9c9e6; }
    #crewEquipmentModal .crew-unavailable-toggle:disabled { opacity: 0.55; cursor: not-allowed; }
    #crewEquipmentModal .crew-unavailable-chevron { transition: transform 0.15s ease; }
    #crewEquipmentModal .crew-unavailable-toggle[aria-expanded="true"] .crew-unavailable-chevron { transform: rotate(180deg); }
    #crewEquipmentModal .crew-unavailable-panel { margin-top: 10px; }
    #crewEquipmentModal .crew-unavailable-panel[hidden] { display: none; }
    #crewEquipmentModal .crew-unavailable-hint { margin: 0 0 8px; color: #7c8aa1; font-size: 0.72rem; line-height: 1.4; }
    #crewEquipmentModal .crew-unavailable-list { display: flex; flex-direction: column; gap: 7px; margin-bottom: 8px; }
    #crewEquipmentModal .crew-unavailable-list:empty::after { content: none; }
    #crewEquipmentModal .crew-unavailable-item {
      display: flex;
      align-items: center;
      gap: 10px;
      border: 1px solid #e6d7d7;
      background: #fdf6f6;
      border-radius: 9px;
      padding: 8px 10px;
    }
    #crewEquipmentModal .crew-unavailable-info { min-width: 0; flex: 1; }
    #crewEquipmentModal .crew-unavailable-name {
      font-weight: 700; font-size: 0.82rem; color: #1f2f49;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    #crewEquipmentModal .crew-unavailable-where {
      color: #a5644f; font-size: 0.7rem; margin-top: 2px;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    #crewEquipmentModal .crew-unavailable-move {
      flex: 0 0 auto;
      border: 1px solid #2b57a0;
      background: #2f68c5;
      color: #ffffff;
      border-radius: 8px;
      padding: 6px 12px;
      font: inherit;
      font-weight: 700;
      font-size: 0.75rem;
      cursor: pointer;
    }
    #crewEquipmentModal .crew-unavailable-move:hover:not(:disabled) { background: #2556a8; }
    #crewEquipmentModal .crew-unavailable-move:disabled { opacity: 0.55; cursor: not-allowed; }

    /* ===== 30-day (month) calendar view ===== */
    .scheduling-page .calendar-view-toggle {
      border: 1px solid #29539c;
      background: #eef3fb;
      color: #29539c;
      border-radius: 8px;
      padding: 0 12px;
      height: 30px;
      cursor: pointer;
      font-weight: 700;
      font-size: 0.8rem;
      white-space: nowrap;
      margin-left: 8px;
    }
    .scheduling-page .calendar-view-toggle:hover { background: #e3ecf9; }
    .scheduling-page .calendar-view-toggle.is-active { background: #2f65bc; color: #ffffff; }

    .scheduling-page .weekly-board[hidden] { display: none !important; }

    .scheduling-page .month-board {
      background: #f7f9fc;
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 0;
      overflow: hidden;
    }
    .scheduling-page .month-board[hidden] { display: none; }
    .scheduling-page .month-weekday-row {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      background: #f1f5fb;
      border-bottom: 1px solid #d5dce8;
      flex: 0 0 auto;
    }
    .scheduling-page .month-weekday-row .month-weekday {
      padding: 8px 6px;
      text-align: center;
      font-size: 0.66rem;
      font-weight: 800;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: #7d8da8;
      border-right: 1px solid #e2e8f2;
    }
    .scheduling-page .month-weekday-row .month-weekday:last-child { border-right: 0; }
    .scheduling-page .month-grid {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      grid-auto-rows: minmax(110px, 1fr);
      flex: 1;
      overflow-y: auto;
      min-height: 0;
    }
    .scheduling-page .month-cell {
      border-right: 1px solid #e2e8f2;
      border-bottom: 1px solid #e2e8f2;
      padding: 4px 4px 6px;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 3px;
      background: #ffffff;
    }
    .scheduling-page .month-cell:nth-child(7n) { border-right: 0; }
    .scheduling-page .month-cell.other-month { background: #f4f6fa; }
    .scheduling-page .month-cell.other-month .month-cell-date { color: #aab4c4; }
    .scheduling-page .month-cell-date {
      align-self: flex-end;
      font-size: 0.74rem;
      font-weight: 700;
      color: #4a5b78;
      line-height: 1;
      padding: 2px 3px;
    }
    .scheduling-page .month-cell.today .month-cell-date {
      background: #2f65bc;
      color: #ffffff;
      border-radius: 999px;
      min-width: 20px;
      text-align: center;
    }
    .scheduling-page .month-cell-events {
      display: flex;
      flex-direction: column;
      gap: 2px;
      min-height: 0;
    }
    .scheduling-page .month-event {
      display: block;
      width: 100%;
      text-align: left;
      border: 0;
      border-radius: 4px;
      padding: 2px 6px;
      font-size: 0.7rem;
      font-weight: 700;
      line-height: 1.25;
      color: #ffffff;
      cursor: pointer;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      font-family: inherit;
    }
    .scheduling-page .month-event:hover { filter: brightness(1.08); }
    .scheduling-page .month-event:focus-visible { outline: 2px solid #1f2f49; outline-offset: 1px; }
    .scheduling-page .month-event-more {
      background: transparent;
      border: 0;
      color: #637491;
      font-size: 0.66rem;
      font-weight: 700;
      cursor: pointer;
      text-align: left;
      padding: 1px 6px;
      font-family: inherit;
    }
    .scheduling-page .month-event-more:hover { color: #2f65bc; }
    @media (max-width: 720px) {
      .scheduling-page .month-grid { grid-auto-rows: minmax(72px, auto); }
      .scheduling-page .month-event { font-size: 0.62rem; }
    }
  </style>
</head>
<body class="admin-page scheduling-page">
  <div class="admin-container">
    <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
    <div class="admin-layout">
      <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
      <main class="content-area">
        <div class="main-content">
          <section class="scheduling-shell">
            <?php /* Crew/Equipment reference data. Not shown in the UI - assignments are managed
                     from the Crew/Equipment modal, which reads employee/equipment lists from here. */ ?>
            <div id="resourceDataStore" hidden aria-hidden="true">
              <div id="personnelList">
                <?php if (!empty($employees)): ?>
                  <?php foreach ($employees as $employee): ?>
                    <?php
                      $name = (string)($employee['name'] ?? '');
                      $roleLabel = trim((string)($employee['role'] ?? ''));
                      $parts = preg_split('/\s+/', trim($name));
                      $initials = '';
                      if (!empty($parts[0])) {
                        $initials .= strtoupper(substr($parts[0], 0, 1));
                      }
                      if (!empty($parts[1])) {
                        $initials .= strtoupper(substr($parts[1], 0, 1));
                      }
                      if ($initials === '' && $name !== '') {
                        $initials = strtoupper(substr($name, 0, 2));
                      }
                    ?>
                    <div class="requirement-source" data-requirement-kind="personnel" data-requirement-value="<?php echo htmlspecialchars($name); ?>">
                      <span class="resource-avatar"><?php echo htmlspecialchars($initials); ?></span>
                      <span class="resource-name"><?php echo htmlspecialchars($name); ?></span>
                      <span class="resource-sub"><?php echo htmlspecialchars($roleLabel !== '' ? ucfirst($roleLabel) : 'Crew Member'); ?></span>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <div id="equipmentList">
                <?php if (!empty($equipments)): ?>
                  <?php foreach ($equipments as $equipment): ?>
                    <?php
                      $equipmentLabel = (string)($equipment['equipment_label'] ?? '');
                      $equipmentType = trim((string)($equipment['equipment_type'] ?? 'Equipment'));
                      $equipmentInitial = strtoupper(substr($equipmentLabel, 0, 1));
                      $operating = trim((string)($equipment['operating_condition'] ?? ''));
                      $op = strtolower($operating);
                      $opClass = 'neutral';
                      if ($op === 'green' || strpos($op, 'green') !== false || strpos($op, 'good') !== false || $op === 'ok') {
                        $opClass = 'good';
                      } elseif ($op === 'yellow' || strpos($op, 'yellow') !== false || strpos($op, 'warn') !== false) {
                        $opClass = 'warn';
                      } elseif ($op === 'red' || strpos($op, 'red') !== false || strpos($op, 'bad') !== false) {
                        $opClass = 'bad';
                      }
                    ?>
                    <div class="requirement-source" data-eid="<?php echo (int)$equipment['equipment_id']; ?>" data-requirement-kind="equipments" data-requirement-value="<?php echo htmlspecialchars($equipmentLabel); ?>">
                      <span class="status-dot <?php echo htmlspecialchars($opClass); ?>" aria-hidden="true" title="<?php echo htmlspecialchars($operating === '' ? 'Unknown' : $operating); ?>"></span>
                      <span class="resource-avatar equipment"><?php echo htmlspecialchars($equipmentInitial); ?></span>
                      <span class="resource-name"><?php echo htmlspecialchars($equipmentLabel); ?></span>
                      <span class="resource-sub"><?php echo htmlspecialchars($equipmentType); ?></span>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <section class="scheduler-panel" aria-label="Scheduling content">
              <div class="scheduler-topbar">
                <div class="week-controls" aria-label="Week navigation">
                  <button type="button" class="week-btn" id="prevWeekBtn" aria-label="Previous week">&#x2039;</button>
                  <button type="button" class="week-btn today" id="todayWeekBtn">Today</button>
                  <button type="button" class="week-btn" id="nextWeekBtn" aria-label="Next week">&#x203A;</button>
                </div>
                <button type="button" class="calendar-view-toggle" id="toggleCalendarViewBtn" aria-pressed="false">30-Day Calendar View</button>
                <div class="week-range" id="weekRangeLabel">Week Range <span class="today-indicator" id="todayIndicator" aria-hidden="true"></span></div>
                <div class="scheduler-actions">
                  <span class="autosave-indicator" id="autosaveIndicator" role="status" aria-live="polite"></span>
                  <button type="button" class="add-project-btn" id="openAddProjectModal">Add Project</button>
                  <button type="button" class="scheduler-icon-btn" id="printWeekBtn" aria-label="Print current week schedule" title="Print current week">
                    <img src="<?php echo htmlspecialchars($printIconPath); ?>" alt="" />
                  </button>
                </div>
              </div>

              <h1 class="scheduling-title">Scheduling</h1>
              <?php if ($formError !== ''): ?>
                <p class="scheduling-form-error"><?php echo htmlspecialchars($formError); ?></p>
              <?php endif; ?>

              <section class="schedule-calendar" aria-label="Scheduling calendar">
                <div class="weekly-board" id="weeklyBoard" aria-label="Weekly schedule board">
                  <div class="weekly-days-row" id="weeklyDaysRow"></div>
                  <div class="weekly-day-columns" id="weeklyDayColumns" aria-live="polite"></div>
                </div>
                <div class="month-board" id="monthBoard" aria-label="Monthly schedule board" hidden>
                  <div class="month-weekday-row" id="monthWeekdayRow"></div>
                  <div class="month-grid" id="monthGrid" aria-live="polite"></div>
                </div>
              </section>
            </section>
          </section>
        </div>
      </main>
    </div>
  </div>

  <div class="modal-overlay" id="addProjectModal" hidden>
    <div class="modal-card add-project-card" role="dialog" aria-modal="true" aria-labelledby="addProjectTitle">
      <div class="add-project-left">
        <div class="modal-head">
          <h2 id="addProjectTitle">Add Project</h2>
        </div>
        <div class="existing-project-tools">
          <button type="button" id="toggleExistingProjectPicker" class="secondary-btn existing-project-toggle" aria-expanded="false" aria-controls="existingProjectsPicker">
            <span>Want to add from existing project instead?</span>
            <span class="picker-chevron" aria-hidden="true">&#9662;</span>
          </button>
        </div>

        <div id="existingProjectsPicker" class="existing-project-picker" hidden>
          <label for="existingProjectSelect">Select an existing project</label>
          <select id="existingProjectSelect">
          <option value="">Choose a project...</option>
          <?php
            $existingProjectGroups = [
              'Ongoing' => [],
              'Completed' => [],
              'Cancelled' => []
            ];
            foreach ($existingChecklistProjects as $existingProject) {
              $projectName = trim((string)($existingProject['Project_Name'] ?? ''));
              $projectStatus = trim((string)($existingProject['Status'] ?? ''));
              if ($projectName === '') {
                continue;
              }
              $statusKey = $projectStatus === 'Completed' ? 'Completed' : ($projectStatus === 'Cancelled' ? 'Cancelled' : 'Ongoing');
              $existingProjectGroups[$statusKey][] = $existingProject;
            }

            foreach (['Ongoing', 'Completed', 'Cancelled'] as $statusKey) {
              $groupItems = $existingProjectGroups[$statusKey] ?? [];
              if (empty($groupItems)) {
                continue;
              }
              echo '<optgroup label="' . htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') . '">';
              foreach ($groupItems as $existingProject) {
                $projectName = trim((string)($existingProject['Project_Name'] ?? ''));
                $projectId = (int)($existingProject['Project_ID'] ?? 0);
                echo '<option value="' . htmlspecialchars((string)$projectId, ENT_QUOTES, 'UTF-8') . '" data-project-name="' . htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') . '</option>';
              }
              echo '</optgroup>';
            }
          ?>
          </select>
        </div>

        <form method="post" class="project-form" id="addProjectForm" autocomplete="off">
          <input type="hidden" name="action" value="add_scheduled_project" />

          <label for="project_name">Project Name</label>
          <input id="project_name" name="project_name" type="text" required autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" />

          <label for="projectDatePicker">Select Dates</label>
          <div class="project-date-picker" id="projectDatePicker">
            <div class="date-picker-header">
              <button type="button" id="previousMonth" aria-label="Previous month">&lt;</button>
              <strong id="calendarMonth"></strong>
              <button type="button" id="nextMonth" aria-label="Next month">&gt;</button>
            </div>
            <div class="calendar-weekdays" aria-hidden="true">
              <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
            </div>
            <div class="calendar-grid" id="calendarGrid"></div>
          </div>
          <input type="hidden" id="selectedDates" name="selected_dates" value="[]" />
          <p class="selected-dates-summary" id="selectedDatesSummary">No dates selected</p>
        </form>
      </div>

      <div class="add-project-notes">
        <div class="project-field-group">
          <div class="notes-field-head">
            <label for="officeNotes">Notes</label>
            <button type="button" class="notes-add-images-btn" id="addNotesImagesBtn">Add Images</button>
          </div>
          <textarea id="officeNotes" name="details" form="addProjectForm" placeholder="Add office notes"></textarea>
          <input type="hidden" id="addNotesDraftKey" name="upload_draft_key" form="addProjectForm" value="<?= htmlspecialchars($noteUploadDraftKey, ENT_QUOTES, 'UTF-8') ?>" />
          <input type="file" id="addNotesImageInput" accept="image/png,image/jpeg,image/gif,image/webp" multiple hidden />
          <p class="notes-images-status" id="addNotesImagesStatus" hidden></p>
          <div class="notes-image-gallery" id="addNotesImageGallery" hidden></div>
        </div>
      </div>

      <div class="add-project-right">
        <div class="modal-head">
          <div class="modal-head-actions">
            <button type="submit" form="addProjectForm" class="secondary-btn create-project-btn" id="createProjectBtn">Create Project</button>
            <button type="button" class="modal-close-btn" id="closeAddProjectModal" aria-label="Close add project form">X</button>
          </div>
        </div>
        <div class="add-project-details">
          <input type="hidden" name="taxable" id="addTaxableField" form="addProjectForm" />
          <input type="hidden" name="certified" id="addCertifiedField" form="addProjectForm" />
          <input type="hidden" name="permit" id="addPermitField" form="addProjectForm" />
          <section class="project-information" aria-labelledby="projectInformationTitle">
            <h3 id="projectInformationTitle">Project Information</h3>

            <div class="project-field-group">
              <input id="projectAddress" name="project_address" form="addProjectForm" type="text" placeholder="Project address" aria-label="Address" />
            </div>

            <div class="project-information-location">
              <div class="project-field-group">
                <input id="projectCity" name="project_city" form="addProjectForm" type="text" placeholder="City" aria-label="City" />
              </div>
              <div class="project-field-group">
                <input id="projectState" name="project_state" form="addProjectForm" type="text" placeholder="State" aria-label="State" />
              </div>
            </div>

            <div class="project-status-options">
              <fieldset class="project-status-fieldset">
                <legend>Taxable</legend>
                <div class="yes-no-toggle" role="group" aria-label="Taxable">
                  <button type="button" class="toggle-option" data-toggle-name="taxable" data-toggle-value="yes" aria-pressed="false">Yes</button>
                  <button type="button" class="toggle-option" data-toggle-name="taxable" data-toggle-value="no" aria-pressed="false">No</button>
                </div>
              </fieldset>

              <fieldset class="project-status-fieldset">
                <legend>Certified</legend>
                <div class="yes-no-toggle" role="group" aria-label="Certified">
                  <button type="button" class="toggle-option" data-toggle-name="certified" data-toggle-value="yes" aria-pressed="false">Yes</button>
                  <button type="button" class="toggle-option" data-toggle-name="certified" data-toggle-value="no" aria-pressed="false">No</button>
                </div>
              </fieldset>

              <fieldset class="project-status-fieldset">
                <legend>Permit</legend>
                <div class="yes-no-toggle" role="group" aria-label="Permit">
                  <button type="button" class="toggle-option" data-toggle-name="permit" data-toggle-value="yes" aria-pressed="false">Yes</button>
                  <button type="button" class="toggle-option" data-toggle-name="permit" data-toggle-value="no" aria-pressed="false">No</button>
                </div>
              </fieldset>
            </div>
          </section>

          <section class="contact-information" aria-labelledby="contractorInformationTitle">
            <h3 id="contractorInformationTitle">General Contractor Information</h3>
            <div class="project-field-group"><input id="contractorName" name="contractor_name" form="addProjectForm" type="text" placeholder="Contractor name" aria-label="Contractor name" /></div>
            <div class="project-field-group"><input id="contractorEmail" name="contractor_email" form="addProjectForm" type="email" placeholder="contractor@example.com" aria-label="Contractor contact email" /></div>
            <div class="project-field-group"><input id="contractorAddress" name="contractor_address" form="addProjectForm" type="text" placeholder="Contractor address" aria-label="Contractor address" /></div>
            <div class="project-information-location">
              <div class="project-field-group"><input id="contractorContact" name="contractor_contact_name" form="addProjectForm" type="text" placeholder="Contact name" aria-label="Contractor contact name" /></div>
              <div class="project-field-group"><input id="contractorPhone" name="contractor_phone" form="addProjectForm" type="tel" placeholder="Phone number" aria-label="Contractor contact number" /></div>
            </div>
          </section>

          <section class="contact-information" aria-labelledby="ownerInformationTitle">
            <h3 id="ownerInformationTitle">Owner Information</h3>
            <div class="project-field-group"><input id="ownerName" name="owner_name" form="addProjectForm" type="text" placeholder="Owner name" aria-label="Owner name" /></div>
            <div class="project-field-group"><input id="ownerEmail" name="owner_email" form="addProjectForm" type="email" placeholder="owner@example.com" aria-label="Owner contact email" /></div>
            <div class="project-field-group"><input id="ownerAddress" name="owner_address" form="addProjectForm" type="text" placeholder="Owner address" aria-label="Owner address" /></div>
            <div class="project-information-location">
              <div class="project-field-group"><input id="ownerContact" name="owner_contact_name" form="addProjectForm" type="text" placeholder="Contact name" aria-label="Owner contact name" /></div>
              <div class="project-field-group"><input id="ownerPhone" name="owner_phone" form="addProjectForm" type="tel" placeholder="Phone number" aria-label="Owner contact number" /></div>
            </div>
          </section>

          <section class="contact-information accommodation-information" aria-labelledby="accommodationInformationTitle">
            <h3 id="accommodationInformationTitle">Accommodation Information</h3>
            <div class="project-field-group"><input id="hotelName" name="hotel_name" form="addProjectForm" type="text" placeholder="Hotel name" aria-label="Hotel name" /></div>
            <div class="project-field-group"><input id="hotelAddress" name="hotel_address" form="addProjectForm" type="text" placeholder="Hotel address" aria-label="Hotel address" /></div>
            <div class="accommodation-information-row">
              <div class="project-field-group"><input id="hotelRooms" name="hotel_rooms" form="addProjectForm" type="number" min="0" placeholder="No. of rooms" aria-label="Number of rooms" /></div>
              <div class="project-field-group"><input id="hotelConfirmation" name="hotel_confirmation" form="addProjectForm" type="text" placeholder="Confirmation number" aria-label="Hotel confirmation number" /></div>
            </div>
            <div class="project-field-group"><input id="hotelPhone" name="hotel_phone" form="addProjectForm" type="tel" placeholder="Hotel phone" aria-label="Hotel phone" /></div>
          </section>
        </div>
      </div>
    </div>
  </div>

    <div class="modal-overlay" id="projectDetailsModal" hidden>
    <div class="modal-card add-project-card edit-project-details-card" role="dialog" aria-modal="true" aria-labelledby="projectDetailsTitle">
      <div class="add-project-left">
        <div class="modal-head">
          <h2 id="projectDetailsTitle">Project Details</h2>
        </div>

        <form method="post" class="project-form" id="editProjectForm" autocomplete="off">
          <label for="editProjectName">Project Name</label>
          <input id="editProjectName" name="project_name" type="text" required autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" />

          <label for="editProjectDatePicker">Select Dates</label>
          <div class="project-date-picker" id="editProjectDatePicker">
            <div class="date-picker-header">
              <button type="button" id="editPreviousMonth" aria-label="Previous month">&lt;</button>
              <strong id="editCalendarMonth"></strong>
              <button type="button" id="editNextMonth" aria-label="Next month">&gt;</button>
            </div>
            <div class="calendar-weekdays" aria-hidden="true">
              <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
            </div>
            <div class="calendar-grid" id="editCalendarGrid"></div>
          </div>
          <input type="hidden" id="editSelectedDatesField" name="selected_dates" value="[]" />
          <p class="selected-dates-summary" id="editSelectedDatesSummary">No dates selected</p>
        </form>
      </div>

      <div class="add-project-notes">
        <div class="project-field-group">
          <div class="notes-field-head">
            <label for="detailsOfficeNotes">Notes</label>
            <button type="button" class="notes-add-images-btn" id="detailsNotesImagesBtn">Add Images</button>
          </div>
          <textarea id="detailsOfficeNotes" placeholder="Add office notes"></textarea>
          <input type="file" id="detailsNotesImageInput" accept="image/png,image/jpeg,image/gif,image/webp" multiple hidden />
          <p class="notes-images-status" id="detailsNotesImagesStatus" hidden></p>
          <div class="notes-image-gallery" id="detailsNotesImageGallery" hidden></div>
        </div>
      </div>

      <div class="add-project-right">
        <div class="modal-head">
          <div class="modal-head-actions">
            <button type="button" class="secondary-btn edit-crew-equipment-btn" id="openCrewEquipmentFromProjectDetails">Edit Crew/Equipment</button>
            <button type="button" class="secondary-btn project-details-print-btn" id="printProjectDetailsBtn">Print</button>
            <button type="button" class="secondary-btn project-details-post-btn" id="postProjectDetailsBtn">Post</button>
            <button type="button" class="secondary-btn project-details-save-btn" id="saveProjectDetailsBtn">Save and Close</button>
            <button type="button" class="modal-close-btn" id="closeProjectDetailsModal" aria-label="Close project details">X</button>
          </div>
        </div>
        <div class="add-project-details">
          <section class="project-information" aria-labelledby="editProjectInformationTitle">
            <h3 id="editProjectInformationTitle">Project Information</h3>

            <div class="project-field-group">
              <input id="editProjectAddress" type="text" placeholder="Project address" aria-label="Address" />
            </div>

            <div class="project-information-location">
              <div class="project-field-group">
                <input id="editProjectCity" type="text" placeholder="City" aria-label="City" />
              </div>
              <div class="project-field-group">
                <input id="editProjectState" type="text" placeholder="State" aria-label="State" />
              </div>
            </div>

            <div class="project-status-options">
              <fieldset class="project-status-fieldset">
                <legend>Taxable</legend>
                <div class="yes-no-toggle" role="group" aria-label="Taxable">
                  <button type="button" class="toggle-option" data-toggle-name="edit-taxable" data-toggle-value="yes" aria-pressed="false">Yes</button>
                  <button type="button" class="toggle-option" data-toggle-name="edit-taxable" data-toggle-value="no" aria-pressed="false">No</button>
                </div>
              </fieldset>

              <fieldset class="project-status-fieldset">
                <legend>Certified</legend>
                <div class="yes-no-toggle" role="group" aria-label="Certified">
                  <button type="button" class="toggle-option" data-toggle-name="edit-certified" data-toggle-value="yes" aria-pressed="false">Yes</button>
                  <button type="button" class="toggle-option" data-toggle-name="edit-certified" data-toggle-value="no" aria-pressed="false">No</button>
                </div>
              </fieldset>

              <fieldset class="project-status-fieldset">
                <legend>Permit</legend>
                <div class="yes-no-toggle" role="group" aria-label="Permit">
                  <button type="button" class="toggle-option" data-toggle-name="edit-permit" data-toggle-value="yes" aria-pressed="false">Yes</button>
                  <button type="button" class="toggle-option" data-toggle-name="edit-permit" data-toggle-value="no" aria-pressed="false">No</button>
                </div>
              </fieldset>
            </div>
          </section>

          <section class="contact-information" aria-labelledby="editContractorInformationTitle">
            <h3 id="editContractorInformationTitle">General Contractor Information</h3>
            <div class="project-field-group"><input id="editContractorName" type="text" placeholder="Contractor name" aria-label="Contractor name" /></div>
            <div class="project-field-group"><input id="editContractorEmail" type="email" placeholder="contractor@example.com" aria-label="Contractor contact email" /></div>
            <div class="project-field-group"><input id="editContractorAddress" type="text" placeholder="Contractor address" aria-label="Contractor address" /></div>
            <div class="project-information-location">
              <div class="project-field-group"><input id="editContractorContact" type="text" placeholder="Contact name" aria-label="Contractor contact name" /></div>
              <div class="project-field-group"><input id="editContractorPhone" type="tel" placeholder="Phone number" aria-label="Contractor contact number" /></div>
            </div>
          </section>

          <section class="contact-information" aria-labelledby="editOwnerInformationTitle">
            <h3 id="editOwnerInformationTitle">Owner Information</h3>
            <div class="project-field-group"><input id="editOwnerName" type="text" placeholder="Owner name" aria-label="Owner name" /></div>
            <div class="project-field-group"><input id="editOwnerEmail" type="email" placeholder="owner@example.com" aria-label="Owner contact email" /></div>
            <div class="project-field-group"><input id="editOwnerAddress" type="text" placeholder="Owner address" aria-label="Owner address" /></div>
            <div class="project-information-location">
              <div class="project-field-group"><input id="editOwnerContact" type="text" placeholder="Contact name" aria-label="Owner contact name" /></div>
              <div class="project-field-group"><input id="editOwnerPhone" type="tel" placeholder="Phone number" aria-label="Owner contact number" /></div>
            </div>
          </section>

          <section class="contact-information accommodation-information" aria-labelledby="editAccommodationInformationTitle">
            <h3 id="editAccommodationInformationTitle">Accommodation Information</h3>
            <div class="project-field-group"><input id="editHotelName" type="text" placeholder="Hotel name" aria-label="Hotel name" /></div>
            <div class="project-field-group"><input id="editHotelAddress" type="text" placeholder="Hotel address" aria-label="Hotel address" /></div>
            <div class="accommodation-information-row">
              <div class="project-field-group"><input id="editHotelRooms" type="number" min="0" placeholder="No. of rooms" aria-label="Number of rooms" /></div>
              <div class="project-field-group"><input id="editHotelConfirmation" type="text" placeholder="Confirmation number" aria-label="Hotel confirmation number" /></div>
            </div>
            <div class="project-field-group"><input id="editHotelPhone" type="tel" placeholder="Hotel phone" aria-label="Hotel phone" /></div>
          </section>
        </div>
      </div>
    </div>
  </div>

  <div class="modal-overlay" id="crewEquipmentModal" hidden>
    <div class="modal-card add-project-card crew-equipment-modal" role="dialog" aria-modal="true" aria-labelledby="crewEquipmentTitle">
      <div class="add-project-left crew-equipment-panel crew-project-panel" aria-labelledby="crewEquipmentProjectName">
        <div class="modal-head">
          <h2 id="crewEquipmentTitle">Crew/Equipment</h2>
        </div>
        <p class="crew-equipment-panel-label">Project</p>
        <h3 id="crewEquipmentProjectName">Project</h3>
        <p class="crew-equipment-panel-label crew-scheduled-days-label">Scheduled Days</p>
        <div class="crew-scheduled-days" id="crewScheduledDays" aria-label="Scheduled project days"></div>
      </div>
      <div class="add-project-notes crew-equipment-panel crew-assignments-panel" aria-labelledby="crewEquipmentAssignmentsTitle">
        <h3 id="crewEquipmentAssignmentsTitle">Assignments</h3>
        <p class="crew-selected-day" id="crewSelectedDay">Select a scheduled day</p>
        <button type="button" class="secondary-btn crew-apply-all-btn" id="applyCrewEquipmentToAllDays">Click here to apply this assignment to all project days</button>
        <div class="crew-assignment-group">
          <h4>Crew Members</h4>
          <div class="crew-assignment-row" id="crewAssignedPersonnel"></div>
        </div>
        <div class="crew-assignment-group">
          <h4>Equipment</h4>
          <div class="crew-assignment-row" id="crewAssignedEquipment"></div>
        </div>
      </div>
      <div class="add-project-right crew-equipment-panel crew-resources-panel" aria-label="Crew and equipment resources">
        <div class="modal-head">
          <div class="modal-head-actions">
            <button type="button" class="secondary-btn crew-save-return-btn" id="saveCrewEquipmentAndReturn">Save &amp; Go Back</button>
            <button type="button" class="modal-close-btn" id="closeCrewEquipmentModal" aria-label="Close crew and equipment">X</button>
          </div>
        </div>
        <p class="crew-equipment-panel-label" id="crewAvailablePersonnelLabel">Available Crew Members</p>
        <div class="crew-available-resources" id="crewAvailablePersonnel"></div>
        <p class="crew-equipment-panel-label" id="crewAvailableEquipmentLabel">Available Equipment</p>
        <div class="crew-available-resources" id="crewAvailableEquipment"></div>

        <div class="crew-unavailable">
          <button type="button" class="crew-unavailable-toggle" id="crewUnavailableToggle" aria-expanded="false" aria-controls="crewUnavailablePanel">
            <span id="crewUnavailableToggleLabel">Not available crew / equipment</span>
            <span class="crew-unavailable-chevron" aria-hidden="true">&#9662;</span>
          </button>
          <div class="crew-unavailable-panel" id="crewUnavailablePanel" hidden>
            <p class="crew-unavailable-hint">These are already assigned to another project on this day. Moving one here removes it from that project for this day.</p>
            <p class="crew-equipment-panel-label">Crew Members</p>
            <div class="crew-unavailable-list" id="crewUnavailablePersonnel"></div>
            <p class="crew-equipment-panel-label">Equipment</p>
            <div class="crew-unavailable-list" id="crewUnavailableEquipment"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal-overlay" id="viewProjectModal" hidden>
    <div class="modal-card project-view-card" role="dialog" aria-modal="true" aria-labelledby="viewProjectTitle">
      <div class="modal-head">
        <h2 id="viewProjectTitle">Project</h2>
        <button type="button" class="modal-close-btn" id="closeViewProjectModal" aria-label="Close project details">X</button>
      </div>

      <div class="project-view-meta">
        <div class="meta-row">
          <span class="meta-label">Dates</span>
          <span class="meta-value" id="viewProjectDates">-</span>
        </div>
        <div class="meta-row">
          <span class="meta-label">Location</span>
          <span class="meta-value"><input id="viewProjectLocation" type="text" placeholder="Project location" style="width:260px;padding:6px;border-radius:6px;border:1px solid #d1d5db;" /></span>
        </div>
          <div class="meta-row">
            <span class="meta-label">Details</span>
            <span class="meta-value"><textarea id="viewProjectDetails" placeholder="Project details" style="width:100%; max-width:420px; min-height:80px; padding:8px; border-radius:6px; border:1px solid #d1d5db; box-sizing:border-box;"></textarea></span>
          </div>
      </div>

      <div class="project-view-grid">
        <section class="project-view-section" aria-label="Assigned Crew Members">
          <h3>Crew Members</h3>
          <div class="chip-drop-area" id="viewProjectPersonnel"></div>
        </section>

        <section class="project-view-section" aria-label="Assigned equipment">
          <h3>Equipment</h3>
          <div class="chip-drop-area" id="viewProjectEquipments"></div>
        </section>
      </div>

      <p class="project-view-helper">Use Edit Crew/Equipment on a project to manage its crew and equipment assignments.</p>

      <div class="project-view-actions">
        <button type="button" class="primary-btn" id="saveProjectMetaBtn" style="margin-right:8px;">Save</button>
        <button type="button" class="danger-btn" id="deleteProjectBtn">Delete Project</button>
        <button type="button" class="secondary-btn" id="closeProjectViewBtn">Close</button>
      </div>
    </div>
  </div>

  <div class="modal-overlay" id="decisionModal" hidden>
    <div class="modal-card decision-modal-card" role="dialog" aria-modal="true" aria-labelledby="decisionModalTitle">
      <div class="modal-head">
        <h2 id="decisionModalTitle">Confirm Action</h2>
      </div>
      <p class="decision-modal-message" id="decisionModalMessage"></p>
      <div class="conflict-visualization" id="conflictVisualization" style="display: none;"></div>
      <div class="conflict-projects-chips" id="conflictProjectsChips" style="display: none;"></div>
      <div class="decision-modal-actions">
        <button type="button" class="secondary-btn" id="decisionCancelBtn">Cancel</button>
        <button type="button" class="primary-btn" id="decisionConfirmBtn">OK</button>
      </div>
    </div>
    
  </div>

  <script>
    (function(){
      // Notes-image managers, built at the bottom of this IIFE.
      var notesImages = { add: null, details: null };
      var APP_BASE_URL = <?php echo json_encode($appBaseUrl); ?>;
      var scheduledProjects = <?php echo json_encode($scheduledProjects, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
      // Map of equipment_id => operating_condition (string)
      var equipmentStatus = <?php
        $__eq_status = [];
        foreach ($equipments as $__eq_row) {
          $__id = isset($__eq_row['equipment_id']) ? (int)$__eq_row['equipment_id'] : 0;
          $__op = isset($__eq_row['operating_condition']) ? $__eq_row['operating_condition'] : '';
          if ($__id) $__eq_status[$__id] = $__op;
        }
        echo json_encode($__eq_status, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
      ?>;
      var serverPerDayDetails = <?php echo json_encode($perDayDetails, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || {};
      var projectById = {};
      // perDayDetails stores equipments/personnel per project-day (key: projectId|YYYY-MM-DD)
      var perDayDetails = {};
      if (Array.isArray(scheduledProjects)) {
        scheduledProjects.forEach(function(project){
          projectById[String(project.project_id)] = project;
          // if server provided per-day details include them
          try {
            // seed from server-provided per-day mapping first
            for (var k in serverPerDayDetails) {
              if (!serverPerDayDetails.hasOwnProperty(k)) continue;
              perDayDetails[String(k)] = {
                equipments: String(serverPerDayDetails[k].equipments || ''),
                personnel: String(serverPerDayDetails[k].personnel || '')
              };
            }
          } catch (e) {}
        });
      }

      var usersToggle = document.getElementById('usersToggle');
      var usersGroup = document.getElementById('usersGroup');
      if (usersToggle && usersGroup) {
        usersToggle.addEventListener('click', function(){
          usersGroup.classList.toggle('open');
        });
      }

      var autosaveIndicator = document.getElementById('autosaveIndicator');
      var autosaveIndicatorTimer = null;
      var existingProjectToggle = document.getElementById('toggleExistingProjectPicker');
      var existingProjectsPicker = document.getElementById('existingProjectsPicker');
      var existingProjectSelect = document.getElementById('existingProjectSelect');
      var projectNameInput = document.getElementById('project_name');
      var isDirty = false;

      if (existingProjectToggle && existingProjectsPicker) {
        existingProjectToggle.addEventListener('click', function(){
          existingProjectsPicker.hidden = !existingProjectsPicker.hidden;
          existingProjectToggle.setAttribute('aria-expanded', existingProjectsPicker.hidden ? 'false' : 'true');
          if (!existingProjectsPicker.hidden && existingProjectSelect) {
            existingProjectSelect.focus();
          }
        });
      }

      // ----- Import Project Information + GC details from an existing project -----
      var IMPORT_TARGETS = {
        project_address:   { id: 'projectAddress',   label: 'Project Address' },
        project_city:      { id: 'projectCity',      label: 'City' },
        project_state:     { id: 'projectState',     label: 'State' },
        contractor_name:   { id: 'contractorName',   label: 'Contractor Name' },
        contractor_email:  { id: 'contractorEmail',  label: 'Contractor Email' },
        contractor_address:{ id: 'contractorAddress', label: 'Contractor Address' },
        contractor_phone:  { id: 'contractorPhone',  label: 'Contractor Phone' }
      };

      function escImport(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
          return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
      }

      function showImportChoiceModal(fieldLabel, options) {
        return new Promise(function (resolve) {
          var overlay = document.getElementById('importChoiceOverlay');
          if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'importChoiceOverlay';
            overlay.className = 'modal-overlay';
            overlay.hidden = true;
            overlay.innerHTML =
              '<div class="modal-card" role="dialog" aria-modal="true" style="width:min(460px,100%);padding:20px;">'
              + '<h2 style="margin:0 0 4px;font-size:1.15rem;color:#1f2f49;">Which value should be used?</h2>'
              + '<p id="importChoiceField" style="margin:0 0 14px;color:#637491;font-size:0.82rem;font-weight:700;"></p>'
              + '<div id="importChoiceOptions" style="display:flex;flex-direction:column;gap:8px;"></div>'
              + '<div style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px;">'
              + '<button type="button" class="secondary-btn" id="importChoiceSkip">Skip this field</button>'
              + '<button type="button" class="primary-btn" id="importChoiceUse">Use selected</button>'
              + '</div></div>';
            document.body.appendChild(overlay);
          }
          overlay.querySelector('#importChoiceField').textContent = fieldLabel;
          var optWrap = overlay.querySelector('#importChoiceOptions');
          optWrap.innerHTML = '';
          options.forEach(function (opt, idx) {
            var label = document.createElement('label');
            label.style.cssText = 'display:flex;gap:10px;align-items:flex-start;border:1px solid #d7dee9;border-radius:9px;padding:10px 12px;cursor:pointer;';
            label.innerHTML =
              '<input type="radio" name="importChoice" value="' + idx + '"' + (idx === 0 ? ' checked' : '') + ' style="margin-top:3px;">'
              + '<span style="min-width:0;">'
              + '<span style="display:block;font-size:0.66rem;font-weight:800;text-transform:uppercase;letter-spacing:0.06em;color:#8a98af;">' + escImport(opt.source) + '</span>'
              + '<span style="display:block;font-weight:700;color:#1f2f49;word-break:break-word;">' + escImport(opt.value) + '</span>'
              + '</span>';
            optWrap.appendChild(label);
          });
          overlay.hidden = false;

          var useBtn = overlay.querySelector('#importChoiceUse');
          var skipBtn = overlay.querySelector('#importChoiceSkip');
          function done(val) {
            overlay.hidden = true;
            useBtn.removeEventListener('click', onUse);
            skipBtn.removeEventListener('click', onSkip);
            resolve(val);
          }
          function onUse() {
            var sel = overlay.querySelector('input[name="importChoice"]:checked');
            var i = sel ? parseInt(sel.value, 10) : 0;
            done(options[i] ? options[i].value : null);
          }
          function onSkip() { done(null); }
          useBtn.addEventListener('click', onUse);
          skipBtn.addEventListener('click', onSkip);
        });
      }

      async function importExistingProject(projectId, projectName) {
        var url = window.location.pathname
          + '?action=import_existing_project&project_id=' + encodeURIComponent(projectId || '')
          + '&project_name=' + encodeURIComponent(projectName || '');
        var data;
        try {
          var res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          data = await res.json();
        } catch (e) {
          await showInfoModal('Import Failed', 'Could not load information for this project.');
          return;
        }
        if (!data || !data.success) {
          await showInfoModal('Import Failed', (data && data.message) || 'Could not load information for this project.');
          return;
        }

        if (projectNameInput && data.clean_name) {
          projectNameInput.value = data.clean_name;
        }

        // Clear the target fields first so a re-selection never leaves stale values.
        Object.keys(IMPORT_TARGETS).forEach(function (key) {
          var el = document.getElementById(IMPORT_TARGETS[key].id);
          if (el) el.value = '';
        });

        var fields = data.fields || {};
        var conflicts = [];
        var importedCount = 0;

        Object.keys(IMPORT_TARGETS).forEach(function (key) {
          var el = document.getElementById(IMPORT_TARGETS[key].id);
          var f = fields[key];
          if (!el || !f) return;
          if (f.conflict) {
            conflicts.push({ key: key, options: f.options || [] });
          } else if (f.value != null && String(f.value).trim() !== '') {
            el.value = f.value;
            importedCount++;
          }
        });

        for (var i = 0; i < conflicts.length; i++) {
          var c = conflicts[i];
          var choice = await showImportChoiceModal(IMPORT_TARGETS[c.key].label, c.options);
          if (choice != null && String(choice).trim() !== '') {
            var el = document.getElementById(IMPORT_TARGETS[c.key].id);
            if (el) { el.value = choice; importedCount++; }
          }
        }

        if (importedCount > 0) {
          var sources = [];
          if (data.found && data.found.bid) sources.push('Bid Tracking');
          if (data.found && data.found.checklist) sources.push('Project Checklist');
          var srcText = sources.length ? ' from ' + sources.join(' and ') : '';
          await showInfoModal('Info Imported', importedCount + ' field' + (importedCount === 1 ? '' : 's') + ' were imported' + srcText + '.');
        } else {
          await showInfoModal('Nothing to Import', 'No matching Bid Tracking or Project Checklist information was found for this project.');
        }
      }

      if (existingProjectSelect) {
        existingProjectSelect.addEventListener('change', function(){
          var selectedOption = this.options[this.selectedIndex];
          if (!selectedOption || !selectedOption.value) {
            return;
          }
          var selectedId = selectedOption.value || '';
          var selectedName = selectedOption.getAttribute('data-project-name') || '';
          if (projectNameInput && selectedName) {
            projectNameInput.value = selectedName;
          }
          this.selectedIndex = 0;
          if (existingProjectsPicker) {
            existingProjectsPicker.hidden = true;
          }
          importExistingProject(selectedId, selectedName);
        });
      }

      // Every assignment change is persisted to the server the moment it happens
      // (drag-and-drop, modal edits, apply-to-all-days). This just surfaces a
      // transient "saved" note where the old Save Changes button used to be.
      function showAutosaveStatus(state) {
        if (!autosaveIndicator) {
          return;
        }
        if (autosaveIndicatorTimer) {
          window.clearTimeout(autosaveIndicatorTimer);
          autosaveIndicatorTimer = null;
        }
        autosaveIndicator.classList.remove('is-saving', 'is-error');
        if (state === 'saving') {
          autosaveIndicator.textContent = 'Saving…';
          autosaveIndicator.classList.add('is-visible', 'is-saving');
          return;
        }
        if (state === 'error') {
          autosaveIndicator.textContent = 'Not saved – changes could not be saved';
          autosaveIndicator.classList.add('is-visible', 'is-error');
          autosaveIndicatorTimer = window.setTimeout(function () {
            autosaveIndicator.classList.remove('is-visible');
          }, 6000);
          return;
        }
        autosaveIndicator.textContent = 'All changes saved ✓';
        autosaveIndicator.classList.add('is-visible');
        autosaveIndicatorTimer = window.setTimeout(function () {
          autosaveIndicator.classList.remove('is-visible');
        }, 2500);
      }

      function setDirty(flag) {
        isDirty = !!flag;
        if (flag) {
          showAutosaveStatus('saved');
        }
      }

      // Crew/equipment resources are managed from the Crew/Equipment modal.
      // #personnelList / #equipmentList exist only as a hidden data source for it.

      function updateProjectRequirement(projectId, kind, value, day) {
        var params = new URLSearchParams();
          params.set('action', 'add_project_requirement');
          params.set('project_id', String(projectId));
          params.set('kind', kind);
          params.set('value', value);
          if (typeof day === 'string' && day) {
            params.set('day', day);
          }

          return fetch(window.location.pathname, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: params.toString()
        }).then(function(res){
          return res.json().then(function(data){
            return { ok: res.ok, data: data };
          });
        });
      }

      function setProjectDayDetails(projectId, dayEntries) {
        if (!projectId || !dayEntries || !Array.isArray(dayEntries)) {
          return;
        }
        dayEntries.forEach(function(entry){
          if (!entry || typeof entry.day !== 'string' || !entry.day) {
            return;
          }
          var key = String(projectId) + '|' + entry.day;
          perDayDetails[key] = {
            equipments: String(entry.equipments || ''),
            personnel: String(entry.personnel || '')
          };
        });
      }
        function removeProjectRequirement(projectId, kind, value, day) {
          var params = new URLSearchParams();
          params.set('action', 'remove_project_requirement');
          params.set('project_id', String(projectId));
          params.set('kind', kind);
          params.set('value', value);
          if (typeof day === 'string' && day) {
            params.set('day', day);
          }

          return fetch(window.location.pathname, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: params.toString()
        }).then(function(res){
          return res.json().then(function(data){
            return { ok: res.ok, data: data };
          });
        });
      }

      function deleteScheduledProject(projectId) {
        var params = new URLSearchParams();
        params.set('action', 'delete_scheduled_project');
        params.set('project_id', String(projectId));

        return fetch(window.location.pathname, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: params.toString()
        }).then(function(res){
          return res.json().then(function(data){
            return { ok: res.ok, data: data };
          });
        });
      }

      // Delete a single day for a project
      function deleteProjectDay(projectId, day) {
        var params = new URLSearchParams();
        params.set('action', 'delete_project_day');
        params.set('project_id', String(projectId));
        params.set('day', String(day));

        return fetch(window.location.pathname, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: params.toString()
        }).then(function(res){
          return res.json().then(function(data){ return { ok: res.ok, data: data }; });
        });
      }

      function updateProjectMeta(projectId, location, details) {
        var params = new URLSearchParams();
        params.set('action', 'update_project_meta');
        params.set('project_id', String(projectId));
        params.set('location', String(location || ''));
        params.set('details', String(details || ''));

        return fetch(window.location.pathname, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: params.toString()
        }).then(function(res){
          return res.json().then(function(data){ return { ok: res.ok, data: data }; });
        });
      }

      function parseDateTime(value) {
        if (!value || typeof value !== 'string') {
          return null;
        }
        var parsed = new Date(value.replace(' ', 'T'));
        return isNaN(parsed.getTime()) ? null : parsed;
      }

      function parseCsvList(csv) {
        if (!csv || typeof csv !== 'string') {
          return [];
        }
        return csv.split(',').map(function(item){ return item.trim(); }).filter(function(item){ return item !== ''; });
      }

      function normalizeAssignmentValue(value) {
        return String(value || '').trim().toLowerCase();
      }

      function getProjectAssignmentsForDay(project, kind, dayKey) {
        if (!project || !kind) {
          return [];
        }

        if (typeof dayKey === 'string' && dayKey) {
          var projectDayKey = String(project.project_id) + '|' + dayKey;
          var perDay = perDayDetails[projectDayKey];
          if (perDay) {
            return kind === 'personnel' ? parseCsvList(perDay.personnel) : parseCsvList(perDay.equipments);
          }

          // If this day has no stored per-day row yet, it is not assigned to that resource on that date.
          return [];
        }

        return kind === 'personnel' ? parseCsvList(project.personnel) : parseCsvList(project.equipments);
      }

      function projectHasAssignment(project, kind, value, dayKey) {
        if (!project || !kind) {
          return false;
        }
        var normalizedTarget = normalizeAssignmentValue(value);
        if (!normalizedTarget) {
          return false;
        }
        var list = getProjectAssignmentsForDay(project, kind, dayKey);
        for (var i = 0; i < list.length; i++) {
          if (normalizeAssignmentValue(list[i]) === normalizedTarget) {
            return true;
          }
        }
        return false;
      }

      function projectDayRange(project) {
        var startDate = parseDateTime(project.start);
        var endDate = parseDateTime(project.end);
        if (!startDate || !endDate) {
          return null;
        }
        return {
          start: new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate()),
          end: new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate())
        };
      }

      function dayRangesOverlap(rangeA, rangeB) {
        if (!rangeA || !rangeB) {
          return false;
        }
        return rangeA.start.getTime() <= rangeB.end.getTime() && rangeB.start.getTime() <= rangeA.end.getTime();
      }

      function findAssignmentConflicts(targetProject, kind, value, dayKey) {
        if (!targetProject) {
          return [];
        }
        var targetRange = projectDayRange(targetProject);
        if (!targetRange) {
          return [];
        }

        var targetId = Number(targetProject.project_id);
        return scheduledProjects.filter(function(existingProject){
          if (!existingProject || Number(existingProject.project_id) === targetId) {
            return false;
          }
          if (!projectHasAssignment(existingProject, kind, value, dayKey)) {
            return false;
          }
          var existingRange = projectDayRange(existingProject);
          return dayRangesOverlap(targetRange, existingRange);
        });
      }

      // Find conflicts for a specific calendar day: return projects (other than target) that have
      // the same `kind` (equipments/personnel) assigned on the provided ISO `dayKey` (YYYY-MM-DD).
      function findAssignmentConflictsForDay(targetProject, kind, value, dayKey) {
        if (!targetProject || !dayKey) {
          return [];
        }
        var normalizedTarget = normalizeAssignmentValue(value);
        if (!normalizedTarget) {
          return [];
        }

        var targetId = Number(targetProject.project_id);
        var conflicts = [];

        scheduledProjects.forEach(function(existingProject){
          if (!existingProject) return;
          var existingId = Number(existingProject.project_id);
          if (Number.isNaN(existingId) || existingId === targetId) return;

          // check whether the dayKey falls within the existing project's date range
          var erange = projectDayRange(existingProject);
          if (!erange) return;
          var dayDate = new Date(dayKey + 'T00:00:00');
          if (dayDate.getTime() < erange.start.getTime() || dayDate.getTime() > erange.end.getTime()) return;

          // check per-day details map first
          var key = String(existingId) + '|' + dayKey;
          var pd = perDayDetails[key];
          var listCsv = '';
          if (pd) {
            listCsv = (kind === 'personnel') ? (pd.personnel || '') : (pd.equipments || '');
          } else {
            // fallback: if the dayKey equals the project's start day, the server-loaded project fields may reflect that day
            try {
              var pstart = parseDateTime(existingProject.start);
              if (pstart) {
                var pstartKey = formatIsoDate(pstart);
                if (pstartKey === dayKey) {
                  listCsv = (kind === 'personnel') ? (existingProject.personnel || '') : (existingProject.equipments || '');
                }
              }
            } catch (e) { /* ignore */ }
          }

          if (!listCsv) return;
          var items = parseCsvList(listCsv);
          for (var i = 0; i < items.length; i++) {
            if (normalizeAssignmentValue(items[i]) === normalizedTarget) {
              conflicts.push(existingProject);
              break;
            }
          }
        });

        return conflicts;
      }

      function formatIsoDate(dateObj) {
        var y = dateObj.getFullYear();
        var m = String(dateObj.getMonth() + 1).padStart(2, '0');
        var d = String(dateObj.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
      }

      function formatHourLabel(dateObj) {
        var h = dateObj.getHours();
        var m = dateObj.getMinutes();
        var suffix = h >= 12 ? 'PM' : 'AM';
        var h12 = h % 12;
        if (h12 === 0) {
          h12 = 12;
        }
        return h12 + (m ? ':' + String(m).padStart(2, '0') : '') + ' ' + suffix;
      }

      function formatFriendlyRange(startDate, endDate) {
        if (!startDate || !endDate) return '';
        var s = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
        var e = new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate());
        var left = s.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        var right = e.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        return left + ' - ' + right;
      }

      var viewProjectModal = document.getElementById('viewProjectModal');
      var closeViewProjectModal = document.getElementById('closeViewProjectModal');
      var closeProjectViewBtn = document.getElementById('closeProjectViewBtn');
      var deleteProjectBtn = document.getElementById('deleteProjectBtn');
      var viewProjectTitle = document.getElementById('viewProjectTitle');
      var viewProjectDates = document.getElementById('viewProjectDates');
      var viewProjectPersonnel = document.getElementById('viewProjectPersonnel');
      var viewProjectEquipments = document.getElementById('viewProjectEquipments');
      var viewProjectLocation = document.getElementById('viewProjectLocation');
      var viewProjectDetails = document.getElementById('viewProjectDetails');
      var saveProjectMetaBtn = document.getElementById('saveProjectMetaBtn');
      var decisionModal = document.getElementById('decisionModal');
      var decisionModalTitle = document.getElementById('decisionModalTitle');
      var decisionModalMessage = document.getElementById('decisionModalMessage');
      var decisionModalHead = decisionModal ? decisionModal.querySelector('.modal-head') : null;
      var conflictVisualization = document.getElementById('conflictVisualization');
      var existingProjectTile = document.getElementById('existingProjectTile');
      var destinationProjectTile = document.getElementById('destinationProjectTile');
      var existingAssignmentText = document.getElementById('existingAssignmentText');
      var destinationAssignmentText = document.getElementById('destinationAssignmentText');
      var conflictProjectsChips = document.getElementById('conflictProjectsChips');
      var decisionCancelBtn = document.getElementById('decisionCancelBtn');
      var decisionConfirmBtn = document.getElementById('decisionConfirmBtn');
      var decisionResolver = null;
      var activeViewProjectId = null;
      var activeViewProjectDay = null;
      var rerenderProjects = null;

      var projectColorPalette = [
        '#2F67C3', '#1F8A70', '#C05B2D', '#5A4FCF', '#1877A8', '#8C4A94', '#2B7A3E', '#A14B5F', '#3A6FA6', '#9A6B1C'
      ];

      function getProjectColor(project) {
        var idNumber = Number(project.project_id);
        if (!Number.isNaN(idNumber) && idNumber > 0) {
          return projectColorPalette[idNumber % projectColorPalette.length];
        }

        var key = String(project.project_name || 'project');
        var hash = 0;
        for (var i = 0; i < key.length; i++) {
          hash = ((hash << 5) - hash) + key.charCodeAt(i);
          hash |= 0;
        }
        return projectColorPalette[Math.abs(hash) % projectColorPalette.length];
      }

      function resolveDecision(result) {
        if (decisionModal) {
          decisionModal.hidden = true;
        }
        if (decisionResolver) {
          var resolver = decisionResolver;
          decisionResolver = null;
          resolver(result);
        }
      }

      function showDecisionModal(options) {
        var config = options || {};
        if (!decisionModal || !decisionModalTitle || !decisionModalMessage || !decisionCancelBtn || !decisionConfirmBtn) {
          return Promise.resolve(!!config.fallbackValue);
        }

        if (decisionResolver) {
          resolveDecision(false);
        }

        // Respect explicit empty title (allow hiding header)
        if (Object.prototype.hasOwnProperty.call(config, 'title')) {
          decisionModalTitle.textContent = config.title;
        } else {
          decisionModalTitle.textContent = 'Confirm Action';
        }
        if (decisionModalHead) {
          if (String(decisionModalTitle.textContent || '').trim() === '') {
            decisionModalHead.style.display = 'none';
          } else {
            decisionModalHead.style.display = '';
          }
        }
        if (config.messageHtml) {
          decisionModalMessage.innerHTML = config.messageHtml || '';
        } else {
          decisionModalMessage.textContent = config.message || '';
        }
        decisionConfirmBtn.textContent = config.confirmText || 'OK';
        decisionCancelBtn.textContent = config.cancelText || 'Cancel';
        decisionCancelBtn.hidden = config.showCancel === false;

        // Hide visualization and chips by default
        if (conflictVisualization) {
          conflictVisualization.style.display = 'none';
        }
        if (conflictProjectsChips) {
          conflictProjectsChips.innerHTML = '';
          conflictProjectsChips.style.display = 'none';
        }

        // Show visualization if conflicts exist
        if (conflictVisualization && config.conflicts && Array.isArray(config.conflicts) && config.conflicts.length > 0) {
          conflictVisualization.style.display = '';
          // The visualization was already rendered by renderConflictVisualization
        }

        decisionModal.hidden = false;

        return new Promise(function(resolve){
          decisionResolver = resolve;
        });
      }

      function showInfoModal(title, message) {
        return showDecisionModal({
          title: title || 'Notice',
          message: message || '',
          confirmText: 'OK',
          showCancel: false,
          fallbackValue: true
        });
      }

      function renderConflictVisualization(targetProject, conflicts) {
        if (!conflictVisualization || !targetProject) {
          return;
        }

        conflictVisualization.innerHTML = '';

        var targetRange = projectDayRange(targetProject);
        if (!targetRange) {
          return;
        }

        var container = document.createElement('div');
        container.className = 'visualization-container';

        var timelineLabel = document.createElement('div');
        timelineLabel.className = 'timeline-label';
        timelineLabel.textContent = 'Timeline Conflicts:';
        container.appendChild(timelineLabel);

        var timeline = document.createElement('div');
        timeline.className = 'timeline';

        var startLabel = formatIsoDate(targetRange.start);
        var endLabel = formatIsoDate(targetRange.end);
        var allDates = {};
        allDates[startLabel] = true;
        allDates[endLabel] = true;

        if (conflicts && Array.isArray(conflicts)) {
          conflicts.forEach(function(conflict){
            var cRange = projectDayRange(conflict);
            if (cRange) {
              allDates[formatIsoDate(cRange.start)] = true;
              allDates[formatIsoDate(cRange.end)] = true;
            }
          });
        }

        var sortedDates = Object.keys(allDates).sort();

        sortedDates.forEach(function(dateStr, idx){
          var dateObj = new Date(dateStr + 'T00:00:00');
          var dayNum = dateObj.getDate();
          var dayName = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][dateObj.getDay()];

          var dayMarker = document.createElement('div');
          dayMarker.className = 'day-marker';

          var dayLabel = document.createElement('div');
          dayLabel.className = 'day-label';
          dayLabel.textContent = dayName + ' ' + dayNum;
          dayMarker.appendChild(dayLabel);

          timeline.appendChild(dayMarker);

          if (idx < sortedDates.length - 1) {
            var connector = document.createElement('div');
            connector.className = 'day-connector';
            timeline.appendChild(connector);
          }
        });

        container.appendChild(timeline);

        var projectsLabel = document.createElement('div');
        projectsLabel.className = 'projects-label';
        projectsLabel.textContent = 'Conflicting Projects:';
        container.appendChild(projectsLabel);

        var projectsList = document.createElement('div');
        projectsList.className = 'projects-list';

        var targetProjectRow = document.createElement('div');
        targetProjectRow.className = 'project-row target-project';

        var targetColor = getProjectColor(targetProject);
        var targetColorBox = document.createElement('div');
        targetColorBox.className = 'project-color-box';
        targetColorBox.style.backgroundColor = targetColor;
        targetProjectRow.appendChild(targetColorBox);

        var targetName = document.createElement('div');
        targetName.className = 'project-name';
        targetName.textContent = targetProject.project_name || 'Project';
        targetProjectRow.appendChild(targetName);

        var targetDates = document.createElement('div');
        targetDates.className = 'project-dates';
        targetDates.textContent = startLabel + ' - ' + endLabel;
        targetProjectRow.appendChild(targetDates);

        projectsList.appendChild(targetProjectRow);

        if (conflicts && Array.isArray(conflicts)) {
          conflicts.forEach(function(conflict){
            var cRange = projectDayRange(conflict);
            if (!cRange) {
              return;
            }

            var conflictRow = document.createElement('div');
            conflictRow.className = 'project-row conflict-project';

            var conflictColor = getProjectColor(conflict);
            var conflictColorBox = document.createElement('div');
            conflictColorBox.className = 'project-color-box';
            conflictColorBox.style.backgroundColor = conflictColor;
            conflictRow.appendChild(conflictColorBox);

            var conflictName = document.createElement('div');
            conflictName.className = 'project-name';
            conflictName.textContent = conflict.project_name || 'Project';
            conflictRow.appendChild(conflictName);

            var conflictDates = document.createElement('div');
            conflictDates.className = 'project-dates';
            conflictDates.textContent = formatIsoDate(cRange.start) + ' - ' + formatIsoDate(cRange.end);
            conflictRow.appendChild(conflictDates);

            projectsList.appendChild(conflictRow);
          });
        }

        container.appendChild(projectsList);
        conflictVisualization.appendChild(container);
      }

      if (decisionConfirmBtn) {
        decisionConfirmBtn.addEventListener('click', function(){
          resolveDecision(true);
        });
      }
      if (decisionCancelBtn) {
        decisionCancelBtn.addEventListener('click', function(){
          resolveDecision(false);
        });
      }
      if (decisionModal) {
        decisionModal.addEventListener('click', function(e){
          if (e.target === decisionModal) {
            resolveDecision(false);
          }
        });
      }
      document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && decisionModal && !decisionModal.hidden) {
          resolveDecision(false);
        }
      });

      function renderViewChips(containerEl, values, kind) {
        if (!containerEl) {
          return;
        }
        containerEl.innerHTML = '';
        if (!Array.isArray(values) || values.length === 0) {
          var empty = document.createElement('span');
          empty.className = 'chip-empty';
          empty.textContent = 'None assigned';
          containerEl.appendChild(empty);
          return;
        }

        values.forEach(function(value){
          var chip = document.createElement('button');
          chip.type = 'button';
          chip.className = 'assign-chip';
          chip.setAttribute('data-remove-kind', kind);
          chip.setAttribute('data-remove-value', value);

          var label = document.createElement('span');
          label.className = 'assign-chip-label';
          label.textContent = value;
          chip.appendChild(label);

          var x = document.createElement('span');
          x.className = 'assign-chip-x';
          x.textContent = 'x';
          chip.appendChild(x);

          containerEl.appendChild(chip);
        });
      }

      function openProjectViewModal(project, dayKey) {
        if (!project || !viewProjectModal) {
          return;
        }
        activeViewProjectId = Number(project.project_id);
        activeViewProjectDay = typeof dayKey === 'string' ? dayKey : '';

        var startDate = parseDateTime(project.start);
        var endDate = parseDateTime(project.end);

        if (viewProjectTitle) {
          viewProjectTitle.textContent = project.project_name || 'Project';
        }
        if (viewProjectDates) {
          if (activeViewProjectDay) {
            try {
              var dayDateObj = new Date(activeViewProjectDay + 'T00:00:00');
              if (!isNaN(dayDateObj.getTime())) {
                viewProjectDates.textContent = dayDateObj.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
              } else if (startDate && endDate) {
                viewProjectDates.textContent = formatFriendlyRange(startDate, endDate);
              }
            } catch (e) {
              if (startDate && endDate) {
                viewProjectDates.textContent = formatFriendlyRange(startDate, endDate);
              }
            }
          } else if (startDate && endDate) {
            viewProjectDates.textContent = formatFriendlyRange(startDate, endDate);
          }
        }

        // prefer per-day details when available
        var perKey = String(project.project_id) + '|' + (activeViewProjectDay || '');
        var pd = perDayDetails[perKey] || null;
        var personnels = pd ? (pd.personnel || '') : (project.personnel || '');
        var equipments = pd ? (pd.equipments || '') : (project.equipments || '');

        // set shared project location & details
        try {
          if (viewProjectLocation) {
            viewProjectLocation.value = String(project.location || '');
          }
          if (viewProjectDetails) {
            viewProjectDetails.value = String(project.details || '');
          }
        } catch (e) {}

        renderViewChips(viewProjectPersonnel, parseCsvList(personnels), 'personnel');
        renderViewChips(viewProjectEquipments, parseCsvList(equipments), 'equipments');

        viewProjectModal.hidden = false;
      }

     var projectDetailsModal = document.getElementById('projectDetailsModal');
var closeProjectDetailsModal = document.getElementById('closeProjectDetailsModal');
var editProjectForm = document.getElementById('editProjectForm');
var editProjectName = document.getElementById('editProjectName');
var editCalendarGrid = document.getElementById('editCalendarGrid');
var editCalendarMonth = document.getElementById('editCalendarMonth');
var editCalendarDate = new Date();
var editSelectedDates = [];
var editSelectedDatesField = document.getElementById('editSelectedDatesField');
var editSelectedDatesSummary = document.getElementById('editSelectedDatesSummary');
var crewEquipmentModal = document.getElementById('crewEquipmentModal');
var closeCrewEquipmentModal = document.getElementById('closeCrewEquipmentModal');
var saveCrewEquipmentAndReturn = document.getElementById('saveCrewEquipmentAndReturn');
var openCrewEquipmentFromProjectDetails = document.getElementById('openCrewEquipmentFromProjectDetails');
var activeProjectDetailsProject = null;
var activeProjectDetailsDay = '';
var editProjectDetailsOriginalDates = [];
var editProjectDetailsOriginalMeta = '';
var crewEquipmentProjectName = document.getElementById('crewEquipmentProjectName');
var crewScheduledDays = document.getElementById('crewScheduledDays');
var crewAvailablePersonnel = document.getElementById('crewAvailablePersonnel');
var crewAvailableEquipment = document.getElementById('crewAvailableEquipment');
var crewAvailablePersonnelLabel = document.getElementById('crewAvailablePersonnelLabel');
var crewAvailableEquipmentLabel = document.getElementById('crewAvailableEquipmentLabel');
var crewUnavailableToggle = document.getElementById('crewUnavailableToggle');
var crewUnavailableToggleLabel = document.getElementById('crewUnavailableToggleLabel');
var crewUnavailablePanel = document.getElementById('crewUnavailablePanel');
var crewUnavailablePersonnel = document.getElementById('crewUnavailablePersonnel');
var crewUnavailableEquipment = document.getElementById('crewUnavailableEquipment');
var crewSelectedDay = document.getElementById('crewSelectedDay');
var crewAssignedPersonnel = document.getElementById('crewAssignedPersonnel');
var crewAssignedEquipment = document.getElementById('crewAssignedEquipment');
var applyCrewEquipmentToAllDays = document.getElementById('applyCrewEquipmentToAllDays');
var activeCrewEquipmentProject = null;
var activeCrewEquipmentDay = '';

      function closeProjectDetailsModalFn() {
        if (projectDetailsModal) {
          projectDetailsModal.hidden = true;
        }
        if (notesImages.details) {
          notesImages.details.clear();
        }
      }

      function updateEditSelectedDatesSummary() {
        editSelectedDates = editSelectedDates
          .filter(function (day, index, all) { return day && all.indexOf(day) === index; })
          .sort();
        if (editSelectedDatesField) {
          editSelectedDatesField.value = JSON.stringify(editSelectedDates);
        }
        if (editSelectedDatesSummary) {
          editSelectedDatesSummary.textContent = editSelectedDates.length
            ? editSelectedDates.length + ' day' + (editSelectedDates.length === 1 ? '' : 's') + ' selected'
            : 'No dates selected';
        }
      }

      // Project Detail fields shared by the edit modal (id in edit modal, DB column, type).
      var PROJECT_META_FIELDS = [
        { col: 'project_address',         editId: 'editProjectAddress',    type: 'text' },
        { col: 'project_city',            editId: 'editProjectCity',       type: 'text' },
        { col: 'project_state',           editId: 'editProjectState',      type: 'text' },
        { col: 'taxable',                 toggle: 'edit-taxable',          type: 'bool' },
        { col: 'certified',               toggle: 'edit-certified',        type: 'bool' },
        { col: 'permit',                  toggle: 'edit-permit',           type: 'bool' },
        { col: 'contractor_name',         editId: 'editContractorName',    type: 'text' },
        { col: 'contractor_email',        editId: 'editContractorEmail',   type: 'text' },
        { col: 'contractor_address',      editId: 'editContractorAddress', type: 'text' },
        { col: 'contractor_contact_name', editId: 'editContractorContact', type: 'text' },
        { col: 'contractor_phone',        editId: 'editContractorPhone',   type: 'text' },
        { col: 'owner_name',              editId: 'editOwnerName',         type: 'text' },
        { col: 'owner_email',             editId: 'editOwnerEmail',        type: 'text' },
        { col: 'owner_address',           editId: 'editOwnerAddress',      type: 'text' },
        { col: 'owner_contact_name',      editId: 'editOwnerContact',      type: 'text' },
        { col: 'owner_phone',             editId: 'editOwnerPhone',        type: 'text' },
        { col: 'hotel_name',              editId: 'editHotelName',         type: 'text' },
        { col: 'hotel_address',           editId: 'editHotelAddress',      type: 'text' },
        { col: 'hotel_rooms',             editId: 'editHotelRooms',        type: 'int'  },
        { col: 'hotel_confirmation',      editId: 'editHotelConfirmation', type: 'text' },
        { col: 'hotel_phone',             editId: 'editHotelPhone',        type: 'text' }
      ];

      function setEditToggle(toggleName, dbValue) {
        var yes = document.querySelector('.toggle-option[data-toggle-name="' + toggleName + '"][data-toggle-value="yes"]');
        var no = document.querySelector('.toggle-option[data-toggle-name="' + toggleName + '"][data-toggle-value="no"]');
        [yes, no].forEach(function (b) { if (b) { b.classList.remove('selected'); b.setAttribute('aria-pressed', 'false'); } });
        var v = (dbValue === null || dbValue === undefined || dbValue === '') ? null : String(dbValue).toLowerCase();
        var pick = null;
        if (v === '1' || v === 'yes' || v === 'true') pick = yes;
        else if (v === '0' || v === 'no' || v === 'false') pick = no;
        if (pick) { pick.classList.add('selected'); pick.setAttribute('aria-pressed', 'true'); }
      }
      function getEditToggle(toggleName) {
        var active = document.querySelector('.toggle-option[data-toggle-name="' + toggleName + '"].selected');
        return active ? (active.getAttribute('data-toggle-value') || '') : '';
      }

      function populateProjectMetaFields(project) {
        PROJECT_META_FIELDS.forEach(function (f) {
          var raw = (project && project[f.col] !== undefined && project[f.col] !== null) ? project[f.col] : '';
          if (f.type === 'bool') {
            setEditToggle(f.toggle, raw);
          } else {
            var el = document.getElementById(f.editId);
            if (el) el.value = String(raw);
          }
        });
        var notesEl = document.getElementById('detailsOfficeNotes');
        if (notesEl) notesEl.value = String((project && project.details) || '');
      }

      function collectProjectMetaPayload() {
        var payload = {};
        PROJECT_META_FIELDS.forEach(function (f) {
          if (f.type === 'bool') {
            payload[f.col] = getEditToggle(f.toggle);
          } else {
            var el = document.getElementById(f.editId);
            payload[f.col] = el ? String(el.value || '').trim() : '';
          }
        });
        var notesEl = document.getElementById('detailsOfficeNotes');
        payload.details = notesEl ? String(notesEl.value || '') : '';
        if (editProjectName) payload.project_name = String(editProjectName.value || '').trim();
        return payload;
      }

      function projectMetaSignature(project) {
        // Build the same shape from a project object for change detection.
        var parts = [];
        PROJECT_META_FIELDS.forEach(function (f) {
          var raw = (project && project[f.col] !== undefined && project[f.col] !== null) ? String(project[f.col]) : '';
          if (f.type === 'bool') {
            var v = raw.toLowerCase();
            raw = (v === '1' || v === 'yes' || v === 'true') ? 'yes' : ((v === '0' || v === 'no' || v === 'false') ? 'no' : '');
          }
          parts.push(f.col + '=' + raw.trim());
        });
        parts.push('details=' + String((project && project.details) || ''));
        parts.push('project_name=' + String((project && project.project_name) || '').trim());
        return parts.join('|');
      }
      function currentProjectMetaSignature() {
        var p = collectProjectMetaPayload();
        var parts = [];
        PROJECT_META_FIELDS.forEach(function (f) { parts.push(f.col + '=' + String(p[f.col] || '').trim()); });
        parts.push('details=' + String(p.details || ''));
        parts.push('project_name=' + String(p.project_name || '').trim());
        return parts.join('|');
      }

      function openProjectDetailsModal(project, dayKey) {
        if (!projectDetailsModal || !project) {
          return;
        }
        activeProjectDetailsProject = project;
        activeProjectDetailsDay = dayKey || '';
        if (editProjectName) {
          editProjectName.value = String(project.project_name || '');
        }
        populateProjectMetaFields(project);
        editProjectDetailsOriginalMeta = projectMetaSignature(project);
        // Pre-select the project's current scheduled days on the calendar.
        editSelectedDates = getCrewEquipmentProjectDays(project).slice();
        editProjectDetailsOriginalDates = editSelectedDates.slice();
        updateEditSelectedDatesSummary();
        var focusDay = (dayKey && editSelectedDates.indexOf(dayKey) !== -1) ? dayKey : editSelectedDates[0];
        var focusDate = focusDay ? new Date(focusDay + 'T00:00:00') : new Date();
        if (!Number.isNaN(focusDate.getTime())) {
          editCalendarDate = new Date(focusDate.getFullYear(), focusDate.getMonth(), 1);
        }
        renderEditProjectCalendar();
        if (notesImages.details) {
          notesImages.details.setProjectId(project.project_id);
          notesImages.details.load();
        }
        projectDetailsModal.hidden = false;
      }

      function renderEditProjectCalendar() {
        if (!editCalendarGrid || !editCalendarMonth) {
          return;
        }
        editCalendarGrid.innerHTML = '';
        editCalendarMonth.textContent = editCalendarDate.toLocaleString('en-US', { month: 'long', year: 'numeric' });
        var year = editCalendarDate.getFullYear();
        var month = editCalendarDate.getMonth();
        var firstDay = new Date(year, month, 1);
        var mondayOffset = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1;
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        for (var blank = 0; blank < mondayOffset; blank++) {
          var emptyDay = document.createElement('span');
          emptyDay.className = 'calendar-day empty';
          editCalendarGrid.appendChild(emptyDay);
        }
        for (var day = 1; day <= daysInMonth; day++) {
          var editDayDate = new Date(year, month, day);
          var editDayKey = formatDateKey(editDayDate);
          var dayButton = document.createElement('button');
          dayButton.type = 'button';
          dayButton.className = 'calendar-day';
          dayButton.textContent = String(day);
          if (editSelectedDates.indexOf(editDayKey) !== -1) {
            dayButton.classList.add('selected');
          }
                   dayButton.addEventListener('click', (function (key) {
            return function () {
              var selectedIndex = editSelectedDates.indexOf(key);
              if (selectedIndex === -1) {
                editSelectedDates.push(key);
              } else {
                editSelectedDates.splice(selectedIndex, 1);
              }
              updateEditSelectedDatesSummary();
              renderEditProjectCalendar();
            };
          })(editDayKey));
          editCalendarGrid.appendChild(dayButton);
        }
      }

      var editPreviousMonth = document.getElementById('editPreviousMonth');
      var editNextMonth = document.getElementById('editNextMonth');
      if (editPreviousMonth) {
        editPreviousMonth.addEventListener('click', function () {
          editCalendarDate.setMonth(editCalendarDate.getMonth() - 1);
          renderEditProjectCalendar();
        });
      }
      if (editNextMonth) {
        editNextMonth.addEventListener('click', function () {
          editCalendarDate.setMonth(editCalendarDate.getMonth() + 1);
          renderEditProjectCalendar();
        });
      }

      function closeCrewEquipmentModalFn() {
        if (crewEquipmentModal) {
          crewEquipmentModal.hidden = true;
        }
      }

      function getCrewEquipmentProjectDays(project) {
        if (!project || !project.project_id) {
          return [];
        }
        var projectPrefix = String(project.project_id) + '|';
        var days = Object.keys(perDayDetails).filter(function (key) {
          return key.indexOf(projectPrefix) === 0 && key.slice(projectPrefix.length);
        }).map(function (key) {
          return key.slice(projectPrefix.length);
        });

        if (days.length === 0) {
          var startDate = parseDateTime(project.start);
          var endDate = parseDateTime(project.end);
          if (startDate && endDate) {
            var cursor = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
            var end = new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate());
            while (cursor <= end) {
              days.push(formatIsoDate(cursor));
              cursor.setDate(cursor.getDate() + 1);
            }
          }
        }
        return days.filter(function (day, index, allDays) {
          return allDays.indexOf(day) === index;
        }).sort();
      }

      function formatCrewEquipmentDay(day) {
        var date = new Date(day + 'T00:00:00');
        return Number.isNaN(date.getTime()) ? day : date.toLocaleDateString('en-US', {
          weekday: 'short',
          month: 'short',
          day: 'numeric',
          year: 'numeric'
        });
      }

      function renderCrewEquipmentAssignmentRow(container, kind, values) {
        if (!container) {
          return;
        }
        container.innerHTML = '';
        if (!activeCrewEquipmentDay) {
          container.textContent = 'Select a scheduled day to manage assignments.';
          return;
        }
        if (!values.length) {
          var empty = document.createElement('span');
          empty.className = 'crew-assignment-empty';
          empty.textContent = 'None assigned';
          container.appendChild(empty);
          return;
        }
        values.forEach(function (value) {
          var chip = document.createElement('span');
          chip.className = 'crew-assignment-chip';
          var label = document.createElement('span');
          label.textContent = value;
          var removeButton = document.createElement('button');
          removeButton.type = 'button';
          removeButton.className = 'crew-assignment-remove';
          removeButton.setAttribute('aria-label', 'Remove ' + value);
          removeButton.title = 'Remove ' + value;
          removeButton.textContent = '\u00d7';
          removeButton.addEventListener('click', function () {
            removeCrewEquipmentAssignment(kind, value, removeButton);
          });
          chip.appendChild(label);
          chip.appendChild(removeButton);
          container.appendChild(chip);
        });
      }

      function renderCrewEquipmentResources() {
        var dayLabel = activeCrewEquipmentDay ? formatCrewEquipmentDay(activeCrewEquipmentDay) : '';
        if (crewAvailablePersonnelLabel) {
          crewAvailablePersonnelLabel.textContent = dayLabel ? 'Available Crew Members for ' + dayLabel : 'Available Crew Members';
        }
        if (crewAvailableEquipmentLabel) {
          crewAvailableEquipmentLabel.textContent = dayLabel ? 'Available Equipment for ' + dayLabel : 'Available Equipment';
        }

        var unavailableCount = 0;

        [
          {
            source: '#personnelList .requirement-source',
            kind: 'personnel',
            available: crewAvailablePersonnel,
            unavailable: crewUnavailablePersonnel
          },
          {
            source: '#equipmentList .requirement-source',
            kind: 'equipments',
            available: crewAvailableEquipment,
            unavailable: crewUnavailableEquipment
          }
        ].forEach(function (group) {
          if (group.available) { group.available.innerHTML = ''; }
          if (group.unavailable) { group.unavailable.innerHTML = ''; }

          document.querySelectorAll(group.source).forEach(function (source) {
            var kind = source.getAttribute('data-requirement-kind') || group.kind;
            var value = source.getAttribute('data-requirement-value') || '';
            var equipmentId = source.getAttribute('data-eid') || '';
            if (!kind || !value) {
              return;
            }

            // Already on this project for this day -> lives in the Assignments column, not here.
            if (activeCrewEquipmentDay && projectHasAssignment(activeCrewEquipmentProject, kind, value, activeCrewEquipmentDay)) {
              return;
            }

            var nameText = (source.querySelector('.resource-name') || {}).textContent || value;
            var subEl = source.querySelector('.resource-sub');
            var subText = subEl ? (subEl.textContent || '') : '';
            var statusDot = source.querySelector('.status-dot');
            var avatar = source.querySelector('.resource-avatar');

            var conflicts = activeCrewEquipmentDay
              ? findAssignmentConflictsForDay(activeCrewEquipmentProject, kind, value, activeCrewEquipmentDay)
              : [];

            if (conflicts.length === 0) {
              // ----- Available: free on this day -----
              var resourceButton = document.createElement('button');
              resourceButton.type = 'button';
              resourceButton.className = 'crew-available-resource';
              resourceButton.setAttribute('aria-label', 'Add ' + value + ' to the selected day');
              if (statusDot) { resourceButton.appendChild(statusDot.cloneNode(true)); }
              if (avatar) { resourceButton.appendChild(avatar.cloneNode(true)); }
              var texts = document.createElement('span');
              texts.className = 'resource-texts';
              var name = document.createElement('span');
              name.className = 'resource-name';
              name.textContent = nameText;
              texts.appendChild(name);
              if (subText) {
                var sub = document.createElement('span');
                sub.className = 'resource-sub';
                sub.textContent = subText;
                texts.appendChild(sub);
              }
              resourceButton.appendChild(texts);
              resourceButton.disabled = !activeCrewEquipmentDay;
              resourceButton.addEventListener('click', function () {
                addCrewEquipmentAssignment(kind, value, resourceButton, equipmentId);
              });
              if (group.available) { group.available.appendChild(resourceButton); }
            } else {
              // ----- Not available: assigned to another project on this day -----
              unavailableCount++;
              var projectNames = conflicts.map(function (conflictProject) {
                return conflictProject.project_name || ('Project #' + conflictProject.project_id);
              });

              var item = document.createElement('div');
              item.className = 'crew-unavailable-item';

              var info = document.createElement('div');
              info.className = 'crew-unavailable-info';
              var itemName = document.createElement('div');
              itemName.className = 'crew-unavailable-name';
              itemName.textContent = nameText;
              info.appendChild(itemName);
              var itemWhere = document.createElement('div');
              itemWhere.className = 'crew-unavailable-where';
              itemWhere.textContent = 'Assigned to: ' + projectNames.join(', ');
              info.appendChild(itemWhere);
              item.appendChild(info);

              var moveButton = document.createElement('button');
              moveButton.type = 'button';
              moveButton.className = 'crew-unavailable-move';
              moveButton.textContent = 'Move here';
              moveButton.setAttribute('aria-label', 'Move ' + value + ' to this day and remove it from ' + projectNames.join(', '));
              moveButton.disabled = !activeCrewEquipmentDay;
              moveButton.addEventListener('click', function () {
                addCrewEquipmentAssignment(kind, value, moveButton, equipmentId);
              });
              item.appendChild(moveButton);

              if (group.unavailable) { group.unavailable.appendChild(item); }
            }
          });

          if (group.available && !group.available.children.length) {
            group.available.textContent = activeCrewEquipmentDay ? 'No available resources for this day' : 'Select a scheduled day';
          }
          if (group.unavailable && !group.unavailable.children.length) {
            group.unavailable.textContent = 'None';
          }
        });

        if (crewUnavailableToggleLabel) {
          crewUnavailableToggleLabel.textContent = 'Not available crew / equipment' + (unavailableCount ? ' (' + unavailableCount + ')' : '');
        }
      }

      function renderCrewEquipmentModal() {
        if (!activeCrewEquipmentProject) {
          return;
        }
        var scheduledDays = getCrewEquipmentProjectDays(activeCrewEquipmentProject);
        if (scheduledDays.indexOf(activeCrewEquipmentDay) === -1) {
          activeCrewEquipmentDay = scheduledDays[0] || '';
        }
        if (crewEquipmentModal) {
          crewEquipmentModal.dataset.day = activeCrewEquipmentDay;
        }
        if (crewEquipmentProjectName) {
          crewEquipmentProjectName.textContent = activeCrewEquipmentProject.project_name || 'Project';
        }
        if (crewScheduledDays) {
          crewScheduledDays.innerHTML = '';
          scheduledDays.forEach(function (day) {
            var dayButton = document.createElement('button');
            dayButton.type = 'button';
            dayButton.className = 'crew-scheduled-day';
            dayButton.textContent = formatCrewEquipmentDay(day);
            dayButton.setAttribute('aria-pressed', day === activeCrewEquipmentDay ? 'true' : 'false');
            dayButton.addEventListener('click', function () {
              activeCrewEquipmentDay = day;
              renderCrewEquipmentModal();
            });
            crewScheduledDays.appendChild(dayButton);
          });
        }
        if (crewSelectedDay) {
          crewSelectedDay.textContent = activeCrewEquipmentDay
            ? 'Selected: ' + formatCrewEquipmentDay(activeCrewEquipmentDay)
            : 'Select a scheduled day';
        }
        var perDay = perDayDetails[String(activeCrewEquipmentProject.project_id) + '|' + activeCrewEquipmentDay] || {};
        var assignedPersonnel = parseCsvList(perDay.personnel || '');
        var assignedEquipment = parseCsvList(perDay.equipments || '');
        renderCrewEquipmentAssignmentRow(crewAssignedPersonnel, 'personnel', assignedPersonnel);
        renderCrewEquipmentAssignmentRow(crewAssignedEquipment, 'equipments', assignedEquipment);
        if (applyCrewEquipmentToAllDays) {
          applyCrewEquipmentToAllDays.disabled = !activeCrewEquipmentDay || (assignedPersonnel.length === 0 && assignedEquipment.length === 0);
        }
        renderCrewEquipmentResources();
      }

      async function addCrewEquipmentAssignment(kind, value, button, equipmentId) {
        if (!activeCrewEquipmentProject || !activeCrewEquipmentDay) {
          return;
        }
        if (projectHasAssignment(activeCrewEquipmentProject, kind, value, activeCrewEquipmentDay)) {
          return;
        }
        if (button) {
          button.disabled = true;
        }
        try {
          if (kind === 'equipments' && equipmentId) {
            var operatingCondition = String(equipmentStatus[String(equipmentId)] || '').toLowerCase();
            if (operatingCondition === 'red' || operatingCondition.indexOf('red') !== -1 || operatingCondition.indexOf('bad') !== -1) {
              var equipmentUrl = '../equipments/equipment.php?id=' + encodeURIComponent(String(equipmentId));
              var proceed = await showDecisionModal({
                title: '',
                messageHtml: '<div class="modal-warning-wrap"><div class="modal-warning-row"><span class="modal-warning-light" aria-hidden="true"></span><div class="modal-warning-text">this equipment is currently not in a condition to operate.</div></div><a href="' + equipmentUrl + '" target="_blank" rel="noopener noreferrer">click here to view more details</a></div>',
                confirmText: 'Proceed Anyway',
                cancelText: 'Cancel',
                showCancel: true,
                fallbackValue: false
              });
              if (!proceed) {
                return;
              }
            }
          }

          var conflicts = findAssignmentConflictsForDay(activeCrewEquipmentProject, kind, value, activeCrewEquipmentDay);
          if (conflicts.length > 0) {
            renderConflictVisualization(activeCrewEquipmentProject, conflicts);
            var moveAssignment = await showDecisionModal({
              title: 'Assignment Conflict',
              message: value + ' is already assigned to the following project(s) for the selected day.\n\nMove this assignment to the selected project?',
              confirmText: 'Move Assignment',
              cancelText: 'Keep Existing',
              showCancel: true,
              conflicts: conflicts,
              fallbackValue: false
            });
            if (!moveAssignment) {
              return;
            }
            await Promise.all(conflicts.map(function (conflictProject) {
              return removeProjectRequirement(conflictProject.project_id, kind, value, activeCrewEquipmentDay)
                .then(function (result) {
                  if (!result.ok || !result.data || !result.data.success) {
                    throw new Error('Remove failed');
                  }
                  perDayDetails[String(conflictProject.project_id) + '|' + activeCrewEquipmentDay] = {
                    equipments: String(result.data.equipments || ''),
                    personnel: String(result.data.personnel || '')
                  };
                });
            }));
          }

          var result = await updateProjectRequirement(activeCrewEquipmentProject.project_id, kind, value, activeCrewEquipmentDay);
          if (!result.ok || !result.data || !result.data.success) {
            throw new Error('Save failed');
          }
          setProjectDayDetails(activeCrewEquipmentProject.project_id, Array.isArray(result.data.days) ? result.data.days : [{
            day: activeCrewEquipmentDay,
            equipments: result.data.equipments || '',
            personnel: result.data.personnel || ''
          }]);
          setDirty(true);
          renderCrewEquipmentModal();
          if (typeof rerenderProjects === 'function') {
            rerenderProjects();
          }
        } catch (error) {
          showInfoModal('Unable To Save', 'Unable to add this assignment right now.');
        } finally {
          if (button) {
            button.disabled = false;
          }
        }
      }

      function removeCrewEquipmentAssignment(kind, value, button) {
        if (!activeCrewEquipmentProject || !activeCrewEquipmentDay) {
          return;
        }
        if (button) {
          button.disabled = true;
        }
        removeProjectRequirement(activeCrewEquipmentProject.project_id, kind, value, activeCrewEquipmentDay)
          .then(function (result) {
            if (!result.ok || !result.data || !result.data.success) {
              throw new Error('Remove failed');
            }
            perDayDetails[String(activeCrewEquipmentProject.project_id) + '|' + activeCrewEquipmentDay] = {
              equipments: String(result.data.equipments || ''),
              personnel: String(result.data.personnel || '')
            };
            setDirty(true);
            renderCrewEquipmentModal();
            if (typeof rerenderProjects === 'function') {
              rerenderProjects();
            }
          })
          .catch(function () {
            showInfoModal('Unable To Save', 'Unable to remove this assignment right now.');
          })
          .finally(function () {
            if (button) {
              button.disabled = false;
            }
          });
      }

      function getEquipmentIdForAssignment(value) {
        var matchingSource = Array.prototype.find.call(document.querySelectorAll('#equipmentList .requirement-source'), function (source) {
          return (source.getAttribute('data-requirement-value') || '') === value;
        });
        return matchingSource ? (matchingSource.getAttribute('data-eid') || '') : '';
      }

      async function applyCrewEquipmentAssignmentsToAllDays() {
        if (!activeCrewEquipmentProject || !activeCrewEquipmentDay) {
          return;
        }
        var sourceDetails = perDayDetails[String(activeCrewEquipmentProject.project_id) + '|' + activeCrewEquipmentDay] || {};
        var sourcePersonnel = parseCsvList(sourceDetails.personnel || '');
        var sourceEquipment = parseCsvList(sourceDetails.equipments || '');
        if (sourcePersonnel.length === 0 && sourceEquipment.length === 0) {
          return;
        }

        var targetDays = getCrewEquipmentProjectDays(activeCrewEquipmentProject).filter(function (day) {
          return day !== activeCrewEquipmentDay;
        });
        if (targetDays.length === 0) {
          await showInfoModal('No Other Project Days', 'There are no other scheduled days to update.');
          return;
        }

        if (applyCrewEquipmentToAllDays) {
          applyCrewEquipmentToAllDays.disabled = true;
        }
        try {
          var checkedEquipment = {};
          for (var equipmentIndex = 0; equipmentIndex < sourceEquipment.length; equipmentIndex++) {
            var equipmentValue = sourceEquipment[equipmentIndex];
            var equipmentId = getEquipmentIdForAssignment(equipmentValue);
            if (!equipmentId || checkedEquipment[equipmentId]) {
              continue;
            }
            checkedEquipment[equipmentId] = true;
            var operatingCondition = String(equipmentStatus[String(equipmentId)] || '').toLowerCase();
            if (operatingCondition === 'red' || operatingCondition.indexOf('red') !== -1 || operatingCondition.indexOf('bad') !== -1) {
              var equipmentUrl = '../equipments/equipment.php?id=' + encodeURIComponent(String(equipmentId));
              var proceed = await showDecisionModal({
                title: '',
                messageHtml: '<div class="modal-warning-wrap"><div class="modal-warning-row"><span class="modal-warning-light" aria-hidden="true"></span><div class="modal-warning-text">this equipment is currently not in a condition to operate.</div></div><a href="' + equipmentUrl + '" target="_blank" rel="noopener noreferrer">click here to view more details</a></div>',
                confirmText: 'Proceed Anyway',
                cancelText: 'Cancel',
                showCancel: true,
                fallbackValue: false
              });
              if (!proceed) {
                return;
              }
            }
          }

          var assignmentCandidates = sourcePersonnel.map(function (value) {
            return { kind: 'personnel', value: value };
          }).concat(sourceEquipment.map(function (value) {
            return { kind: 'equipments', value: value };
          }));
          var conflictOperations = [];
          var conflictProjects = [];
          targetDays.forEach(function (day) {
            assignmentCandidates.forEach(function (assignment) {
              var conflicts = findAssignmentConflictsForDay(activeCrewEquipmentProject, assignment.kind, assignment.value, day);
              if (conflicts.length > 0) {
                conflictOperations.push({
                  day: day,
                  kind: assignment.kind,
                  value: assignment.value,
                  conflicts: conflicts
                });
                conflictProjects = conflictProjects.concat(conflicts);
              }
            });
          });

          var forceConflicts = false;
          if (conflictOperations.length > 0) {
            renderConflictVisualization(activeCrewEquipmentProject, conflictProjects);
            forceConflicts = await showDecisionModal({
              title: 'Assignment Conflict',
              message: 'One or more crew or equipment assignments are already scheduled on another project for the affected day(s).\n\nForce this configuration and move those assignments to this project?',
              confirmText: 'Force Changes',
              cancelText: 'Keep Existing',
              showCancel: true,
              conflicts: conflictProjects,
              fallbackValue: false
            });
          }

          // Map of "day|kind|normalizedValue" -> original value for every conflicting resource.
          var conflictingByDayKind = {};
          conflictOperations.forEach(function (operation) {
            conflictingByDayKind[[operation.day, operation.kind, normalizeAssignmentValue(operation.value)].join('|')] = operation.value;
          });

          if (forceConflicts) {
            // "Force Changes": pull each conflicting resource off the other project(s) for that day
            // so it can be reassigned to this project.
            var removedAssignments = {};
            for (var conflictOperationIndex = 0; conflictOperationIndex < conflictOperations.length; conflictOperationIndex++) {
              var conflictOperation = conflictOperations[conflictOperationIndex];
              for (var conflictIndex = 0; conflictIndex < conflictOperation.conflicts.length; conflictIndex++) {
                var conflictProject = conflictOperation.conflicts[conflictIndex];
                var removeKey = [conflictProject.project_id, conflictOperation.kind, conflictOperation.value, conflictOperation.day].join('|');
                if (removedAssignments[removeKey]) {
                  continue;
                }
                removedAssignments[removeKey] = true;
                var removeResult = await removeProjectRequirement(conflictProject.project_id, conflictOperation.kind, conflictOperation.value, conflictOperation.day);
                if (!removeResult.ok || !removeResult.data || !removeResult.data.success) {
                  throw new Error('Remove failed');
                }
                perDayDetails[String(conflictProject.project_id) + '|' + conflictOperation.day] = {
                  equipments: String(removeResult.data.equipments || ''),
                  personnel: String(removeResult.data.personnel || '')
                };
              }
            }
          }

          // Resources that ultimately could not be copied onto a given day (Keep Existing path only).
          var unappliedResources = [];

          // Build the full replacement assignment for one day + kind.
          // Default behaviour: the day is replaced entirely with the selected day's assignment.
          // Keep Existing exception: a conflicting resource is not forced onto the day - if this
          // project's day already had it, it is left untouched; otherwise it is skipped entirely
          // and the rest of the assignment is still applied.
          function buildDayAssignment(day, kind, sourceList) {
            var seen = {};
            var result = [];
            var existing = getProjectAssignmentsForDay(activeCrewEquipmentProject, kind, day);
            var existingByNorm = {};
            existing.forEach(function (value) {
              existingByNorm[normalizeAssignmentValue(value)] = value;
            });

            sourceList.forEach(function (value) {
              var norm = normalizeAssignmentValue(value);
              if (!norm || seen[norm]) {
                return;
              }
              var isConflicting = !forceConflicts && Object.prototype.hasOwnProperty.call(
                conflictingByDayKind, [day, kind, norm].join('|')
              );
              if (isConflicting) {
                if (existingByNorm[norm]) {
                  // Leave the conflicting person/crew exactly as it already is on this day.
                  seen[norm] = true;
                  result.push(existingByNorm[norm]);
                } else {
                  unappliedResources.push({ day: day, kind: kind, value: value });
                }
                return;
              }
              seen[norm] = true;
              result.push(value);
            });

            return result.join(', ');
          }

          var replacementEntries = targetDays.map(function (day) {
            return {
              project_id: Number(activeCrewEquipmentProject.project_id),
              day: day,
              personnel: buildDayAssignment(day, 'personnel', sourcePersonnel),
              equipments: buildDayAssignment(day, 'equipments', sourceEquipment)
            };
          });
          var response = await fetch(window.location.pathname + '?action=bulk_save_project_details', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json; charset=UTF-8',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ entries: replacementEntries })
          });
          var data = await response.json();
          if (!response.ok || !data || !data.success) {
            throw new Error('Save failed');
          }
          replacementEntries.forEach(function (entry) {
            perDayDetails[String(entry.project_id) + '|' + entry.day] = {
              equipments: entry.equipments,
              personnel: entry.personnel
            };
          });
          setDirty(true);
          renderCrewEquipmentModal();
          if (typeof rerenderProjects === 'function') {
            rerenderProjects();
          }

          var appliedDayCount = replacementEntries.length;
          var confirmationMessage = 'The assignment from ' + formatCrewEquipmentDay(activeCrewEquipmentDay) +
            ' was copied to ' + appliedDayCount + ' other project day' + (appliedDayCount === 1 ? '' : 's');

          if (unappliedResources.length > 0) {
            var unappliedSeen = {};
            var unappliedLines = [];
            unappliedResources.forEach(function (item) {
              var lineKey = [item.day, item.kind, normalizeAssignmentValue(item.value)].join('|');
              if (unappliedSeen[lineKey]) {
                return;
              }
              unappliedSeen[lineKey] = true;
              unappliedLines.push('• ' + item.value + ' (' + (item.kind === 'personnel' ? 'Crew' : 'Equipment') +
                ') on ' + formatCrewEquipmentDay(item.day));
            });
            confirmationMessage += '\n\nThe following could not be applied because they stayed assigned to another project on those days:\n' +
              unappliedLines.join('\n');
            await showInfoModal('Assignment Partially Copied', confirmationMessage);
          } else {
            await showInfoModal('Assignment Copied', confirmationMessage);
          }
        } catch (error) {
          showInfoModal('Unable To Save', 'Unable to apply this configuration to every project day right now.');
        } finally {
          if (applyCrewEquipmentToAllDays) {
            applyCrewEquipmentToAllDays.disabled = false;
          }
        }
      }

      function openCrewEquipmentModal(project, dayKey) {
        if (!crewEquipmentModal || !project) {
          return;
        }
        activeCrewEquipmentProject = project;
        activeCrewEquipmentDay = dayKey || '';
        crewEquipmentModal.dataset.projectId = String(project.project_id || '');
        crewEquipmentModal.dataset.day = activeCrewEquipmentDay;
        renderCrewEquipmentModal();
        crewEquipmentModal.hidden = false;
      }

      async function saveCrewEquipmentAndReturnToProjectDetails() {
        if (!activeCrewEquipmentProject || !activeCrewEquipmentDay) {
          return;
        }
        var details = perDayDetails[String(activeCrewEquipmentProject.project_id) + '|' + activeCrewEquipmentDay] || {};
        if (saveCrewEquipmentAndReturn) {
          saveCrewEquipmentAndReturn.disabled = true;
        }
        try {
          var response = await fetch(window.location.pathname + '?action=bulk_save_project_details', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json; charset=UTF-8',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
              entries: [{
                project_id: Number(activeCrewEquipmentProject.project_id),
                day: activeCrewEquipmentDay,
                equipments: String(details.equipments || ''),
                personnel: String(details.personnel || '')
              }]
            })
          });
          var data = await response.json();
          if (!response.ok || !data || !data.success) {
            throw new Error('Save failed');
          }
          setDirty(false);
          closeCrewEquipmentModalFn();
          openProjectDetailsModal(activeCrewEquipmentProject, activeCrewEquipmentDay);
        } catch (error) {
          showInfoModal('Unable To Save', 'Unable to save crew and equipment assignments right now.');
        } finally {
          if (saveCrewEquipmentAndReturn) {
            saveCrewEquipmentAndReturn.disabled = false;
          }
        }
      }
      if (closeProjectDetailsModal) {
        closeProjectDetailsModal.addEventListener('click', function () {
          handleCloseProjectDetails();
        });
      }
      var saveProjectDetailsBtn = document.getElementById('saveProjectDetailsBtn');
      if (saveProjectDetailsBtn) {
        saveProjectDetailsBtn.addEventListener('click', function () {
          saveProjectDetailsDays();
        });
      }
      var printProjectDetailsBtn = document.getElementById('printProjectDetailsBtn');
      if (printProjectDetailsBtn) {
        printProjectDetailsBtn.addEventListener('click', function () {
          printProjectReport();
        });
      }
      if (editProjectForm) {
        editProjectForm.addEventListener('submit', function (event) {
          event.preventDefault();
          saveProjectDetailsDays();
        });
      }

      function escReport(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
          return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
      }

      function printProjectReport() {
        var project = activeProjectDetailsProject;
        if (!project) { return; }
        var meta = collectProjectMetaPayload();
        var projName = (editProjectName && editProjectName.value ? editProjectName.value.trim() : '')
          || meta.project_name || String(project.project_name || 'Project');
        var notesEl = document.getElementById('detailsOfficeNotes');
        var notes = notesEl ? String(notesEl.value || '') : '';

        function fv(x) {
          x = (x == null ? '' : String(x)).trim();
          return x === '' ? '<span class="muted">&mdash;</span>' : escReport(x);
        }
        function bv(x) {
          var s = String(x || '').toLowerCase();
          if (s === 'yes' || s === '1' || s === 'true') return 'Yes';
          if (s === 'no' || s === '0' || s === 'false') return 'No';
          return '<span class="muted">&mdash;</span>';
        }
        function kvTable(pairs) {
          var body = pairs.map(function (p) {
            return '<tr><th>' + escReport(p[0]) + '</th><td>' + p[1] + '</td></tr>';
          }).join('');
          return '<table class="kv">' + body + '</table>';
        }
        function sect(title, inner) {
          return '<section><h2>' + title + '</h2>' + inner + '</section>';
        }

        var days = (typeof editSelectedDates !== 'undefined' && editSelectedDates && editSelectedDates.length)
          ? editSelectedDates.slice().filter(function (d, i, a) { return d && a.indexOf(d) === i; }).sort()
          : getCrewEquipmentProjectDays(project);

        var dayRows = days.map(function (d) {
          var pd = perDayDetails[String(project.project_id) + '|' + d] || {};
          var crew = parseCsvList(pd.personnel || '');
          var equip = parseCsvList(pd.equipments || '');
          return '<tr>'
            + '<td class="day-cell">' + escReport(formatCrewEquipmentDay(d)) + '</td>'
            + '<td>' + (crew.length ? crew.map(escReport).join('<br>') : '<span class="muted">None</span>') + '</td>'
            + '<td>' + (equip.length ? equip.map(escReport).join('<br>') : '<span class="muted">None</span>') + '</td>'
            + '</tr>';
        }).join('');

        var css = ''
          + '@page{margin:16mm 14mm;}'
          + '*{box-sizing:border-box;}'
          + 'body{font-family:"Segoe UI",Arial,sans-serif;color:#1f2937;margin:0;font-size:12px;line-height:1.5;}'
          + 'header{border-bottom:3px solid #1f2f49;padding-bottom:10px;margin-bottom:18px;}'
          + 'header h1{margin:0;font-size:15px;letter-spacing:.14em;text-transform:uppercase;color:#637491;font-weight:700;}'
          + 'header .project-name{font-size:22px;font-weight:800;color:#132b4f;margin-top:4px;}'
          + 'header .meta-line{font-size:11px;color:#8a98af;margin-top:4px;}'
          + 'section{margin-bottom:16px;break-inside:avoid;}'
          + 'h2{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#2f4161;border-bottom:1px solid #d7dee9;padding-bottom:4px;margin:0 0 8px;}'
          + 'table{width:100%;border-collapse:collapse;}'
          + 'table.kv th{text-align:left;width:170px;color:#637491;font-weight:600;padding:4px 10px 4px 0;vertical-align:top;}'
          + 'table.kv td{padding:4px 0;vertical-align:top;}'
          + 'table.days{border:1px solid #cdd6e4;}'
          + 'table.days th{background:#eef2f8;text-align:left;padding:6px 8px;border:1px solid #cdd6e4;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#42506b;}'
          + 'table.days td{padding:6px 8px;border:1px solid #cdd6e4;vertical-align:top;}'
          + 'table.days td.day-cell{white-space:nowrap;font-weight:700;color:#1f2f49;width:190px;}'
          + '.muted{color:#9aa6b6;}'
          + '.notes{white-space:pre-wrap;border:1px solid #d7dee9;border-radius:6px;padding:10px;background:#fbfcfe;}';

        var docHtml = '<!doctype html><html><head><meta charset="utf-8"><title>'
          + escReport(projName) + ' - Project Report</title><style>' + css + '</style></head><body>'
          + '<header><h1>Project Report</h1><div class="project-name">' + escReport(projName) + '</div>'
          + '<div class="meta-line">' + days.length + ' scheduled day' + (days.length === 1 ? '' : 's')
          + ' &middot; Generated ' + escReport(new Date().toLocaleString()) + '</div></header>'
          + sect('Project Information', kvTable([
              ['Address', fv(meta.project_address)],
              ['City', fv(meta.project_city)],
              ['State', fv(meta.project_state)],
              ['Taxable', bv(meta.taxable)],
              ['Certified', bv(meta.certified)],
              ['Permit', bv(meta.permit)]
            ]))
          + sect('General Contractor', kvTable([
              ['Name', fv(meta.contractor_name)],
              ['Email', fv(meta.contractor_email)],
              ['Address', fv(meta.contractor_address)],
              ['Contact Name', fv(meta.contractor_contact_name)],
              ['Phone', fv(meta.contractor_phone)]
            ]))
          + sect('Owner', kvTable([
              ['Name', fv(meta.owner_name)],
              ['Email', fv(meta.owner_email)],
              ['Address', fv(meta.owner_address)],
              ['Contact Name', fv(meta.owner_contact_name)],
              ['Phone', fv(meta.owner_phone)]
            ]))
          + sect('Accommodation', kvTable([
              ['Hotel Name', fv(meta.hotel_name)],
              ['Hotel Address', fv(meta.hotel_address)],
              ['No. of Rooms', fv(meta.hotel_rooms)],
              ['Confirmation Number', fv(meta.hotel_confirmation)],
              ['Hotel Phone', fv(meta.hotel_phone)]
            ]))
          + sect('Scheduled Days &amp; Assignments', days.length
              ? '<table class="days"><thead><tr><th>Day</th><th>Crew Members</th><th>Equipment</th></tr></thead><tbody>' + dayRows + '</tbody></table>'
              : '<p class="muted">No scheduled days.</p>')
          + sect('Notes', notes.trim() !== '' ? '<div class="notes">' + escReport(notes) + '</div>' : '<p class="muted">&mdash;</p>')
          + '</body></html>';

        var iframe = document.createElement('iframe');
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
        document.body.appendChild(iframe);
        var removed = false;
        function cleanup() {
          if (removed) return;
          removed = true;
          setTimeout(function () { try { document.body.removeChild(iframe); } catch (e) {} }, 800);
        }
        var idoc = iframe.contentWindow.document;
        idoc.open();
        idoc.write(docHtml);
        idoc.close();
        setTimeout(function () {
          try {
            iframe.contentWindow.focus();
            if (iframe.contentWindow.matchMedia) {
              var mql = iframe.contentWindow.matchMedia('print');
              mql.addListener(function (m) { if (!m.matches) cleanup(); });
            }
            iframe.contentWindow.onafterprint = cleanup;
            iframe.contentWindow.print();
          } catch (e) {
            cleanup();
          }
          setTimeout(cleanup, 60000);
        }, 250);
      }

      function projectDetailsHasUnsavedChanges() {
        if (!activeProjectDetailsProject) {
          return false;
        }
        var selected = editSelectedDates
          .filter(function (day, index, all) { return day && all.indexOf(day) === index; })
          .sort().join(',');
        var original = (editProjectDetailsOriginalDates || []).slice().sort().join(',');
        if (selected !== original) return true;
        return currentProjectMetaSignature() !== editProjectDetailsOriginalMeta;
      }

      async function handleCloseProjectDetails() {
        if (!projectDetailsHasUnsavedChanges()) {
          closeProjectDetailsModalFn();
          return;
        }
        var choice = await showDecisionModal({
          title: 'Unsaved Changes',
          message: 'You have unsaved changes to this project.\n\nSave them before closing?',
          confirmText: 'Save and Close',
          cancelText: 'Discard Changes',
          showCancel: true,
          fallbackValue: false
        });
        if (choice) {
          await saveProjectDetailsDays();
        } else {
          closeProjectDetailsModalFn();
        }
      }

      async function saveProjectDetailsDays() {
        var project = activeProjectDetailsProject;
        if (!project) {
          closeProjectDetailsModalFn();
          return;
        }

        var selected = editSelectedDates
          .filter(function (day, index, all) { return day && all.indexOf(day) === index; })
          .sort();
        var original = (editProjectDetailsOriginalDates || []).slice().sort();
        var daysChanged = selected.join(',') !== original.join(',');
        var metaChanged = currentProjectMetaSignature() !== editProjectDetailsOriginalMeta;

        if (selected.length === 0) {
          await showInfoModal('No Dates Selected', 'A project needs at least one scheduled day.');
          return;
        }
        if (!daysChanged && !metaChanged) {
          closeProjectDetailsModalFn();
          return;
        }

        if (daysChanged) {
          var removedDays = original.filter(function (day) { return selected.indexOf(day) === -1; });
          var removedWithAssignments = removedDays.filter(function (day) {
            var pd = perDayDetails[String(project.project_id) + '|' + day];
            return pd && (String(pd.personnel || '').trim() !== '' || String(pd.equipments || '').trim() !== '');
          });
          if (removedWithAssignments.length > 0) {
            var proceed = await showDecisionModal({
              title: 'Remove Scheduled Days',
              message: removedWithAssignments.length + ' day(s) you are removing still have crew or equipment assigned.\n\nRemoving the day also clears those assignments. Continue?',
              confirmText: 'Remove Days',
              cancelText: 'Cancel',
              showCancel: true,
              fallbackValue: false
            });
            if (!proceed) {
              return;
            }
          }
        }

        var saveBtn = document.getElementById('saveProjectDetailsBtn');
        if (saveBtn) { saveBtn.disabled = true; }
        showAutosaveStatus('saving');
        try {
          // 1. Project Details fields (name, notes, Project Information / Owner / Accommodation / GC).
          if (metaChanged) {
            var metaPayload = collectProjectMetaPayload();
            metaPayload.project_id = Number(project.project_id);
            var metaRes = await fetch(window.location.pathname + '?action=save_project_meta', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: JSON.stringify(metaPayload)
            });
            var metaData = await metaRes.json();
            if (!metaRes.ok || !metaData || !metaData.success) {
              throw new Error(metaData && metaData.message ? metaData.message : 'Save failed');
            }
            PROJECT_META_FIELDS.forEach(function (f) {
              if (f.type === 'bool') {
                var t = getEditToggle(f.toggle);
                project[f.col] = (t === 'yes') ? 1 : (t === 'no' ? 0 : null);
              } else {
                var el = document.getElementById(f.editId);
                project[f.col] = el ? String(el.value || '').trim() : '';
              }
            });
            var notesEl = document.getElementById('detailsOfficeNotes');
            project.details = notesEl ? String(notesEl.value || '') : '';
            if (metaPayload.project_name) project.project_name = metaPayload.project_name;
            var locParts = [String(project.project_city || '').trim(), String(project.project_state || '').trim()]
              .filter(function (p) { return p !== ''; });
            project.location = locParts.join(', ');
            editProjectDetailsOriginalMeta = projectMetaSignature(project);
          }

          // 2. Scheduled days.
          if (daysChanged) {
            var params = new URLSearchParams();
            params.set('action', 'update_project_days');
            params.set('project_id', String(project.project_id));
            params.set('days', JSON.stringify(selected));
            var res = await fetch(window.location.pathname, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: params.toString()
            });
            var data = await res.json();
            if (!res.ok || !data || !data.success) {
              throw new Error(data && data.message ? data.message : 'Save failed');
            }

            var prefix = String(project.project_id) + '|';
            Object.keys(perDayDetails).forEach(function (key) {
              if (key.indexOf(prefix) === 0) {
                delete perDayDetails[key];
              }
            });
            (data.days || []).forEach(function (row) {
              perDayDetails[prefix + row.day] = {
                equipments: String(row.equipments || ''),
                personnel: String(row.personnel || '')
              };
            });
            if (data.start) { project.start = data.start; }
            if (data.end) { project.end = data.end; }
            editProjectDetailsOriginalDates = selected.slice();
          }

          editProjectDetailsOriginalMeta = projectMetaSignature(project);
          setDirty(true);
          closeProjectDetailsModalFn();
          if (typeof rerenderProjects === 'function') {
            rerenderProjects();
          } else if (typeof renderProjectTiles === 'function') {
            renderProjectTiles();
          }
        } catch (error) {
          showAutosaveStatus('error');
          await showInfoModal('Unable To Save', 'Unable to save the project changes right now.');
        } finally {
          if (saveBtn) { saveBtn.disabled = false; }
        }
      }
      if (openCrewEquipmentFromProjectDetails) {
        openCrewEquipmentFromProjectDetails.addEventListener('click', async function () {
          if (!activeProjectDetailsProject) {
            return;
          }
          if (projectDetailsHasUnsavedChanges()) {
            var choice = await showDecisionModal({
              title: 'Unsaved Changes',
              message: 'You have unsaved changes to this project.\n\nSave them before opening Crew/Equipment?',
              confirmText: 'Save and Continue',
              cancelText: 'Discard Changes',
              showCancel: true,
              fallbackValue: false
            });
            if (choice) {
              await saveProjectDetailsDays();
              if (projectDetailsHasUnsavedChanges()) {
                return; // save failed - stay on the details modal
              }
            }
          }
          var project = activeProjectDetailsProject;
          var day = activeProjectDetailsDay;
          closeProjectDetailsModalFn();
          openCrewEquipmentModal(project, day);
        });
      }
      if (projectDetailsModal) {
        projectDetailsModal.addEventListener('click', function (event) {
          if (event.target === projectDetailsModal) {
            handleCloseProjectDetails();
          }
        });
      }
      if (closeCrewEquipmentModal) {
        closeCrewEquipmentModal.addEventListener('click', closeCrewEquipmentModalFn);
      }
      if (saveCrewEquipmentAndReturn) {
        saveCrewEquipmentAndReturn.addEventListener('click', saveCrewEquipmentAndReturnToProjectDetails);
      }
      if (applyCrewEquipmentToAllDays) {
        applyCrewEquipmentToAllDays.addEventListener('click', applyCrewEquipmentAssignmentsToAllDays);
      }
      if (crewUnavailableToggle && crewUnavailablePanel) {
        crewUnavailableToggle.addEventListener('click', function () {
          var expanded = crewUnavailableToggle.getAttribute('aria-expanded') === 'true';
          crewUnavailableToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
          crewUnavailablePanel.hidden = expanded;
        });
      }
      if (crewEquipmentModal) {
        crewEquipmentModal.addEventListener('click', function (event) {
          if (event.target === crewEquipmentModal) {
            closeCrewEquipmentModalFn();
          }
        });
      }
      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          if (projectDetailsModal && !projectDetailsModal.hidden) {
            handleCloseProjectDetails();
          }
          if (typeof addProjectModal !== 'undefined' && addProjectModal && !addProjectModal.hidden) {
            handleCloseAddProject();
          }
          closeCrewEquipmentModalFn();
        }
      });

      function closeProjectViewModalFn() {
        if (viewProjectModal) {
          viewProjectModal.hidden = true;
        }
        activeViewProjectId = null;
      }

      var weeklyDaysRow = document.getElementById('weeklyDaysRow');
      var weeklyDayColumns = document.getElementById('weeklyDayColumns');
      var weekRangeLabel = document.getElementById('weekRangeLabel');
      var prevWeekBtn = document.getElementById('prevWeekBtn');
      var todayWeekBtn = document.getElementById('todayWeekBtn');
      var nextWeekBtn = document.getElementById('nextWeekBtn');
      var printWeekBtn = document.getElementById('printWeekBtn');

      if (weeklyDaysRow && weeklyDayColumns && weekRangeLabel) {
        var dayCount = 7;
        var weekdayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        function startOfWeek(dateObj) {
          var d = new Date(dateObj.getFullYear(), dateObj.getMonth(), dateObj.getDate());
          var day = d.getDay();
          var mondayOffset = day === 0 ? -6 : (1 - day);
          d.setDate(d.getDate() + mondayOffset);
          d.setHours(0, 0, 0, 0);
          return d;
        }

        function getDayKey(dateObj) {
          var y = dateObj.getFullYear();
          var m = String(dateObj.getMonth() + 1).padStart(2, '0');
          var d = String(dateObj.getDate()).padStart(2, '0');
          return y + '-' + m + '-' + d;
        }

        var currentWeekStart = startOfWeek(new Date());

        function renderDayColumns() {
          weeklyDayColumns.innerHTML = '';
        }

        function renderWeekHeader() {
          weeklyDaysRow.innerHTML = '';
          var headerFormatter = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' });
          for (var i = 0; i < dayCount; i++) {
            var dayDate = new Date(currentWeekStart);
            dayDate.setDate(currentWeekStart.getDate() + i);

            var cell = document.createElement('div');
            cell.className = 'day-head-cell';

            var dow = document.createElement('span');
            dow.className = 'dow';
            dow.textContent = weekdayNames[i];
            cell.appendChild(dow);

            var date = document.createElement('span');
            date.className = 'dom';
            date.textContent = headerFormatter.format(dayDate);
            cell.appendChild(date);

            weeklyDaysRow.appendChild(cell);
          }

          var weekEnd = new Date(currentWeekStart);
          weekEnd.setDate(currentWeekStart.getDate() + 6);
          var rangeFormatter = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
          var rangeText = rangeFormatter.format(currentWeekStart) + ' - ' + rangeFormatter.format(weekEnd);
          // set HTML so the today indicator element remains in the DOM
          try {
            weekRangeLabel.innerHTML = rangeText + ' <span class="today-indicator" id="todayIndicator" aria-hidden="true"></span>';
          } catch (e) {
            weekRangeLabel.textContent = rangeText;
          }

          // update today indicator
          try {
            var todayEl = document.getElementById('todayIndicator');
            if (todayEl) {
              var today = new Date();
              var nice = today.toLocaleString('en-US', { month: 'short', day: 'numeric' });
              todayEl.textContent = 'Today: ' + nice;
              var wkEnd = new Date(currentWeekStart);
              wkEnd.setDate(currentWeekStart.getDate() + 6);
              if (today.getTime() >= currentWeekStart.getTime() && today.getTime() <= wkEnd.getTime()) {
                todayEl.classList.add('today-in-week');
              } else {
                todayEl.classList.remove('today-in-week');
              }
            }
          } catch (e) { /* ignore */ }
        }

        function renderProjectTile(project, dayIndex, rowIndex) {
          var tile = document.createElement('div');
          tile.className = 'project-tile day-bubble';
          tile.setAttribute('role', 'button');
          tile.tabIndex = 0;
          tile.setAttribute('aria-label', 'Edit project: ' + (project.project_name || 'Project'));
          tile.style.gridColumn = String(dayIndex + 1) + ' / ' + String(dayIndex + 2);
          tile.style.gridRow = String(rowIndex + 1);
          // attach the concrete day for per-day assignments
          try {
            var dayDate = new Date(currentWeekStart);
            dayDate.setDate(currentWeekStart.getDate() + Number(dayIndex));
            tile.dataset.day = formatIsoDate(dayDate);
          } catch (e) {
            tile.dataset.day = '';
          }
          var tileColor = getProjectColor(project);
          tile.style.background = tileColor;
          tile.style.borderColor = tileColor;

          var nameEl = document.createElement('div');
          nameEl.className = 'project-tile-name';
          nameEl.textContent = project.project_name || 'Project';
          tile.appendChild(nameEl);

          var reqMeta = document.createElement('div');
          reqMeta.className = 'project-tile-req';
          var perKey = String(project.project_id) + '|' + (tile.dataset.day || '');
          var perData = perDayDetails[perKey] || null;
          var personnelsText = perData ? (perData.personnel || '-') : (project.personnel ? project.personnel : '-');
          var equipmentsText = perData ? (perData.equipments || '-') : (project.equipments ? project.equipments : '-');

          function escapeHtml(s){ return String(s || '').replace(/[&<>\"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

          var row1 = document.createElement('div');
          row1.className = 'req-row';
          var label1 = document.createElement('span'); label1.className = 'req-label'; label1.textContent = 'Crew Members:';
          var val1 = document.createElement('span'); val1.className = 'personnel-value'; val1.innerHTML = escapeHtml(personnelsText);
          row1.appendChild(label1); row1.appendChild(val1);

          var row2 = document.createElement('div');
          row2.className = 'req-row';
          var label2 = document.createElement('span'); label2.className = 'req-label'; label2.textContent = 'Equipments:';
          var val2 = document.createElement('span'); val2.className = 'equipments-value'; val2.innerHTML = escapeHtml(equipmentsText);
          row2.appendChild(label2); row2.appendChild(val2);

          reqMeta.appendChild(row1); reqMeta.appendChild(row2);
          tile.appendChild(reqMeta);

          tile.addEventListener('click', function(){
            if (tile.dataset.justDropped === '1') {
              return;
            }
            openProjectDetailsModal(project, tile.dataset.day || '');
          });
          tile.addEventListener('keydown', function(event){
            if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault();
              tile.click();
            }
          });

          // Crew/equipment are assigned from the Crew/Equipment modal (opened via the
          // project tile), so project tiles are no longer drag-and-drop targets.

          weeklyDayColumns.appendChild(tile);
        }

        function renderProjectTiles() {
          weeklyDayColumns.innerHTML = '';

          if (!Array.isArray(scheduledProjects) || scheduledProjects.length === 0) {
            return;
          }

          var weekStart = new Date(currentWeekStart);
          var weekEndExclusive = new Date(weekStart);
          weekEndExclusive.setDate(weekEndExclusive.getDate() + dayCount);
          var weekLastDay = new Date(weekEndExclusive);
          weekLastDay.setDate(weekLastDay.getDate() - 1);

          var weekEntries = [];

          // Sorted list of explicitly-scheduled ISO days for a project (from its per-day rows).
          function getProjectScheduledDayList(pid) {
            var prefix = String(pid) + '|';
            var out = [];
            for (var kk in perDayDetails) {
              if (!Object.prototype.hasOwnProperty.call(perDayDetails, kk)) continue;
              if (String(kk).indexOf(prefix) === 0) {
                var iso = String(kk).slice(prefix.length);
                if (iso) { out.push(iso); }
              }
            }
            return out.sort();
          }

          scheduledProjects.forEach(function(project){
            var startDate = parseDateTime(project.start);
            var endDate = parseDateTime(project.end);

            // The per-day rows are the authoritative schedule. When they exist, drive the
            // visible span from them so weekend days (or any day past the legacy end date)
            // still render.
            var scheduledDayList = getProjectScheduledDayList(project.project_id);
            if (scheduledDayList.length > 0) {
              startDate = new Date(scheduledDayList[0] + 'T00:00:00');
              endDate = new Date(scheduledDayList[scheduledDayList.length - 1] + 'T00:00:00');
            }

            if (!startDate || !endDate || endDate < startDate) {
              return;
            }

            var projectStartDay = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
            var projectEndDay = new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate());

            var clampedStartDay = projectStartDay < weekStart ? new Date(weekStart) : projectStartDay;
            var clampedEndDay = projectEndDay > weekLastDay ? new Date(weekLastDay) : projectEndDay;
            if (clampedEndDay < clampedStartDay) {
              return;
            }

            var startDayIndex = Math.floor((clampedStartDay.getTime() - weekStart.getTime()) / (24 * 60 * 60 * 1000));
            var endDayIndex = Math.floor((clampedEndDay.getTime() - weekStart.getTime()) / (24 * 60 * 60 * 1000));
            if (startDayIndex < 0 || endDayIndex < 0 || startDayIndex >= dayCount || endDayIndex >= dayCount) {
              return;
            }

            weekEntries.push({
              project: project,
              startDayIndex: startDayIndex,
              endDayIndex: endDayIndex
            });
          });

          weekEntries.sort(function(a, b){
            if (a.startDayIndex !== b.startDayIndex) {
              return a.startDayIndex - b.startDayIndex;
            }
            if (a.endDayIndex !== b.endDayIndex) {
              return a.endDayIndex - b.endDayIndex;
            }
            return Number(a.project.project_id) - Number(b.project.project_id);
          });

          var laneEnds = [];
          weekEntries.forEach(function(entry){
            var laneIndex = -1;
            for (var i = 0; i < laneEnds.length; i++) {
              if (entry.startDayIndex > laneEnds[i]) {
                laneIndex = i;
                break;
              }
            }
            if (laneIndex === -1) {
              laneIndex = laneEnds.length;
              laneEnds.push(entry.endDayIndex);
            } else {
              laneEnds[laneIndex] = entry.endDayIndex;
            }

            // Render only dates represented by detail rows for projects using explicit date selection.
            function projectHasPerDayEntries(pid) {
              for (var kk in perDayDetails) {
                if (!Object.prototype.hasOwnProperty.call(perDayDetails, kk)) continue;
                if (String(kk).indexOf(String(pid) + '|') === 0) return true;
              }
              return false;
            }

            var hasPerDayRows = projectHasPerDayEntries(entry.project.project_id);

            for (var d = entry.startDayIndex; d <= entry.endDayIndex; d++) {
              try {
                var dayDate = new Date(weekStart);
                dayDate.setDate(weekStart.getDate() + d);

                if (hasPerDayRows) {
                  // Explicit per-day rows are authoritative: render exactly the days that have a
                  // row (weekends included), ignore the legacy exclude_weekends flag.
                  var perKey = String(entry.project.project_id) + '|' + formatIsoDate(dayDate);
                  if (!Object.prototype.hasOwnProperty.call(perDayDetails, perKey)) {
                    continue;
                  }
                } else {
                  var isWeekend = (dayDate.getDay() === 0 || dayDate.getDay() === 6); // 0=Sun,6=Sat
                  if (entry.project && entry.project.exclude_weekends && isWeekend) {
                    continue;
                  }
                }
              } catch (e) {}
              renderProjectTile(entry.project, d, laneIndex);
            }
          });

          var visibleRows = Math.max(6, laneEnds.length);
          weeklyDayColumns.style.gridTemplateRows = 'repeat(' + String(visibleRows) + ', minmax(66px, auto))';
        }

        // ===== 30-day (month) calendar view =====
        var monthBoard = document.getElementById('monthBoard');
        var monthWeekdayRow = document.getElementById('monthWeekdayRow');
        var monthGrid = document.getElementById('monthGrid');
        var weeklyBoard = document.getElementById('weeklyBoard');
        var toggleCalendarViewBtn = document.getElementById('toggleCalendarViewBtn');
        var calendarViewMode = 'week';
        var MONTH_EVENTS_PER_CELL = 4;
        var currentMonthAnchor = new Date();
        currentMonthAnchor = new Date(currentMonthAnchor.getFullYear(), currentMonthAnchor.getMonth(), 1);

        // ISO days a project occupies (per-day rows are authoritative; else its date range).
        function projectOccupiedDays(project) {
          var prefix = String(project.project_id) + '|';
          var perDayList = [];
          for (var kk in perDayDetails) {
            if (!Object.prototype.hasOwnProperty.call(perDayDetails, kk)) continue;
            if (String(kk).indexOf(prefix) === 0) {
              var iso = String(kk).slice(prefix.length);
              if (iso) perDayList.push(iso);
            }
          }
          if (perDayList.length > 0) {
            return perDayList.sort();
          }
          var startDate = parseDateTime(project.start);
          var endDate = parseDateTime(project.end);
          if (!startDate || !endDate || endDate < startDate) return [];
          var out = [];
          var cur = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
          var end = new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate());
          while (cur <= end) {
            var weekend = (cur.getDay() === 0 || cur.getDay() === 6);
            if (!(project.exclude_weekends && weekend)) {
              out.push(formatIsoDate(cur));
            }
            cur.setDate(cur.getDate() + 1);
          }
          return out;
        }

        function renderMonthHeader() {
          if (monthWeekdayRow && monthWeekdayRow.children.length === 0) {
            weekdayNames.forEach(function (nm) {
              var c = document.createElement('div');
              c.className = 'month-weekday';
              c.textContent = nm;
              monthWeekdayRow.appendChild(c);
            });
          }
          var label = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(currentMonthAnchor);
          try {
            weekRangeLabel.innerHTML = label + ' <span class="today-indicator" id="todayIndicator" aria-hidden="true"></span>';
            var todayEl = document.getElementById('todayIndicator');
            var now = new Date();
            if (todayEl) {
              todayEl.textContent = 'Today: ' + now.toLocaleString('en-US', { month: 'short', day: 'numeric' });
              if (now.getFullYear() === currentMonthAnchor.getFullYear() && now.getMonth() === currentMonthAnchor.getMonth()) {
                todayEl.classList.add('today-in-week');
              } else {
                todayEl.classList.remove('today-in-week');
              }
            }
          } catch (e) {
            weekRangeLabel.textContent = label;
          }
        }

        function renderMonthGrid() {
          if (!monthGrid) return;
          monthGrid.innerHTML = '';

          var anchorYear = currentMonthAnchor.getFullYear();
          var anchorMonth = currentMonthAnchor.getMonth();
          var gridStart = startOfWeek(new Date(anchorYear, anchorMonth, 1));
          var daysInMonth = new Date(anchorYear, anchorMonth + 1, 0).getDate();
          var leading = Math.round((new Date(anchorYear, anchorMonth, 1) - gridStart) / 86400000);
          var totalCells = Math.ceil((leading + daysInMonth) / 7) * 7;
          var todayKey = formatIsoDate(new Date());

          // Bucket projects by ISO day for the visible range.
          var byDay = {};
          if (Array.isArray(scheduledProjects)) {
            scheduledProjects.forEach(function (project) {
              projectOccupiedDays(project).forEach(function (iso) {
                (byDay[iso] || (byDay[iso] = [])).push(project);
              });
            });
          }

          for (var i = 0; i < totalCells; i++) {
            var cellDate = new Date(gridStart);
            cellDate.setDate(gridStart.getDate() + i);
            var iso = formatIsoDate(cellDate);

            var cell = document.createElement('div');
            cell.className = 'month-cell';
            if (cellDate.getMonth() !== anchorMonth) cell.classList.add('other-month');
            if (iso === todayKey) cell.classList.add('today');

            var dateEl = document.createElement('div');
            dateEl.className = 'month-cell-date';
            dateEl.textContent = String(cellDate.getDate());
            cell.appendChild(dateEl);

            var events = document.createElement('div');
            events.className = 'month-cell-events';
            cell.appendChild(events);

            var dayProjects = (byDay[iso] || []).slice().sort(function (a, b) {
              return String(a.project_name || '').localeCompare(String(b.project_name || ''));
            });

            (function (dayProjectsRef, eventsRef, dayIso) {
              var shown = dayProjectsRef.slice(0, MONTH_EVENTS_PER_CELL);
              shown.forEach(function (project) {
                var ev = document.createElement('button');
                ev.type = 'button';
                ev.className = 'month-event';
                var color = getProjectColor(project);
                ev.style.background = color;
                ev.textContent = project.project_name || 'Project';
                ev.title = (project.project_name || 'Project');
                ev.addEventListener('click', function () {
                  openProjectDetailsModal(project, dayIso);
                });
                eventsRef.appendChild(ev);
              });
              var extra = dayProjectsRef.length - shown.length;
              if (extra > 0) {
                var more = document.createElement('button');
                more.type = 'button';
                more.className = 'month-event-more';
                more.textContent = '+' + extra + ' more';
                more.addEventListener('click', function () {
                  // reveal the rest in place
                  dayProjectsRef.slice(MONTH_EVENTS_PER_CELL).forEach(function (project) {
                    var ev = document.createElement('button');
                    ev.type = 'button';
                    ev.className = 'month-event';
                    ev.style.background = getProjectColor(project);
                    ev.textContent = project.project_name || 'Project';
                    ev.title = (project.project_name || 'Project');
                    ev.addEventListener('click', function () { openProjectDetailsModal(project, dayIso); });
                    eventsRef.insertBefore(ev, more);
                  });
                  more.remove();
                });
                eventsRef.appendChild(more);
              }
            })(dayProjects, events, iso);

            monthGrid.appendChild(cell);
          }
        }

        function setCalendarViewMode(mode) {
          calendarViewMode = (mode === 'month') ? 'month' : 'week';
          var isMonth = calendarViewMode === 'month';
          if (weeklyBoard) weeklyBoard.hidden = isMonth;
          if (monthBoard) monthBoard.hidden = !isMonth;
          if (toggleCalendarViewBtn) {
            toggleCalendarViewBtn.textContent = isMonth ? 'Week Calendar View' : '30-Day Calendar View';
            toggleCalendarViewBtn.setAttribute('aria-pressed', isMonth ? 'true' : 'false');
            toggleCalendarViewBtn.classList.toggle('is-active', isMonth);
          }
          if (isMonth) {
            renderMonthHeader();
            renderMonthGrid();
          } else {
            renderWeekHeader();
            renderProjectTiles();
          }
        }

        function printCurrentWeekSchedule() {
          var schedulerPanel = document.querySelector('.scheduler-panel');
          if (!schedulerPanel) {
            showInfoModal('Unable To Print', 'Schedule preview is unavailable right now.');
            return;
          }

          // Remove any existing temporary print container
          var existing = document.getElementById('print-root');
          if (existing) {
            try { existing.parentNode.removeChild(existing); } catch (e) {}
          }

          // Clone the scheduler panel for printing
          var panelClone = schedulerPanel.cloneNode(true);
          // Remove interactive controls from the clone
          var addBtn = panelClone.querySelector('#openAddProjectModal');
          if (addBtn) addBtn.remove();
          var printBtn = panelClone.querySelector('#printWeekBtn');
          if (printBtn) printBtn.remove();

          // Create print root container and append cloned panel
          var printRoot = document.createElement('div');
          printRoot.id = 'print-root';
          printRoot.style.display = 'none';
          printRoot.className = 'scheduling-page';
          printRoot.appendChild(panelClone);
          document.body.appendChild(printRoot);

          // Use a body class to hide everything except #print-root during print (handled by CSS @media print rules)
          document.body.classList.add('printing');

          // Ensure printRoot is shown when printing
          printRoot.style.display = '';

          // Clean up after printing
          var cleanup = function() {
            document.body.classList.remove('printing');
            try { if (printRoot && printRoot.parentNode) printRoot.parentNode.removeChild(printRoot); } catch (e) {}
            window.removeEventListener('afterprint', cleanup);
            // In some browsers afterprint may not fire; set a brief timeout fallback
            setTimeout(function(){ try { var ex = document.getElementById('print-root'); if (ex) ex.parentNode.removeChild(ex); } catch(e){} }, 1000);
          };

          window.addEventListener('afterprint', cleanup);

          // Trigger the print dialog from the same window
          try {
            window.print();
          } catch (e) {
            // If print is blocked/fails, show info and cleanup
            cleanup();
            showInfoModal('Print Failed', 'Unable to open print dialog. Please try again.');
          }
        }

        renderDayColumns();
        renderWeekHeader();
        renderProjectTiles();
        rerenderProjects = function () {
          if (calendarViewMode === 'month') {
            renderMonthGrid();
          } else {
            renderProjectTiles();
          }
        };

        if (prevWeekBtn) {
          prevWeekBtn.addEventListener('click', function(){
            if (calendarViewMode === 'month') {
              currentMonthAnchor = new Date(currentMonthAnchor.getFullYear(), currentMonthAnchor.getMonth() - 1, 1);
              renderMonthHeader();
              renderMonthGrid();
            } else {
              currentWeekStart.setDate(currentWeekStart.getDate() - 7);
              renderWeekHeader();
              renderProjectTiles();
            }
          });
        }

        if (todayWeekBtn) {
          todayWeekBtn.addEventListener('click', function(){
            if (calendarViewMode === 'month') {
              var now = new Date();
              currentMonthAnchor = new Date(now.getFullYear(), now.getMonth(), 1);
              renderMonthHeader();
              renderMonthGrid();
            } else {
              currentWeekStart = startOfWeek(new Date());
              renderWeekHeader();
              renderProjectTiles();
            }
          });
        }

        if (nextWeekBtn) {
          nextWeekBtn.addEventListener('click', function(){
            if (calendarViewMode === 'month') {
              currentMonthAnchor = new Date(currentMonthAnchor.getFullYear(), currentMonthAnchor.getMonth() + 1, 1);
              renderMonthHeader();
              renderMonthGrid();
            } else {
              currentWeekStart.setDate(currentWeekStart.getDate() + 7);
              renderWeekHeader();
              renderProjectTiles();
            }
          });
        }

        if (toggleCalendarViewBtn) {
          toggleCalendarViewBtn.addEventListener('click', function () {
            setCalendarViewMode(calendarViewMode === 'month' ? 'week' : 'month');
          });
        }

        if (printWeekBtn) {
          printWeekBtn.addEventListener('click', printCurrentWeekSchedule);
        }
      }

      if (closeViewProjectModal) {
        closeViewProjectModal.addEventListener('click', closeProjectViewModalFn);
      }
      if (closeProjectViewBtn) {
        closeProjectViewBtn.addEventListener('click', closeProjectViewModalFn);
      }
          if (viewProjectModal) {
        viewProjectModal.addEventListener('click', function(e){
          var chipBtn = e.target.closest('.assign-chip');
          if (chipBtn && activeViewProjectId) {
            var removeKind = chipBtn.getAttribute('data-remove-kind') || '';
            var removeValue = chipBtn.getAttribute('data-remove-value') || '';
            if ((removeKind === 'personnel' || removeKind === 'equipments') && removeValue) {
              chipBtn.disabled = true;
                removeProjectRequirement(activeViewProjectId, removeKind, removeValue, activeViewProjectDay)
                .then(function(result){
                  if (!result.ok || !result.data || !result.data.success) {
                    throw new Error('Remove failed');
                  }

                  try {
                    var key = String(activeViewProjectId) + '|' + (activeViewProjectDay || '');
                    perDayDetails[key] = {
                      equipments: String(result.data.equipments || ''),
                      personnel: String(result.data.personnel || '')
                    };
                    setDirty(true);
                  } catch (e) {}

                  var current = projectById[String(activeViewProjectId)];
                  if (current) {
                    // re-open modal showing the same day
                    openProjectViewModal(current, activeViewProjectDay);
                  }

                  if (typeof rerenderProjects === 'function') {
                    rerenderProjects();
                  }
                })
                .catch(function(){
                  chipBtn.disabled = false;
                  showInfoModal('Unable To Remove', 'Unable to remove this assignment right now.');
                });
            }
            return;
          }

          if (e.target === viewProjectModal) {
            closeProjectViewModalFn();
          }
        });
      }

      if (deleteProjectBtn) {
        deleteProjectBtn.addEventListener('click', async function(){
          if (!activeViewProjectId) {
            return;
          }

          var shouldDelete = await showDecisionModal({
            title: 'Delete Project',
            message: 'Delete this project? This cannot be undone.',
            confirmText: 'Delete Project',
            cancelText: 'Cancel',
            showCancel: true,
            fallbackValue: false
          });
          if (!shouldDelete) {
            return;
          }

          deleteProjectBtn.disabled = true;
          deleteScheduledProject(activeViewProjectId)
            .then(function(result){
              if (!result.ok || !result.data || !result.data.success) {
                throw new Error('Delete failed');
              }

              scheduledProjects = scheduledProjects.filter(function(p){
                return Number(p.project_id) !== Number(activeViewProjectId);
              });
              delete projectById[String(activeViewProjectId)];
              closeProjectViewModalFn();
              if (typeof rerenderProjects === 'function') {
                rerenderProjects();
              }
            })
            .catch(function(){
              showInfoModal('Unable To Delete', 'Unable to delete project right now.');
            })
            .finally(function(){
              deleteProjectBtn.disabled = false;
            });
        });
      }

      var openModalBtn = document.getElementById('openAddProjectModal');
      var closeModalBtn = document.getElementById('closeAddProjectModal');
      var createProjectBtn = document.getElementById('createProjectBtn');
      var addProjectModal = document.getElementById('addProjectModal');
      var projectForm = document.getElementById('addProjectForm');
      var calendarGrid = document.getElementById('calendarGrid');
      var calendarMonth = document.getElementById('calendarMonth');
      var selectedDatesInput = document.getElementById('selectedDates');
      var selectedDatesSummary = document.getElementById('selectedDatesSummary');
      var selectedDates = [];
      var calendarDate = new Date();

      var ADD_TOGGLE_HIDDEN = { taxable: 'addTaxableField', certified: 'addCertifiedField', permit: 'addPermitField' };
      function syncToggleHiddenField(toggleName) {
        var hiddenId = ADD_TOGGLE_HIDDEN[toggleName];
        if (!hiddenId) return;
        var hidden = document.getElementById(hiddenId);
        if (!hidden) return;
        var active = document.querySelector('.toggle-option[data-toggle-name="' + toggleName + '"].selected');
        hidden.value = active ? (active.getAttribute('data-toggle-value') || '') : '';
      }

      document.querySelectorAll('.toggle-option').forEach(function (toggleOption) {
        toggleOption.addEventListener('click', function () {
          var toggleName = toggleOption.getAttribute('data-toggle-name');
          var wasSelected = toggleOption.classList.contains('selected');
          document.querySelectorAll('.toggle-option[data-toggle-name="' + toggleName + '"]').forEach(function (option) {
            option.classList.remove('selected');
            option.setAttribute('aria-pressed', 'false');
          });
          if (!wasSelected) {
            toggleOption.classList.add('selected');
            toggleOption.setAttribute('aria-pressed', 'true');
          }
          syncToggleHiddenField(toggleName);
        });
      });

      function formatDateKey(dateObj) {
        return dateObj.getFullYear() + '-' +
          String(dateObj.getMonth() + 1).padStart(2, '0') + '-' +
          String(dateObj.getDate()).padStart(2, '0');
      }

      function renderProjectDatePicker() {
        if (!calendarGrid || !calendarMonth) {
          return;
        }

        calendarGrid.innerHTML = '';
        calendarMonth.textContent = calendarDate.toLocaleString('en-US', {
          month: 'long',
          year: 'numeric'
        });

        var year = calendarDate.getFullYear();
        var month = calendarDate.getMonth();
        var firstDay = new Date(year, month, 1);
        var mondayOffset = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1;
        var daysInMonth = new Date(year, month + 1, 0).getDate();

        for (var blank = 0; blank < mondayOffset; blank++) {
          var emptyDay = document.createElement('span');
          emptyDay.className = 'calendar-day empty';
          emptyDay.setAttribute('aria-hidden', 'true');
          calendarGrid.appendChild(emptyDay);
        }

        for (var day = 1; day <= daysInMonth; day++) {
          var dayDate = new Date(year, month, day);
          var dayKey = formatDateKey(dayDate);
          var dayButton = document.createElement('button');
          dayButton.type = 'button';
          dayButton.className = 'calendar-day';
          dayButton.textContent = String(day);
          dayButton.setAttribute('aria-label', 'Select ' + dayKey);
          if (selectedDates.indexOf(dayKey) !== -1) {
            dayButton.classList.add('selected');
            dayButton.setAttribute('aria-pressed', 'true');
          } else {
            dayButton.setAttribute('aria-pressed', 'false');
          }

          dayButton.addEventListener('click', (function (key) {
            return function () {
              var selectedIndex = selectedDates.indexOf(key);
              if (selectedIndex === -1) {
                selectedDates.push(key);
              } else {
                selectedDates.splice(selectedIndex, 1);
              }
              selectedDates.sort();
              if (selectedDatesInput) {
                selectedDatesInput.value = JSON.stringify(selectedDates);
              }
              if (selectedDatesSummary) {
                selectedDatesSummary.textContent = selectedDates.length
                  ? selectedDates.length + ' date' + (selectedDates.length === 1 ? '' : 's') + ' selected: ' + selectedDates.join(', ')
                  : 'No dates selected';
              }
              renderProjectDatePicker();
            };
          })(dayKey));
          calendarGrid.appendChild(dayButton);
        }
      }

      var previousMonthBtn = document.getElementById('previousMonth');
      var nextMonthBtn = document.getElementById('nextMonth');
      if (previousMonthBtn) {
        previousMonthBtn.addEventListener('click', function () {
          calendarDate.setMonth(calendarDate.getMonth() - 1);
          renderProjectDatePicker();
        });
      }
      if (nextMonthBtn) {
        nextMonthBtn.addEventListener('click', function () {
          calendarDate.setMonth(calendarDate.getMonth() + 1);
          renderProjectDatePicker();
        });
      }
      if (projectForm) {
        projectForm.addEventListener('submit', function (event) {
          if (selectedDates.length === 0) {
            event.preventDefault();
            if (selectedDatesSummary) {
              selectedDatesSummary.textContent = 'Select at least one date';
            }
          }
        });
      }
      renderProjectDatePicker();

      function resetAddProjectModal() {
        try {
          var form = document.getElementById('addProjectForm');
          if (form) {
            Array.prototype.forEach.call(form.elements, function (el) {
              if (!el.name || el.type === 'hidden' || el.type === 'submit' || el.type === 'button') return;
              if (el.tagName === 'TEXTAREA' || el.type === 'text' || el.type === 'email' || el.type === 'tel' || el.type === 'number' || el.type === 'date') {
                el.value = '';
              }
            });
          }
          ['addTaxableField', 'addCertifiedField', 'addPermitField'].forEach(function (id) {
            var h = document.getElementById(id); if (h) h.value = '';
          });
          document.querySelectorAll('#addProjectModal .toggle-option.selected').forEach(function (b) {
            b.classList.remove('selected'); b.setAttribute('aria-pressed', 'false');
          });
          selectedDates = [];
          if (selectedDatesInput) selectedDatesInput.value = '[]';
          if (selectedDatesSummary) selectedDatesSummary.textContent = 'No dates selected';
          if (typeof renderProjectDatePicker === 'function') renderProjectDatePicker();
        } catch (e) { /* ignore */ }
      }

      function openModal() {
        if (addProjectModal) {
          addProjectModal.hidden = false;
        }
        resetAddProjectModal();
        if (notesImages.add) {
          notesImages.add.load();
        }
        if (existingProjectsPicker && existingProjectToggle) {
          existingProjectsPicker.hidden = true;
          existingProjectToggle.setAttribute('aria-expanded', 'false');
        }
      }

      if (saveProjectMetaBtn) {
        saveProjectMetaBtn.addEventListener('click', function(){
          if (!activeViewProjectId) return;
          var val = '';
          var detailsVal = '';
          try { val = String(viewProjectLocation.value || '').trim(); } catch (e) { val = ''; }
          try { detailsVal = String(viewProjectDetails.value || '').trim(); } catch (e) { detailsVal = ''; }
          saveProjectMetaBtn.disabled = true;
          updateProjectMeta(activeViewProjectId, val, detailsVal).then(function(result){
            if (!result.ok || !result.data || !result.data.success) {
              throw new Error('Save failed');
            }
            // update client-side project objects
            try {
              var proj = projectById[String(activeViewProjectId)];
              if (proj) {
                proj.location = String(result.data.location || '');
                proj.details = String(result.data.details || '');
              }
              for (var i = 0; i < scheduledProjects.length; i++) {
                if (Number(scheduledProjects[i].project_id) === Number(activeViewProjectId)) {
                  scheduledProjects[i].location = String(result.data.location || '');
                  scheduledProjects[i].details = String(result.data.details || '');
                }
              }
              showInfoModal('Saved', 'Project saved.');
              if (typeof rerenderProjects === 'function') rerenderProjects();
            } catch (e) {}
          }).catch(function(){
            showInfoModal('Unable To Save', 'Unable to save project.');
          }).finally(function(){
            saveProjectMetaBtn.disabled = false;
          });
        });
      }

      function closeModal() {
        if (addProjectModal) {
          addProjectModal.hidden = true;
        }
        // The project was never created, so its staged images have nothing to attach to.
        if (notesImages.add) {
          notesImages.add.discardDraft();
        }
      }

      function addProjectHasUnsavedInput() {
        var hasName = false;
        try { hasName = String(projectNameInput && projectNameInput.value || '').trim() !== ''; } catch (e) { hasName = false; }
        var hasImages = !!(notesImages.add && notesImages.add.hasImages());
        return hasName || hasImages || selectedDates.length > 0;
      }

      async function handleCloseAddProject() {
        if (!addProjectHasUnsavedInput()) {
          closeModal();
          return;
        }
        var choice = await showDecisionModal({
          title: 'Unsaved Changes',
          message: 'This project has not been created yet.\n\nCreate it now or discard?',
          confirmText: 'Create Project',
          cancelText: 'Discard',
          showCancel: true,
          fallbackValue: false
        });
        if (choice) {
          if (createProjectBtn) {
            createProjectBtn.click();
          } else if (projectForm && typeof projectForm.requestSubmit === 'function') {
            projectForm.requestSubmit();
          }
        } else {
          closeModal();
        }
      }

      if (openModalBtn) {
        openModalBtn.addEventListener('click', openModal);
      }
      if (closeModalBtn) {
        closeModalBtn.addEventListener('click', function () {
          handleCloseAddProject();
        });
      }
      if (addProjectModal) {
        addProjectModal.addEventListener('click', function(e){
          if (e.target === addProjectModal) {
            handleCloseAddProject();
          }
        });
      }

      /* ---------- Notes images (Add Project + Project Details modals) ----------
         Files upload as soon as they are picked, using the same mechanism as the
         equipment dimension page. In the Add modal the project row does not exist
         yet, so uploads are staged against a draft key and the server attaches
         them to the project once it is created. */

      function createNotesImageManager(config) {
        var btn = document.getElementById(config.btnId);
        var input = document.getElementById(config.inputId);
        var gallery = document.getElementById(config.galleryId);
        var status = document.getElementById(config.statusId);
        var projectId = 0;
        var replaceTargetId = 0;

        if (!btn || !input || !gallery) {
          return {
            load: function () {},
            clear: function () {},
            setProjectId: function () {},
            discardDraft: function () {},
            hasImages: function () { return false; }
          };
        }

        function draftKey() {
          var field = config.draftKeyId ? document.getElementById(config.draftKeyId) : null;
          return field ? String(field.value || '') : '';
        }

        function scopeParams() {
          var params = new URLSearchParams();
          if (projectId > 0) {
            params.set('project_id', String(projectId));
          } else {
            params.set('draft_key', draftKey());
          }
          return params;
        }

        function setStatus(message, isError) {
          if (!status) return;
          status.textContent = message || '';
          status.hidden = !message;
          status.classList.toggle('is-error', !!isError);
        }

        function render(uploads, scrollToId) {
          gallery.innerHTML = '';
          if (!uploads || !uploads.length) {
            gallery.hidden = true;
            setStatus('');
            return;
          }
          // Say how many there are — the column scrolls, so later ones start off-screen.
          setStatus(uploads.length + (uploads.length === 1 ? ' image' : ' images'));
          var scrollTarget = null;
          uploads.forEach(function (upload) {
            var imageUrl = APP_BASE_URL + (upload.file_url || '');

            var card = document.createElement('div');
            card.className = 'notes-image-card';

            var img = document.createElement('img');
            img.src = imageUrl;
            img.alt = upload.original_name || 'Note image';
            // Eager: these galleries are small, and it avoids blank bars on scroll.
            card.appendChild(img);

            var actions = document.createElement('div');
            actions.className = 'notes-image-actions';

            var viewBtn = document.createElement('button');
            viewBtn.type = 'button';
            viewBtn.className = 'notes-image-action';
            viewBtn.textContent = 'View full preview';
            viewBtn.addEventListener('click', function () {
              // Opens the raw image on its own, in a new tab.
              window.open(imageUrl, '_blank', 'noopener');
            });

            var changeBtn = document.createElement('button');
            changeBtn.type = 'button';
            changeBtn.className = 'notes-image-action';
            changeBtn.textContent = 'Change';
            changeBtn.addEventListener('click', function () {
              replaceTargetId = Number(upload.id) || 0;
              openPicker();
            });

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'notes-image-action';
            removeBtn.textContent = 'Remove';
            removeBtn.addEventListener('click', function () {
              confirmRemove(Number(upload.id) || 0);
            });

            actions.appendChild(viewBtn);
            actions.appendChild(changeBtn);
            actions.appendChild(removeBtn);
            card.appendChild(actions);
            gallery.appendChild(card);

            if (scrollToId && Number(upload.id) === Number(scrollToId)) {
              scrollTarget = card;
            }
          });
          gallery.hidden = false;

          // Bring a just-added image into view once it has laid out.
          if (scrollTarget) {
            var target = scrollTarget;
            var img = target.querySelector('img');
            var reveal = function () {
              try { target.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); } catch (e) { target.scrollIntoView(); }
            };
            if (img && !img.complete) {
              img.addEventListener('load', reveal, { once: true });
              img.addEventListener('error', reveal, { once: true });
            } else {
              window.setTimeout(reveal, 50);
            }
          }
        }

        function load(scrollToId) {
          if (projectId <= 0 && draftKey() === '') {
            render([]);
            return Promise.resolve();
          }
          return fetch(APP_BASE_URL + '/api/get_project_uploads.php?' + scopeParams().toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          })
            .then(function (res) { return res.json(); })
            .then(function (data) {
              render(data && data.success ? (data.uploads || []) : [], scrollToId);
            })
            .catch(function () {
              setStatus('Unable to load images.', true);
            });
        }

        function openPicker() {
          input.multiple = !replaceTargetId;
          input.value = '';
          input.click();
        }

        function upload(files) {
          if (!files || !files.length) return;
          var replacing = replaceTargetId;
          replaceTargetId = 0;
          var count = files.length;

          var form = new FormData();
          if (projectId > 0) {
            form.append('project_id', String(projectId));
          } else {
            form.append('draft_key', draftKey());
          }
          Array.prototype.forEach.call(files, function (file) { form.append('files[]', file); });

          btn.disabled = true;
          setStatus(replacing
            ? 'Replacing image...'
            : 'Uploading ' + count + ' image' + (count === 1 ? '' : 's') + '...');

          fetch(APP_BASE_URL + '/api/add_project_upload.php', { method: 'POST', body: form })
            .then(function (res) { return res.json().catch(function () { return { success: false }; }); })
            .then(function (data) {
              if (!data || !data.success) {
                var reason = (data && data.errors && data.errors.length) ? data.errors[0] : 'Upload failed.';
                setStatus(reason, true);
                return;
              }
              // "Change" swaps the picture: drop the old one only once the new one is stored.
              if (replacing) {
                return removeImage(replacing, true);
              }
              // Scroll to the first image of this batch so the user sees it land.
              var stored = (data.uploaded || []).length;
              var firstNew = stored ? data.uploaded[0].id : 0;
              return load(firstNew).then(function () {
                // PHP silently drops files past max_file_uploads, so compare counts
                // rather than trusting the error list alone.
                var missed = count - stored;
                if (missed > 0) {
                  setStatus(stored + ' of ' + count + ' added — ' + missed + ' could not be uploaded.', true);
                }
              });
            })
            .catch(function () {
              setStatus('Upload failed.', true);
            })
            .finally(function () {
              btn.disabled = false;
              input.value = '';
            });
        }

        function removeImage(id, silent) {
          if (!id) return Promise.resolve();
          var body = new URLSearchParams();
          body.set('id', String(id));
          return fetch(APP_BASE_URL + '/api/delete_project_upload.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
          })
            .then(function (res) { return res.json().catch(function () { return { success: false }; }); })
            .then(function (data) {
              if (!data || !data.success) {
                setStatus('Unable to remove the image.', true);
                return;
              }
              if (!silent) setStatus('');
              return load();
            })
            .catch(function () {
              setStatus('Unable to remove the image.', true);
            });
        }

        async function confirmRemove(id) {
          var ok = await showDecisionModal({
            title: 'Remove Image',
            message: 'Remove this image from the notes?',
            confirmText: 'Remove',
            cancelText: 'Keep',
            showCancel: true,
            fallbackValue: false
          });
          if (ok) { removeImage(id, false); }
        }

        // Purge everything staged for an abandoned Add Project modal.
        function discardDraft() {
          var key = draftKey();
          render([]);
          setStatus('');
          if (projectId > 0 || key === '') return;
          var body = new URLSearchParams();
          body.set('draft_key', key);
          fetch(APP_BASE_URL + '/api/delete_project_upload.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
          }).catch(function () { /* best effort */ });
        }

        btn.addEventListener('click', function () {
          replaceTargetId = 0;
          openPicker();
        });

        input.addEventListener('change', function () {
          upload(input.files);
        });

        return {
          load: load,
          clear: function () { render([]); setStatus(''); },
          setProjectId: function (id) { projectId = Number(id) || 0; },
          discardDraft: discardDraft,
          hasImages: function () { return gallery.children.length > 0; }
        };
      }

      notesImages.add = createNotesImageManager({
        btnId: 'addNotesImagesBtn',
        inputId: 'addNotesImageInput',
        galleryId: 'addNotesImageGallery',
        statusId: 'addNotesImagesStatus',
        draftKeyId: 'addNotesDraftKey'
      });

      notesImages.details = createNotesImageManager({
        btnId: 'detailsNotesImagesBtn',
        inputId: 'detailsNotesImageInput',
        galleryId: 'detailsNotesImageGallery',
        statusId: 'detailsNotesImagesStatus'
      });
    })();
  </script>
</body>
</html>
