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

$conn->query('CREATE TABLE IF NOT EXISTS scheduled_projects (
  project_id INT AUTO_INCREMENT PRIMARY KEY,
  project_name VARCHAR(255) NOT NULL,
  `start` DATETIME NOT NULL,
  `end` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$conn->query('CREATE TABLE IF NOT EXISTS scheduled_project_details (
  project_id INT NOT NULL,
  equipments TEXT NULL,
  personnel TEXT NULL,
  PRIMARY KEY (project_id),
  CONSTRAINT fk_scheduled_project_details_project FOREIGN KEY (project_id)
    REFERENCES scheduled_projects(project_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

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

    $ensureStmt = $conn->prepare('INSERT INTO scheduled_project_details (project_id, equipments, personnel) VALUES (?, "", "") ON DUPLICATE KEY UPDATE project_id = project_id');
    if (!$ensureStmt) {
      throw new Exception('Unable to ensure project details row');
    }
    $ensureStmt->bind_param('i', $projectId);
    $ensureStmt->execute();
    $ensureStmt->close();

    $detailsStmt = $conn->prepare('SELECT COALESCE(equipments, "") AS equipments, COALESCE(personnel, "") AS personnel FROM scheduled_project_details WHERE project_id = ? LIMIT 1');
    if (!$detailsStmt) {
      throw new Exception('Unable to fetch project details');
    }
    $detailsStmt->bind_param('i', $projectId);
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
      $updateStmt = $conn->prepare('UPDATE scheduled_project_details SET equipments = ? WHERE project_id = ?');
    } else {
      $updateStmt = $conn->prepare('UPDATE scheduled_project_details SET personnel = ? WHERE project_id = ?');
    }
    if (!$updateStmt) {
      throw new Exception('Unable to update project details');
    }
    $updateStmt->bind_param('si', $updatedCsv, $projectId);
    $updateStmt->execute();
    $updateStmt->close();

    $finalStmt = $conn->prepare('SELECT COALESCE(equipments, "") AS equipments, COALESCE(personnel, "") AS personnel FROM scheduled_project_details WHERE project_id = ? LIMIT 1');
    if (!$finalStmt) {
      throw new Exception('Unable to fetch updated details');
    }
    $finalStmt->bind_param('i', $projectId);
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
    $detailsStmt = $conn->prepare('SELECT COALESCE(equipments, "") AS equipments, COALESCE(personnel, "") AS personnel FROM scheduled_project_details WHERE project_id = ? LIMIT 1');
    if (!$detailsStmt) {
      throw new Exception('Unable to fetch project details');
    }
    $detailsStmt->bind_param('i', $projectId);
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
      $updateStmt = $conn->prepare('UPDATE scheduled_project_details SET equipments = ? WHERE project_id = ?');
    } else {
      $updateStmt = $conn->prepare('UPDATE scheduled_project_details SET personnel = ? WHERE project_id = ?');
    }
    if (!$updateStmt) {
      throw new Exception('Unable to update project details');
    }
    $updateStmt->bind_param('si', $updatedCsv, $projectId);
    $updateStmt->execute();
    $updateStmt->close();

    $finalStmt = $conn->prepare('SELECT COALESCE(equipments, "") AS equipments, COALESCE(personnel, "") AS personnel FROM scheduled_project_details WHERE project_id = ? LIMIT 1');
    if (!$finalStmt) {
      throw new Exception('Unable to fetch updated details');
    }
    $finalStmt->bind_param('i', $projectId);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_scheduled_project') {
  $projectName = trim($_POST['project_name'] ?? '');
  $startDateRaw = trim($_POST['project_start_date'] ?? '');
  $endDateRaw = trim($_POST['project_end_date'] ?? '');

  if ($startDateRaw === '' && isset($_POST['project_start'])) {
    $legacyStartTs = strtotime(trim($_POST['project_start'] ?? ''));
    if ($legacyStartTs) {
      $startDateRaw = date('Y-m-d', $legacyStartTs);
    }
  }
  if ($endDateRaw === '' && isset($_POST['project_end'])) {
    $legacyEndTs = strtotime(trim($_POST['project_end'] ?? ''));
    if ($legacyEndTs) {
      $endDateRaw = date('Y-m-d', $legacyEndTs);
    }
  }

  $startRaw = trim($startDateRaw . ' 07:00:00');
  $endRaw = trim($endDateRaw . ' 18:00:00');

  $startTs = strtotime($startRaw);
  $endTs = strtotime($endRaw);

  if ($projectName === '' || !$startTs || !$endTs) {
    $formError = 'Project name, start date, and end date are required.';
  } elseif ($endTs < $startTs) {
    $formError = 'Project end must be same or later than project start.';
  } else {
    $startDb = date('Y-m-d H:i:s', $startTs);
    $endDb = date('Y-m-d H:i:s', $endTs);

    $conn->begin_transaction();
    try {
      $projectStmt = $conn->prepare('INSERT INTO scheduled_projects (project_name, ' . $startColumnSql . ', ' . $endColumnSql . ') VALUES (?, ?, ?)');
      if (!$projectStmt) {
        throw new Exception('Unable to prepare project insert.');
      }
      $projectStmt->bind_param('sss', $projectName, $startDb, $endDb);
      if (!$projectStmt->execute()) {
        throw new Exception('Unable to save project.');
      }
      $projectId = (int)$projectStmt->insert_id;
      $projectStmt->close();

      $detailsStmt = $conn->prepare('INSERT INTO scheduled_project_details (project_id, equipments, personnel) VALUES (?, "", "")');
      if (!$detailsStmt) {
        throw new Exception('Unable to prepare project details insert.');
      }
      $detailsStmt->bind_param('i', $projectId);
      if (!$detailsStmt->execute()) {
        throw new Exception('Unable to save project details.');
      }
      $detailsStmt->close();

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
$empStmt = $conn->prepare('SELECT name, COALESCE(role, "") AS role FROM users WHERE name IS NOT NULL AND name <> "" ORDER BY name ASC');
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
$eqStmt = $conn->prepare('SELECT equipment_id, COALESCE(NULLIF(dhss_equipment_number, ""), NULLIF(equipment_number, ""), CONCAT("#", equipment_id)) AS equipment_label, COALESCE(NULLIF(type, ""), "Equipment") AS equipment_type FROM equipments ORDER BY equipment_id ASC');
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
$projectsSql = 'SELECT sp.project_id, sp.project_name, sp.' . $startColumnSql . ' AS `start`, sp.' . $endColumnSql . ' AS `end`, COALESCE(spd.equipments, "") AS equipments, COALESCE(spd.personnel, "") AS personnel FROM scheduled_projects sp LEFT JOIN scheduled_project_details spd ON spd.project_id = sp.project_id ORDER BY sp.' . $startColumnSql . ' ASC';
$projectsRes = $conn->query($projectsSql);
if ($projectsRes) {
  while ($row = $projectsRes->fetch_assoc()) {
    $scheduledProjects[] = $row;
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
  <link rel="stylesheet" href="style.css?v=20260323w" />
</head>
<body class="admin-page scheduling-page">
  <div class="admin-container">
    <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
    <div class="admin-layout">
      <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
      <main class="content-area">
        <div class="main-content">
          <section class="scheduling-shell">
            <aside class="resource-sidebar" aria-label="Scheduler resources">
              <div class="resource-group">
                <button type="button" class="resource-section-toggle" id="personnelCollapseBtn" aria-expanded="true" aria-controls="personnelList">
                  <span class="resource-section-title">Crew Members</span>
                  <span class="collapse-icon" aria-hidden="true">&#9662;</span>
                </button>
                <div class="resource-list" id="personnelList">
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
                      <div class="resource-card requirement-source" draggable="true" data-requirement-kind="personnel" data-requirement-value="<?php echo htmlspecialchars($name); ?>">
                        <span class="resource-avatar"><?php echo htmlspecialchars($initials); ?></span>
                        <span class="resource-texts">
                          <span class="resource-name"><?php echo htmlspecialchars($name); ?></span>
                          <span class="resource-sub"><?php echo htmlspecialchars($roleLabel !== '' ? ucfirst($roleLabel) : 'Crew Member'); ?></span>
                        </span>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="resource-empty">No employees found</div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="resource-group">
                <button type="button" class="resource-section-toggle" id="equipmentCollapseBtn" aria-expanded="true" aria-controls="equipmentList">
                  <span class="resource-section-title">Equipment</span>
                  <span class="collapse-icon" aria-hidden="true">&#9662;</span>
                </button>
                <div class="resource-list" id="equipmentList">
                  <?php if (!empty($equipments)): ?>
                    <?php foreach ($equipments as $equipment): ?>
                      <?php
                        $equipmentLabel = (string)($equipment['equipment_label'] ?? '');
                        $equipmentType = trim((string)($equipment['equipment_type'] ?? 'Equipment'));
                        $equipmentInitial = strtoupper(substr($equipmentLabel, 0, 1));
                      ?>
                      <div class="resource-card requirement-source" draggable="true" data-requirement-kind="equipments" data-requirement-value="<?php echo htmlspecialchars($equipmentLabel); ?>">
                        <span class="resource-avatar equipment"><?php echo htmlspecialchars($equipmentInitial); ?></span>
                        <span class="resource-texts">
                          <span class="resource-name"><?php echo htmlspecialchars($equipmentLabel); ?></span>
                          <span class="resource-sub"><?php echo htmlspecialchars($equipmentType); ?></span>
                        </span>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="resource-empty">No equipments found</div>
                  <?php endif; ?>
                </div>
              </div>
            </aside>

            <section class="scheduler-panel" aria-label="Scheduling content">
              <div class="scheduler-topbar">
                <div class="week-controls" aria-label="Week navigation">
                  <button type="button" class="week-btn" id="prevWeekBtn" aria-label="Previous week">&#x2039;</button>
                  <button type="button" class="week-btn today" id="todayWeekBtn">Today</button>
                  <button type="button" class="week-btn" id="nextWeekBtn" aria-label="Next week">&#x203A;</button>
                </div>
                <div class="week-range" id="weekRangeLabel">Week Range</div>
                <div class="scheduler-actions">
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
                <div class="weekly-board" aria-label="Weekly schedule board">
                  <div class="weekly-days-row" id="weeklyDaysRow"></div>
                  <div class="weekly-day-columns" id="weeklyDayColumns" aria-live="polite"></div>
                </div>
              </section>
            </section>
          </section>
        </div>
      </main>
    </div>
  </div>

  <div class="modal-overlay" id="addProjectModal" hidden>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="addProjectTitle">
      <div class="modal-head">
        <h2 id="addProjectTitle">Add Project</h2>
        <button type="button" class="modal-close-btn" id="closeAddProjectModal" aria-label="Close add project form">X</button>
      </div>

      <form method="post" class="project-form" autocomplete="off">
        <input type="hidden" name="action" value="add_scheduled_project" />

        <label for="project_name">Project Name</label>
        <input id="project_name" name="project_name" type="text" required autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" />

        <label for="project_start_date">Start Date</label>
        <input id="project_start_date" name="project_start_date" type="date" required />

        <label for="project_end_date">End Date</label>
        <input id="project_end_date" name="project_end_date" type="date" required />

        <div class="project-form-actions">
          <button type="button" class="secondary-btn" id="cancelAddProjectModal">Cancel</button>
          <button type="submit" class="primary-btn">Save Project</button>
        </div>
      </form>
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
          <span class="meta-label">Hours</span>
          <span class="meta-value" id="viewProjectHours">-</span>
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

      <p class="project-view-helper">Drag crew members or equipment from the left rail and drop on project tiles to assign.</p>

      <div class="project-view-actions">
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
      var scheduledProjects = <?php echo json_encode($scheduledProjects, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
      var projectById = {};
      if (Array.isArray(scheduledProjects)) {
        scheduledProjects.forEach(function(project){
          projectById[String(project.project_id)] = project;
        });
      }

      var usersToggle = document.getElementById('usersToggle');
      var usersGroup = document.getElementById('usersGroup');
      if (usersToggle && usersGroup) {
        usersToggle.addEventListener('click', function(){
          usersGroup.classList.toggle('open');
        });
      }

      function bindResourceCollapse(buttonId, listId) {
        var button = document.getElementById(buttonId);
        var list = document.getElementById(listId);
        if (!button || !list) {
          return;
        }

        button.addEventListener('click', function(){
          var isCollapsed = list.classList.toggle('is-collapsed');
          button.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
        });
      }

      bindResourceCollapse('personnelCollapseBtn', 'personnelList');
      bindResourceCollapse('equipmentCollapseBtn', 'equipmentList');

      var requirementSources = document.querySelectorAll('.requirement-source');
      requirementSources.forEach(function(source){
        source.addEventListener('dragstart', function(e){
          var value = source.getAttribute('data-requirement-value') || '';
          if (!value) {
            e.preventDefault();
            return;
          }

          var payload = {
            kind: source.getAttribute('data-requirement-kind') || '',
            value: value
          };
          e.dataTransfer.setData('text/plain', JSON.stringify(payload));
          e.dataTransfer.effectAllowed = 'copy';
          source.classList.add('is-dragging');
        });

        source.addEventListener('dragend', function(){
          source.classList.remove('is-dragging');
        });
      });

      function updateProjectRequirement(projectId, kind, value) {
        var params = new URLSearchParams();
        params.set('action', 'add_project_requirement');
        params.set('project_id', String(projectId));
        params.set('kind', kind);
        params.set('value', value);

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

      function removeProjectRequirement(projectId, kind, value) {
        var params = new URLSearchParams();
        params.set('action', 'remove_project_requirement');
        params.set('project_id', String(projectId));
        params.set('kind', kind);
        params.set('value', value);

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

      function projectHasAssignment(project, kind, value) {
        if (!project || !kind) {
          return false;
        }
        var normalizedTarget = normalizeAssignmentValue(value);
        if (!normalizedTarget) {
          return false;
        }
        var list = kind === 'personnel' ? parseCsvList(project.personnel) : parseCsvList(project.equipments);
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

      function findAssignmentConflicts(targetProject, kind, value) {
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
          if (!projectHasAssignment(existingProject, kind, value)) {
            return false;
          }
          var existingRange = projectDayRange(existingProject);
          return dayRangesOverlap(targetRange, existingRange);
        });
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

      var viewProjectModal = document.getElementById('viewProjectModal');
      var closeViewProjectModal = document.getElementById('closeViewProjectModal');
      var closeProjectViewBtn = document.getElementById('closeProjectViewBtn');
      var deleteProjectBtn = document.getElementById('deleteProjectBtn');
      var viewProjectTitle = document.getElementById('viewProjectTitle');
      var viewProjectDates = document.getElementById('viewProjectDates');
      var viewProjectHours = document.getElementById('viewProjectHours');
      var viewProjectPersonnel = document.getElementById('viewProjectPersonnel');
      var viewProjectEquipments = document.getElementById('viewProjectEquipments');
      var decisionModal = document.getElementById('decisionModal');
      var decisionModalTitle = document.getElementById('decisionModalTitle');
      var decisionModalMessage = document.getElementById('decisionModalMessage');
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

        decisionModalTitle.textContent = config.title || 'Confirm Action';
        decisionModalMessage.textContent = config.message || '';
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

      function openProjectViewModal(project) {
        if (!project || !viewProjectModal) {
          return;
        }
        activeViewProjectId = Number(project.project_id);

        var startDate = parseDateTime(project.start);
        var endDate = parseDateTime(project.end);

        if (viewProjectTitle) {
          viewProjectTitle.textContent = project.project_name || 'Project';
        }
        if (viewProjectDates && startDate && endDate) {
          viewProjectDates.textContent = formatIsoDate(startDate) + ' -> ' + formatIsoDate(endDate);
        }
        if (viewProjectHours && startDate && endDate) {
          viewProjectHours.textContent = formatHourLabel(startDate) + ' - ' + formatHourLabel(endDate);
        }

        renderViewChips(viewProjectPersonnel, parseCsvList(project.personnel), 'personnel');
        renderViewChips(viewProjectEquipments, parseCsvList(project.equipments), 'equipments');

        viewProjectModal.hidden = false;
      }

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
          weekRangeLabel.textContent = rangeFormatter.format(currentWeekStart) + ' - ' + rangeFormatter.format(weekEnd);
        }

        function renderProjectTile(project, startDayIndex, endDayIndex, rowIndex) {
          var tile = document.createElement('div');
          tile.className = 'project-tile day-bubble spanning-bubble';
          tile.style.gridColumn = String(startDayIndex + 1) + ' / ' + String(endDayIndex + 2);
          tile.style.gridRow = String(rowIndex + 1);
          var tileColor = getProjectColor(project);
          tile.style.background = tileColor;
          tile.style.borderColor = tileColor;

          var nameEl = document.createElement('div');
          nameEl.className = 'project-tile-name';
          nameEl.textContent = project.project_name || 'Project';
          tile.appendChild(nameEl);

          var reqMeta = document.createElement('div');
          reqMeta.className = 'project-tile-req';
          var personnelsText = project.personnel ? project.personnel : '-';
          var equipmentsText = project.equipments ? project.equipments : '-';
          reqMeta.textContent = 'Crew Members: ' + personnelsText + '\nEquipments: ' + equipmentsText;
          tile.appendChild(reqMeta);

          var tooltip = [];
          tooltip.push('Dates: ' + (project.start || '') + ' -> ' + (project.end || ''));
          if (project.equipments) {
            tooltip.push('Equipments: ' + project.equipments);
          }
          if (project.personnel) {
            tooltip.push('Crew Members: ' + project.personnel);
          }
          if (tooltip.length > 0) {
            tile.title = tooltip.join('\n');
          }

          tile.addEventListener('dragover', function(e){
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            tile.classList.add('is-drop-target');
          });

          tile.addEventListener('dragleave', function(){
            tile.classList.remove('is-drop-target');
          });

          tile.addEventListener('drop', async function(e){
            e.preventDefault();
            tile.classList.remove('is-drop-target');
            tile.dataset.justDropped = '1';
            window.setTimeout(function(){
              tile.dataset.justDropped = '';
            }, 160);
            var raw = e.dataTransfer.getData('text/plain') || '';
            if (!raw) {
              return;
            }

            var payload;
            try {
              payload = JSON.parse(raw);
            } catch (err) {
              return;
            }
            if (!payload || (payload.kind !== 'equipments' && payload.kind !== 'personnel') || !payload.value) {
              return;
            }

            if (projectHasAssignment(project, payload.kind, payload.value)) {
              await showInfoModal(
                'Already Assigned',
                payload.value + ' is already assigned to project #' + String(project.project_id) + ' (' + (project.project_name || 'Project') + ').'
              );
              return;
            }

            var conflicts = findAssignmentConflicts(project, payload.kind, payload.value);
            if (conflicts.length > 0) {
              renderConflictVisualization(project, conflicts);
              var confirmMove = await showDecisionModal({
                title: 'Assignment Conflict',
                message: payload.value + ' is already assigned to the following project(s) for overlapping day(s).\n\nMove this assignment to ' + (project.project_name || 'Project') + '?',
                confirmText: 'Move Assignment',
                cancelText: 'Keep Existing',
                showCancel: true,
                conflicts: conflicts,
                fallbackValue: false
              });
              if (!confirmMove) {
                return;
              }
            }

            tile.classList.add('is-saving');
            try {
              var removePromises = conflicts.map(function(conflictProject){
                return removeProjectRequirement(conflictProject.project_id, payload.kind, payload.value)
                  .then(function(result){
                    if (!result.ok || !result.data || !result.data.success) {
                      throw new Error('Remove failed');
                    }

                    var fromProject = projectById[String(conflictProject.project_id)];
                    if (fromProject) {
                      fromProject.equipments = result.data.equipments || '';
                      fromProject.personnel = result.data.personnel || '';
                    }
                  });
              });

              await Promise.all(removePromises);

              var result = await updateProjectRequirement(project.project_id, payload.kind, payload.value);
              if (!result.ok || !result.data || !result.data.success) {
                throw new Error('Save failed');
              }

              var targetProject = projectById[String(project.project_id)];
              if (targetProject) {
                targetProject.equipments = result.data.equipments || '';
                targetProject.personnel = result.data.personnel || '';
              }
              renderProjectTiles();
            } catch (err) {
              await showInfoModal('Unable To Save', 'Unable to add requirement to this project. Please try again.');
            } finally {
              tile.classList.remove('is-saving');
            }
          });

          tile.addEventListener('click', function(){
            if (tile.dataset.justDropped === '1') {
              return;
            }
            openProjectViewModal(project);
          });

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

          scheduledProjects.forEach(function(project){
            var startDate = parseDateTime(project.start);
            var endDate = parseDateTime(project.end);
            if (!startDate || !endDate || endDate <= startDate) {
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

            renderProjectTile(entry.project, entry.startDayIndex, entry.endDayIndex, laneIndex);
          });

          var visibleRows = Math.max(6, laneEnds.length);
          weeklyDayColumns.style.gridTemplateRows = 'repeat(' + String(visibleRows) + ', minmax(66px, auto))';
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
        rerenderProjects = renderProjectTiles;

        if (prevWeekBtn) {
          prevWeekBtn.addEventListener('click', function(){
            currentWeekStart.setDate(currentWeekStart.getDate() - 7);
            renderWeekHeader();
            renderProjectTiles();
          });
        }

        if (todayWeekBtn) {
          todayWeekBtn.addEventListener('click', function(){
            currentWeekStart = startOfWeek(new Date());
            renderWeekHeader();
            renderProjectTiles();
          });
        }

        if (nextWeekBtn) {
          nextWeekBtn.addEventListener('click', function(){
            currentWeekStart.setDate(currentWeekStart.getDate() + 7);
            renderWeekHeader();
            renderProjectTiles();
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
              removeProjectRequirement(activeViewProjectId, removeKind, removeValue)
                .then(function(result){
                  if (!result.ok || !result.data || !result.data.success) {
                    throw new Error('Remove failed');
                  }
                  var current = projectById[String(activeViewProjectId)];
                  if (current) {
                    current.equipments = result.data.equipments || '';
                    current.personnel = result.data.personnel || '';
                    openProjectViewModal(current);
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
      var cancelModalBtn = document.getElementById('cancelAddProjectModal');
      var addProjectModal = document.getElementById('addProjectModal');

      function openModal() {
        if (addProjectModal) {
          addProjectModal.hidden = false;
        }
      }

      function closeModal() {
        if (addProjectModal) {
          addProjectModal.hidden = true;
        }
      }

      if (openModalBtn) {
        openModalBtn.addEventListener('click', openModal);
      }
      if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeModal);
      }
      if (cancelModalBtn) {
        cancelModalBtn.addEventListener('click', closeModal);
      }
      if (addProjectModal) {
        addProjectModal.addEventListener('click', function(e){
          if (e.target === addProjectModal) {
            closeModal();
          }
        });
      }
    })();
  </script>
</body>
</html>
