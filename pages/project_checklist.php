<?php
require_once __DIR__ . '/../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Include database configuration
require_once __DIR__ . '/../config/config.php';

// Get user role for sidebar
$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
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
  <link rel="stylesheet" href="../assets/css/base.css" />
  <link rel="stylesheet" href="../assets/css/admin-layout.css" />
  <link rel="stylesheet" href="../assets/css/dashboard.css" />
  <link rel="stylesheet" href="../assets/css/project-checklist.css" />
</head>
<body class="admin-page">
  <div class="admin-container">
    <?php include __DIR__ . '/../partials/portalheader.php'; ?>
    <div class="admin-layout">
      <?php include __DIR__ . '/../partials/sidebar.php'; ?>
      <main class="content-area">
        <div class="main-content">
          <div class="toolbar" style="display:flex;align-items:center;margin-bottom:16px;gap:12px;">
            <div class="toolbar-left" style="flex:0 0 auto;">
              <button id="addProjectBtn" class="btn btn-primary">New Project</button>
            </div>
            <div class="toolbar-center">
              <div class="filter-dropdown">
                <button id="filterBtn" class="filter-btn">Filter ▾</button>
                <div id="filterMenu" class="filter-menu" aria-hidden="true">
                  <div class="filter-option" data-status="">All Projects</div>
                  <div class="filter-option" data-status="Completed">Completed Projects</div>
                  <div class="filter-option" data-status="Cancelled">Cancelled Projects</div>
                  <div class="filter-option" data-status="Ongoing">On going Projects</div>
                </div>
              </div>
            </div>
            <div class="toolbar-right" style="flex:0 0 auto;">
              <!-- reserved for other controls -->
            </div>
          </div>

          <!-- Table area placed below the toolbar -->
          <div class="table-area" style="width:100%;padding:0 16px;">
            <div class="table-wrap" style="width:100%;overflow-x:auto;overflow-y:visible;-webkit-overflow-scrolling:touch;padding:8px 0;">
              <style>
                .table-wrap::-webkit-scrollbar{height:12px}
                .table-wrap::-webkit-scrollbar-track{background:transparent}
                .table-wrap::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:8px}
                /* Proxy scrollbar styling (larger, always-visible) */
                #tableScrollProxy { background: transparent; }
                #tableScrollProxy::-webkit-scrollbar{height:18px}
                #tableScrollProxy::-webkit-scrollbar-track{background:transparent}
                #tableScrollProxy::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:10px}
                #tableScrollProxy{ scrollbar-width: auto; scrollbar-color: #cbd5e1 transparent; }
                /* Make table size based on content so horizontal scroll appears when needed */
                .project-table{border-collapse:separate;border-spacing:0 8px;width: -moz-max-content; width: max-content; min-width:2200px;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial}
                .project-table thead th{position:sticky;top:0;background:#fff;padding:12px 14px;text-align:left;font-weight:600;border-bottom:1px solid #e6eef8;color:#0f172a;white-space:nowrap;font-size:13px}
                .project-table tbody td{padding:12px 14px;background:#fff;border-bottom:1px solid #f1f5f9;color:#334155;white-space:nowrap;font-size:13px}
                /* Minimalistic vertical dividers between columns */
                .project-table thead th:not(:last-child),
                .project-table tbody td:not(:last-child) {
                  border-right: 1px solid #eef2f7; /* subtle vertical line */
                }
                /* Align content vertically for nicer divider appearance */
                .project-table thead th,
                .project-table tbody td {
                  vertical-align: middle;
                }
                /* Slight row shadow to emphasize separation when using border-spacing */
                .project-table tbody tr {
                  box-shadow: 0 1px 0 rgba(2,6,23,0.02);
                }

                /* Sticky first column (Project Name) - anchored while other columns scroll */
                .project-table thead th:first-child,
                .project-table tbody td:first-child {
                  position: -webkit-sticky;
                  position: sticky;
                  left: 0;
                  background: #ffffff; /* ensure opaque so underlying cells don't show through */
                  z-index: 30;
                  border-right: 1px solid #e6eef8;
                }
                /* Slight shadow on the sticky column to indicate separation */
                .project-table tbody td:first-child {
                  box-shadow: 2px 0 6px rgba(15,23,42,0.04);
                }
                /* Header sticky cell above body sticky cells */
                .project-table thead th:first-child { z-index: 40; }

           /* Ensure the scroll area shows a bottom scrollbar for horizontal nav.
             Use scroll to force a visible scrollbar on platforms that hide overlay scrollbars. */
           .table-wrap { overflow-x: scroll; overflow-y: visible; scrollbar-gutter: stable; padding-bottom:6px; }
           /* Fallback: if you prefer auto behavior, change overflow-x to auto above. */
           /* Allow the table-container to be visible so the horizontal scrollbar isn't clipped by overflow:hidden */
           .table-container{box-shadow:0 6px 18px rgba(15,23,42,0.06);border-radius:10px;overflow:visible;background:#f8fafc}
                .project-table tbody tr td:first-child{font-weight:600;color:#0b1220}
                .project-table tbody tr:hover td{background:#f1f5f9}
                @media (max-width:900px){
                  .project-table thead th,.project-table tbody td{padding:10px 8px;font-size:12px}
                }
              </style>

              <div class="table-container" role="region" aria-label="Project checklist table">
                <table class="project-table" role="table" aria-label="Projects checklist">
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
                      if ($projects_res && $projects_res->num_rows > 0) {
                        while ($p = $projects_res->fetch_assoc()) {
                          echo "<tr>\n";
                          // First column is Project Name (and styled)
                          $name = htmlspecialchars($p['Project_Name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                          echo "<td>{$name}</td>\n";
                          // Output remaining columns in the order defined
                          foreach (array_slice($columns,1) as $col) {
                            $val = htmlspecialchars($p[$col] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            echo "<td>{$val}</td>\n";
                          }
                          echo "</tr>\n";
                        }
                      } else {
                        echo '<tr><td colspan="'.count($columns).'" style="text-align:center;padding:40px 16px;color:#64748b">No projects to show</td></tr>';
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
  <script>
    (function(){
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

        fetch('../api/create_project.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(resp){ return resp.json(); })
          .then(function(json){
            if (json && json.success) {
              // reload to reflect the new row (keeps logic simple and consistent)
              window.location.reload();
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
    // Filter dropdown behavior
    (function(){
      var filterBtn = document.getElementById('filterBtn');
      var filterMenu = document.getElementById('filterMenu');
      if(!filterBtn || !filterMenu) return;
      function closeMenu(){ filterMenu.style.display='none'; filterMenu.setAttribute('aria-hidden','true'); }
      function openMenu(){ filterMenu.style.display='block'; filterMenu.setAttribute('aria-hidden','false'); }
      filterBtn.addEventListener('click', function(e){
        e.stopPropagation();
        if(filterMenu.style.display === 'block') closeMenu(); else openMenu();
      });
      // option clicks
      Array.prototype.slice.call(filterMenu.querySelectorAll('.filter-option')).forEach(function(opt){
        opt.addEventListener('click', function(){
          var status = this.getAttribute('data-status') || '';
          // Navigate with status query
          var url = window.location.pathname + (status ? ('?status='+encodeURIComponent(status)) : '');
          window.location.href = url;
        });
      });
      // close on outside click
      document.addEventListener('click', function(){ closeMenu(); });
      // close on escape
      document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeMenu(); });
    })();
  </script>
  <script>
    // Horizontal scrollbar proxy: syncs a visible, larger scrollbar with the table wrapper
    (function(){
      var tableWrap = document.querySelector('.table-wrap');
      var proxy = document.getElementById('tableScrollProxy');
      var proxyInner = document.getElementById('tableScrollProxyInner');
      if (!tableWrap || !proxy || !proxyInner) return;

      function updateProxyWidth(){
        var table = tableWrap.querySelector('.project-table');
        if (!table) return;
        proxyInner.style.width = table.scrollWidth + 'px';
      }

      // Two-way sync
      proxy.addEventListener('scroll', function(){ tableWrap.scrollLeft = proxy.scrollLeft; });
      tableWrap.addEventListener('scroll', function(){ proxy.scrollLeft = tableWrap.scrollLeft; });

      // Update on load and resize
      window.addEventListener('resize', updateProxyWidth);

      // Use ResizeObserver if available to detect table width changes
      var tbl = tableWrap.querySelector('.project-table');
      if (window.ResizeObserver && tbl) {
        var ro = new ResizeObserver(function(){ updateProxyWidth(); });
        ro.observe(tbl);
      } else if (tbl) {
        // Fallback mutation observer
        var mo = new MutationObserver(function(){ updateProxyWidth(); });
        mo.observe(tbl, { attributes:true, childList:true, subtree:true });
      }

      // Initial sync after a short delay to allow layout
      setTimeout(function(){ updateProxyWidth(); proxy.scrollLeft = tableWrap.scrollLeft; }, 50);
    })();
  </script>
  <script src="../assets/js/mobile-menu.js"></script>
</body>
</html>
