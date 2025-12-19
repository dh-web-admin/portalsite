<?php
require_once __DIR__ . '/../../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

// Include database configuration
require_once __DIR__ . '/../../config/config.php';

// Get user role for sidebar
$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$actualRole = $user ? $user['role'] : 'laborer';

// Check if developer is previewing as another role
if ($actualRole === 'developer' && isset($_GET['preview_role'])) {
    $role = $_GET['preview_role'];
} else {
    $role = $actualRole;
}

$stmt->close();

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
              <button id="addProjectBtn" class="btn btn-primary">New Project</button>
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
              <button id="toggleEditBtn" class="btn btn-success" title="Enable editing" style="margin-left:8px;opacity:1">Edit</button>
              <button id="cancelChangesBtn" class="btn btn-secondary" disabled style="margin-left:6px;opacity:0.6">Cancel</button>
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
          </div>

          <!-- Table area placed below the toolbar -->
          <div class="table-area" style="width:100%;margin:0;padding:0;">
            <div class="table-wrap" style="width:100%;padding:8px 0;">
              <!-- Inline styles removed; consolidated into external project-checklist.css -->

              <!-- Top horizontal scrollbar synced with table -->
              <div id="topScrollbar"><div></div></div>

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
                      <th>AIA</th>
                      <th>Process Field Paperwork</th>
                      <th>Review Processed Paperwork</th>
                      <th>Invoice</th>
                      <th>Sign Change Order</th>
                      <th>Send Signed Change Order</th>
                      <th>Send Supplier Lein Waiver</th>
                      <th>Supplier Lein Waiver</th>
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
                    $columns = array_merge($columns, array('City','County','State','Coordinates','Client','Anticipated_Start_Date','State_License','City_License','Get_Contract','Review_and_Sign_Contract','Get_Tax_Exempt_Form','Complete_Vendor_Form','Send_W9','Send_BWC','Updated_BWC','Request_Certificate_of_INS','Send_Certificate_of_INS','Send_to_Lawyer','Request_NOC','Send_NOF','File_NOC_NOF','Get_Signed_Quote','Complete_Win_Packet','Create_Foreman_Field_Folder','Add_to_Project_Calendar','Soil_Testing','Soil_Sampling','Lab','Mix_Design_Sent','Results','Mix_Design_Approval','Call_OUPS','Schedule_Mobilization','Schedule_Field_Testing','Get_Field_Testing_Results','Send_Submittals','Schedule_Fuel','Fuel_Supplier','Selected_Material_Supplier','Schedule_Material','Selected_Trucking_Company','Schedule_Trucker','Hotel','Find_Water','Water_Semi','Schedule_Men','Grade_File','Cure_Type','Schedule_Cure','Cure_Provider','Turn_in_Paperwork','AIA','Process_Field_Paperwork','Review_Processed_Paperwork','Invoice','Sign_Change_Order','Send_Signed_Change_Order','Send_Supplier_Lein_Waiver','Supplier_Lein_Waiver','DHSS_Lein_Waiver'));

                    // Server-side filtering by status (if provided and valid AND table has column)
                    $allowed_statuses = ['Ongoing','Completed','Cancelled'];
                    $status_filter = '';
                    if (!empty($has_status) && isset($_GET['status']) && $_GET['status'] !== '') {
                      $candidate = trim($_GET['status']);
                      if (in_array($candidate, $allowed_statuses, true)) {
                        $status_filter = $candidate;
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
                          $actions = "<span class=\"project-actions\" style=\"margin-left:auto\">" .
                                     "<button class=\"icon-btn clone-btn\" data-project-id=\"{$pid}\" title=\"Clone project\">" .
                                    "<svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M16 1H4a2 2 0 0 0-2 2v12\" stroke=\"currentColor\" stroke-width=\"1.6\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/><rect x=\"8\" y=\"7\" width=\"13\" height=\"13\" rx=\"2\" stroke=\"currentColor\" stroke-width=\"1.6\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/></svg>" .
                                     "</button>" .
                                     "<button class=\"icon-btn edit-btn\" data-project-id=\"{$pid}\" title=\"Edit project\">" .
                                    "<svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M3 21l3-1 11-11 1-3-3 1L4 20z\" stroke=\"currentColor\" stroke-width=\"1.6\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/></svg>" .
                                     "</button>" .
                                     "" .
                                     "</span>";
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
  </div>
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
              // show toast if available, then reload shortly so user sees confirmation
              try { if (window && typeof window.showStatusToast === 'function') window.showStatusToast('Project cloned', 'success'); } catch(e){}
              setTimeout(function(){ window.location.reload(); }, 900);
            } else {
              alert((json && json.message) ? json.message : 'Failed to clone project');
            }
          }).catch(function(err){ console.error(err); alert('Failed to clone project'); });
      });

      // NOTE: rename is handled by the edit-menu (see separate script)

      // delete action moved into the edit-menu; click handled there
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

      // Create action
      if (createBtn) createBtn.addEventListener('click', function(e){
        e.preventDefault();
        var name = nameInput ? nameInput.value.trim() : '';
        if (!name) { alert('Please enter a project name'); if(nameInput) nameInput.focus(); return; }

        createBtn.disabled = true;
        createBtn.textContent = 'Creating...';

        var fd = new FormData();
        fd.append('project_name', name);

        fetch('../../api/create_project.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(resp){ return resp.json(); })
          .then(function(json){
            if (json && json.success) {
              // show toast (if helper available) then reload so new row appears
              try { if (window && typeof window.showStatusToast === 'function') window.showStatusToast('Project created', 'success'); } catch(e){}
              // Clear any unsaved-change state before reloading to avoid false leave-confirm prompts
              try { if (window.UnsavedGuard) window.UnsavedGuard.markClean(); } catch(_){ }
              setTimeout(function(){ window.location.reload(); }, 900);
            } else {
              alert((json && json.message) ? json.message : 'Failed to create project');
            }
          })
          .catch(function(err){
            console.error('Create project error', err);
            alert('Failed to create project');
          })
          .finally(function(){ createBtn.disabled = false; createBtn.textContent = 'Create'; });
      });

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

      // On load, reflect current status query param in the button label
      (function reflectSelected(){
        try{
          var params = new URLSearchParams(window.location.search);
          var curr = params.get('status') || '';
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

      // option clicks - set label then navigate
      Array.prototype.slice.call(filterMenu.querySelectorAll('.filter-option')).forEach(function(opt){
        opt.addEventListener('click', function(){
          var status = this.getAttribute('data-status') || '';
          var label = this.textContent.trim() || 'Filter';
          // set button label immediately (page will navigate)
          filterBtn.textContent = label + ' ▾';
          // Navigate with status query
          var url = window.location.pathname + (status ? ('?status='+encodeURIComponent(status)) : '');
          window.location.href = url;
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
    alert('Failed to save changes');
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
    console.log('[SSE] Connecting:', url);

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

    es.onerror = function () {
      console.warn('[SSE] connection lost, retrying…');
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
</body>
</html>
