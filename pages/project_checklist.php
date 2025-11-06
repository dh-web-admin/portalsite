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
      <main class="content-area" style="padding-top:0;">
        <div class="main-content">
          <div class="toolbar" style="display:flex;align-items:center;margin-bottom:16px;gap:12px;position:sticky;top:0;z-index:100;background:#ffffff;padding:40px 16px 12px 16px;box-shadow:0 2px 8px rgba(2,6,23,0.04);">
            <div class="toolbar-left" style="flex:0 0 auto;">
              <button id="addProjectBtn" class="btn btn-primary">New Project</button>
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
          <div class="table-area" style="width:100%;padding:0 16px;">
            <div class="table-wrap" style="width:100%;padding:8px 0;">
              <style>
                /* Disable default browser scroll - all scrolling happens in the table container */
                html, body { overflow: hidden !important; height: 100vh; }
                .admin-container { height: 100vh; overflow: hidden; }
                .admin-layout { height: calc(100vh - var(--admin-header-h, 70px)); overflow: hidden; }
                .content-area { height: 100%; overflow: hidden; }
                
                /* The table-wrap is just a non-scrolling container now */
                .table-wrap { padding-bottom:6px; }
                /* Hide the proxy scrollbar if present (we rely on native scrollbar now) */
                #tableScrollProxy { display: none !important; }
                /* Make table size based on content so horizontal scroll appears when needed */
           /* Make cells visually distinct by giving each a subtle box (separate cells by spacing).
             Increase the vertical gap between rows so the per-cell border is visible. */
           .project-table{border-collapse:separate;border-spacing:8px 14px;width: -moz-max-content; width: max-content; min-width:2200px;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial;table-layout:auto}
           /* All columns have same min-width; will expand automatically if content is larger */
                /* Header: professional boxed style with stronger contrast 
                   This is the scrolling container - MUST have overflow for sticky to work
                   Height calculated to fit viewport: 100vh - header - toolbar - margins */
                .table-container { 
                  box-shadow: 0 10px 30px rgba(101, 119, 201, 0.08); 
                  border-radius:12px; 
                  border: 3px solid #64748b;
                  border-bottom: 8px solid #64748b;
                  overflow: auto;
                  max-width: 100%;
                  height: calc(100vh - var(--admin-header-h, 70px) - 120px);
                  margin-bottom: 50px;
                  padding-bottom: 50px;
                  position: relative;
                }
                /* Force scrollbars to always be visible */
                .table-container {
                  overflow-x: scroll !important;
                  overflow-y: scroll !important;
                }
                /* Hide the bottom horizontal scrollbar - we only use the top one */
                .table-container::-webkit-scrollbar-horizontal {
                  display: none;
                }
                /* Scrollbar styling for the table container (vertical only) */
                .table-container::-webkit-scrollbar{ height:0px; width:14px; }
                .table-container::-webkit-scrollbar-track{background:#f1f5f9; border-radius:8px;}
                .table-container::-webkit-scrollbar-thumb{background:#94a3b8; border-radius:8px;}
                .table-container::-webkit-scrollbar-thumb:hover{background:#64748b;}
                /* Use a smooth, professional header color for better aesthetics */
           /* Header appearance: ALL header cells sticky at top when scrolling vertically.
              Each TH gets a consistent solid background color. */
           .project-table thead th{
             position: sticky;
             top: 0;
             background:#475569;
             padding:18px 16px;
             text-align:center;
             font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial;
             font-weight:700;
             border:none;
             color:#ffffff;
             white-space:nowrap;
             font-size:12px;
             min-width:160px;
             width:160px;
             max-width:none;
             z-index:20;
           }
           /* Body cells: give each cell a distinct card-like border and rounded corners.
             Keep a white background so the centered dividing line remains visible. */
           .project-table tbody td{padding:12px 14px;background:#ffffff;border:1px solid rgba(2,6,23,0.06);color:#334155;white-space:nowrap;font-size:11px;min-width:160px;width:160px;max-width:none;text-align:center;border-radius:8px}
           /* Make the first column (Project Name) wider by default */
           .project-table thead th:first-child, .project-table tbody td:first-child { min-width: 220px; width: 220px; max-width: none }
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
                /* Slight row shadow removed in favor of per-cell borders (visual separation done via
                   border-spacing + per-cell border). Keep the TR lightweight. */
                .project-table tbody tr { }

                /* Sticky first column (Project Name) - stays fixed when scrolling horizontally.
                   Works for both header and body cells in the first column. */
                .project-table thead th:first-child,
                .project-table tbody td:first-child {
                  position: sticky !important;
                  left: 0 !important;
                  z-index: 30;
                  border-right: 1px solid #e6eef8;
                  text-align: left;
                  padding-left: 18px;
                  box-shadow: 2px 0 10px rgba(2,6,23,0.04);
                }
                
                /* First header cell needs highest z-index (it's both sticky top AND sticky left) */
                .project-table thead th:first-child { 
                  z-index: 50 !important;
                  background:#475569 !important;
                }
                /* Apply background only to project-name cells that actually have content */
                .project-table tbody td.project-name {
                  background: #3f78bd !important; /* slightly darker blue for better contrast */
                  color: #ffffff !important; /* strong readable text for project names */
                  font-weight: 700;
                  font-size: 11px;
                  position: sticky !important;
                  left: 0 !important;
                }
                
                /* Status-based color coding for project names */
                .project-table tbody td.project-name.status-ongoing {
                  background: #3f78bd !important; /* Blue - same as default */
                }
                .project-table tbody td.project-name.status-cancelled {
                  background: #8b4545 !important; /* Reddish-Brown */
                }
                .project-table tbody td.project-name.status-completed {
                  background: #2f9a55 !important; /* Green */
                }
                
                /* Ensure project title text remains visible and doesn't get overridden by
                   more general td rules. Use a focused selector and ellipsis for overflow. */
                .project-table td.project-name .project-title {
                  color: #ffffff !important;
                  display: inline-block;
                  max-width: calc(100% - 56px);
                  overflow: hidden;
                  text-overflow: ellipsis;
                  white-space: nowrap;
                }
                /* Empty placeholder cells in the first column should remain transparent */
                .project-table tbody td.empty-project { 
                  background: transparent !important; 
                  color: #64748b; 
                  font-weight: 400;
                  position: sticky !important;
                  left: 0 !important;
                }

           /* The table-container is the scrolling element - has overflow:scroll for both directions
              Height is fixed to viewport with 50px bottom margin and 50px bottom padding */
        .table-container{box-shadow:0 6px 18px rgba(15,23,42,0.06);border-radius:10px;overflow:auto;background:#f8fafc;position:relative;max-width:100%;height:calc(100vh - var(--admin-header-h, 70px) - 120px);margin-bottom:50px;padding-bottom:50px}

        /* Center dividing line: a subtle vertical rule centered inside the visible table container
          to visually split the table into two sections. It's pointer-events:none so it never
          interferes with table interactions. */
        .table-container::before{ content: ""; position:absolute; top:12px; bottom:12px; left:50%; width:1px; background: rgba(2,6,23,0.06); transform: translateX(-50%); pointer-events:none; z-index:20; border-radius:1px }

        /* Continuous header strip: removed in favor of per-cell blue backgrounds.
          Each TH now has its own background:#4b8ad6 so the header color stays
          consistent during horizontal scroll. The ::after overlay is no longer needed. */
        .table-container{ --header-height: 56px; }
        /* .table-container::after removed - no longer needed */
                /* Project Name styling: bold white text on a matching deep-blue tint for contrast */
                .project-table tbody tr td:first-child{font-weight:700;color:#ffffff}

                /* Align action icons to the right inside the project-name cell 
                   CRITICAL: Keep display as table-cell (default) for sticky to work! */
                .project-table td.project-name{ 
                  display: table-cell !important; /* MUST be table-cell for sticky to work */
                  position: sticky !important;
                  left: 0 !important;
                  background: #3f78bd !important;
                  vertical-align: middle;
                }
                /* Inner wrapper for flex layout without breaking sticky */
                .project-table td.project-name > div {
                  display: flex;
                  align-items: center;
                  gap: 8px;
                  padding-right: 12px;
                }
                .project-title{ flex: 1 1 auto; }
                .project-actions{ margin-left:auto; display:inline-flex; gap:6px; align-items:center }
                .project-actions .icon-btn{ background:transparent; border:0; padding:4px; display:inline-flex; align-items:center; justify-content:center; color: #fff; cursor: pointer }
                /* Delete (danger) icon removed — delete handled via the edit menu now */
                .project-actions .icon-btn svg{ display:block }
                .project-actions .icon-btn:focus{ outline:2px solid rgba(255,255,255,0.18); border-radius:6px }
                /* Filter dropdown styling - ensure it sits above the table and is fully visible */
                .filter-dropdown{ position: relative }
                .filter-menu{ position:absolute; right:0; top:calc(100% + 8px); z-index:11000; display:none; background:#ffffff; border-radius:8px; box-shadow:0 8px 30px rgba(2,6,23,0.12); border:1px solid rgba(2,6,23,0.06); min-width:180px; padding:6px 6px; }
                .filter-option{ padding:8px 10px; font-size:13px; color:#0f172a; cursor:pointer; border-radius:6px }
                .filter-option:hover{ background:#f1f5f9 }

                /* Inline edit menu for project actions (rename / delete / mark status) */
                .edit-menu{ position:absolute; z-index:11010; display:none; min-width:220px; background:#fff; border-radius:8px; box-shadow:0 10px 30px rgba(2,6,23,0.12); border:1px solid rgba(2,6,23,0.06); padding:8px; }
                .edit-menu .row{ display:flex; gap:8px; align-items:center; margin-bottom:8px }
                .edit-menu input[name="edit_name"]{ flex:1; padding:8px; border:1px solid #e2e8f0; border-radius:6px; font-size:13px }
                .edit-menu .btn{ padding:6px 8px; border-radius:6px; font-size:13px }
                .edit-menu .actions{ display:flex; gap:8px; justify-content:flex-end }
                .edit-menu .danger{ background:#fff; color:#c91f37; border:1px solid rgba(201,31,55,0.08) }
                .edit-menu .muted{ background:#f8fafc; color:#0b3d91; border:1px solid rgba(11,61,145,0.06) }

                /* Editable cells styling */
                .project-table td.editable{background:transparent;cursor:text;min-width:120px}
                .project-table td.editable:focus{outline:2px solid rgba(75,138,214,0.22);background:#fbfdff}
                .project-table td.saving{opacity:0.65}
                .project-table td.save-error{outline:2px solid rgba(225,29,72,0.9)}

           /* Top horizontal scrollbar - synced with table container */
           #topScrollbar { 
             overflow-x: scroll; 
             overflow-y: hidden; 
             height: 20px; 
             margin-bottom: 8px;
             background: #f1f5f9;
             border-radius: 8px;
           }
           #topScrollbar > div { 
             height: 1px; 
             /* Width will be set dynamically by JS to match table width */
           }
           #topScrollbar::-webkit-scrollbar { height: 14px; }
           #topScrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 8px; }
           #topScrollbar::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 8px; }
           #topScrollbar::-webkit-scrollbar-thumb:hover { background: #64748b; }

           /* Toast notification for status updates (minimal, no gradient)
             Positioned inside the main content area (top-right). */
           .main-content { position: relative; }
                /* Toast is hidden by default; JS sets inline style to 'flex' when showing. */
                #statusToast{ position: absolute; top: 12px; right: 12px; z-index:1200; min-width:200px; max-width:340px; display:none; padding:8px 12px; border-radius:6px; box-shadow:0 6px 18px rgba(2,6,23,0.06); color:#064e3b; font-weight:600; font-size:13px; border:1px solid rgba(2,6,23,0.06); background:#e6f5ec; align-items:center; justify-content:space-between }
                #statusToast.success{ background:#e6f5ec; color:#064e3b; border-color: rgba(6,78,59,0.08); }
                #statusToast.error{ background:#feecea; color:#6b1212; border-color: rgba(201,31,55,0.08); }
                #statusToast button{ background:transparent; border:1px solid rgba(2,6,23,0.06); padding:6px 8px; border-radius:6px; font-weight:600; font-size:12px; cursor:pointer }
                #statusToast button:disabled{ opacity:0.5; cursor:default }

                /* Row highlight / fade animations */
                .row-flash{ transition: background-color .35s ease, opacity .35s ease; }
                .row-flash.success { background: rgba(47,154,85,0.12); }
                .row-flash.error { background: rgba(201,31,55,0.08); }
                .fade-out { opacity: 0; transform: translateY(-6px); height:0; padding:0; margin:0; transition: opacity .28s ease, transform .28s ease, height .28s ease, padding .28s ease, margin .28s ease; }
                /* Empty cell highlight (yellow) — shows when a data cell has no value */
                .project-table td.empty-cell { background: #fff7cc; color: #0b0b00; }
                /* Hide the Status column by default for cleaner view while keeping
                   the column present in the DOM and usable by filters and APIs.
                   When the Projects table has a Status column (data-has-status="1")
                   we hide the header (2nd column) and any cells with data-col="Status".
                   This keeps the column available for programmatic updates and
                   server-side filtering but removes it from the default table view.
                */
                .project-table[data-has-status="1"] thead th:nth-child(2),
                .project-table[data-has-status="1"] tbody td[data-col="Status"] {
                  display: none;
                }

                /* Edit/Cancel button polish */
                #toggleEditBtn, #cancelChangesBtn {
                  border-radius:10px;
                  padding:8px 12px;
                  font-weight:600;
                  font-size:13px;
                  line-height:1;
                  box-shadow: 0 6px 18px rgba(2,6,23,0.06);
                  transition: transform .08s ease, box-shadow .12s ease, opacity .12s ease;
                  border: none;
                  display: inline-block;
                }
                /* Primary save: green gradient with white text */
                #toggleEditBtn{ background: linear-gradient(180deg,#43b26f 0%,#2f9a55 100%); color:#ffffff }
                #toggleEditBtn:not([disabled]):hover{ transform:translateY(-2px); box-shadow:0 10px 26px rgba(47,154,85,0.12) }
                #toggleEditBtn[disabled]{ opacity:0.6; filter:grayscale(10%); cursor:default }

                /* Secondary cancel: subtle light outline */
                #cancelChangesBtn{ background: #f8fafc; color:#0b3d91; border:1px solid rgba(11,61,145,0.08) }
                #cancelChangesBtn:not([disabled]):hover{ transform:translateY(-2px); box-shadow:0 8px 20px rgba(11,61,145,0.04) }
                #cancelChangesBtn[disabled]{ opacity:0.6; filter:grayscale(10%); cursor:default }

                /* Removed hover background effect per request (keep rows visually consistent) */
                /* .project-table tbody tr:hover td{background:#f1f5f9} */
                @media (max-width:900px){
                  .project-table thead th,.project-table tbody td{padding:10px 8px;font-size:12px}
                }
              </style>

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
                          $actions = "<span class=\"project-actions\">" .
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

  <script>
    (function(){
    // Project actions: clone, rename, delete
    (function(){
      var table = document.querySelector('.project-table');
      if (!table) return;

      // clone
      table.addEventListener('click', function(e){
        var btn = e.target.closest('.clone-btn');
        if (!btn) return;
        e.preventDefault();
        var pid = btn.getAttribute('data-project-id');
        if (!pid) return;
        if (!confirm('Create a copy of this project?')) return;
        var fd = new FormData(); fd.append('project_id', pid);
        fetch('../api/clone_project.php', { method:'POST', body: fd, credentials: 'same-origin' })
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

        fetch('../api/create_project.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(resp){ return resp.json(); })
          .then(function(json){
            if (json && json.success) {
              // show toast (if helper available) then reload so new row appears
              try { if (window && typeof window.showStatusToast === 'function') window.showStatusToast('Project created', 'success'); } catch(e){}
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

      // Track pending changes in a map: key = projectId + '|' + column
      var pending = {};
      var editingEnabled = false;

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
            // refresh baseline original value when entering edit mode
            td.dataset.original = td.textContent.trim();
          } else {
            // when disabling, undo any pending changes to avoid accidental commits
            var key = td.closest('tr') && td.closest('tr').dataset.projectId ? (td.closest('tr').dataset.projectId + '|' + (td.dataset.col || '')) : null;
            if (key && pending[key]) {
              pending[key].td.textContent = pending[key].td.dataset.original || '';
              pending[key].td.classList.remove('dirty');
              delete pending[key];
            }
          }
        });
        // clear pending entirely when locking editing off
        if (!editingEnabled) {
          pending = {};
          updateControlsVisibility();
        }
  // update edit button text/aria
  editBtn.textContent = editingEnabled ? 'Save' : 'Edit';
  editBtn.title = editingEnabled ? 'Save changes (or click to save and lock)' : 'Enable editing';
  // Update control enabled/disabled states based on whether there are pending edits
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
        pending = {};
        updateControlsVisibility();
      });

      // Toggle edit / Save behavior on the primary toggle button.
      editBtn.addEventListener('click', function(e){
        e.preventDefault();
        // If currently not editing, turn editing on (button becomes Save)
        if (!editingEnabled) {
          setEditing(true);
          return;
        }

        // If editing is active, treat this click as Save (if there are pending changes)
        var keys = Object.keys(pending);
        if (keys.length === 0) {
          // Nothing to save — just lock editing back off
          setEditing(false);
          return;
        }

        // Disable controls while saving
        editBtn.disabled = true; cancelBtn.disabled = true;

        var payload = { changes: keys.map(function(k){
          var it = pending[k];
          return { project_id: it.project_id, column: it.column, value: it.value };
        }) };

        fetch('../api/update_project_cells.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        }).then(function(r){ return r.json(); })
        .then(function(json){
          if (json && json.success) {
            // apply success: clear dirty classes, update originals
            keys.forEach(function(k){
                var it = pending[k];
                if (it && it.td) {
                    it.td.classList.remove('dirty');
                    it.td.classList.remove('save-error');
                    it.td.dataset.original = it.value;
                    // remove empty highlight if value is non-empty, otherwise keep it
                    if ((it.value || '').toString().trim() === '') {
                      it.td.classList.add('empty-cell');
                    } else {
                      it.td.classList.remove('empty-cell');
                    }
                    
                    // If Status column was changed, update the project name cell color
                    if (it.column === 'Status') {
                      var tr = it.td.closest('tr');
                      if (tr) {
                        var projectNameCell = tr.querySelector('td.project-name');
                        if (projectNameCell) {
                          // Remove all status classes
                          projectNameCell.classList.remove('status-ongoing', 'status-cancelled', 'status-completed');
                          // Add the appropriate class based on the new status value
                          if (it.value === 'Ongoing') {
                            projectNameCell.classList.add('status-ongoing');
                          } else if (it.value === 'Cancelled') {
                            projectNameCell.classList.add('status-cancelled');
                          } else if (it.value === 'Completed') {
                            projectNameCell.classList.add('status-completed');
                          }
                        }
                      }
                    }
                    
                    try { if (window && typeof window.flashRow === 'function') { var r = it.td.closest('tr'); if (r) window.flashRow(r, 'success', false); } } catch(e){}
                  }
              });
              pending = {};
              updateControlsVisibility();
              // After saving, lock editing off to avoid accidental further edits
              setEditing(false);
              try { if (window && typeof window.showStatusToast === 'function') window.showStatusToast('Changes saved', 'success'); } catch(e){}
          } else {
            alert((json && json.message) ? json.message : 'Failed to save changes');
            // mark all as error
            keys.forEach(function(k){ var it = pending[k]; if (it && it.td) it.td.classList.add('save-error'); });
          }
        }).catch(function(err){
          console.error('Batch save error', err);
          try { if (window && typeof window.showStatusToast === 'function') window.showStatusToast('Failed to save changes', 'error'); else alert('Failed to save changes'); } catch(e){ alert('Failed to save changes'); }
          keys.forEach(function(k){ var it = pending[k]; if (it && it.td) it.td.classList.add('save-error'); });
        }).finally(function(){ editBtn.disabled = false; cancelBtn.disabled = false; });
      });

      // Initialize controls disabled state (buttons are visible but inactive by default)
      setEditing(false);
      setControlsEnabled(false);
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
        // Ensure the proxy inner width is at least slightly larger than the visible wrapper
        // so the proxy always shows a scrollbar even when table exactly fits the viewport.
        var minWidth = tableWrap.clientWidth + 20;
        var w = Math.max(table.scrollWidth, minWidth);
        proxyInner.style.width = w + 'px';
        proxy.style.display = 'block';
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
  setTimeout(function(){ updateProxyWidth(); proxy.scrollLeft = tableWrap.scrollLeft; }, 200);
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
    })();
  </script>
  <script src="../assets/js/mobile-menu.js"></script>
  <script>
    // Edit menu behavior: rename / delete / mark status
    (function(){
      var table = document.querySelector('.project-table');
      var editMenu = document.getElementById('editMenu');
      if (!table || !editMenu) return;
      var hasStatus = table.getAttribute('data-has-status') === '1';
      var current = { projectId: null, tr: null, td: null, button: null };

      // Visual helpers: toast + row highlight
      function showStatusToast(message, type, undoData){
        try{
          var t = document.getElementById('statusToast');
          if (!t) return;
          // ensure the toast lives inside the main content area so it appears
          // below the header and inside the primary content region
          var container = document.querySelector('.main-content') || document.body;
          if (t.parentElement !== container) container.appendChild(t);
          // set message while keeping an Undo button element inside
          // ensure we don't clobber the Undo button when setting text
          var undoBtn = document.getElementById('statusToastUndo');
          // prepare content node
          var msgNode = t.querySelector('.toast-msg');
          if (!msgNode) {
            msgNode = document.createElement('span');
            msgNode.className = 'toast-msg';
            // insert at start
            t.insertBefore(msgNode, t.firstChild);
          }
          msgNode.textContent = message || '';
          t.className = '';
          t.classList.add(type === 'error' ? 'error' : 'success');
          // if undoData provided, enable and show undo button; otherwise hide it
          if (undoData && undoBtn) {
            undoBtn.style.display = 'inline-block';
            undoBtn.disabled = false;
            // store rollback info on the toast element for the handler
            t._lastChange = undoData;
          } else if (undoBtn) {
            undoBtn.style.display = 'none';
            t._lastChange = null;
          }
          t.style.display = 'flex';
          // small reflow
          void t.offsetWidth;
          clearTimeout(t._hideTimer);
          t._hideTimer = setTimeout(function(){ t.style.display = 'none'; if (t._lastChange) t._lastChange = null; }, 10000);
        }catch(e){ /* ignore */ }
      }

      function flashRow(tr, type, removeAfter){
        if (!tr) return;
        try{
          tr.classList.add('row-flash');
          tr.classList.add(type === 'error' ? 'error' : 'success');
          // remove flash after a short delay
          setTimeout(function(){
            tr.classList.remove(type === 'error' ? 'error' : 'success');
            tr.classList.remove('row-flash');
            if (removeAfter) {
              // fade out then remove
              tr.classList.add('fade-out');
              setTimeout(function(){ if (tr && tr.parentNode) tr.parentNode.removeChild(tr); }, 320);
            }
          }, 900);
        }catch(e){ /* ignore */ }
      }

  // Wire Undo button click handler: revert last status change if possible
      (function(){
        var toast = document.getElementById('statusToast');
        var undo = document.getElementById('statusToastUndo');
        if (!toast || !undo) return;
        undo.addEventListener('click', function(e){
          e.preventDefault();
          var data = toast._lastChange;
          if (!data) return;
          // disable button to prevent double-click
          undo.disabled = true;
          // send request to revert status
          var fd = new FormData();
          fd.append('project_id', data.projectId);
          fd.append('column', 'Status');
          fd.append('value', data.prev || '');
          fetch('../api/update_project_cell.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(json){
              if (json && json.success) {
                // if the row was removed, re-insert backupRow if present
                if (data.removed && data.backupRow) {
                  var tbody = document.querySelector('.project-table tbody');
                  if (tbody) tbody.insertBefore(data.backupRow, tbody.firstChild);
                } else {
                  // update existing row status cell
                  var tr = document.querySelector('tr[data-project-id="'+data.projectId+'"]');
                  if (tr) {
                    var st = tr.querySelector('td[data-col="Status"]');
                    if (st) st.textContent = data.prev || '';
                    
                    // Update the project name cell's color based on the reverted status
                    var projectNameCell = tr.querySelector('td.project-name');
                    if (projectNameCell) {
                      // Remove all status classes
                      projectNameCell.classList.remove('status-ongoing', 'status-cancelled', 'status-completed');
                      // Add the appropriate class based on the previous status
                      if (data.prev === 'Ongoing') {
                        projectNameCell.classList.add('status-ongoing');
                      } else if (data.prev === 'Cancelled') {
                        projectNameCell.classList.add('status-cancelled');
                      } else if (data.prev === 'Completed') {
                        projectNameCell.classList.add('status-completed');
                      }
                    }
                    
                    flashRow(tr, 'success', false);
                  }
                }
                showStatusToast('Undo successful', 'success');
                // clear stored rollback
                toast._lastChange = null;
              } else {
                showStatusToast((json && json.message) ? json.message : 'Undo failed', 'error');
              }
            }).catch(function(err){ console.error('Undo error', err); showStatusToast('Undo failed', 'error'); })
            .finally(function(){ undo.disabled = false; });
        });
      })();

      // expose helpers globally so other scripts (save flow) can call them
      try{
        window.showStatusToast = showStatusToast;
        window.flashRow = flashRow;
      }catch(e){ /* ignore in strict environments */ }

      function openMenuFor(button){
        var tr = button.closest('tr');
        if (!tr) return;
        var pid = tr.dataset.projectId;
        current.projectId = pid;
        current.tr = tr;
        current.td = button.closest('td');
        current.button = button;
        // set input to current name
        var nameSpan = current.td.querySelector('.project-title');
        var input = editMenu.querySelector('input[name="edit_name"]');
        input.value = nameSpan ? nameSpan.textContent.trim() : '';
        
        // Get current status to show/hide appropriate buttons
        var currentStatus = '';
        var statusCell = tr.querySelector('td[data-col="Status"]');
        if (statusCell) {
          currentStatus = (statusCell.textContent || '').trim();
        }
        
        // Show status action buttons in the edit menu. If the Projects table
        // does not have a Status column we keep the buttons visible but
        // disabled and provide a tooltip so the user understands why they
        // are inactive. This avoids the menu looking incomplete.
        var btnCompleteVisible = editMenu.querySelector('#editMenuCompleteProject');
        var btnCancelVisible = editMenu.querySelector('#editMenuCancelProject');
        var btnContinueVisible = editMenu.querySelector('#editMenuContinueProject');
        
        // Logic: Show Complete/Cancel buttons only if NOT already Completed or Cancelled
        // Show Continue button only if Completed or Cancelled
        var isCompletedOrCancelled = (currentStatus === 'Completed' || currentStatus === 'Cancelled');
        
        if (btnCompleteVisible) {
          btnCompleteVisible.style.display = (!isCompletedOrCancelled && hasStatus) ? 'inline-block' : 'none';
          btnCompleteVisible.disabled = !hasStatus;
          btnCompleteVisible.title = hasStatus ? 'Mark project as completed' : 'Status column not available in database';
          if (!hasStatus) btnCompleteVisible.classList.add('muted'); else btnCompleteVisible.classList.remove('muted');
        }
        if (btnCancelVisible) {
          btnCancelVisible.style.display = (!isCompletedOrCancelled && hasStatus) ? 'inline-block' : 'none';
          btnCancelVisible.disabled = !hasStatus;
          btnCancelVisible.title = hasStatus ? 'Cancel this project' : 'Status column not available in database';
          if (!hasStatus) btnCancelVisible.classList.add('muted'); else btnCancelVisible.classList.remove('muted');
        }
        if (btnContinueVisible) {
          btnContinueVisible.style.display = (isCompletedOrCancelled && hasStatus) ? 'inline-block' : 'none';
          btnContinueVisible.disabled = !hasStatus;
          btnContinueVisible.title = hasStatus ? 'Continue this project (set status to Ongoing)' : 'Status column not available in database';
          if (!hasStatus) btnContinueVisible.classList.add('muted'); else btnContinueVisible.classList.remove('muted');
        }
        
        // position menu near button
        editMenu.style.display = 'block';
        editMenu.setAttribute('aria-hidden','false');
        // compute after making visible so offsetWidth is available
        var rect = button.getBoundingClientRect();
        var left = rect.right + window.pageXOffset - editMenu.offsetWidth;
        if (left < 8) left = rect.left + window.pageXOffset; // fallback
        editMenu.style.left = left + 'px';
        editMenu.style.top = (rect.bottom + window.pageYOffset + 6) + 'px';
        input.focus();
        input.select && input.select();
      }

      function closeMenu(){
        editMenu.style.display = 'none';
        editMenu.setAttribute('aria-hidden','true');
        current = { projectId: null, tr: null, td: null, button: null };
      }

      // open menu when clicking the edit icon
      table.addEventListener('click', function(e){
        var btn = e.target.closest('.edit-btn');
        if (!btn) return;
        e.preventDefault();
        var pid = btn.getAttribute('data-project-id');
        if (!pid) return;
        // toggle if same row
        if (editMenu.style.display === 'block' && current.projectId === pid) { closeMenu(); return; }
        openMenuFor(btn);
      });

      // Rename
      editMenu.querySelector('#editMenuRename').addEventListener('click', function(){
        var input = editMenu.querySelector('input[name="edit_name"]');
        var name = (input.value || '').trim();
        if (!name) { alert('Name cannot be empty'); input.focus(); return; }
        var fd = new FormData(); fd.append('project_id', current.projectId); fd.append('new_name', name);
        this.disabled = true;
        fetch('../api/rename_project.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(json){
            if (json && json.success) {
              var span = current.tr && current.tr.querySelector('.project-title');
              if (span) span.textContent = name;
              try { showStatusToast('Project renamed', 'success'); } catch(e){}
              closeMenu();
            } else {
              alert((json && json.message) ? json.message : 'Failed to rename project');
            }
          }).catch(function(err){ console.error(err); alert('Failed to rename project'); })
          .finally(() => { editMenu.querySelector('#editMenuRename').disabled = false; });
      });

      // Delete
      editMenu.querySelector('#editMenuDelete').addEventListener('click', function(){
        if (!confirm('Permanently delete this project? This cannot be undone.')) return;
        var fd = new FormData(); fd.append('project_id', current.projectId);
        this.disabled = true;
        fetch('../api/delete_project.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(json){
            if (json && json.success) {
              if (current.tr) current.tr.parentNode.removeChild(current.tr);
              try { showStatusToast('Project deleted', 'success'); } catch(e){}
              closeMenu();
            } else {
              alert((json && json.message) ? json.message : 'Failed to delete project');
            }
          }).catch(function(err){ console.error(err); alert('Failed to delete project'); })
          .finally(() => { editMenu.querySelector('#editMenuDelete').disabled = false; });
      });

      // Mark status helper
      function markStatus(value, btn){
        if (!hasStatus) { alert('Status column is not available in this database'); return; }
        // capture previous status from the row (if present) so we can undo
        var stBefore = current.tr && current.tr.querySelector('td[data-col="Status"]');
        var prev = stBefore ? (stBefore.textContent || '').trim() : '';
        var fd = new FormData(); fd.append('project_id', current.projectId); fd.append('column', 'Status'); fd.append('value', value);
        btn.disabled = true;
        fetch('../api/update_project_cell.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(json){
            if (json && json.success) {
              // Update the row's Status cell if present
              var st = current.tr && current.tr.querySelector('td[data-col="Status"]');
              if (st) st.textContent = value;

              // Update the project name cell's color based on the new status
              if (current.tr) {
                var projectNameCell = current.tr.querySelector('td.project-name');
                if (projectNameCell) {
                  // Remove all status classes
                  projectNameCell.classList.remove('status-ongoing', 'status-cancelled', 'status-completed');
                  // Add the appropriate class based on the new status
                  if (value === 'Ongoing') {
                    projectNameCell.classList.add('status-ongoing');
                  } else if (value === 'Cancelled') {
                    projectNameCell.classList.add('status-cancelled');
                  } else if (value === 'Completed') {
                    projectNameCell.classList.add('status-completed');
                  }
                }
              }

              // If the page is currently filtered by status and the new value
              // doesn't match the active filter, remove the row from the table
              // so it immediately disappears from the current view.
              try {
                var params = new URLSearchParams(window.location.search);
                var currFilter = params.get('status') || '';
                // If a specific filter is active (not the 'All Projects' empty filter)
                // and it doesn't match the value we just set, remove the row.
                if (currFilter !== '' && currFilter !== value) {
                  // Keep a backup of the existing row so we can undo if needed
                  var backup = current.tr ? current.tr.cloneNode(true) : null;
                  // set backup status cell back to previous value for restore
                  if (backup) {
                    var bst = backup.querySelector('td[data-col="Status"]');
                    if (bst) bst.textContent = (typeof prev !== 'undefined') ? prev : '';
                  }
                  // show a toast & fade the row out before removing it for a nicer UX
                  showStatusToast('Status updated — removing from current view', 'success', { projectId: current.projectId, prev: prev, newValue: value, removed: true, backupRow: backup });
                  if (current.tr && current.tr.parentNode) {
                    // store backupRow as a DOM node on the stored undo data (clone is detached)
                    // (already set above)
                    flashRow(current.tr, 'success', true);
                  }
                } else {
                  // update in-place and show confirmation with undo available
                  showStatusToast('Status updated', 'success', { projectId: current.projectId, prev: prev, newValue: value, removed: false });
                  if (current.tr) flashRow(current.tr, 'success', false);
                }
              } catch (e) {
                // ignore URL parsing issues
              }

              closeMenu();
            } else {
              alert((json && json.message) ? json.message : 'Failed to update status');
            }
          }).catch(function(err){ console.error(err); alert('Failed to update status'); })
          .finally(() => { btn.disabled = false; });
      }

      // Wire visible action buttons to update status
      var completeBtn = editMenu.querySelector('#editMenuCompleteProject');
      if (completeBtn) completeBtn.addEventListener('click', function(){ markStatus('Completed', this); });
      var cancelBtn = editMenu.querySelector('#editMenuCancelProject');
      if (cancelBtn) cancelBtn.addEventListener('click', function(){ markStatus('Cancelled', this); });
      var continueBtn = editMenu.querySelector('#editMenuContinueProject');
      if (continueBtn) continueBtn.addEventListener('click', function(){ markStatus('Ongoing', this); });

      // Close on outside click / escape
      document.addEventListener('click', function(e){ if (!editMenu.contains(e.target) && !e.target.closest('.edit-btn')) closeMenu(); });
      document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeMenu(); });

      // Reposition on scroll/resize
      window.addEventListener('scroll', function(){ if (editMenu.style.display === 'block' && current.button) { var rect = current.button.getBoundingClientRect(); editMenu.style.left = (rect.right + window.pageXOffset - editMenu.offsetWidth) + 'px'; editMenu.style.top = (rect.bottom + window.pageYOffset + 6) + 'px'; } }, true);
      window.addEventListener('resize', function(){ if (editMenu.style.display === 'block' && current.button) { var rect = current.button.getBoundingClientRect(); editMenu.style.left = (rect.right + window.pageXOffset - editMenu.offsetWidth) + 'px'; editMenu.style.top = (rect.bottom + window.pageYOffset + 6) + 'px'; } });
    })();
  </script>
</body>
</html>
