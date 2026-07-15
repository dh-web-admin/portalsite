<?php
require_once __DIR__ . '/../../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

// Include database configuration
require_once __DIR__ . '/../../config/config.php';

// Get user role and saved checklist filter for sidebar
$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role, checklist_status_filter FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$actualRole = $user ? $user['role'] : 'laborer';
$savedFilter = ($user && array_key_exists('checklist_status_filter', $user))
  ? $user['checklist_status_filter']
  : null;

$role = $actualRole;

$stmt->close();

require_once __DIR__ . '/../../partials/permissions.php';

if (!can_access($role, 'project_checklist')) {
  header('Location: /index.php');
  exit();
}

$canEditProjectChecklist = can_edit_page('project_checklist');

// Detect whether Projects table has a Status column (avoid fatal if not)
$has_status = false;
try {
  $colRes = $conn->query("SHOW COLUMNS FROM Projects LIKE 'Status'");
  if ($colRes && $colRes->num_rows > 0) {
    $has_status = true;
  }
} catch (Exception $e) {
  // ignore - we'll treat as if column absent
  $has_status = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
  <meta name="theme-color" content="#667eea" />
  <title>Project Checklist — Maintenance</title>
  <link rel="stylesheet" href="../../assets/css/base.css" />
  <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="../../assets/css/project-checklist.css" />
  <link rel="stylesheet" href="style.css" />
  <script>
  // Detect base path dynamically (XAMPP + Railway safe)
  window.APP_BASE = "<?php
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    echo rtrim(str_replace('/pages/project_checklist/', '', $path), '/');
  ?>";

    window.CAN_EDIT_PROJECT_CHECKLIST = <?php echo !empty($canEditProjectChecklist) ? 'true' : 'false'; ?>;
    window.INITIAL_STATUS_FILTER = "";
</script>
</head>
<body class="admin-page">
  <div class="admin-container">
  <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
    <div class="admin-layout">
      <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
      <main class="content-area" style="padding-top:0;">
        <div class="main-content">
            <div class="toolbar" style="display:flex;align-items:center;margin-bottom:0px;gap:12px;position:sticky;top:0;z-index:100;background:#ffffff;padding:10px;box-shadow:0 2px 8px rgba(2,6,23,0.04);">
            <div class="toolbar-left" style="flex:0 0 auto;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
              <?php if ($canEditProjectChecklist) { ?>
                <button id="addProjectBtn" class="btn btn-primary">New Project</button>
              <?php } ?>
              <div id="projectSummaryTab" class="project-summary-tab" aria-live="polite">
                <div class="summary-header-line">Project: <span id="summaryProjectName" class="project-name-text">—</span></div>
                <div class="summary-divider" role="presentation"></div>
                <div class="summary-stats-line"><span id="summaryCounts">0/0</span> remaining | <span id="summaryPct">0%</span> completed</div>
                <div class="summary-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="Completion progress">
                  <span id="summaryBar"></span>
                </div>
              </div>
            </div>
            <!-- Centered controls (Save/Cancel + Filter inside a controlled centered area) -->
            <div style="position:absolute;left:50%;transform:translateX(-50%);display:flex;gap:12px;align-items:center;">
              <?php if ($canEditProjectChecklist) { ?>
                <button id="toggleEditBtn" class="btn btn-success" title="Enable editing" style="margin-left:8px;opacity:1">Edit</button>
                <button id="cancelChangesBtn" class="btn btn-secondary" disabled style="margin-left:6px;opacity:0.6">Cancel</button>
              <?php } ?>
              <div class="filter-dropdown" style="margin-left:12px;">
                <button id="filterBtn" class="filter-btn">Filter ▾</button>
                <div id="filterMenu" class="filter-menu" aria-hidden="true">
                  <div class="filter-option" data-status="">All Projects</div>
                  <div class="filter-option" data-status="Completed">Completed Projects</div>
                  <div class="filter-option" data-status="Cancelled">Cancelled Projects</div>
                  <div class="filter-option" data-status="Ongoing">On going Projects</div>
                </div>
              </div>
            </div>
            <div style="margin-left:auto;display:flex;align-items:center;gap:8px;">
              <?php if ($role === 'admin' || $role === 'developer') { ?>
                <button id="fieldAssignBtn" class="btn btn-secondary">Field Assignment</button>
              <?php } ?>
            </div>
          </div>

          <!-- Table area placed below the toolbar -->
          <div class="table-area" style="width:100%;margin:0;padding:0;">
            <div class="table-wrap" style="width:100%;padding:8px 0;">
              <!-- Inline styles removed; consolidated into external project-checklist.css -->

              <!-- Top horizontal scrollbar synced with table -->
              <div id="topScrollbar" style="height:20px;overflow-x:scroll;overflow-y:hidden;"><div></div></div>
              <!-- Assignments row (shows assigned user per column) -->
              <div id="assignmentsRowWrapper" style="height:44px;overflow-x:auto;overflow-y:hidden;border-bottom:1px solid rgba(15,23,42,0.04);">
                <div id="assignmentsRowInner" style="height:44px;display:block;"></div>
              </div>

              <div class="table-container" role="region" aria-label="Project checklist table">
                <table class="project-table" role="table" aria-label="Projects checklist" data-has-status="<?php echo !empty($has_status) ? '1' : '0'; ?>">
                  <thead>
                    <tr>
                      <th>Project Name</th>
                      <?php if (!empty($has_status)) { echo "<th>Status</th>\n"; } ?>
                      <th>City</th>
                      <th>County</th>
                      <th>State</th>
                      <th>Coordinates</th>
                      <th>Client</th>
                      <th>Anticipated Start Date</th>
                      <th>State License</th>
                      <th>City License</th>
                      <th>Get Contract</th>
                      <th>Review and sign Contract</th>
                      <th>Get Tax Exempt Form</th>
                      <th>Complete Vendor Form</th>
                      <th>Send W9</th>
                      <th>Send BWC</th>
                      <th>Updated BWC</th>
                      <th>Request Certificate of INS</th>
                      <th>Send Certificate of INS</th>
                      <th>Send to Lawyer</th>
                      <th>Request NOC</th>
                      <th>Send NOF</th>
                      <th>File NOC/NOF</th>
                      <th>Get signed Quote</th>
                      <th>Complete Win Packet</th>
                      <th>Create Foreman Field Folder</th>
                      <th>Add to Project Calendar</th>
                      <th>Soil Testing</th>
                      <th>Soil Sampling</th>
                      <th>Lab</th>
                      <th>Mix Design Sent</th>
                      <th>Results</th>
                      <th>Mix Design Approval</th>
                      <th>Call OUPS</th>
                      <th>Schedule Mobilization</th>
                      <th>Schedule Field Testing</th>
                      <th>Get Field Testing Results</th>
                      <th>Send Submittals</th>
                      <th>Schedule Fuel</th>
                      <th>Fuel Supplier</th>
                      <th>Selected Material Supplier</th>
                      <th>Schedule Material</th>
                      <th>Selected Trucking Company</th>
                      <th>Schedule Trucker</th>
                      <th>Hotel</th>
                      <th>Find Water</th>
                      <th>Water Semi</th>
                      <th>Schedule Men</th>
                      <th>Grade File</th>
                      <th>Cure Type</th>
                      <th>Schedule Cure</th>
                      <th>Cure Provider</th>
                      <th>Turn in Paperwork</th>
                      <th>Process Field Paperwork</th>
                      <th>Review Processed Paperwork</th>
                      <th>Sign Change Order</th>
                      <th>Send Signed Change Order</th>
                      <th>Invoice</th>
                      <th>AIA</th>
                      <th>Supplier Lein Waiver</th>
                      <th>Send Supplier Lein Waiver</th>
                      <th>DHSS Lein Waiver</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    // Fetch projects from database and render rows
                    // Build columns array dynamically: include Status only when table actually has it
                    $columns = array(
                      'Project_Name'
                    );
                    if (!empty($has_status)) {
                      $columns[] = 'Status';
                    }

                    // (INITIAL_STATUS_FILTER will be set after server computes $status_filter)
                    $columns = array_merge($columns, array('City','County','State','Coordinates','Client','Anticipated_Start_Date','State_License','City_License','Get_Contract','Review_and_Sign_Contract','Get_Tax_Exempt_Form','Complete_Vendor_Form','Send_W9','Send_BWC','Updated_BWC','Request_Certificate_of_INS','Send_Certificate_of_INS','Send_to_Lawyer','Request_NOC','Send_NOF','File_NOC_NOF','Get_Signed_Quote','Complete_Win_Packet','Create_Foreman_Field_Folder','Add_to_Project_Calendar','Soil_Testing','Soil_Sampling','Lab','Mix_Design_Sent','Results','Mix_Design_Approval','Call_OUPS','Schedule_Mobilization','Schedule_Field_Testing','Get_Field_Testing_Results','Send_Submittals','Schedule_Fuel','Fuel_Supplier','Selected_Material_Supplier','Schedule_Material','Selected_Trucking_Company','Schedule_Trucker','Hotel','Find_Water','Water_Semi','Schedule_Men','Grade_File','Cure_Type','Schedule_Cure','Cure_Provider','Turn_in_Paperwork','Process_Field_Paperwork','Review_Processed_Paperwork','Sign_Change_Order','Send_Signed_Change_Order','Invoice','AIA','Supplier_Lein_Waiver','Send_Supplier_Lein_Waiver','DHSS_Lein_Waiver'));

                    // Server-side filtering by status.
                    // Priority: explicit ?status= URL param (e.g. shared link) > saved user preference > All.
                    $allowed_statuses = ['Ongoing','Completed','Cancelled'];
                    $status_filter = '';
                    if (!empty($has_status)) {
                      if (isset($_GET['status'])) {
                        $candidate = trim($_GET['status']);
                        if (in_array($candidate, $allowed_statuses, true)) {
                          $status_filter = $candidate;
                        }
                        // if candidate is '' or invalid, status_filter stays '' (All) -- this also
                        // covers the explicit "All Projects" click, which passes no ?status param.
                      } elseif ($savedFilter !== null && ($savedFilter === '' || in_array($savedFilter, $allowed_statuses, true))) {
                        // No ?status in URL at all -> fall back to the user's saved preference.
                        $status_filter = $savedFilter;
                      }
                    }

                    if ($status_filter !== '') {
                      $projects_stmt = $conn->prepare('SELECT * FROM Projects WHERE Status = ? ORDER BY Project_ID DESC LIMIT 500');
                      if ($projects_stmt) {
                        $projects_stmt->bind_param('s', $status_filter);
                        $projects_stmt->execute();
                        $projects_res = $projects_stmt->get_result();
                      } else {
                        $projects_res = false;
                      }
                    } else {
                      $projects_stmt = $conn->prepare('SELECT * FROM Projects ORDER BY Project_ID DESC LIMIT 500');
                      if ($projects_stmt) {
                        $projects_stmt->execute();
                        $projects_res = $projects_stmt->get_result();
                      } else {
                        $projects_res = false;
                      }
                    }
                    if ($projects_stmt) {
                      $allRows = array();
                      if ($projects_res && $projects_res->num_rows > 0) {
                        while ($p = $projects_res->fetch_assoc()) {
                          $allRows[] = $p;
                        }
                      }

                      // Ensure at least 10 visible rows (placeholders) when table is empty or has few rows
                      $minVisible = 10;
                      $totalToRender = max($minVisible, count($allRows));

                      for ($r = 0; $r < $totalToRender; $r++) {
                        if (isset($allRows[$r])) {
                          $p = $allRows[$r];
                          $pid = intval($p['Project_ID']);
                          echo "<tr data-project-id=\"{$pid}\">\n";
                          $name = htmlspecialchars($p['Project_Name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                          
                          // Get status for color coding
                          $status = '';
                          $statusClass = '';
                          if (!empty($has_status) && isset($p['Status'])) {
                            $status = trim($p['Status']);
                            if ($status === 'Cancelled') {
                              $statusClass = ' status-cancelled';
                            } elseif ($status === 'Completed') {
                              $statusClass = ' status-completed';
                            } elseif ($status === 'Ongoing') {
                              $statusClass = ' status-ongoing';
                            }
                          }
                          
                          // Minimal action icons: clone (copy), rename (pencil), delete (trash)
                          $actions = '';
                          if ($canEditProjectChecklist) {
                            $actions = "<span class=\"project-actions\" style=\"margin-left:auto\">" .
                                       "<button class=\"icon-btn clone-btn\" data-project-id=\"{$pid}\" title=\"Clone project\">" .
                                      "<svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M16 1H4a2 2 0 0 0-2 2v12\" stroke=\"currentColor\" stroke-width=\"1.6\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/><rect x=\"8\" y=\"7\" width=\"13\" height=\"13\" rx=\"2\" stroke=\"currentColor\" stroke-width=\"1.6\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/></svg>" .
                                       "</button>" .
                                       "<button class=\"icon-btn edit-btn\" data-project-id=\"{$pid}\" title=\"Edit project\">" .
                                      "<svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M3 21l3-1 11-11 1-3-3 1L4 20z\" stroke=\"currentColor\" stroke-width=\"1.6\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/></svg>" .
                                       "</button>" .
                                       "</span>";
                          }
                          // wrap the project name in a span so we can flex it and push actions to the right
                          // CRITICAL: Wrap everything in a div to use flex layout without breaking sticky positioning
                          echo "<td class=\"project-name{$statusClass}\"><div><span class=\"project-title\">{$name}</span>{$actions}</div></td>\n";
                          foreach (array_slice($columns,1) as $col) {
                            $rawVal = $p[$col] ?? '';
                            $val = htmlspecialchars($rawVal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            // editable cells (all except project name)
                            $colEsc = htmlspecialchars($col, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            // mark empty cells with a class so they show a yellow background by default
                            $emptyClass = ($rawVal === '' || $rawVal === null) ? ' empty-cell' : '';
                            // contentEditable is toggled by the Edit button to avoid accidental changes
                            echo "<td class=\"editable{$emptyClass}\" data-col=\"{$colEsc}\">{$val}</td>\n";
                          }
                          echo "</tr>\n";
                        }
                        // Skip rendering empty placeholder rows - only render actual data rows
                      }
                      // Add a spacer row at the very bottom so the last
                      // real project row is never visually cut off by the
                      // scroll container's bottom edge.
                      echo '<tr class="project-bottom-spacer"><td colspan="'.count($columns).'"></td></tr>';
                      $projects_stmt->close();
                    } else {
                      // In case prepare fails, show a friendly message but keep page usable
                      echo '<tr><td colspan="'.count($columns).'" style="text-align:center;padding:40px 16px;color:#64748b">Unable to load projects</td></tr>';
                    }
                    ?>
                  </tbody>
                </table>
              </div>
              <!-- Persistent, larger horizontal scrollbar proxy for cross-browser consistency -->
              <div id="tableScrollProxy" style="height:20px;overflow-x:scroll;overflow-y:hidden;">
                <div id="tableScrollProxyInner" style="height:1px"></div>
              </div>
            </div>
          </div>
      </main>
    </div>
  </div>
  
  <!-- Field Assignment Modal (admin/developer only) -->
  <style>
    /* Field Assignment modal styles */
    #fieldAssignModal { display:none; position:fixed; inset:0; background:rgba(2,6,23,0.45); align-items:center; justify-content:center; z-index:6000; padding:24px; }
    #fieldAssignModal .modal-panel { background:#fff; border-radius:12px; padding:20px; max-width:980px; width:100%; max-height:86vh; overflow:hidden; box-shadow:0 20px 50px rgba(2,6,23,0.18); border:1px solid rgba(15,23,42,0.04); }
    #fieldAssignModal .modal-header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding-bottom:6px; border-bottom:1px solid #eef2f7; }
    #fieldAssignModal .modal-title { font-weight:700; color:#0f172a; font-size:18px; }
    #fieldAssignModal .modal-close { background:transparent; border:0; font-size:18px; cursor:pointer; color:#475569; padding:6px; border-radius:8px; }
    #fieldAssignModal .modal-close:hover { background:#f8fafc; }
    #fieldAssignModal .modal-body { padding:14px 0; overflow:auto; max-height:64vh; }
    #fieldAssignTable { width:100%; border-collapse:separate; border-spacing:0 8px; }
    #fieldAssignTable thead th { text-align:left; padding:10px 12px; font-weight:700; color:#0f172a; border-bottom:none; background:transparent; }
    #fieldAssignTable tbody tr { background:#ffffff; }
    #fieldAssignTable tbody tr td { padding:10px 12px; vertical-align:middle; border-bottom:none; }
    #fieldAssignTable tbody tr td:first-child { color:#0f172a; font-weight:500; }
    .select-assignee { width:100%; padding:8px 10px; border:1px solid #e6eaf0; border-radius:8px; background:#fff; color:#0f172a; }
    #fieldAssignModal .modal-actions { display:flex; justify-content:flex-end; gap:10px; padding-top:12px; border-top:1px solid #f1f5f9; margin-top:8px; }
    #saveFieldAssign { background:#10b981; color:#fff; border:0; padding:8px 14px; border-radius:8px; box-shadow:0 8px 24px rgba(16,185,129,0.14); cursor:pointer; }
    #cancelFieldAssign { background:#fff; color:#0f172a; border:1px solid #e6eaf0; padding:8px 14px; border-radius:8px; cursor:pointer; }
    #cancelFieldAssign.cancel-red { background:#ef4444; color:#fff; border:0; box-shadow:0 8px 18px rgba(239,68,68,0.12); }
    @media (max-width:720px){ #fieldAssignModal .modal-panel{ max-width:100%; padding:14px; } #fieldAssignTable thead th{ font-size:14px; } }
    /* Make the Field Assignment toolbar button bluish (plain color) without altering other button styles */
    #fieldAssignBtn { background-color: #2563eb; color: #ffffff; border: 0; padding:8px 12px; border-radius:8px; box-shadow: 0 8px 20px rgba(37,99,235,0.12); cursor:pointer; }
    #fieldAssignBtn:hover { filter: brightness(0.96); transform: translateY(-1px); }
  </style>

  <div id="fieldAssignModal">
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="fieldAssignTitle">
      <div class="modal-header">
        <div id="fieldAssignTitle" class="modal-title">Field Assignment</div>
        <button id="closeFieldAssign" class="modal-close" aria-label="Close">✕</button>
      </div>
      <div class="modal-body">
        <div style="margin-bottom:8px;color:#475569;font-size:14px;">Assign a user to each project checklist column. Changes affect global assignments.</div>
        <div id="fieldAssignContainer">
          <table id="fieldAssignTable">
            <thead><tr><th>Field</th><th>Assigned User</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modal-actions">
        <button id="unassignAll" class="btn" type="button">Unassign All</button>
        <button id="cancelFieldAssign" class="btn" type="button">Cancel</button>
        <button id="saveFieldAssign" class="btn" type="button">Save</button>
      </div>
    </div>
  </div>

  <?php if ($canEditProjectChecklist) { ?>
    <!-- New Project Modal -->
    <div id="newProjectModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.6);align-items:center;justify-content:center;z-index:60"> 
      <div style="background:#fff;border-radius:8px;padding:18px;max-width:520px;width:100%;box-shadow:0 8px 30px rgba(2,6,23,0.2)">
        <h3 style="margin:0 0 8px 0;font-size:18px">Create new project</h3>
        <div style="margin-bottom:12px">
          <label style="display:block;font-size:13px;margin-bottom:6px">Project name</label>
          <input id="newProjectName" type="text" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px" placeholder="Enter project name" />
        </div>
        <div style="text-align:right;display:flex;gap:8px;justify-content:flex-end">
          <button id="cancelNewProject" class="btn">Cancel</button>
          <button id="createNewProject" class="btn btn-primary">Create</button>
        </div>
      </div>
    </div>
  <?php } ?>
  </div>
  <script>
  (function(){
    document.addEventListener('DOMContentLoaded', function(){
      var btn = document.getElementById('fieldAssignBtn');
      var modal = document.getElementById('fieldAssignModal');
      if (!btn || !modal) return;
      var closeBtn = document.getElementById('closeFieldAssign');
      var cancelBtn = document.getElementById('cancelFieldAssign');
      var saveBtn = document.getElementById('saveFieldAssign');

      btn.addEventListener('click', openFieldAssign);
      if (closeBtn) closeBtn.addEventListener('click', closeFieldAssign);
      if (cancelBtn) cancelBtn.addEventListener('click', closeFieldAssign);
      var unassignBtn = document.getElementById('unassignAll');
      if (unassignBtn) unassignBtn.addEventListener('click', function(){
        try {
          var sels = Array.from(document.querySelectorAll('#fieldAssignTable tbody select')) || [];
          sels.forEach(function(s){ s.value = ''; var evt = new Event('change', { bubbles:true }); s.dispatchEvent(evt); });
          try { if (typeof showToast === 'function') showToast('All selections cleared', 'info'); } catch(e){}
        } catch(e) { console.warn('unassignAll failed', e); }
      });

      function openFieldAssign(){ modal.style.display = 'flex'; loadFieldAssign(); }
      function closeFieldAssign(){ modal.style.display = 'none'; }

      async function loadFieldAssign(){
        try {
          var ths = Array.from(document.querySelectorAll('.project-table thead th')) || [];
          var fields = ths.map(function(t){ return (t.textContent || '').toString().trim(); }).filter(function(x){ return x; });

          // fetch users (admin/developer only)
          var users = [];
          try {
            var uresp = await fetch('../../api/get_assignable_users.php', { credentials: 'same-origin' });
            var uj = await uresp.json(); if (uj && uj.success && Array.isArray(uj.users)) users = uj.users;
          } catch(e) { console.warn('fetch users failed', e); }

          // fetch existing assignments
          var assignments = {};
          try {
            var aresp = await fetch('../../api/get_field_assignments.php', { credentials: 'same-origin' });
            var aj = await aresp.json(); if (aj && aj.success && aj.assignments) assignments = aj.assignments;
          } catch(e) { console.warn('fetch assignments failed', e); }

          var tbody = document.querySelector('#fieldAssignTable tbody'); if (!tbody) return; tbody.innerHTML = '';
          fields.forEach(function(f){
            var keyCheck = (f || '').toString().trim().toLowerCase();
            // Do not allow assigning the project name or status columns
            if (keyCheck === 'project name' || keyCheck === 'project_name' || keyCheck === 'status') return;
            var tr = document.createElement('tr');
            var td1 = document.createElement('td'); td1.style.padding = '8px'; td1.textContent = f;
            var td2 = document.createElement('td'); td2.style.padding = '8px';
            var sel = document.createElement('select'); sel.className = 'select-assignee'; sel.dataset.field = f;
            var opt0 = document.createElement('option'); opt0.value = ''; opt0.textContent = '-- unassigned --'; sel.appendChild(opt0);
            users.forEach(function(u){ var o = document.createElement('option'); o.value = u.id; o.textContent = (u.name || u.email || u.id); sel.appendChild(o); });
            // match by lowercased field key
            var key = (f || '').toString().trim().toLowerCase();
            if (assignments && typeof assignments[key] !== 'undefined' && assignments[key]) {
              try { sel.value = String(assignments[key]); } catch(e){}
            }
            td2.appendChild(sel); tr.appendChild(td1); tr.appendChild(td2); tbody.appendChild(tr);
          });
        } catch(e) { console.warn('loadFieldAssign error', e); }
      }

      if (saveBtn) saveBtn.addEventListener('click', async function(){
        try {
          var rows = Array.from(document.querySelectorAll('#fieldAssignTable tbody select')) || [];
          var payload = {};
          rows.forEach(function(s){ var f = (s.dataset.field||'').toString().trim(); var v = s.value || ''; payload[f.toLowerCase()] = v ? parseInt(v,10) : null; });
          var fd = new FormData(); fd.append('assignments', JSON.stringify(payload));
          var resp = await fetch('../../api/save_field_assignments.php', { method: 'POST', credentials: 'same-origin', body: fd });
          var j = await resp.json();
          if (j && j.success) { try { if (typeof showToast === 'function') showToast('Field assignments saved', 'success'); } catch(e){} closeFieldAssign(); }
          else { try { if (typeof showToast === 'function') showToast((j && j.message) ? j.message : 'Failed to save', 'error'); } catch(e){} }
        } catch(e) { console.warn('save failed', e); try { if (typeof showToast === 'function') showToast('Failed to save', 'error'); } catch(ignore){} }
      });
    });
  })();
  </script>
  <?php if ($canEditProjectChecklist) { ?>
    <!-- Inline edit menu (rename / delete / mark status) -->
    <div id="editMenu" class="edit-menu" role="dialog" aria-hidden="true">
      <div class="row">
        <input type="text" name="edit_name" placeholder="Project name" aria-label="Project name" />
        <button class="btn" id="editMenuRename">Rename</button>
      </div>
      <div class="row actions">
        <button class="btn danger" id="editMenuDelete">Delete</button>
        <button class="btn muted" id="editMenuCancelProject">Cancel Project</button>
        <button class="btn muted" id="editMenuCompleteProject">Mark Completed</button>
        <button class="btn muted" id="editMenuContinueProject" style="display:none;">Continue Project</button>
    <!-- visible action buttons now handle status changes directly -->
      </div>
    </div>
  <?php } ?>

  <!-- Status change toast -->
  <div id="statusToast" role="status" aria-live="polite">
    <button id="statusToastUndo" class="btn" style="display:none;margin-left:8px;padding:6px 8px;border-radius:6px;font-size:12px">Undo</button>
  </div>

  <!-- Page-specific unsaved modal removed: using global modal from unsaved-guard.js -->

  <script>
    (function(){
    // Project actions: clone, rename, delete
    (function(){
      var table = document.querySelector('.project-table');
      if (!table) return;

      // Floating project summary logic
      var summary = document.getElementById('projectSummaryTab');
  var summaryCounts = document.getElementById('summaryCounts');
  var summaryPct = document.getElementById('summaryPct');
  var summaryProjectName = document.getElementById('summaryProjectName');
  var summaryBar = document.getElementById('summaryBar');
  // no close button by design — summary remains visible when a selection exists

      var hasStatusAttr = table && table.getAttribute('data-has-status') === '1';

      function computeProjectStats(tr){
        // Count total checklist items for this row (all columns except first)
        var cells = Array.prototype.slice.call(tr.querySelectorAll('td'));
        // Remove first sticky project-name cell
        if (cells.length > 0) cells.shift();
        // Exclude hidden/non-checklist columns (e.g., Status is hidden via CSS when present)
        cells = cells.filter(function(td){
          var col = td.getAttribute('data-col');
          if (hasStatusAttr && col === 'Status') return false;
          return true;
        });
        var total = cells.length;
        var remaining = 0;
        cells.forEach(function(td){
          var val = (td.textContent || '').trim();
          if (val === '') remaining += 1;
        });
        var completed = total - remaining;
        var pct = total > 0 ? Math.round((completed / total) * 100) : 0;
        return { remaining: remaining, total: total, pct: pct };
      }

      function showSummaryForRow(tr){
        if (!summary || !tr) return;
        var stats = computeProjectStats(tr);
        if (summaryCounts) summaryCounts.textContent = stats.remaining + '/' + stats.total;
        if (summaryPct) {
          summaryPct.textContent = stats.pct + '%';
        }
        if (summaryBar) {
          summaryBar.style.width = stats.pct + '%';
          summaryBar.parentElement && summaryBar.parentElement.setAttribute('aria-valuenow', stats.pct);
        }
        // Extract project name
        var nameEl = tr.querySelector('.project-title');
        var projectName = nameEl ? nameEl.textContent.trim() : '—';
        if (summaryProjectName) summaryProjectName.textContent = projectName || '—';
        // Row selection highlight
        table.querySelectorAll('tbody tr.is-selected').forEach(function(r){ r.classList.remove('is-selected'); });
        tr.classList.add('is-selected');
        // Persist selection across reloads
        if (tr.dataset && tr.dataset.projectId) {
          try { sessionStorage.setItem('pc_selected_project_id', tr.dataset.projectId); } catch(_){}
        }
        summary.style.display = 'block';
      }

      // Expose a small API so other scripts can refresh the summary live while typing
      try {
        window.ProjectSummary = {
          showForRow: showSummaryForRow,
          refreshForRow: function(tr){
            var selected = tr || (table && table.querySelector('tbody tr.is-selected'));
            if (!selected || !summary) return;
            var stats = computeProjectStats(selected);
            if (summaryCounts) summaryCounts.textContent = stats.remaining + '/' + stats.total;
            if (summaryPct) summaryPct.textContent = stats.pct + '%';
            if (summaryBar) {
              summaryBar.style.width = stats.pct + '%';
              summaryBar.parentElement && summaryBar.parentElement.setAttribute('aria-valuenow', stats.pct);
            }
            var nameEl = selected.querySelector('.project-title');
            var projectName = nameEl ? nameEl.textContent.trim() : '—';
            if (summaryProjectName) summaryProjectName.textContent = projectName || '—';
            summary.style.display = 'block';
          }
        };
      } catch(e) { /* noop */ }

      // Clicking a row opens floating summary
      table.addEventListener('click', function(e){
        var tr = e.target.closest('tr');
        if (!tr) return;
        // Avoid triggering when clicking action buttons
        if (e.target.closest('.project-actions')) return;
        showSummaryForRow(tr);
      });

      // On load, restore a previously selected project if available
      (function restoreSelection(){
        var storedId = null;
        try { storedId = sessionStorage.getItem('pc_selected_project_id'); } catch(_){}
        if (!storedId) return;
        var tr = table.querySelector('tbody tr[data-project-id="' + storedId + '"]');
        if (tr) {
          showSummaryForRow(tr);
        } else {
          try { sessionStorage.removeItem('pc_selected_project_id'); } catch(_){}
        }
      })();

      // clone
      table.addEventListener('click', function(e){
        var btn = e.target.closest('.clone-btn');
        if (!btn) return;
        e.preventDefault();
        var pid = btn.getAttribute('data-project-id');
        if (!pid) return;
        if (!confirm('Create a copy of this project?')) return;
        var fd = new FormData(); fd.append('project_id', pid);
        fetch('../../api/clone_project.php', { method:'POST', body: fd, credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(json){
            if (json && json.success) {
              try { if (window && typeof window.showStatusToast === 'function') window.showStatusToast('Project cloned', 'success'); } catch(e){}
              setTimeout(function(){ window.location.reload(); }, 900);
            } else {
              alert((json && json.message) ? json.message : 'Failed to clone project');
            }
          }).catch(function(err){ console.error(err); alert('Failed to clone project: ' + (err && err.message ? err.message : err)); });
      });

      // NOTE: rename is handled by the edit-menu (see separate script)
      // delete action moved into the edit-menu; click handled there
      // --- Edit menu handling (open, rename, delete, status changes)
      (function(){
        var editMenu = document.getElementById('editMenu');
        if (!editMenu) return;
        var input = editMenu.querySelector('input[name="edit_name"]');
        var btnRename = document.getElementById('editMenuRename');
        var btnDelete = document.getElementById('editMenuDelete');
        var btnCancelProject = document.getElementById('editMenuCancelProject');
        var btnComplete = document.getElementById('editMenuCompleteProject');
        var btnContinue = document.getElementById('editMenuContinueProject');
        var table = document.querySelector('.project-table');

        function openMenuFor(btn, projectId){
          if (!projectId) return;
          var tr = table.querySelector('tbody tr[data-project-id="' + projectId + '"]');
          editMenu.dataset.projectId = projectId;
          input.value = tr ? (tr.querySelector('.project-title') ? tr.querySelector('.project-title').textContent.trim() : '') : '';
          editMenu.style.display = 'block';
          editMenu.setAttribute('aria-hidden','false');
          // Position next to button if possible
          try{
            var rect = btn.getBoundingClientRect();
            editMenu.style.position = 'absolute';
            editMenu.style.left = (rect.right + window.pageXOffset + 8) + 'px';
            editMenu.style.top = (rect.top + window.pageYOffset) + 'px';
          }catch(e){ /* ignore positioning errors */ }
        }

        // Open when clicking the edit icon
        table.addEventListener('click', function(e){
          var btn = e.target.closest('.edit-btn');
          if (!btn) return;
          e.preventDefault();
          var pid = btn.getAttribute('data-project-id');
          openMenuFor(btn, pid);
        });

        // Close when clicking outside
        document.addEventListener('click', function(e){
          if (!editMenu) return;
          if (editMenu.contains(e.target)) return;
          if (e.target.closest && e.target.closest('.edit-btn')) return;
          editMenu.style.display = 'none';
          editMenu.setAttribute('aria-hidden','true');
        });

        // Rename action
        if (btnRename) btnRename.addEventListener('click', function(){
          var pid = editMenu.dataset.projectId;
          var newName = (input.value || '').trim();
          if (!pid || newName === '') { alert('Please enter a project name'); return; }
          var fd = new FormData(); fd.append('project_id', pid); fd.append('new_name', newName);
          fetch('../../api/rename_project.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(json){ if (json && json.success) { window.location.reload(); } else { alert((json && json.message) ? json.message : 'Rename failed'); } })
            .catch(function(err){ console.error('Rename error', err); alert('Rename failed: ' + (err && err.message ? err.message : err)); });
        });

        // Delete action
        if (btnDelete) btnDelete.addEventListener('click', function(){
          var pid = editMenu.dataset.projectId;
          if (!pid) return;
          if (!confirm('Delete this project? This action cannot be undone.')) return;
          var fd = new FormData(); fd.append('project_id', pid);
          fetch('../../api/delete_project.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r){ return r.text(); })
            .then(function(text){
              var json = null;
              try {
                json = text ? JSON.parse(text) : null;
              } catch (e) {
                console.error('Delete response not JSON:', text);
                alert('Delete failed: ' + (text ? text.substring(0, 200) : 'Unexpected response'));
                return;
              }
              if (json && json.success) {
                window.location.reload();
              } else {
                alert((json && json.message) ? json.message : 'Delete failed');
              }
            })
            .catch(function(err){ console.error('Delete error', err); alert('Delete failed: ' + (err && err.message ? err.message : err)); });
        });

        // Helper to set status via update_project_cell.php
        function setStatus(pid, statusValue){
          if (!pid) return;
          var fd = new FormData(); fd.append('project_id', pid); fd.append('column', 'Status'); fd.append('value', statusValue);
          fetch('../../api/update_project_cell.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(json){ if (json && json.success) { window.location.reload(); } else { alert((json && json.message) ? json.message : 'Failed to update status'); } })
            .catch(function(err){ console.error('Status update error', err); alert('Failed to update status: ' + (err && err.message ? err.message : err)); });
        }

        if (btnCancelProject) btnCancelProject.addEventListener('click', function(){ var pid = editMenu.dataset.projectId; if (!pid) return; if (!confirm('Mark project as Cancelled?')) return; setStatus(pid, 'Cancelled'); });
        if (btnComplete) btnComplete.addEventListener('click', function(){ var pid = editMenu.dataset.projectId; if (!pid) return; setStatus(pid, 'Completed'); });
        if (btnContinue) btnContinue.addEventListener('click', function(){ var pid = editMenu.dataset.projectId; if (!pid) return; setStatus(pid, 'Ongoing'); });
      })();
    })();
      var usersToggle = document.getElementById('usersToggle');
      var usersGroup = document.getElementById('usersGroup');
      if (usersToggle && usersGroup) {
        usersToggle.addEventListener('click', function(){
          usersGroup.classList.toggle('open');
        });
      }
    })();
  </script>
  <script>
    // New Project modal and creation flow
    (function(){
      var addBtn = document.getElementById('addProjectBtn');
      var modal = document.getElementById('newProjectModal');
      var cancelBtn = document.getElementById('cancelNewProject');
      var createBtn = document.getElementById('createNewProject');
      var nameInput = document.getElementById('newProjectName');

      function openModal(){ if(modal) modal.style.display = 'flex'; if(nameInput) { nameInput.value=''; nameInput.focus(); } }
      function closeModal(){ if(modal) modal.style.display = 'none'; }

      if (addBtn) addBtn.addEventListener('click', function(e){ e.preventDefault(); openModal(); });
      if (cancelBtn) cancelBtn.addEventListener('click', function(e){ e.preventDefault(); closeModal(); });

      // Create action with duplicate-name check
      if (createBtn) createBtn.addEventListener('click', function(e){
        e.preventDefault();
        var name = nameInput ? nameInput.value.trim() : '';
        if (!name) { alert('Please enter a project name'); if(nameInput) nameInput.focus(); return; }

        createBtn.disabled = true;
        createBtn.textContent = 'Checking...';

        // Check for existing project name
        fetch('../../api/check_project_name.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'project_name=' + encodeURIComponent(name) })
          .then(function(r){ return r.json(); })
          .then(function(j){
            try {
              if (j && j.success && j.exists) {
                // show confirm modal offering to open existing project or create new
                showDuplicateConfirm(name, j.project_id, function(choice){
                  if (choice === 'open') {
                    // navigate to existing project
                    window.location.href = '/pages/project_checklist/index.php?project_id=' + encodeURIComponent(j.project_id);
                  } else {
                    // proceed to create new project with same name
                    actuallyCreate(name);
                  }
                });
              } else {
                actuallyCreate(name);
              }
            } catch(e) { actuallyCreate(name); }
          })
          .catch(function(){ actuallyCreate(name); })
          .finally(function(){ createBtn.disabled = false; createBtn.textContent = 'Create'; });
      });

      function actuallyCreate(name) {
        createBtn.disabled = true; createBtn.textContent = 'Creating...';
        var fd = new FormData(); fd.append('project_name', name);
        fetch('../../api/create_project_checklist.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(resp){ return resp.json(); })
          .then(function(json){
            if (json && json.success) {
              try { if (window && typeof window.showStatusToast === 'function') window.showStatusToast('Project created', 'success'); } catch(e){}
              try { if (window.UnsavedGuard) window.UnsavedGuard.markClean(); } catch(_){ }
              setTimeout(function(){ window.location.reload(); }, 900);
            } else {
              let msg = 'Failed to create project';
              if (json && json.message) msg = 'Error: ' + json.message;
              alert(msg);
            }
          })
          .catch(function(err){ console.error('Create project error', err); alert('Failed to create project'); })
          .finally(function(){ createBtn.disabled = false; createBtn.textContent = 'Create'; });
      }

      function showDuplicateConfirm(name, projectId, cb) {
        if (!document.getElementById('duplicateProjectModal')) {
          var m = document.createElement('div'); m.id = 'duplicateProjectModal'; m.style = 'position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(2,6,23,0.45);z-index:16000;padding:20px;';
          m.innerHTML = "<div style='background:#fff;border-radius:10px;padding:18px;max-width:520px;width:100%;box-shadow:0 12px 40px rgba(2,6,23,0.18);'>" +
            "<h3 style=\"margin:0 0 8px 0;font-size:18px\">This project already exists</h3>" +
            "<p style=\"margin:0 0 12px\">This project already exists. Create a new project with the same name?</p>" +
            "<div style=\"display:flex;justify-content:flex-end;gap:8px\">" +
              "<button id=\"dupCancel\" style=\"background:#fff;border:1px solid #e6edf0;padding:8px 12px;border-radius:8px;cursor:pointer;font-weight:700;\">Cancel</button>" +
              "<button id=\"dupCreate\" style=\"background:#10b981;color:#fff;padding:8px 12px;border-radius:8px;border:0;font-weight:700;\">Create new</button>" +
            "</div></div>";
          document.body.appendChild(m);
          document.getElementById('dupCancel').addEventListener('click', function(){ m.style.display = 'none'; });
          document.getElementById('dupCreate').addEventListener('click', function(){ m.style.display = 'none'; if (typeof m._cb === 'function') m._cb('create'); });
        }
        var modalEl = document.getElementById('duplicateProjectModal');
        modalEl._cb = function(choice){ if (cb) { if (choice === 'open') cb('open'); else cb('create'); } };
        modalEl.style.display = 'flex';
      }

      // Close modal on outside click or Escape
      if (modal) {
        modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });
      }
    })();
  </script>
  <script>
    // Filter dropdown behavior — render the menu as a top-level element so sticky table
    // headers and other stacking contexts don't clip it. We compute coordinates when
    // opening so the menu visually anchors to the filter button.
    (function(){
      var filterBtn = document.getElementById('filterBtn');
      var filterMenu = document.getElementById('filterMenu');
      if(!filterBtn || !filterMenu) return;

      // Build mapping of status -> label for display on the button
      var statusLabelMap = {};
      Array.prototype.slice.call(filterMenu.querySelectorAll('.filter-option')).forEach(function(opt){
        var s = opt.getAttribute('data-status') || '';
        statusLabelMap[s] = opt.textContent.trim();
      });

      // On load, reflect the server-resolved filter (URL param OR saved
      // user preference — see window.INITIAL_STATUS_FILTER) in the button label
      (function reflectSelected(){
        try{
          // Prefer the server-resolved filter (saved preference or URL param at server-time).
          var curr = (window.INITIAL_STATUS_FILTER || '').trim();
          // If server didn't provide a resolved filter, fall back to the live URL param.
          if (!curr) {
            try { curr = new URLSearchParams(window.location.search).get('status') || ''; } catch(e) { curr = ''; }
          }
          curr = (curr || '').trim();
          if (statusLabelMap.hasOwnProperty(curr) && curr !== ''){
            filterBtn.textContent = statusLabelMap[curr] + ' ▾';
          } else if (statusLabelMap.hasOwnProperty('')){
            filterBtn.textContent = statusLabelMap[''] + ' ▾';
          }
        }catch(err){ /* ignore */ }
      })();

      var isOpen = false;

      function openMenu(){
        // ensure the menu is a child of body so it's not clipped by ancestor stacking contexts
        if (filterMenu.parentElement !== document.body) document.body.appendChild(filterMenu);
        filterMenu.style.position = 'absolute';
        filterMenu.style.zIndex = 11000;
        // ensure we don't keep right set from original CSS when moved to body
        filterMenu.style.right = 'auto';
        filterMenu.style.width = 'auto';
        filterMenu.style.minWidth = filterMenu.style.minWidth || '180px';
        filterMenu.style.display = 'block';
        filterMenu.setAttribute('aria-hidden','false');
        // hide while we compute size to avoid flicker
        filterMenu.style.visibility = 'hidden';
        // compute anchored position: align right edge of menu with button's right edge
        var rect = filterBtn.getBoundingClientRect();
        // allow layout to compute width (force reflow by reading offsetWidth)
        var mw = filterMenu.offsetWidth || 220;
        if (!mw || mw < 1) {
          // try getBoundingClientRect as a fallback
          mw = Math.max(Math.round(filterMenu.getBoundingClientRect().width || 0), 180);
        }
        var left = rect.right + window.pageXOffset - mw;
        if (left < 8) left = rect.left + window.pageXOffset;
        var top = rect.bottom + window.pageYOffset + 8;
        filterMenu.style.left = left + 'px';
        filterMenu.style.top = top + 'px';
        filterMenu.style.visibility = 'visible';
        isOpen = true;
      }

      function closeMenu(){
        filterMenu.style.display = 'none';
        filterMenu.setAttribute('aria-hidden','true');
        isOpen = false;
      }

      // Toggle menu
      filterBtn.addEventListener('click', function(e){
        e.stopPropagation();
        if (isOpen) closeMenu(); else openMenu();
      });

      // option clicks - persist preference server-side, then navigate
      Array.prototype.slice.call(filterMenu.querySelectorAll('.filter-option')).forEach(function(opt){
        opt.addEventListener('click', function(){
          var status = this.getAttribute('data-status') || '';
          var label = this.textContent.trim() || 'Filter';
          // set button label immediately (page will navigate)
          filterBtn.textContent = label + ' ▾';

          fetch('../../api/save_checklist_filter.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'status=' + encodeURIComponent(status)
          }).catch(function(err){
            console.warn('Failed to save filter preference', err);
          }).finally(function(){
            // Navigate with status query regardless of save success/failure
            var url = window.location.pathname + (status ? ('?status='+encodeURIComponent(status)) : '');
            window.location.href = url;
          });
        });
      });

      // close on outside click
      document.addEventListener('click', function(e){ if (!filterMenu.contains(e.target) && !filterBtn.contains(e.target)) closeMenu(); });
      // close on escape
      document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeMenu(); });

      // reposition on scroll/resize so it stays anchored
      function reposition(){
        if (!isOpen) return;
        var rect = filterBtn.getBoundingClientRect();
        // ensure right is not applied (we position via left)
        filterMenu.style.right = 'auto';
        filterMenu.style.width = 'auto';
        var mw = filterMenu.offsetWidth || 220;
        if (!mw || mw < 1) mw = Math.max(Math.round(filterMenu.getBoundingClientRect().width || 0), 180);
        var left = rect.right + window.pageXOffset - mw;
        if (left < 8) left = rect.left + window.pageXOffset;
        filterMenu.style.left = left + 'px';
        filterMenu.style.top = (rect.bottom + window.pageYOffset + 8) + 'px';
      }
      window.addEventListener('resize', reposition);
      window.addEventListener('scroll', reposition, true);
    })();
  </script>
  <script>
    // Inline editing for project table (all cells except Project Name)
    (function(){
  var table = document.querySelector('.project-table');
  var cancelBtn = document.getElementById('cancelChangesBtn');
  var editBtn = document.getElementById('toggleEditBtn');
  if (!table || !cancelBtn || !editBtn) return;
      // Track unsaved navigation intent
  // (Global UnsavedGuard handles navigation intercept; this page only supplies save logic)

      // Track pending changes in a map: key = projectId + '|' + column
      var pending = {};
      var editingEnabled = false;
      var refreshTimer = null; // debounce timer for summary refresh during typing

        // Reusable exitEditMode function per requirements
        function exitEditMode() {
          editingEnabled = false;
          pending = {};
          table.querySelectorAll('td.editable').forEach(td => {
            td.contentEditable = 'false';
            td.classList.remove('dirty', 'save-error');
          });
          editBtn.textContent = 'Edit';
          editBtn.title = 'Enable editing';
          cancelBtn.disabled = true;
          window.hasUnsavedChanges = false;
          window.onbeforeunload = null;
          if (window.UnsavedGuard) {
            UnsavedGuard.markClean();
            UnsavedGuard.syncSnapshot();
          }
          updateControlsVisibility();
        }

      function refreshSummaryIfNeeded(targetCell){
        if (!window.ProjectSummary) return;
        var selected = table.querySelector('tbody tr.is-selected');
        if (!selected) return;
        if (targetCell && !selected.contains(targetCell)) return; // only refresh when editing selected row
        try { window.ProjectSummary.refreshForRow(selected); } catch(_){}
      }

      // Initialize original values for each editable cell
      table.querySelectorAll('td.editable').forEach(function(td){
        td.dataset.original = td.textContent.trim();
        // ensure not editable by default
        td.contentEditable = 'false';
        // ensure empty cells have the empty-cell class set correctly on client side too
        if ((td.textContent || '').trim() === '') td.classList.add('empty-cell');
      });

      // When editing, toggle empty-cell on input so yellow disappears as user types
      table.addEventListener('input', function(e){
        var td = e.target;
        if (!td || !td.classList) return;
        if (!td.classList.contains('editable')) return;
        if ((td.textContent || '').trim() === '') td.classList.add('empty-cell'); else td.classList.remove('empty-cell');
        // Debounced live summary refresh while typing in the selected row
        if (refreshTimer) clearTimeout(refreshTimer);
        refreshTimer = setTimeout(function(){ refreshSummaryIfNeeded(td); }, 150);
      });

      function setControlsEnabled(enabled){
        // When editing is enabled, the Edit button becomes the Save action and should
        // be enabled/disabled based on whether there are pending edits. When editing
        // is disabled, the Edit button should remain enabled so the user can enter edit mode.
        if (editingEnabled) {
          // While editing is active, the primary button acts as Save — keep it enabled so
          // the user can either save pending edits or click to lock editing when no edits.
          editBtn.disabled = false;
          // Cancel is enabled only when there are pending changes
          cancelBtn.disabled = !enabled;
          if (!cancelBtn.disabled){
            cancelBtn.style.opacity = '1'; cancelBtn.style.cursor = 'pointer';
          } else {
            cancelBtn.style.opacity = '0.6'; cancelBtn.style.cursor = 'default';
          }
        } else {
          // not editing: Edit button stays active so users can toggle edit mode; cancel is inactive
          editBtn.disabled = false;
          editBtn.style.opacity = '1'; editBtn.style.cursor = 'pointer';
          cancelBtn.disabled = true;
          cancelBtn.style.opacity = '0.6'; cancelBtn.style.cursor = 'default';
        }
      }

      function updateControlsVisibility(){
        setControlsEnabled(Object.keys(pending).length > 0);
      }

      // Toggle edit mode: enabling will allow inline edits; disabling will revert pending edits
      function setEditing(enabled){
        editingEnabled = !!enabled;
        table.querySelectorAll('td.editable').forEach(function(td){
          td.contentEditable = editingEnabled ? 'true' : 'false';
          if (editingEnabled) {
            td.dataset.original = td.textContent.trim();
            if (window.UnsavedGuard) { window.UnsavedGuard.registerElement(td); }
          } else {
            var key = td.closest('tr') && td.closest('tr').dataset.projectId ? (td.closest('tr').dataset.projectId + '|' + (td.dataset.col || '')) : null;
            if (key && pending[key]) {
              pending[key].td.textContent = pending[key].td.dataset.original || '';
              pending[key].td.classList.remove('dirty');
              delete pending[key];
            }
          }
        });
        if (!editingEnabled) {
          pending = {};
          updateControlsVisibility();
        }
        editBtn.textContent = editingEnabled ? 'Save' : 'Edit';
        editBtn.title = editingEnabled ? 'Save changes (or click to save and lock)' : 'Enable editing';
        updateControlsVisibility();
      }

      // Enter commits (blur) to avoid inserting newlines (only relevant when editing enabled)
      // Arrow keys navigate between cells
      table.addEventListener('keydown', function(e){
        var td = e.target;
        if (!td || !td.classList) return;
        if (!td.classList.contains('editable')) return;
        
        // Enter commits the cell
        if (e.key === 'Enter' && editingEnabled){
          e.preventDefault();
          td.blur();
          return;
        }
        
        // Arrow key navigation
        if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].indexOf(e.key) !== -1) {
          e.preventDefault();
          
          var tr = td.closest('tr');
          if (!tr) return;
          
          var cells = Array.from(tr.querySelectorAll('td.editable'));
          var currentIndex = cells.indexOf(td);
          
          var targetCell = null;
          
          if (e.key === 'ArrowRight') {
            // Move to next cell in same row
            if (currentIndex < cells.length - 1) {
              targetCell = cells[currentIndex + 1];
            }
          } else if (e.key === 'ArrowLeft') {
            // Move to previous cell in same row
            if (currentIndex > 0) {
              targetCell = cells[currentIndex - 1];
            }
          } else if (e.key === 'ArrowUp') {
            // Move to same column in previous row
            var prevRow = tr.previousElementSibling;
            if (prevRow) {
              var prevCells = Array.from(prevRow.querySelectorAll('td.editable'));
              if (prevCells[currentIndex]) {
                targetCell = prevCells[currentIndex];
              }
            }
          } else if (e.key === 'ArrowDown') {
            // Move to same column in next row
            var nextRow = tr.nextElementSibling;
            if (nextRow) {
              var nextCells = Array.from(nextRow.querySelectorAll('td.editable'));
              if (nextCells[currentIndex]) {
                targetCell = nextCells[currentIndex];
              }
            }
          }
          
          if (targetCell) {
            targetCell.focus();
            // Select all text in the cell for easy editing
            if (window.getSelection && document.createRange) {
              var range = document.createRange();
              range.selectNodeContents(targetCell);
              var sel = window.getSelection();
              sel.removeAllRanges();
              sel.addRange(range);
            }
          }
        }
      });

      // On focusout, record change if different from original (do not auto-save)
      table.addEventListener('focusout', function(e){
        var td = e.target;
        if (!td || !td.classList) return;
        if (!td.classList.contains('editable')) return;
        if (!editingEnabled) return; // ignore focus changes when editing is locked
        var tr = td.closest('tr');
        if (!tr || !tr.dataset.projectId) return; // placeholder rows have no project id
        var projectId = tr.dataset.projectId;
        var column = td.dataset.col;
        var value = td.textContent.trim();
        var original = (typeof td.dataset.original !== 'undefined') ? td.dataset.original : '';
        var key = projectId + '|' + column;
        if (value === original) {
          // If previously pending, remove it
          if (pending[key]) {
            delete pending[key];
            td.classList.remove('dirty');
          }
        } else {
          // record pending change
          pending[key] = { project_id: projectId, column: column, value: value, td: td };
          td.classList.add('dirty');
        }
        updateControlsVisibility();
        // Immediate refresh on blur (ensures counts stable even if user leaves cell quickly)
        refreshSummaryIfNeeded(td);
      });

      // Cancel: revert all pending changes
      cancelBtn.addEventListener('click', function(e){
        e.preventDefault();
        Object.keys(pending).forEach(function(k){
          var item = pending[k];
          if (item && item.td) {
            item.td.textContent = item.td.dataset.original || '';
            item.td.classList.remove('dirty');
            // restore empty-cell class based on original
            if ((item.td.dataset.original || '').trim() === '') item.td.classList.add('empty-cell'); else item.td.classList.remove('empty-cell');
          }
        });
        exitEditMode();
      });

      // Toggle edit / Save behavior on the primary toggle button.
editBtn.addEventListener('click', function(e){
  e.preventDefault();
  if (!editingEnabled) {
    setEditing(true);
    return;
  }
  var keys = Object.keys(pending);
  if (keys.length === 0) {
    exitEditMode();
    return;
  }
  // Mark page as clean BEFORE starting fetch
  if (window.UnsavedGuard && typeof window.UnsavedGuard.markClean === 'function') {
    window.UnsavedGuard.markClean();
  }
  window.hasUnsavedChanges = false;
  window.onbeforeunload = null;
  editBtn.disabled = true; cancelBtn.disabled = true;
  var payload = { changes: keys.map(function(k){
    var it = pending[k];
    return { project_id: it.project_id, column: it.column, value: it.value };
  }) };
  fetch('../../api/update_project_cells.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify(payload)
  }).then(function(r){ return r.json(); })
  .then(function(json){
    if (json && json.success) {
      // update originals
      keys.forEach(function(k){
        var it = pending[k];
        if (!it || !it.td) return;
        it.td.dataset.original = it.value;
        it.td.classList.remove('dirty', 'save-error');
      });
      // THIS is the critical line
      exitEditMode();
      // optional toast
      if (window.showStatusToast) {
        showStatusToast('Changes saved', 'success');
      }
    } else {
      throw new Error(json.message || 'Save failed');
    }
  }).catch(function(err){
    console.error('Save error', err);
    // Show error state on all pending cells
    keys.forEach(function(k){
      var it = pending[k];
      if (it && it.td) {
        it.td.classList.add('save-error');
      }
    });
    // Re-enable buttons
    editBtn.disabled = false;
    cancelBtn.disabled = false;
    var msg = 'Failed to save changes';
    if (err && err.message) msg += ': ' + err.message;
    alert(msg);
  });
});
    })();
  </script>
  <script>
/**
 * SSE: Real-time project checklist updates
 * Other users see changes instantly without refresh
 */
(function () {
  var table = document.querySelector('.project-table');
  if (!table) return;

  // Track cells currently being edited locally
  var editingCells = new Set();
  table.addEventListener('focusin', function (e) {
    var td = e.target.closest('td.editable');
    if (td) editingCells.add(td);
  });
  table.addEventListener('focusout', function (e) {
    var td = e.target.closest('td.editable');
    if (td) editingCells.delete(td);
  });

  // SSE logic: single EventSource for all projects
  var since = Math.floor(Date.now() / 1000) - 60; // start with last 60s
  var es;
  function getSelectedProjectId() {
    var selected = table.querySelector('tbody tr.is-selected');
    if (selected && selected.dataset.projectId) return selected.dataset.projectId;
    // fallback: first row
    var first = table.querySelector('tbody tr[data-project-id]');
    return first ? first.dataset.projectId : '';
  }
  function connectSSE() {
    var projectId = getSelectedProjectId();
    if (!projectId) return;
    var url = window.APP_BASE + '/pages/project_checklist/events.php?project_id=' + encodeURIComponent(projectId) + '&since=' + encodeURIComponent(since);
    if (es) es.close();
    es = new EventSource(url);

    es.addEventListener('projectUpdate', function (ev) {
      try {
        var payload = JSON.parse(ev.data || '{}');
        if (!payload.project_id || !payload.row) return;
        var row = table.querySelector('tr[data-project-id="' + payload.project_id + '"]');
        if (!row) return;
        var rowData = payload.row;
        // Update only cells not being edited
        row.querySelectorAll('td[data-col]').forEach(function (td) {
          if (editingCells.has(td)) return;
          var col = td.dataset.col;
          if (typeof rowData[col] === 'undefined') return;
          var newVal = String(rowData[col] ?? '');
          if (td.textContent.trim() !== newVal) {
            td.textContent = newVal;
            td.dataset.original = newVal;
            td.classList.add('sse-updated');
            setTimeout(function () {
              td.classList.remove('sse-updated');
            }, 1200);
          }
        });
        // Update since timestamp for next poll
        if (payload.updated_at && payload.updated_at > since) {
          since = payload.updated_at;
        }
      } catch (e) {
        console.warn('[SSE] parse error', e);
      }
    });

    es.addEventListener('heartbeat', function () {
      // no-op, just to keep connection alive
    });

    // On network/connection error, silently reconnect after a short delay.
    // The backend endpoint is intentionally short-lived, so frequent
    // reconnects are expected and shouldn't spam the console.
    es.onerror = function () {
      es.close();
      setTimeout(connectSSE, 3000);
    };
  }
  connectSSE();

  // highlight effect
  var style = document.createElement('style');
  style.textContent =
    '.sse-updated{background:#fff8b3!important;transition:background 1.2s;}';
  document.head.appendChild(style);
})();
</script>
<script>
          // Sync top and bottom horizontal scrollbars
          (function(){
            var topScroll = document.getElementById('topScrollbar');
            var tableContainer = document.querySelector('.table-container');
            var table = document.querySelector('.project-table');
            if (!topScroll || !tableContainer || !table) return;

            var topScrollInner = topScroll.querySelector('div');

            function updateTopScrollWidth(){
              // Set the inner div width to match the table width so scrollbar appears
              topScrollInner.style.width = table.scrollWidth + 'px';
            }

            // Two-way sync between top and bottom scrollbars
            topScroll.addEventListener('scroll', function(){ 
              tableContainer.scrollLeft = topScroll.scrollLeft; 
            });
            tableContainer.addEventListener('scroll', function(){ 
              topScroll.scrollLeft = tableContainer.scrollLeft; 
            });

            // Update on load and resize
            window.addEventListener('resize', updateTopScrollWidth);
            updateTopScrollWidth();

            // Use ResizeObserver if available to detect table width changes
            if (window.ResizeObserver) {
              var ro = new ResizeObserver(updateTopScrollWidth);
              ro.observe(table);
            }
            // Populate and sync assignments row
            var assignmentsWrapper = document.getElementById('assignmentsRowWrapper');
            var assignmentsInner = document.getElementById('assignmentsRowInner');
            function updateAssignmentsWidth(){
              if (!assignmentsInner) return; assignmentsInner.style.width = table.scrollWidth + 'px';
            }
            // two-way sync
            if (assignmentsWrapper) {
              assignmentsWrapper.addEventListener('scroll', function(){ tableContainer.scrollLeft = assignmentsWrapper.scrollLeft; topScroll.scrollLeft = assignmentsWrapper.scrollLeft; });
              tableContainer.addEventListener('scroll', function(){ if(assignmentsWrapper) assignmentsWrapper.scrollLeft = tableContainer.scrollLeft; });
            }
            window.addEventListener('resize', updateAssignmentsWidth);
            updateAssignmentsWidth();
            if (window.ResizeObserver) {
              var ro2 = new ResizeObserver(updateAssignmentsWidth);
              ro2.observe(table);
            }

            async function renderAssignments(){
              try {
                var ths = Array.from(document.querySelectorAll('.project-table thead th')) || [];
                if (!assignmentsInner) return;
                // measure header widths - get actual rendered widths INCLUDING borders and padding
                var widths = ths.map(function(t){ 
                  var r = t.getBoundingClientRect(); 
                  return Math.max(48, Math.round(r.width)); 
                });
                var fields = ths.map(function(t){ return (t.textContent || '').toString().trim(); }).filter(function(x){ return x; });

                // fetch assignments and optional user map
                var assignments = {};
                var usersMap = {};
                try {
                  var aresp = await fetch('../../api/get_field_assignments.php', { credentials: 'same-origin' });
                  var aj = await aresp.json();
                  if (aj && aj.success) { assignments = aj.assignments || {}; if (aj.user_map) usersMap = aj.user_map; }
                } catch(e){ console.warn('assign fetch failed', e); }

                // Build flex row with fixed px widths matching headers EXACTLY
                var container = document.createElement('div'); 
                container.style.display = 'flex'; 
                container.style.width = '100%'; 
                container.style.boxSizing = 'border-box';
                container.style.alignItems = 'stretch';
                
                var total = 0;
                for (var i = 0; i < widths.length; i++) {
                  var w = widths[i]; 
                  total += w;
                  var cell = document.createElement('div');
                  
                  // CRITICAL: Use exact same width as header (no flex-shrink, no flex-grow)
                  cell.style.flex = '0 0 ' + w + 'px';
                  cell.style.minWidth = w + 'px';
                  cell.style.maxWidth = w + 'px';
                  cell.style.width = w + 'px';
                  
                  // Match header padding exactly
                  cell.style.padding = '6px 8px';
                  cell.style.boxSizing = 'border-box';
                  
                  // Match header borders
                  cell.style.borderRight = '1px solid rgba(15,23,42,0.03)';
                  
                  cell.style.background = '#f8fafc';
                  cell.style.color = '#0f172a';
                  cell.style.fontSize = '13px';
                  cell.style.display = 'flex';
                  cell.style.alignItems = 'center';
                  cell.style.justifyContent = 'center';
                  cell.style.overflow = 'hidden';
                  cell.style.textOverflow = 'ellipsis';
                  cell.style.whiteSpace = 'nowrap';
                  
                  var f = (ths[i] && ths[i].textContent) ? ths[i].textContent.trim() : '';
                  var key = (f||'').toString().trim().toLowerCase();
                  var uid = (assignments && assignments[key]) ? assignments[key] : null;
                  var text = '--';
                  
                  // Hide first two assignment cells visually (leave blank) while preserving alignment
                  if (i === 0 || i === 1) {
                    text = '';
                  } else if (uid) {
                    var full = (usersMap && usersMap[uid]) ? usersMap[uid] : ('User #' + uid);
                    // extract first name
                    var first = (full || '').toString().trim().split(/\s+/)[0] || full;
                    text = first;
                  }
                  cell.textContent = text;
                  container.appendChild(cell);
                }
                
                assignmentsInner.innerHTML = '';
                
                // NO padding-left offset - we want exact 1:1 alignment with table headers
                // The container width must exactly match the table scroll width
                assignmentsInner.style.width = (table.scrollWidth || total) + 'px';
                assignmentsInner.style.paddingLeft = '0';
                
                assignmentsInner.appendChild(container);
                updateAssignmentsWidth();
              } catch(e) { console.warn('renderAssignments error', e); }
            }
            renderAssignments();
          })();
          </script>
</body>
</html>