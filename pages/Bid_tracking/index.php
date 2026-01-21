<?php
require_once __DIR__ . '/../../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

// Include database configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../partials/permissions.php';

// Get user role for sidebar
$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$stmt->close();

// Enforce access control for this page
if (!can_access($role, 'Bid_tracking')) {
  header('Location: /pages/dashboard/');
  exit();
}

// Determine if current user can edit this page (used to show add button)
$canEditBidTracking = function_exists('can_edit_page') ? can_edit_page('Bid_tracking') : false;

// Load bids table
$bidColumns = [];
$bidRows = [];
$bidTableExists = false;

try {
  $tres = $conn->query("SHOW TABLES LIKE 'bids'");
  if ($tres && $tres->num_rows) {
    $bidTableExists = true;

    $colResult = $conn->query("SHOW COLUMNS FROM bids");
    if ($colResult) {
      while ($c = $colResult->fetch_assoc()) {
        // Exclude timestamp metadata and primary id columns from the UI
        if (in_array($c['Field'], ['created_at','updated_at','bid_id'], true)) continue;
        $bidColumns[] = $c['Field'];
      }
    }

    $rowResult = $conn->query("SELECT * FROM bids ORDER BY bid_id DESC");
    if ($rowResult) {
      while ($r = $rowResult->fetch_assoc()) $bidRows[] = $r;
    }
  }
} catch (Throwable $ex) {
  $bidTableExists = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Bid tracking</title>
  <link rel="stylesheet" href="../../assets/css/base.css" />
  <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="style.css" />
  <style>
    /* NOTE: Project style guide — no gradients allowed. Replaced gradient with solid color. */
    #addProjectBtn {
      background: #10b981;
      border: none;
      color: #ffffff;
      padding: 10px 16px;
      border-radius: 10px;
      font-weight: 700;
      font-size: 14px;
      box-shadow: 0 8px 22px rgba(16,185,129,0.12);
      transition: transform .12s ease, box-shadow .12s ease, opacity .12s ease;
      cursor: pointer;
    }
    #addProjectBtn:hover { transform: translateY(-2px); box-shadow: 0 12px 34px rgba(16,185,129,0.16); }
    #addProjectBtn:active { transform: translateY(0); box-shadow: 0 8px 22px rgba(16,185,129,0.12); }
    #addProjectBtn:disabled { opacity: 0.6; cursor: not-allowed; }

    #cancelAddProject {
      background: #ffffff;
      border: 1px solid #e6edf0;
      color: #0f172a;
      padding: 10px 14px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
      transition: background .12s ease, transform .08s ease, box-shadow .12s ease;
      box-shadow: 0 1px 0 rgba(255,255,255,0.6) inset;
      margin-top: 8px;
      margin-right: 6px;
    }
    #cancelAddProject:hover { background: #fbfdfe; transform: translateY(-1px); }
    #cancelAddProject:active { transform: translateY(0); }

    #confirmAddProject {
      background: #10b981;
      border: none;
      color: #ffffff;
      padding: 10px 18px;
      border-radius: 8px;
      font-weight: 700;
      font-size: 14px;
      cursor: pointer;
      box-shadow: 0 8px 20px rgba(16,185,129,0.12);
      transition: transform .12s ease, box-shadow .12s ease, opacity .12s ease;
      margin-top: 8px;
      margin-left: 6px;
    }
    #confirmAddProject:hover { transform: translateY(-2px); box-shadow: 0 12px 34px rgba(16,185,129,0.16); }
    #confirmAddProject:active { transform: translateY(0); box-shadow: 0 8px 20px rgba(16,185,129,0.12); }
    #confirmAddProject:disabled { opacity: 0.6; cursor: not-allowed; }

    #projectToast {
      position: fixed;
      top: 24px;
      right: 24px;
      min-width: 220px;
      max-width: 420px;
      background: #f3f4f6;
      color: #0f172a;
      padding: 12px 14px;
      border-radius: 8px;
      box-shadow: 0 12px 30px rgba(2,6,23,0.08);
      display: none;
      align-items: center;
      gap: 10px;
      z-index: 6000;
      border: 1px solid rgba(15,23,42,0.06);
    }
    #projectToast.centered {
      top: 50% !important;
      left: 50% !important;
      right: auto !important;
      transform: translate(-50%, -50%) !important;
      box-shadow: 0 20px 50px rgba(2,6,23,0.12);
    }
    #projectToast.success { background: #f3f4f6; }
    #projectToast.error { background: #fee2e2; color: #7f1d1d; border-color: rgba(127,29,29,0.08); }
    #projectToast .msg { flex:1; font-weight:700; }
    #projectToast .close { background:transparent;border:0;color:rgba(15,23,42,0.7);cursor:pointer;font-weight:700;padding:6px;border-radius:6px }

    /* Status pill style for table */
    .status-pill { display:inline-block; padding:6px 12px; border-radius:999px; font-weight:700; font-size:13px; line-height:1; background: #f1f5f9; color:#0f172a; box-shadow: 0 1px 0 rgba(255,255,255,0.6) inset; }
    .status-pill.status-won { background: rgba(16,185,129,0.12); color:#065f46; }
    .status-pill.status-completed { background: rgba(59,130,246,0.08); color:#1e40af; }
    .status-pill.status-lost { background: rgba(239,68,68,0.08); color:#7f1d1d; }
    .status-pill.status-pending { background: rgba(99,102,241,0.04); color:#334155; }

    /* Ensure the bids table can expand to its content width */
    #tableContainer { overflow-x: auto; overflow-y: auto; box-sizing:border-box; }
    /* Make the table container fill available viewport height so table area remains tall even when empty */
    #tableContainer { min-height: calc(100vh - 220px); }
    #bidsTable { display: inline-table; width: -webkit-max-content; width: -moz-max-content; width: max-content; table-layout: auto; }
    /* Make the scroll area stretch full width while allowing the table to be wider */
    #tableTopScroller { box-sizing: border-box; }

    /* TABLE HEADER — modern, elevated look */
    #bidsTable thead th {
      padding: 14px 16px;
      background: rgba(249,250,251,0.92);
      border-bottom: 1px solid #e5e7eb;
      font-weight: 800;
      letter-spacing: .02em;
      box-shadow: 0 1px 0 rgba(15,23,42,.06);
      color: #334155;
      font-size: 13px;
      text-align: left;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      position: sticky;
      top: 0;
      z-index: 20;
    }

    #bidsTable thead th.col-status, #bidsTable tbody td.col-status { width: 120px; }
    #bidsTable thead th.col-dhss, #bidsTable tbody td.col-dhss { width: 90px; text-align: center; }

    /* BODY CELLS — roomier reading space */
    #bidsTable tbody td {
      padding: 12px 16px;
      font-size: 13px;
      color: #0f172a;
      vertical-align: middle;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    #bidsTable tbody tr { transition: background .12s ease; }
    #bidsTable tbody tr:hover { background: #f8fafc; }

    /* Notes column exception (keep allowing it to be wider) */
    #bidsTable td.notes-col, #bidsTable th.notes-col { max-width: 420px; }

    /* old group divider (safe to keep) */
    .group-spacer td { padding: 0; border: 0; height: auto; }
    .group-spacer .group-divider {
      height: 12px;
      width: 100%;
      border-top: 2px solid rgba(229,231,235,0.9);
      border-bottom: 1px solid rgba(229,231,235,0.6);
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);
      display: block;
    }

    /* Ensure sticky column cells retain solid backgrounds while scrolling */
    #bidsTable th[style*="position: sticky"], #bidsTable td[style*="position: sticky"] {
      background: rgba(255,255,255,0.98) !important;
      -webkit-backdrop-filter: none;
      backdrop-filter: none;
    }

    /* Floating cloned header (used when page scroll moves table out of view) */
    #floatingHeader {
      position: fixed;
      left: 0;
      right: 0;
      display: none;
      z-index: 1200;
      pointer-events: none; /* header is visual only */
      overflow: hidden;
    }
    #floatingHeader table { border-collapse: collapse; width: 100%; background: rgba(249,250,251,0.98); }
    #floatingHeader th { padding: 14px 16px; font-weight:800; color:#334155; text-align:left; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    /* Manage Columns modal button styles */
    .manage-columns-actions { display:flex; justify-content:flex-end; gap:12px; margin-top:12px; }
    .mc-btn { background:#fff; border:1px solid #e6edf0; padding:8px 14px; border-radius:10px; font-weight:700; cursor:pointer; color:#0f172a; box-shadow: 0 1px 0 rgba(255,255,255,0.6) inset; transition: transform .08s ease, box-shadow .12s ease, opacity .12s ease; }
    .mc-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(2,6,23,0.06); }
    .mc-btn:active { transform: translateY(0); }
    .mc-btn[disabled], .mc-btn.disabled { opacity:0.6; cursor:not-allowed; transform:none; box-shadow:none; }
    .mc-btn-ghost { background:#fff; border:1px solid #e6edf0; color:#0f172a; }
    .mc-btn-primary { background:#10b981; border:none; color:#ffffff; }
    .mc-btn-primary:hover { box-shadow: 0 12px 34px rgba(16,185,129,0.16); }

    /* Normalize toolbar buttons so they line up */
    .toolbar .btn, #addProjectBtn, #manageColumnsBtn { display:inline-flex; align-items:center; height:40px; padding:8px 14px; }
    #addProjectBtn { padding:8px 16px; }

    /* Drag-reorder visuals for Manage Columns */
    .drag-grip { cursor: grab; opacity:0.7; padding:4px 8px; border-radius:6px; user-select:none; }
    .drag-grip[draggable="false"] { cursor: not-allowed; opacity:0.35; }
    .dragging { opacity:0.5; }
    .drop-placeholder { border: 2px dashed rgba(14,20,26,0.06); background: #fff; height:48px; border-radius:8px; margin:0; box-sizing:border-box; }
    .drop-placeholder .placeholder-inner { height:100%; display:flex; align-items:center; padding:8px; color:#94a3b8; font-weight:700; }
    .drag-over { outline: 2px solid rgba(16,185,129,0.08); }

    /* =========================================================
       Show separators ONLY between different DHSS Project # groups
       (JS inserts: tr.group-spacer between project-id groups)
       ========================================================= */

    /* Remove per-row dividers so same-project rows don't show lines */
    #bidsTable tbody tr[data-bid] td { border-bottom: 0 !important; }

    /* Optional zebra for readability */
    #bidsTable tbody tr[data-bid]:nth-child(even) td { background: rgba(248, 250, 252, 0.55); }

   /* Group spacer: keep harmless and don't rely on it for important separators */
#bidsTable tbody tr.group-spacer td {
  padding: 6px 0 !important;
  border: 0 !important;
  height: 12px;                 /* keep small spacing if present */
  background: transparent !important;
}
#bidsTable tbody tr.group-spacer td::before,
#bidsTable tbody tr.group-spacer td::after { content: none !important; }

/* Project boundary separator — applied to rows where the next visible row has a different DHSS project # */
#bidsTable tbody tr.project-break td {
  border-bottom: 1px solid rgb(188, 190, 194) !important; /* gray */
  box-shadow: 0 1px 0 rgba(255,255,255,0.9) inset !important;
}

#bidsTable tbody tr.project-break:hover td { /* preserve hover but keep separator visible */
  box-shadow: 0 1px 0 rgba(255,255,255,0.9) inset !important;
}



    /* ================================
       Header filter controls (NEW)
       ================================ */
    .th-with-filter {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .th-with-filter .th-label {
      font-weight: 800;
      letter-spacing: .02em;
    }
    .th-filter {
      font-size: 12px;
      font-weight: 800;
      color: #334155;
      padding: 6px 32px 6px 10px;
      border: 1px solid rgba(15,23,42,0.10);
      border-radius: 10px;
      background: #ffffff;
      outline: none;
      cursor: pointer;
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;

      /* Inverted triangle caret */
      background-image: url("data:image/svg+xml;utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='16' height='16'%3E%3Cpath fill='%23334155' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 10px center;
      background-size: 16px;
    }
    .th-filter:focus {
      border-color: rgba(16,185,129,0.45);
      box-shadow: 0 0 0 3px rgba(16,185,129,0.12);
    }
    .th-filter[hidden] { display:none !important; }
    #bidsTable thead th.col-status { padding: 10px 12px; }

  </style>
</head>
<body class="admin-page">
  <div class="admin-container">
    <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
    <div class="admin-layout">
      <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
      <main class="content-area">
        <div class="main-content">

          <div class="toolbar" style="display:flex;align-items:center;justify-content:flex-start;gap:12px;flex-wrap:nowrap;min-height:48px;padding:16px 0 8px 40px;">
            <?php if (!empty($canEditBidTracking)) { ?>
              <button id="addProjectBtn" class="btn btn-primary">add Project +</button>
              <button id="manageColumnsBtn" class="btn" style="padding:8px 12px;border:1px solid #e6edf0;border-radius:8px;font-weight:700;">Manage Columns</button>
              <button id="enableEmailBtn" class="btn" style="margin-left:auto;padding:8px 12px;border:1px solid #e6edf0;border-radius:8px;font-weight:700;">Email Notifications</button>
            <?php } ?>
          </div>

          <div style="padding:16px 40px;">
            <div id="tableTopScroller" style="display:none;height:12px;overflow-x:auto;overflow-y:hidden;margin-bottom:8px;border-radius:6px;width:100%;">
              <div id="tableTopScrollerInner" style="height:1px;"></div>
            </div>

            <div id="tableContainer" style="overflow:auto;border:1px solid #e6edf0;border-radius:8px;padding:8px;background:#fff;">
              <?php if (!$bidTableExists) { ?>
                <div style="padding:12px;color:#7f1d1d;background:#fff5f5;border:1px solid rgba(127,29,29,0.06);border-radius:6px;margin-bottom:8px;">Bids table not found in the database.</div>
              <?php } ?>

              <table id="bidsTable" style="width:auto;border-collapse:collapse;font-size:13px;text-align:left;table-layout:auto;">
                <thead>
                  <tr>
                    <?php
                      if ($bidTableExists && !empty($bidColumns)) {
                        foreach ($bidColumns as $col) {
                          // Skip the native status column from the regular headers (we render status separately)
                          if ($col === 'status') continue;

                          // Insert status header cell before DHSS project # (NEW: status filter dropdown)
                          if ($col === 'dhss_project_number') {
                            echo '<th class="col-status" data-col="status">
                                    <select id="statusFilter" class="th-filter" title="Filter status">
                                      <option value="all" selected>All</option>
                                      <option value="won">Won</option>
                                      <option value="lost">Lost</option>
                                      <option value="pending">Pending</option>
                                      <option value="completed">Completed</option>
                                    </select>
                                  </th>';
                          }

                          // Build a human-friendly, title-cased label.
                          if ($col === 'dhss_project_number') {
                            $label = 'DHSS Project #';
                          } elseif ($col === 'gc_name') {
                            $label = 'General Contractor Name';
                          } elseif ($col === 'gc_number') {
                            $label = 'General Contractor Number';
                          } elseif (strpos(strtolower($col), 'gc') !== false || $col === 'general_contractor') {
                            $label = 'General Contractor';
                          } else {
                            $label = ucwords(str_replace('_',' ',$col));
                          }

                          // NEW: year filter dropdown embedded in DHSS Project # header
                          if ($col === 'dhss_project_number') {
                            echo '<th class="col-dhss" data-col="' . htmlspecialchars($col) . '">
                                    <div class="th-with-filter">
                                      <span class="th-label">' . htmlspecialchars($label) . '</span>
                                      <select id="yearFilter" class="th-filter" title="Filter by year"></select>
                                    </div>
                                  </th>';
                          } else {
                            echo '<th data-col="' . htmlspecialchars($col) . '">' . htmlspecialchars($label) . '</th>';
                          }
                        }
                      } else {
                        echo '<th>No columns</th>';
                      }
                    ?>
                  </tr>
                </thead>

                <tbody>
                  <?php if (!$bidTableExists) { ?>
                    <tr><td class="notes-col" colspan="1">Table not available.</td></tr>
                  <?php } else if (empty($bidRows)) { ?>
                    <tr><td colspan="<?php echo max(1, count($bidColumns)+1); ?>">No bids found.</td></tr>
                  <?php } else { ?>
                    <?php
                      // NOTE: We render rows normally; JS will do filtering + grouping on load.
                      foreach ($bidRows as $r) {
                    ?>
                      <tr data-bid='<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8"); ?>' style="cursor:pointer;">
                      <?php
                        foreach ($bidColumns as $col) {
                          if ($col === 'status') continue;
                          if ($col === 'dhss_project_number') {
                            $statusRaw = isset($r['status']) ? $r['status'] : '';
                            $statusKey = strtolower(trim((string)$statusRaw));
                            $normalized = preg_replace('/[^a-z0-9]/', '', $statusKey);
                            if ($normalized === '') $normalized = 'pending';

                            $label = $statusRaw;
                            if ($normalized === 'won') { $label = 'won'; }
                            else if ($normalized === 'completed') { $label = 'completed'; }
                            else if ($normalized === 'lost') { $label = 'lost'; }
                            else if ($normalized === 'didntbid' || $normalized === 'didnt') { $label = "didn't bid"; }
                            else { $label = $statusRaw ? $statusRaw : 'pending'; }
                            ?>
                            <td class="col-status" data-col="status"><span class="status-pill status-<?php echo htmlspecialchars($normalized); ?>"><?php echo htmlspecialchars($label); ?></span></td>
                            <td class="col-dhss" data-col="<?php echo htmlspecialchars($col); ?>"><?php echo htmlspecialchars(isset($r[$col]) ? $r[$col] : ''); ?></td>
                          <?php } else { ?>
                            <td data-col="<?php echo htmlspecialchars($col); ?>"><?php echo htmlspecialchars(isset($r[$col]) ? $r[$col] : ''); ?></td>
                          <?php }
                        }
                      ?>
                      </tr>
                    <?php } ?>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Manage Columns Modal -->
          <div id="manageColumnsModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.35);align-items:center;justify-content:center;z-index:5000;padding:20px;">
            <div style="background:#fff;border-radius:12px;padding:16px;max-width:640px;width:100%;box-shadow:0 8px 30px rgba(2,6,23,0.12);max-height:80vh;overflow:auto;">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <div style="font-weight:800;color:#0f172a;font-size:16px;">Manage Columns</div>
                <button id="closeManageColumns" style="background:transparent;border:0;font-weight:700;cursor:pointer;">✕</button>
              </div>
              
              <div style="border:1px solid #e6edf0;border-radius:8px;padding:12px;background:#fbfdfe;">
                <ul id="manageColumnsList" style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px;">
                  <!-- items populated by JS -->
                </ul>
              </div>
              <div class="manage-columns-actions">
                <button type="button" id="resetColumnsBtn" class="mc-btn mc-btn-ghost">Reset</button>
                <button type="button" id="cancelColumnsBtn" class="mc-btn mc-btn-ghost">Cancel</button>
                <button type="button" id="saveColumnsBtn" class="mc-btn mc-btn-primary">Save</button>
              </div>
            </div>
          </div>

          <!-- Edit Bid Modal -->
          <div id="editBidModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.35);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);align-items:center;justify-content:center;z-index:4500;padding:20px;overflow-y:auto;">
            <div style="background:#fff;border-radius:12px;padding:16px;max-width:980px;width:100%;box-shadow:0 8px 30px rgba(2,6,23,0.12);max-height:90vh;overflow-y:auto;">
              <form id="editBidForm" style="display:block;">
                <input type="hidden" id="editBidId" name="bid_id" />
                <!-- general_contractor_id removed: GC info stored in general_contractor table only -->
                <div style="margin-bottom:12px;text-align:center;">
                  <select id="editStatus" name="status" style="min-width:90px;padding:6px 36px 6px 6px;border:0;background:transparent;appearance:none;-webkit-appearance:none;-moz-appearance:none;color:#374151;font-weight:600;background-image:url('data:image/svg+xml;utf8,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' width=\'16\' height=\'16\'%3E%3Cpath fill=\'currentColor\' d=\'M7 10l5 5 5-5z\'/%3E%3C/svg%3E');background-repeat:no-repeat;background-position:right 10px center;background-size:16px;">
                    <option value="won" style="color:#10b981;">won</option>
                    <option value="lost" style="color:#ef4444;">lost</option>
                    <option value="pending" selected style="color:#374151;">pending</option>
                    <option value="didn't bid" style="color:#f97316;">didn't bid</option>
                    <option value="completed" style="color:#3b82f6;">completed</option>
                  </select>
                </div>

                <style>
                  /* Top row compact styling so three inputs fit nicely */
                  .modal-top { display:grid; grid-template-columns:1fr 2fr 1fr; gap:8px; align-items:start; margin-bottom:12px; }
                  .modal-top label { font-size:11px; margin-bottom:6px; color:#475569; }
                  /* Override inline styles to force a smaller, tighter appearance */
                  .modal-top input { font-size:12px !important; padding:6px 8px !important; height:36px !important; border-radius:6px !important; }
                  /* Target the specific top inputs as a fallback to ensure they shrink */
                  #editDhssProjectNumber, #editProjectName, #editBidDate { font-size:12px !important; padding:6px 8px !important; height:36px !important; }
                  /* Make date control's inner text smaller on some browsers */
                  #editBidDate::-webkit-datetime-edit, #editBidDate::-webkit-inner-spin-button { font-size:12px !important; }
                </style>

                <div class="modal-top" style="grid-template-columns:1fr 2fr 1fr;">
                  <div style="display:flex;flex-direction:column;">
                    <label style="font-weight:600;color:#475569;margin-bottom:6px;">DHSS Project #</label>
                    <input type="text" id="editDhssProjectNumber" name="dhss_project_number" data-col="dhss_project_number" style="padding:10px;border:1px solid #cbd5e1;border-radius:6px;" />
                  </div>
                  <div style="display:flex;flex-direction:column;">
                    <label style="font-weight:600;color:#475569;margin-bottom:6px;">Project Name</label>
                    <input type="text" id="editProjectName" name="project_name" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;" />
                  </div>
                  <div style="display:flex;flex-direction:column;">
                    <label style="font-weight:600;color:#475569;margin-bottom:6px;">Bid Date</label>
                    <input type="date" id="editBidDate" name="bid_date" data-col="bid_date" style="padding:10px;border:1px solid #cbd5e1;border-radius:6px;" />
                  </div>
                </div>

                <?php
                  // Partition columns into logical modal subsections
                  $locFields = [];
                  $gcFields = [];
                  $specFields = [];
                  $otherFields = [];
                  foreach ($bidColumns as $col) {
                    if ($col === 'project_name' || $col === 'status') continue;
                    if ($col === 'dhss_project_number' || $col === 'bid_date') continue;
                    $lc = strtolower($col);

                    if (strpos($lc, 'project_city') !== false || strpos($lc, 'project_county') !== false || strpos($lc, 'project_state') !== false) {
                      $locFields[] = $col;
                      continue;
                    }
                    if (strpos($lc, 'gc') !== false || strpos($lc, 'general_contractor') !== false) {
                      $gcFields[] = $col;
                      continue;
                    }
                    // Exclude explicit "total price" fields from Project Specifications
                    if (preg_match('/total.*price|total_price/i', $lc)) {
                      // put total price into other fields instead of specs
                      $otherFields[] = $col;
                      continue;
                    }
                    if (preg_match('/material|material_type|price/i', $lc)) {
                      $specFields[] = $col;
                      continue;
                    }
                    if (strpos($lc, 'dhss_project_number') !== false || strpos($lc, 'project_') === 0 || strpos($lc, 'bid_date') !== false || preg_match('/square|ton|dimension|area|spec/i', $lc)) {
                      $specFields[] = $col;
                      continue;
                    }
                    $otherFields[] = $col;
                  }
                ?>

                <style>
                  .modal-section { margin-top:12px; padding:0; border-radius:8px; border:1px solid #eef2f7; background:#fbfdfe; overflow:hidden; }
                  .modal-section .header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 12px; cursor:pointer; }
                  .modal-section .header h4 { margin:0;font-size:13px;font-weight:700;color:#334155; }
                  .modal-section .header .toggle { font-size:12px;color:#64748b;display:inline-flex;align-items:center;gap:8px }
                  .modal-section .toggle .chev { transition: transform .18s ease; display:inline-block; }
                  .modal-section .section-content { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; padding:10px 12px 14px 12px; }
                  #section-gc .section-content { grid-template-columns: repeat(3, 1fr); }
                  .add-gc-btn { background: #f3f4f6; border: 1px solid #e6edf0; color: #0f172a; padding:6px 10px; border-radius:8px; font-weight:700; cursor:pointer; font-size:12px; }
                  .modal-section.collapsed .header .add-gc-btn { display: none !important; }
                  .modal-section .field { display:flex; flex-direction:column; }
                  .modal-section .field label { font-weight:600; color:#475569; margin-bottom:6px; }
                  .modal-section .field input { padding:8px; border:1px solid #cbd5e1; border-radius:6px; }
                  .modal-section.collapsed .section-content { display:none; }
                  .modal-section.collapsed .toggle .chev { transform: rotate(-90deg); }
                </style>

                <div class="modal-section collapsed" id="section-location">
                  <div class="header" role="button" aria-expanded="false">
                    <h4>Project Location</h4>
                    <div style="display:flex;align-items:center;gap:10px;">
                      <div class="toggle"><span class="chev">▾</span><span>collapse</span></div>
                    </div>
                  </div>
                  <div class="section-content">
                    <?php foreach ($locFields as $col) {
                      if ($col === 'project_city') { $label = 'Project City'; }
                      elseif ($col === 'project_county') { $label = 'Project County'; }
                      elseif ($col === 'project_state') { $label = 'Project State'; }
                      elseif ($col === 'dhss_project_number') { $label = 'DHSS Project #'; }
                      else { $label = ucwords(str_replace('_',' ',$col)); }
                    ?>
                      <div class="field">
                        <label><?php echo htmlspecialchars($label); ?></label>
                        <input type="text" data-col="<?php echo htmlspecialchars($col); ?>" name="<?php echo htmlspecialchars($col); ?>" />
                      </div>
                    <?php } ?>
                  </div>
                </div>

                <div class="modal-section collapsed" id="section-gc">
                  <div class="header" role="button" aria-expanded="false">
                    <h4>General Contractor</h4>
                    <div style="display:flex;align-items:center;gap:10px;">
                      <button type="button" id="addGcBtn" class="add-gc-btn">+ Add contractor</button>
                      <div class="toggle"><span class="chev">▾</span><span>collapse</span></div>
                    </div>
                  </div>
                  <div class="section-content">
                    <!-- existing contractor selector removed per request -->
                    <div style="margin-bottom:8px;">
                      <label style="font-weight:600;color:#475569;display:block;margin-bottom:6px;">Client Winner</label>
                      <select id="editClientWinner" data-col="client_winner" name="client_winner" style="padding:8px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;width:100%;max-width:360px;">
                        <option value="">--</option>
                      </select>
                    </div>
                    <!-- Top GC quick-edit inputs removed; use the list below and the modal Save button instead -->
                    <div id="newGcContainer" style="grid-column:1/-1;display:flex;flex-direction:column;gap:8px;margin-top:8px;"></div>
                    <div id="gcTableList" style="grid-column:1/-1;margin-top:12px;max-height:220px;overflow:auto;border-top:1px solid #e6edf0;padding-top:8px;">
                      <!-- populated dynamically with contractors for this project -->
                    </div>
                  </div>
                </div>

                <div class="modal-section collapsed" id="section-specs">
                  <div class="header" role="button" aria-expanded="false">
                    <h4>Project Specifications</h4>
                    <div class="toggle"><span class="chev">▾</span><span>collapse</span></div>
                  </div>
                  <div class="section-content">
                    <?php foreach ($specFields as $col) {
                      if ($col === 'gc_name') { $label = 'General Contractor Name'; }
                      elseif ($col === 'gc_number') { $label = 'General Contractor Number'; }
                      elseif (strpos(strtolower($col),'gc') !== false || $col === 'general_contractor') { $label = 'General Contractor'; }
                      elseif ($col === 'dhss_project_number') { $label = 'DHSS Project #'; }
                      else { $label = ucwords(str_replace('_',' ',$col)); }
                      $type = (strtolower($col) === 'bid_date') ? 'date' : 'text';
                    ?>
                      <div class="field">
                        <label><?php echo htmlspecialchars($label); ?></label>
                        <input type="<?php echo $type; ?>" data-col="<?php echo htmlspecialchars($col); ?>" name="<?php echo htmlspecialchars($col); ?>" />
                      </div>
                    <?php } ?>
                  </div>
                </div>

                <div class="modal-section collapsed" id="section-addl">
                  <div class="header" role="button" aria-expanded="false">
                    <h4>Additional Information</h4>
                    <div class="toggle"><span class="chev">▾</span><span>collapse</span></div>
                  </div>
                  <div class="section-content">
                    <?php foreach ($otherFields as $col) {
                      if ($col === 'notes') continue;
                      // avoid duplicate Client Winner select in Additional Information (it lives under General Contractor section)
                      if ($col === 'client_winner') continue;
                      if ($col === 'gc_name') { $label = 'General Contractor Name'; }
                      elseif ($col === 'gc_number') { $label = 'General Contractor Number'; }
                      elseif (strpos(strtolower($col),'gc') !== false || $col === 'general_contractor') { $label = 'General Contractor'; }
                      elseif ($col === 'dhss_project_number') { $label = 'DHSS Project #'; }
                      else { $label = ucwords(str_replace('_',' ',$col)); }

                      // Render a custom UI for some specific columns
                      if ($col === 'reason') {
                        ?>
                        <div class="field">
                          <label><?php echo htmlspecialchars($label); ?></label>
                          <select data-col="reason" name="reason" class="reason-select" style="padding:8px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;">
                            <option value="">--</option>
                            <option>Didn't Bid</option>
                            <option>High</option>
                            <option>Never Went</option>
                            <option>Did Themselves</option>
                            <option>Self Performed</option>
                            <option>Don't Know</option>
                            <option value="Other">Other</option>
                          </select>
                          <input type="text" data-col="reason_other" name="reason_other" placeholder="Other (specify)" style="margin-top:8px;display:none;padding:8px;border:1px solid #cbd5e1;border-radius:6px;" />
                        </div>
                        <?php
                      } else {
                        ?>
                        <div class="field">
                          <label><?php echo htmlspecialchars($label); ?></label>
                          <input type="text" data-col="<?php echo htmlspecialchars($col); ?>" name="<?php echo htmlspecialchars($col); ?>" />
                        </div>
                        <?php
                      }
                    } ?>
                  </div>
                </div>

                <?php if (in_array('notes', $bidColumns, true)) { ?>
                  <div style="margin-top:12px;">
                    <label style="font-weight:600;color:#475569;display:block;margin-bottom:6px;">Notes</label>
                    <textarea id="editNotes" name="notes" data-col="notes" style="width:100%;min-height:120px;padding:10px;border:1px solid #cbd5e1;border-radius:6px;"></textarea>
                  </div>
                <?php } ?>



                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px;">
                  <button type="button" id="closeEditBid" style="background:#fff;border:1px solid #e6edf0;color:#0f172a;padding:10px 14px;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
                  <button type="button" id="deleteBidBtn" style="background:#fee2e2;border:1px solid rgba(239,68,68,0.12);color:#7f1d1d;padding:10px 14px;border-radius:8px;font-weight:700;cursor:pointer;">Delete</button>
                  <button type="submit" id="saveEditBid" style="background:#10b981;border:none;color:#fff;padding:10px 16px;border-radius:8px;font-weight:700;cursor:pointer;">Save</button>
                </div>
              </form>
            </div>
          </div>

          <!-- Add Project Modal -->
          <div id="addProjectModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.35);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);align-items:center;justify-content:center;z-index:4000;padding:20px;overflow-y:auto;">
            <div style="background:#fff;border-radius:12px;padding:20px;max-width:520px;width:100%;box-shadow:0 8px 30px rgba(2,6,23,0.12);max-height:90vh;overflow-y:auto;">
              <form id="addProjectForm" style="display:grid;gap:12px;">
                <div style="display:flex;justify-content:flex-start;align-items:center;">
                  <div style="font-size:18px;font-weight:700;color:#0f172a;">Add new Project</div>
                </div>
                <div>
                  <label style="font-weight:600;color:#475569;display:block;margin-bottom:6px;">DHSS Project Number</label>
                  <input type="text" id="dhssProjectNumber" name="dhss_project_number" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;" />
                </div>
                <div>
                  <label style="font-weight:600;color:#475569;display:block;margin-bottom:6px;">Project Name</label>
                  <input type="text" id="projectName" name="project_name" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;" />
                </div>
                <div>
                  <label style="font-weight:600;color:#475569;display:block;margin-bottom:6px;">Bid Date</label>
                  <input type="date" id="bidDate" name="bid_date" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px;" />
                </div>
                <hr />
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
                  <div>
                    <label style="font-weight:600;color:#475569;display:block;margin-bottom:6px;">Project City</label>
                    <input type="text" id="projectCity" name="project_city" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;" />
                  </div>
                  <div>
                    <label style="font-weight:600;color:#475569;display:block;margin-bottom:6px;">Project County</label>
                    <input type="text" id="projectCounty" name="project_county" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;" />
                  </div>
                  <div>
                    <label style="font-weight:600;color:#475569;display:block;margin-bottom:6px;">Project State</label>
                    <input type="text" id="projectState" name="project_state" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;" />
                  </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:6px;">
                  <button type="button" id="cancelAddProject" class="btn">Cancel</button>
                  <button type="submit" id="confirmAddProject" class="btn btn-primary">Create Project</button>
                </div>
              </form>
            </div>
          </div>

          <!-- Email Settings Modal -->
          <div id="emailSettingsModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.35);align-items:center;justify-content:center;z-index:4600;padding:20px;">
            <div style="background:#fff;border-radius:12px;padding:16px;max-width:520px;width:100%;box-shadow:0 8px 30px rgba(2,6,23,0.12);max-height:90vh;overflow-y:auto;">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <div style="font-weight:800;color:#0f172a;font-size:16px;">Email Notifications</div>
                <button id="closeEmailSettings" style="background:transparent;border:0;font-weight:700;cursor:pointer;">✕</button>
              </div>
              <div style="padding:8px;border:1px solid #e6edf0;border-radius:8px;background:#fbfdfe;">
                <p style="margin:0 0 8px 0;color:#475569;">Select how many days prior to a bid you want to receive reminders. You may select multiple values, up to 5.</p>
                <div id="emailDaysList" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding-top:6px;">
                  <!-- checkboxes populated by JS -->
                </div>
              </div>
              <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px;">
                <button type="button" id="cancelEmailSettings" style="background:#fff;border:1px solid #e6edf0;color:#0f172a;padding:8px 12px;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
                <button type="button" id="saveEmailSettings" style="background:#10b981;border:none;color:#fff;padding:8px 12px;border-radius:8px;font-weight:700;cursor:pointer;">Save</button>
              </div>
            </div>
          </div>

          <!-- Toast notification -->
          <div id="projectToast" role="status" aria-live="polite">
            <div class="msg"></div>
            <button class="close" aria-label="Dismiss">×</button>
          </div>

        </div>
      </main>
    </div>
  </div>

  <script>
    // Make toast GLOBAL so other scripts can call it
    window.showToast = function(message, type) {
      var t = document.getElementById('projectToast');
      if (!t) return;
      var msg = t.querySelector('.msg');
      var close = t.querySelector('.close');
      msg.textContent = message;

      t.classList.remove('success','error','centered');
      if (type === 'success') t.classList.add('success','centered');
      else if (type === 'error') t.classList.add('error');

      t.style.display = 'flex';
      clearTimeout(t._hideTimer);
      t._hideTimer = setTimeout(hideToast, 3000);

      function hideToast() {
        t.style.display = 'none';
        t.classList.remove('success','error','centered');
      }
      close.onclick = function(){ clearTimeout(t._hideTimer); hideToast(); };
    };

    (function(){
      var usersToggle = document.getElementById('usersToggle');
      var usersGroup = document.getElementById('usersGroup');
      if (usersToggle && usersGroup) {
        usersToggle.addEventListener('click', function(){
          usersGroup.classList.toggle('open');
        });
      }

      // Add Project modal behavior
      var addBtn = document.getElementById('addProjectBtn');
      var addModal = document.getElementById('addProjectModal');
      var cancelBtn = document.getElementById('cancelAddProject');
      var addForm = document.getElementById('addProjectForm');
      var dhssInput = document.getElementById('dhssProjectNumber');

      function openAddModal() {
        if (!addModal) return;
        fetch('../../api/get_next_project_number.php', { credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(data){ if (data && data.suggested && dhssInput) dhssInput.value = data.suggested; })
          .catch(function(){})
          .finally(function(){ addModal.style.display = 'flex'; });
      }

      if (addBtn) addBtn.addEventListener('click', openAddModal);
      if (cancelBtn) cancelBtn.addEventListener('click', function(){ if (addModal) addModal.style.display = 'none'; });

      if (addForm) {
        addForm.addEventListener('submit', function(e){
          e.preventDefault();
          var fd = new FormData(addForm);

          var submitBtn = document.getElementById('confirmAddProject');
          if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Creating...'; }

          fetch('../../api/create_project.php', { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(data){
              if (data && data.success) {
                showToast('Project added', 'success');
                if (addModal) addModal.style.display = 'none';
                setTimeout(function(){ window.location.reload(); }, 900);
              } else {
                var msg = (data && data.message) ? data.message : 'Failed to create project';
                showToast(msg, 'error');
              }
            })
            .catch(function(){ showToast('Failed to create project', 'error'); })
            .finally(function(){
              if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Create Project'; }
            });
        });
      }
    })();
  </script>

  <script>
    (function(){
      var bidColumns = <?php echo json_encode($bidColumns); ?> || [];
      var allTableColumns = <?php echo json_encode(array_merge(['status'], $bidColumns)); ?> || [];
      var updateUrl = '../../api/update_bid.php'; // ✅ WORKS on local + Railway for /pages/... structure

      // Helper: set status control color based on status value
      function setStatusColor(val) {
        var s = (val || '').toString().toLowerCase().replace(/[^a-z0-9]/g,'');
        var color = '#374151';
        var font = 'Arial, sans-serif';
        if (s === 'won') { color = '#10b981'; font = 'Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial'; }
        else if (s === 'completed') { color = '#3b82f6'; font = 'Tahoma, Verdana, Segoe UI, sans-serif'; }
        else if (s === 'lost') { color = '#ef4444'; font = 'Georgia, "Times New Roman", Times, serif'; }
        else if (s === 'didntbid' || s === 'didnt') { color = '#f97316'; font = '"Courier New", Courier, monospace'; }
        else { font = 'Arial, sans-serif'; }
        var el = document.getElementById('editStatus');
        if (el) {
          el.style.color = color;
          el.style.fontFamily = font;
        }
      }

      // Columns that should show a dollar sign prefix in the UI (but not modify stored value)
      var moneyCols = ['client_win_price','stabilizer_bid_win_price','total_price'];

      function formatForDisplay(col, val) {
        if (!val && val !== 0 && val !== '0') return '';
        var s = (val === null || val === undefined) ? '' : String(val);
        if (moneyCols.indexOf(col) !== -1) {
          // avoid double prefix
          if (s.trim().indexOf('$') === 0) return s;
          return '$' + s;
        }
        return s;
      }

      function wrapMoneyInputs() {
        try {
          moneyCols.forEach(function(col){
            var sel = '#editBidModal [data-col="' + col + '"]';
            var inp = document.querySelector(sel);
            if (!inp) return;
            // already wrapped?
            if (inp.parentNode && inp.parentNode.classList && inp.parentNode.classList.contains('money-wrapper')) return;
            var wrap = document.createElement('div');
            wrap.className = 'money-wrapper';
            wrap.style.display = 'flex';
            wrap.style.alignItems = 'center';
            wrap.style.gap = '8px';
            wrap.style.border = '1px solid #cbd5e1';
            wrap.style.borderRadius = '6px';
            wrap.style.padding = '4px 8px';
            wrap.style.background = '#fff';
            var span = document.createElement('span'); span.textContent = '$'; span.style.color = '#374151'; span.style.fontWeight = '700'; span.style.marginRight = '4px';
            span.style.flex = '0 0 auto';
            // move input into wrapper
            inp.style.border = '0'; inp.style.padding = '6px 0'; inp.style.margin = '0'; inp.style.background = 'transparent'; inp.style.flex = '1 1 auto';
            inp.parentNode.replaceChild(wrap, inp);
            wrap.appendChild(span);
            wrap.appendChild(inp);
          });
        } catch(e) { console.warn('wrapMoneyInputs failed', e); }
      }

      function applyDollarPrefixToTableCells() {
        try {
          moneyCols.forEach(function(col){
            var tds = Array.from(document.querySelectorAll('#bidsTable td[data-col="' + col + '"]'));
            tds.forEach(function(td){
              try {
                var txt = (td.textContent || '').toString();
                if (!txt) return;
                if (txt.trim().indexOf('$') === 0) return;
                td.textContent = '$' + txt;
              } catch(e) {}
            });
          });
        } catch(e) { console.warn('applyDollarPrefixToTableCells failed', e); }
      }

      // Load and render General Contractors for a given project into #gcTableList
      function loadGcList(projectKey) {
        try {
          var container = document.getElementById('gcTableList');
          if (!container) return;
          container.innerHTML = '<div style="padding:12px;color:#6b7280">Loading contractors&hellip;</div>';
          var qs = projectKey ? '?dhss_project_number=' + encodeURIComponent(projectKey) : '';
          fetch('../../api/get_general_contractors.php' + qs, { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(j){
              try {
                var items = (j && j.contractors) ? j.contractors : [];
                // Also populate Client Winner select if present so the dropdown is in sync with the list
                try {
                  var clientSel = document.getElementById('editClientWinner');
                  var prevSelId = '';
                  var prevSelText = '';
                  if (clientSel) {
                    prevSelId = clientSel.value || '';
                    var si = clientSel.selectedIndex;
                    if (si >= 0 && clientSel.options[si]) prevSelText = clientSel.options[si].getAttribute('data-name') || clientSel.options[si].textContent || '';
                    clientSel.innerHTML = '';
                    var ph = document.createElement('option'); ph.value = ''; ph.textContent = '--'; clientSel.appendChild(ph);
                    var seenOpt = new Set();
                    items.forEach(function(c){
                      // Prefer the explicit contractor label, fall back to contractor_name if missing
                      var name = (c.general_contractor || c.general_contractor_name || '').toString().trim();
                      var id = (c.id || '').toString();
                      // If there's no textual name, still include an option so a winner flag can be selected
                      var display = name || ('Contractor #' + id);
                      var norm = display.toLowerCase();
                      if (seenOpt.has(norm)) return; seenOpt.add(norm);
                      var o = document.createElement('option'); o.value = id; o.textContent = display; o.setAttribute('data-name', display);
                      if (c.winner && (c.winner == 1 || c.winner === '1' || c.winner === true)) o.setAttribute('data-winner','1');
                      clientSel.appendChild(o);
                    });
                    // attach onchange (overwrite safe)
                    try {
                      clientSel.onchange = function(){
                        try {
                          var selOpt = clientSel.options[clientSel.selectedIndex];
                          var name = selOpt ? (selOpt.getAttribute('data-name') || selOpt.textContent) : '';
                          var id = clientSel.value || '';
                          if (!id) {
                            var fdclear = new FormData(); fdclear.append('dhss_project_number', projectKey || ''); fetch('../../api/set_winner_general_contractor.php', { method:'POST', credentials:'same-origin', body: fdclear }).catch(function(){});
                          } else {
                            var fd2 = new FormData(); fd2.append('id', id); fd2.append('dhss_project_number', projectKey || '');
                            fetch('../../api/set_winner_general_contractor.php', { method:'POST', credentials:'same-origin', body: fd2 }).then(function(r){ return r.json(); }).then(function(res){ if (res && res.success) { try { loadGcList(projectKey); applyGcWinnerHighlight(projectKey, name); showToast && showToast('Winner updated', 'success'); } catch(e){} } else { showToast && showToast('Failed to set winner', 'error'); } }).catch(function(){ showToast && showToast('Failed to set winner', 'error'); });
                          }
                        } catch(e){}
                      };
                    } catch(e){}

                    // try to restore previous selection or select the contractor marked winner
                    try {
                      var selectedSet = false;
                      if (prevSelId) {
                        var optById = clientSel.querySelector('option[value="' + prevSelId + '"]');
                        if (optById) { clientSel.value = optById.value; selectedSet = true; }
                      }
                      if (!selectedSet && prevSelText) {
                        Array.from(clientSel.options).forEach(function(o){ if (!selectedSet && o.textContent && o.textContent.toString().toLowerCase() === prevSelText.toString().toLowerCase()) { clientSel.value = o.value; selectedSet = true; } });
                      }
                      if (!selectedSet) {
                        // try find winner flag
                        var winOpt = clientSel.querySelector('option[data-winner="1"]');
                        if (winOpt) { clientSel.value = winOpt.value; selectedSet = true; }
                      }
                      // finally apply highlight if selected
                      try { var selText = clientSel.options[clientSel.selectedIndex] ? (clientSel.options[clientSel.selectedIndex].getAttribute('data-name') || clientSel.options[clientSel.selectedIndex].textContent) : ''; applyGcWinnerHighlight(projectKey, selText || ''); } catch(e){}
                    } catch(e){}
                  }
                } catch(e) {}
                if (!items || items.length === 0) {
                  container.innerHTML = '<div style="padding:12px;color:#6b7280">No contractors found for this project.</div>';
                  return;
                }
                var table = document.createElement('div');
                table.style.display = 'grid';
                /* add an actions column on the right for remove 'X' buttons */
                table.style.gridTemplateColumns = '2fr 2fr 1.5fr 2fr 2fr 48px';
                table.style.gap = '8px';
                table.style.alignItems = 'center';
                // header row
                var hdrs = ['General Contractor','Name','Number','Email','Address'];
                hdrs.forEach(function(h){ var e = document.createElement('div'); e.style.fontWeight = '600'; e.style.padding = '6px 8px'; e.style.color = '#374151'; e.textContent = h; e.style.position = 'sticky'; e.style.top = '0'; e.style.background = '#ffffff'; e.style.zIndex = '4'; e.style.borderBottom = '1px solid #e6edf0'; e.style.textAlign = 'left'; table.appendChild(e); });
                // Add actions header (empty but keeps layout consistent)
                var actHdr = document.createElement('div'); actHdr.style.padding = '6px 8px'; actHdr.style.position = 'sticky'; actHdr.style.top = '0'; actHdr.style.background = '#ffffff'; actHdr.style.zIndex = '4'; actHdr.style.borderBottom = '1px solid #e6edf0'; actHdr.style.textAlign = 'left'; actHdr.textContent = '';
                table.appendChild(actHdr);
                // ensure container is a positioned scroll container so sticky headers work
                container.style.position = 'relative';

                items.forEach(function(it){
                  var id = it.id || '';
                  var gc = it.general_contractor || '';
                  var name = it.general_contractor_name || '';
                  var num = it.general_contractor_number || '';
                  var mail = it.general_contractor_email || '';
                  var addr = it.general_contractor_address || '';
                  var isWinner = (it.winner && (it.winner == 1 || it.winner === '1' || it.winner === true));

                  function makeCellInput(val, nameAttr, placeholder, highlightColor) {
                    var wrapper = document.createElement('div');
                    wrapper.style.padding = '6px 8px';
                    wrapper.style.borderBottom = '1px solid #eef2f7';
                    wrapper.style.textAlign = 'left';
                    wrapper.setAttribute('data-gc-id', id);
                    var inp = document.createElement('input');
                    inp.type = 'text';
                    inp.value = val;
                    inp.placeholder = placeholder || '';
                    inp.style.width = '100%';
                    inp.style.border = '0';
                    inp.style.background = 'transparent';
                    inp.style.textAlign = 'left';
                    inp.setAttribute('data-field', nameAttr);
                    inp.setAttribute('data-id', id);
                    if (highlightColor) {
                      inp.style.color = highlightColor;
                      inp.style.fontWeight = '600';
                    } else {
                      // Non-winner rows: make all GC columns red
                      inp.style.color = '#ef4444';
                    }
                    wrapper.appendChild(inp);
                    return wrapper;
                  }

                  var winnerColor = isWinner ? '#10b981' : null;
                  table.appendChild(makeCellInput(gc, 'general_contractor', 'General contractor', winnerColor));
                  table.appendChild(makeCellInput(name, 'general_contractor_name', 'Name', winnerColor));
                  table.appendChild(makeCellInput(num, 'general_contractor_number', 'Number', winnerColor));
                  table.appendChild(makeCellInput(mail, 'general_contractor_email', 'Email', winnerColor));
                  table.appendChild(makeCellInput(addr, 'general_contractor_address', 'Address', winnerColor));

                  // Action cell: remove 'X' button on the right
                  var actionCell = document.createElement('div');
                  actionCell.style.padding = '6px 8px';
                  actionCell.style.borderBottom = '1px solid #eef2f7';
                  actionCell.style.display = 'flex';
                  actionCell.style.alignItems = 'center';
                  actionCell.style.justifyContent = 'flex-end';
                  actionCell.setAttribute('data-gc-id', id);
                  var remBtn = document.createElement('button');
                  remBtn.type = 'button';
                  remBtn.textContent = '✕';
                  remBtn.title = 'Remove contractor';
                  remBtn.style.background = 'transparent';
                  remBtn.style.border = '0';
                  remBtn.style.color = '#ef4444';
                  remBtn.style.fontWeight = '800';
                  remBtn.style.cursor = 'pointer';
                  remBtn.style.fontSize = '16px';
                  remBtn.addEventListener('click', function(ev){
                    ev && ev.stopPropagation && ev.stopPropagation();
                    try {
                      // If this contractor exists on the server (id present), mark for deletion on submit
                      if (id) {
                        var form = document.getElementById('editBidForm');
                        if (form) {
                          var hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'delete_general_contractor_ids[]'; hidden.value = id; hidden.dataset.gcHidden = '1'; form.appendChild(hidden);
                        }
                        // remove corresponding option from client winner select if present
                        try { var cs = document.getElementById('editClientWinner'); if (cs) { var opt = cs.querySelector('option[value="' + id + '"]'); if (opt) opt.parentNode.removeChild(opt); } } catch(e){}
                      }
                      // Remove all grid cells for this contractor in the table
                      var siblings = Array.from(table.querySelectorAll('[data-gc-id="' + id + '"]'));
                      siblings.forEach(function(s){ s.parentNode && s.parentNode.removeChild(s); });
                    } catch(e) { console.warn('remove gc failed', e); }
                  });
                  actionCell.appendChild(remBtn);
                  table.appendChild(actionCell);

                });
                container.innerHTML = '';
                container.appendChild(table);
              } catch(err) {
                container.innerHTML = '<div style="padding:12px;color:#ef4444">Failed to render contractors</div>';
                console.warn('loadGcList render failed', err);
              }
            }).catch(function(err){
              try { container.innerHTML = '<div style="padding:12px;color:#ef4444">Could not load contractors</div>'; } catch(e){}
              console.warn('loadGcList fetch failed', err);
            });
        } catch(e) { console.warn('loadGcList error', e); }
      }

      function openEditModal(bidObj) {
        var modal = document.getElementById('editBidModal');
        if (!modal) return;

        var idInput = document.getElementById('editBidId');
        if (idInput) idInput.value = bidObj.bid_id || '';

        var pn = document.getElementById('editProjectName');
        if (pn) pn.value = bidObj.project_name || '';

        // status select normalize and apply color
        var st = document.getElementById('editStatus');
        if (st) {
          var v = (bidObj.status || '').toString().trim().toLowerCase();
          if (v === "didnt bid" || v === "didntbid" || v === "didn'tbid" || v === "didnt") v = "didn't bid";
          if (!v) v = "pending";
          st.value = v;
          setStatusColor(v);
        }

        // fill other fields (supports inputs, selects, and textareas)
        bidColumns.forEach(function(col){
          if (col === 'project_name') return;
          var els = modal.querySelectorAll('[data-col="' + col + '"]');
          els.forEach(function(el){
            var tag = (el.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') {
              el.value = (bidObj[col] !== undefined && bidObj[col] !== null) ? bidObj[col] : '';
            }
          });
        });

        // Populate reason_other explicitly if present in the bid object
        try {
          var reasonOtherEl = modal.querySelector('[data-col="reason_other"]');
          if (reasonOtherEl) {
            reasonOtherEl.value = (bidObj.reason_other !== undefined && bidObj.reason_other !== null) ? bidObj.reason_other : '';
          }
        } catch(e) {}

        // Ensure reason "Other" text input visibility matches the selected value
        try {
          var reasonSel = modal.querySelector('.reason-select');
          if (reasonSel) {
            var ro = modal.querySelector('[data-col="reason_other"]');
            // try to match option case-insensitively to stored value
            try {
              var raw = (bidObj.reason !== undefined && bidObj.reason !== null) ? String(bidObj.reason) : '';
              if (raw) {
                var matched = false;
                Array.from(reasonSel.options).forEach(function(opt){
                  if (!opt) return;
                  var ov = (opt.value || opt.text || '').toString().toLowerCase();
                  if (ov === raw.toString().toLowerCase()) { reasonSel.value = opt.value; matched = true; }
                });
                if (!matched) reasonSel.value = raw;
              }
            } catch(e) {}
            function toggleReasonOther() { if (!ro) return; ro.style.display = (reasonSel.value === 'Other') ? 'block' : 'none'; }
            reasonSel.addEventListener('change', toggleReasonOther);
            toggleReasonOther();
          }
        } catch(e) {}

        // When project number changes in the modal, refresh client winners list
        try {
          var dhssInput = document.getElementById('editDhssProjectNumber');
          if (dhssInput) {
            dhssInput.addEventListener('input', function(){ try { if (typeof loadGcList === 'function') loadGcList(this.value); } catch(e){} });
          }
        } catch(e) {}

        // Load and display full general contractor list for this project (this will populate Client Winner)
        try {
          var projKey = (bidObj.dhss_project_number || '').toString().trim();
          try { if (typeof loadGcList === 'function') loadGcList(projKey); } catch(e){}
        } catch(e) {}

        // Ensure the select also updates if GC fields are changed live in the modal (helpful if user edits GC fields)
        try {
          var gcFieldsEls = modal.querySelectorAll('[data-col="gc_name"],[data-col="general_contractor"],[data-col="gc_number"]');
          gcFieldsEls.forEach(function(el){ el.addEventListener('input', function(){ try { if (typeof loadGcList === 'function') loadGcList(document.getElementById('editDhssProjectNumber') ? document.getElementById('editDhssProjectNumber').value : ''); } catch(e){} }); });
        } catch(e) {}


        modal.style.display = 'flex';
      }

      function closeEditModal() {
        var modal = document.getElementById('editBidModal');
        if (!modal) return;
        modal.style.display = 'none';
      }

      document.addEventListener('DOMContentLoaded', function(){
        // update color when user changes selection
        var statusEl = document.getElementById('editStatus');
        if (statusEl) {
          statusEl.addEventListener('change', function(){ setStatusColor(this.value); });
        }

        var closeBtn = document.getElementById('closeEditBid');
        if (closeBtn) closeBtn.addEventListener('click', closeEditModal);

        var editForm = document.getElementById('editBidForm');
        if (editForm) {
          // Handle GC clone creation before submitting the normal update
          editForm.addEventListener('submit', function(e){
            e.preventDefault();

            var fd = new FormData(editForm);

            // If reason select is set to "Other", send the typed `reason_other` value as `reason` instead
            try {
              var rs = editForm.querySelector('[name="reason"]');
              var ro = editForm.querySelector('[name="reason_other"]');
              if (rs && ro && rs.value === 'Other') {
                fd.set('reason', ro.value || 'Other');
                // also send reason_other field so backend can store it separately if desired
                fd.set('reason_other', ro.value || '');
              }
            } catch (e) { /* ignore if fields not present */ }

            // collect new GC entries if any
            var newGcContainer = document.getElementById('newGcContainer');
            var newClones = [];
            if (newGcContainer) {
              var rows = newGcContainer.querySelectorAll('.new-gc-row');
              rows.forEach(function(r){
                var gc = r.querySelector('input[name="new_gc_general"]');
                var name = r.querySelector('input[name="new_gc_name"]');
                var num = r.querySelector('input[name="new_gc_number"]');
                var obj = {};
                if (gc) obj['general_contractor'] = gc.value || null;
                if (name) obj['gc_name'] = name.value || null;
                if (num) obj['gc_number'] = num.value || null;
                if (obj.general_contractor || obj.gc_name || obj.gc_number) newClones.push(obj);
              });
            }

            // Also include any GC info typed into the modal's GC fields (users often edit the existing inputs)
            try {
              var modalGc = document.querySelector('#editBidModal');
              if (modalGc) {
                var mgc = (modalGc.querySelector('[data-col="general_contractor"]') || { value: '' }).value.trim();
                var mgc_name = (modalGc.querySelector('[data-col="gc_name"]') || { value: '' }).value.trim();
                var mgc_num = (modalGc.querySelector('[data-col="gc_number"]') || { value: '' }).value.trim();
                if (mgc) {
                  // avoid duplicate if already in newClones
                  var exists = newClones.find(function(x){ return x.general_contractor && x.general_contractor.toString().trim().toLowerCase() === mgc.toLowerCase(); });
                  if (!exists) {
                    newClones.push({ general_contractor: mgc, gc_name: mgc_name || null, gc_number: mgc_num || null });
                  }
                }
              }
            } catch(e) {}

            var saveBtn = document.getElementById('saveEditBid');
            if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving...'; }

            var theUpdateUrl = (typeof updateUrl !== 'undefined' && updateUrl) ? updateUrl : '../../api/update_bid.php';

            // If there are new GC rows, save them into the general_contractor table (do NOT clone bids)
            (new Promise(function(resolve, reject){
              if (!newClones.length) return resolve(null);
              // For each new GC, POST to add_general_contractor.php
              var tasks = newClones.map(function(c){
                var form = new FormData();
                if (c.general_contractor) form.append('general_contractor', c.general_contractor);
                if (c.gc_name) form.append('general_contractor_name', c.gc_name);
                if (c.gc_number) form.append('general_contractor_number', c.gc_number);
                var dhss = document.getElementById('editDhssProjectNumber') ? document.getElementById('editDhssProjectNumber').value.trim() : '';
                if (dhss) form.append('dhss_project_number', dhss);
                return fetch('../../api/add_general_contractor.php', { method: 'POST', credentials: 'same-origin', body: form }).then(function(r){ return r.json(); });
              });
              Promise.all(tasks).then(function(results){
                // If any failed, reject
                var bad = results.find(function(x){ return !x || !x.success; });
                if (bad) return reject(bad);
                resolve(results);
              }).catch(function(err){ reject(err); });
            })).then(function(){
              // Now collect edits made in the GC list and send updates for existing rows
              return new Promise(function(resolveUpdates, rejectUpdates){
                try {
                  var gcContainer = document.getElementById('gcTableList');
                  var updateTasks = [];
                  if (gcContainer) {
                    var inputs = gcContainer.querySelectorAll('input[data-field][data-id]');
                    var groups = {};
                    inputs.forEach(function(inp){
                      var id = inp.getAttribute('data-id');
                      var field = inp.getAttribute('data-field');
                      if (!id) return;
                      groups[id] = groups[id] || {};
                      groups[id][field] = inp.value || '';
                    });
                    Object.keys(groups).forEach(function(id){
                      var g = groups[id];
                      var form = new FormData();
                      form.append('id', id);
                      if (g.general_contractor !== undefined) form.append('general_contractor', g.general_contractor);
                      if (g.general_contractor_name !== undefined) form.append('general_contractor_name', g.general_contractor_name);
                      if (g.general_contractor_number !== undefined) form.append('general_contractor_number', g.general_contractor_number);
                      if (g.general_contractor_email !== undefined) form.append('general_contractor_email', g.general_contractor_email);
                      if (g.general_contractor_address !== undefined) form.append('general_contractor_address', g.general_contractor_address);
                      var dhss = document.getElementById('editDhssProjectNumber') ? document.getElementById('editDhssProjectNumber').value.trim() : '';
                      if (dhss) form.append('dhss_project_number', dhss);
                      updateTasks.push(fetch('../../api/update_general_contractor.php', { method: 'POST', credentials: 'same-origin', body: form }).then(function(r){ return r.json ? r.json() : null; }));
                    });
                  }
                  if (!updateTasks.length) return resolveUpdates(null);
                  Promise.all(updateTasks).then(function(results){
                    var bad = results.find(function(x){ return !x || !x.success; });
                    if (bad) return rejectUpdates(bad);
                    resolveUpdates(results);
                  }).catch(function(err){ rejectUpdates(err); });
                } catch(err) { rejectUpdates(err); }
              }).then(function(){
                // Ensure the selected Client Winner is persisted in general_contractor table
                return new Promise(function(resolveWinner, rejectWinner){
                  try {
                    var clientSel = document.getElementById('editClientWinner');
                    var selectedId = clientSel ? (clientSel.value || '') : '';
                    var dhss = document.getElementById('editDhssProjectNumber') ? document.getElementById('editDhssProjectNumber').value.trim() : '';
                    var fdSet = new FormData();
                    if (selectedId) fdSet.append('id', selectedId);
                    if (dhss) fdSet.append('dhss_project_number', dhss);
                    fetch('../../api/set_winner_general_contractor.php', { method: 'POST', credentials: 'same-origin', body: fdSet }).then(function(r){ return r.json ? r.json() : null; }).then(function(j){ resolveWinner(j); }).catch(function(err){ console.warn('set_winner failed', err); resolveWinner(null); });
                  } catch(e) { resolveWinner(null); }
                }).then(function(){
                  // Remove GC fields from the bid update - GC data should only live in general_contractor table
                  try {
                    fd.delete('general_contractor');
                    fd.delete('gc_name');
                    fd.delete('gc_number');
                    fd.delete('general_contractor_email');
                    fd.delete('general_contractor_address');
                    fd.delete('general_contractor_id');
                  } catch(e){}

                  return fetch(theUpdateUrl, { method: 'POST', credentials: 'same-origin', body: fd });
                });
              });
            }).then(function(r){
                var ct = (r.headers.get('content-type') || '').toLowerCase();
                return r.text().then(function(text){
                  if (ct.indexOf('text/html') !== -1 || String(text || '').trim().toLowerCase().indexOf('<!doctype html') === 0) {
                    try { showToast('Session expired - please sign in again', 'error'); } catch(e){}
                    setTimeout(function(){ window.location.href = '/auth/login.php'; }, 900);
                    throw new Error('Non-JSON HTML response');
                  }
                  return JSON.parse(text);
                });
              })
              .then(function(data){
                if (data && data.success) {
                  try { closeEditModal(); } catch(e){}
                  try { showToast('Saved', 'success'); } catch(e){}
                  try {
                    // Update the in-memory originalRows and the row DOM so changes persist without a full reload
                    var newBid = data.bid || null;
                    if (newBid && window.originalRows && window.originalRows.length) {
                      var found = window.originalRows.find(function(it){ return it && it.obj && (it.obj.bid_id && it.obj.bid_id.toString() === (newBid.bid_id || '').toString()); });
                      if (found) {
                        found.obj = newBid;
                        try { found.row.setAttribute('data-bid', JSON.stringify(newBid)); } catch(e){}
                        try {
                          // Update visible table cells for this row from the returned bid object
                          var r = found.row;
                          Object.keys(newBid).forEach(function(k){
                            try {
                              var td = r.querySelector('td[data-col="' + k + '"]');
                              if (td) {
                                // Prefer the exact user-typed value from the open modal if present
                                var modal = document.getElementById('editBidModal');
                                var inputEl = null;
                                try { if (modal) inputEl = modal.querySelector('[name="' + k + '"]') || modal.querySelector('[data-col="' + k + '"]'); } catch(e) { inputEl = null; }
                                var v = null;
                                if (inputEl && (typeof inputEl.value !== 'undefined')) {
                                  v = inputEl.value;
                                }
                                if (v === null || v === undefined) {
                                  v = newBid[k];
                                }
                                if (v === null || v === undefined) v = '';
                                td.textContent = formatForDisplay(k, v);
                              }
                            } catch(e) {}
                          });
                          // Ensure Project Location fields (city/county/state) reflect the modal inputs immediately
                          try {
                            var modal = document.getElementById('editBidModal');
                            if (modal) {
                              ['project_city','project_county','project_state'].forEach(function(k){
                                try {
                                  var inp = modal.querySelector('[name="' + k + '"]');
                                  if (inp) {
                                    var td = r.querySelector('td[data-col="' + k + '"]');
                                    if (td) td.textContent = inp.value || '';
                                  }
                                } catch(e){}
                              });
                            }
                          } catch(e){}
                        } catch(e) {}
                      }
                    }
                    try { applyFiltersAndGrouping(); } catch(e){}
                    try {
                      // Ensure GC display and highlights are refreshed immediately
                      if (typeof syncGcDisplayForProjects === 'function') syncGcDisplayForProjects();
                      var pk = newBid ? (newBid.dhss_project_number || '') : '';
                      var clientSel = document.getElementById('editClientWinner');
                      var selName = '';
                      if (clientSel && clientSel.selectedIndex >= 0) selName = clientSel.options[clientSel.selectedIndex].getAttribute('data-name') || clientSel.options[clientSel.selectedIndex].textContent || '';
                      if (pk && selName && typeof applyGcWinnerHighlight === 'function') applyGcWinnerHighlight(pk, selName);
                    } catch(e){}
                  } catch(e){}
                } else {
                  var msg = (data && data.message) ? data.message : 'Failed to save';
                  try { showToast(msg, 'error'); } catch(e){}
                }
              })
              .catch(function(){
                try { showToast('Failed to save', 'error'); } catch(e){}
              })
              .finally(function(){
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }
              });
          });
        }

        // Delete button handler inside modal
        try {
          var deleteBtn = document.getElementById('deleteBidBtn');
          if (deleteBtn) {
            deleteBtn.addEventListener('click', function(e){
              e.preventDefault();
              var idInput = document.getElementById('editBidId');
              var bidId = idInput ? idInput.value : null;
              if (!bidId) return showToast('Missing bid id', 'error');
              if (!confirm('Delete this bid? This action cannot be undone.')) return;

              deleteBtn.disabled = true; deleteBtn.textContent = 'Deleting...';

              var fd = new FormData(); fd.append('bid_id', bidId);
              fetch('../../api/delete_bid.php', { method: 'POST', credentials: 'same-origin', body: fd })
                .then(function(r){ return r.json ? r.json() : r.text(); })
                .then(function(data){
                  if (data && data.success) {
                    try { showToast('Deleted', 'success'); } catch(e){}
                    try { closeEditModal(); } catch(e){}
                    setTimeout(function(){ window.location.reload(); }, 600);
                  } else {
                    var msg = (data && data.message) ? data.message : 'Delete failed';
                    try { showToast(msg, 'error'); } catch(e){}
                    deleteBtn.disabled = false; deleteBtn.textContent = 'Delete';
                  }
                }).catch(function(){ try { showToast('Delete failed', 'error'); } catch(e){}; deleteBtn.disabled = false; deleteBtn.textContent = 'Delete'; });
            });
          }
        } catch(e) { console.warn('delete handler init failed', e); }

        // -----------------------------
        // Top scrollbar sync + sticky columns + floating header
        // -----------------------------
        try {
          var container = document.getElementById('tableContainer');
          var table = document.getElementById('bidsTable');
          var topScroller = document.getElementById('tableTopScroller');
          var topInner = document.getElementById('tableTopScrollerInner');

          // Floating header element (cloned thead) for viewport-anchored header
          var floatingHeader = document.createElement('div');
          floatingHeader.id = 'floatingHeader';
          document.body.appendChild(floatingHeader);

          function buildFloatingHeader() {
            if (!table) return;
            floatingHeader.innerHTML = '';
            var clone = document.createElement('table');
            clone.id = 'floatingBidsHeader';
            var thead = table.querySelector('thead');
            if (!thead) return;
            clone.appendChild(thead.cloneNode(true));
            floatingHeader.appendChild(clone);
            updateFloatingHeaderWidths();
          }

          function updateFloatingHeaderWidths() {
            var origThs = table.querySelectorAll('thead th');
            var cloneThs = floatingHeader.querySelectorAll('th');
            if (!origThs || !cloneThs || origThs.length !== cloneThs.length) return;
            var tableRect = table.getBoundingClientRect();
            floatingHeader.style.left = tableRect.left + 'px';
            floatingHeader.style.width = tableRect.width + 'px';
            var total = 0;
            for (var i = 0; i < origThs.length; i++) {
              var w = Math.ceil(origThs[i].getBoundingClientRect().width);
              cloneThs[i].style.width = w + 'px';
              total += w;
            }
            var inner = floatingHeader.querySelector('table');
            if (inner) inner.style.width = total + 'px';
          }

          function showOrHideFloatingHeader() {
            if (!table) return;
            var rect = table.getBoundingClientRect();
            var pageHeader = document.querySelector('.portalheader, header, .portalHeader');
            var offset = 0;
            if (pageHeader) offset = pageHeader.getBoundingClientRect().height || 0;
            var toolbar = document.querySelector('.toolbar');
            if (toolbar) offset += toolbar.getBoundingClientRect().height || 0;
            var headerHeight = (table.querySelector('thead th') && table.querySelector('thead th').getBoundingClientRect().height) || 40;

            if (rect.top < offset && rect.bottom > offset + headerHeight) {
              if (floatingHeader.style.display !== 'block') floatingHeader.style.display = 'block';
              floatingHeader.style.top = offset + 'px';
              updateFloatingHeaderWidths();
              var scrollLeft = container ? container.scrollLeft : 0;
              var inner = floatingHeader.querySelector('table');
              if (inner) inner.style.transform = 'translateX(' + (-scrollLeft) + 'px)';
            } else {
              if (floatingHeader.style.display !== 'none') floatingHeader.style.display = 'none';
            }
          }

          window.addEventListener('resize', function(){ try { updateFloatingHeaderWidths(); showOrHideFloatingHeader(); } catch(e){} });
          if (container) container.addEventListener('scroll', function(){ try { showOrHideFloatingHeader(); } catch(e){} });
          window.addEventListener('scroll', function(){ try { showOrHideFloatingHeader(); } catch(e){} });
          setTimeout(function(){ try { buildFloatingHeader(); showOrHideFloatingHeader(); } catch(e){} }, 120);

          function syncTopScroller() {
            if (!container || !table || !topScroller || !topInner) return;
            topInner.style.width = table.scrollWidth + 'px';
            topScroller.style.display = (table.scrollWidth > container.clientWidth) ? 'block' : 'none';
          }
          window.syncTopScroller = syncTopScroller; // expose for later calls

          if (topScroller && container) {
            topScroller.addEventListener('scroll', function(){ container.scrollLeft = topScroller.scrollLeft; });
            container.addEventListener('scroll', function(){ topScroller.scrollLeft = container.scrollLeft; });
            window.addEventListener('resize', syncTopScroller);
            setTimeout(syncTopScroller, 60);
          }

          function setupStickyColumns(count) {
            if (!table) return;
            var thead = table.querySelector('thead');
            var headCells = thead ? Array.from(thead.querySelectorAll('th')) : [];
            table.querySelectorAll('th, td').forEach(function(cell){ cell.style.position = ''; cell.style.left = ''; cell.style.zIndex = ''; cell.style.background = ''; cell.style.boxShadow = ''; });

            var cumulative = 0;
            for (var i=0;i<count && i < headCells.length;i++) {
              var th = headCells[i];
              var w = Math.ceil(th.getBoundingClientRect().width);
              th.style.position = 'sticky'; th.style.left = cumulative + 'px'; th.style.top = '0'; th.style.zIndex = 60; th.style.background = '#fff';
              table.querySelectorAll('tbody tr').forEach(function(row){
                var cells = row.querySelectorAll('td');
                if (cells && cells[i]) {
                  var td = cells[i]; td.style.position = 'sticky'; td.style.left = cumulative + 'px'; td.style.zIndex = 50; td.style.background = '#fff';
                }
              });
              cumulative += w;
            }
          }
          window.setupStickyColumns = setupStickyColumns; // expose for later calls

          // Update project separators: add/remove the `project-break` class on rows
          function updateProjectSeparators() {
            try {
              var tb = document.querySelector('#bidsTable tbody');
              if (!tb) return;
              var dataRows = Array.from(tb.querySelectorAll('tr[data-bid]'));
              if (!dataRows.length) return;

              function getProj(r) {
                if (!r) return '';
                var raw = r.getAttribute('data-bid');
                if (raw) {
                  try { var obj = JSON.parse(raw || '{}'); if (obj && obj.dhss_project_number) return String(obj.dhss_project_number).trim(); } catch(e) {}
                }
                var cell = r.querySelector('td[data-col="dhss_project_number"]');
                return cell ? String((cell.textContent||'').trim()) : '';
              }

              for (var i = 0; i < dataRows.length; i++) {
                var r = dataRows[i];
                // last visible data row should not show a separator
                if (i === dataRows.length - 1) { r.classList.remove('project-break'); continue; }
                var cur = getProj(r);
                var nxt = getProj(dataRows[i+1]);
                if (cur !== nxt) r.classList.add('project-break'); else r.classList.remove('project-break');
              }
            } catch(e) { console.warn('updateProjectSeparators error', e); }
          }


          setTimeout(function(){ try { setupStickyColumns(4); } catch(e){} }, 120);
          window.addEventListener('resize', function(){ try { setupStickyColumns(4); syncTopScroller(); } catch(e){} });
        } catch(e) { console.warn('sticky/topScroller init failed', e); }

        // ------------------------------------
        // NEW: Year + Status filters + Project grouping (rebuild tbody)
        // ------------------------------------
        try {
          var table = document.getElementById('bidsTable');
          var tbody = document.querySelector('#bidsTable tbody');

          var yearFilterEl = document.getElementById('yearFilter');
          var statusFilterEl = document.getElementById('statusFilter');

          // Build last 5 years dropdown (auto updates each year)
        (function initYearOptions(){
  if (!yearFilterEl) return;
  var nowY = new Date().getFullYear();

  yearFilterEl.innerHTML = '';

  // ✅ All years option
  var allOpt = document.createElement('option');
  allOpt.value = '';
  allOpt.textContent = 'All Years';
  yearFilterEl.appendChild(allOpt);

  for (var i = 0; i < 5; i++) {
    var y = nowY - i;
    var opt = document.createElement('option');
    opt.value = String(y).slice(-2); // 2026 -> "26"
    opt.textContent = String(y);
    yearFilterEl.appendChild(opt);
  }

  // choose default: current year (keep your preference)
          // Restore saved year filter if present
          try {
            var savedYear = null;
            try { savedYear = localStorage.getItem('bidTracking_yearFilter'); } catch(e) { savedYear = null; }
            if (savedYear !== null && savedYear !== undefined && yearFilterEl.querySelector('option[value="' + savedYear + '"]')) {
              yearFilterEl.value = savedYear;
            } else {
              yearFilterEl.value = String(nowY).slice(-2);
            }
          } catch(e) {
            yearFilterEl.value = String(nowY).slice(-2);
          }
          // persist changes and trigger filtering
          yearFilterEl.addEventListener('change', function(){ try { localStorage.setItem('bidTracking_yearFilter', this.value || ''); applyFiltersAndGrouping(); } catch(e){} });
})();
          function normStatus(raw) {
            var s = (raw || '').toString().trim().toLowerCase();
            s = s.replace(/[^a-z0-9]/g,'');
            if (!s) return 'pending';
            if (s === 'didntbid' || s === 'didnt') return 'didntbid';
            return s;
          }

          function getVisibleHeaderCount() {
            var ths = table ? Array.from(table.querySelectorAll('thead th')) : [];
            var count = 0;
            ths.forEach(function(th){
              if (th.style.display === 'none') return;
              count++;
            });
            return Math.max(1, count);
          }

          // Capture original rows once (source of truth)
          var originalRows = [];
          (function captureRows(){
            if (!tbody) return;
            originalRows = Array.from(tbody.querySelectorAll('tr[data-bid]')).map(function(r){
              var obj = {};
              try { obj = JSON.parse(r.getAttribute('data-bid')) || {}; } catch(e){}
              var proj = (obj.dhss_project_number || '').toString().trim();
              var yearPrefix = proj.slice(0,2);
              var st = normStatus(obj.status);
              var dateVal = null;
              if (obj.bid_date) {
                var d = new Date(obj.bid_date);
                if (!isNaN(d)) dateVal = d;
              }
              return { row: r, obj: obj, project: proj, yearPrefix: yearPrefix, status: st, date: dateVal };
            });
          })();

function applyFiltersAndGrouping() {
  if (!tbody || !table) return;

  var selectedYear = yearFilterEl ? yearFilterEl.value : '';
  var selectedStatus = statusFilterEl ? statusFilterEl.value : 'all';

  // Show status filter only when a year is selected (default is current year)
  if (statusFilterEl) statusFilterEl.hidden = !selectedYear;

  // 1) Year filter (projects starting with YY)
  var filtered = originalRows.filter(function(it){
    if (!selectedYear) return true;
    return it.project && it.project.indexOf(selectedYear) === 0;
  });

  // 2) Status filter (applies only within selected year set)
  if (selectedStatus && selectedStatus !== 'all') {
    filtered = filtered.filter(function(it){ return it.status === selectedStatus; });
  }

  // 3) Group by DHSS Project #.
  //    Sort PROJECTS globally by the earliest bid_date in the project (due-date style).
  //    Sort ROWS inside each project by bid_date ascending.
  var groups = new Map();

  filtered.forEach(function(it){
    var key = (it.project || '').toString();
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push({ it: it, date: it.date });
  });

  var groupEntries = Array.from(groups.entries()).map(function(ent){
    var key = ent[0];
    var items = ent[1];

    // Sort rows within project by bid_date ascending (nulls last)
    items.sort(function(a, b){
      var ad = a.date ? a.date.getTime() : Number.POSITIVE_INFINITY;
      var bd = b.date ? b.date.getTime() : Number.POSITIVE_INFINITY;
      return ad - bd;
    });

    // Project "due date" = earliest bid_date in project (nulls go to bottom)
    var projectDue = (items.length && items[0].date) ? items[0].date.getTime() : Number.POSITIVE_INFINITY;

    return { key: key, items: items, projectDue: projectDue };
  });

  // Sort ALL projects by earliest bid_date (earliest first), tie-break by project id
  groupEntries.sort(function(a, b){
    if (a.projectDue !== b.projectDue) return a.projectDue - b.projectDue;
    return (a.key || '').localeCompare(b.key || '');
  });

  // 4) Rebuild tbody with spacer rows between groups
  var frag = document.createDocumentFragment();
  var colCount = getVisibleHeaderCount();

  groupEntries.forEach(function(g, gi){
    if (gi !== 0) {
      var spr = document.createElement('tr');
      spr.className = 'group-spacer';
      var td = document.createElement('td');
      td.colSpan = colCount;
      spr.appendChild(td);
      frag.appendChild(spr);
    }
    g.items.forEach(function(w){ frag.appendChild(w.it.row); });
  });

  tbody.innerHTML = '';
  tbody.appendChild(frag);
  try { syncGcDisplayForProjects(); } catch(e){}

  try { window.setupStickyColumns && window.setupStickyColumns(4); } catch(e){}
  try { window.syncTopScroller && window.syncTopScroller(); } catch(e){}

  // Update project separators after tbody rebuild
  try { updateProjectSeparators(); } catch(e) { console.warn('updateProjectSeparators failed', e); }
  // re-apply GC highlight if modal is open
  try {
    var modalOpen = document.getElementById('editBidModal') && document.getElementById('editBidModal').style.display === 'flex';
    if (modalOpen) {
      var pk = document.getElementById('editDhssProjectNumber') ? document.getElementById('editDhssProjectNumber').value.trim() : '';
      var cw = document.getElementById('editClientWinner') ? document.getElementById('editClientWinner').value : '';
      try { applyGcWinnerHighlight(pk, cw); } catch(e){}
    }
  } catch(e){}
}

        // Populate Client Winner select for a given project key. Keep unique order from visible rows.
// Populate Client Winner select for a given project key using ONLY `general_contractor`
function populateClientWinners(projectKey, selectedValue) {
  try {
    var clientSel = document.getElementById('editClientWinner');
    if (!clientSel) return;

    var key = (projectKey || '').toString().trim();

    clientSel.innerHTML = '';
    var placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '--';
    clientSel.appendChild(placeholder);

    // Prefer sourcing client winners from the general_contractor table via API
    if (key) {
      try {
        fetch('../../api/get_general_contractors.php?dhss_project_number=' + encodeURIComponent(key), { credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(j){
            if (j && j.success && Array.isArray(j.contractors) && j.contractors.length) {
              var seen = new Set();
              j.contractors.forEach(function(c){
                var name = (c.general_contractor || '').toString().trim();
                if (!name) return;
                var norm = name.toLowerCase();
                if (seen.has(norm)) return; seen.add(norm);
                // option value is contractor id; text is name
                var opt = document.createElement('option'); opt.value = (c.id || '').toString(); opt.textContent = name; opt.setAttribute('data-name', name); clientSel.appendChild(opt);
              });
              // attempt to select by provided selectedValue which may be an id or a name
              try {
                if (selectedValue) {
                  // try match id
                  var matched = false;
                  var byId = clientSel.querySelector('option[value="' + String(selectedValue) + '"]');
                  if (byId) { clientSel.value = byId.value; matched = true; }
                  if (!matched) {
                    // try matching by visible text (name)
                    Array.from(clientSel.options).forEach(function(o){ if (!matched && o.textContent && o.textContent.toString().toLowerCase() === selectedValue.toString().toLowerCase()) { clientSel.value = o.value; matched = true; } });
                  }
                }
              } catch(e){}
              try {
                clientSel.onchange = function(){ try { var selOpt = clientSel.options[clientSel.selectedIndex]; var name = selOpt ? (selOpt.getAttribute('data-name') || selOpt.textContent) : ''; var id = clientSel.value || ''; if (!id) { /* clear winners for project */ fetch('../../api/set_winner_general_contractor.php', { method:'POST', credentials:'same-origin', body: new FormData() }).catch(function(){}); } else { var fd = new FormData(); fd.append('id', id); fd.append('dhss_project_number', key); fetch('../../api/set_winner_general_contractor.php', { method:'POST', credentials:'same-origin', body: fd }).then(function(r){ return r.json(); }).then(function(res){ if (res && res.success) { try { loadGcList(key); applyGcWinnerHighlight(key, name); showToast && showToast('Winner updated', 'success'); } catch(e){} } else { showToast && showToast('Failed to set winner', 'error'); } }).catch(function(){ showToast && showToast('Failed to set winner', 'error'); }); } } catch(e){} };
              } catch(e){}
              try { applyGcWinnerHighlight(key, clientSel.options[clientSel.selectedIndex] ? (clientSel.options[clientSel.selectedIndex].getAttribute('data-name') || clientSel.options[clientSel.selectedIndex].textContent) : ''); } catch(e){}
              return;
            }
            // fallback to scraping rows if API returned nothing
            populateClientWinners_fallback(key, selectedValue);
          }).catch(function(){ populateClientWinners_fallback(key, selectedValue); });
        return;
      } catch(e) {
        // fallback below
      }
    }

    // fallback: scan visible rows for general_contractor values
    populateClientWinners_fallback(key, selectedValue);
  } catch(e) {
    console.warn('populateClientWinners failed', e);
  }
}

function populateClientWinners_fallback(projectKey, selectedValue) {
  try {
    var clientSel = document.getElementById('editClientWinner');
    if (!clientSel) return;
    var key = (projectKey || '').toString().trim();
    var seen = new Set();
    var rows = Array.from(document.querySelectorAll('#bidsTable tbody tr[data-bid]'));
    rows.forEach(function(r){
      var raw = r.getAttribute('data-bid');
      var obj = {};
      try { obj = JSON.parse(raw || '{}') || {}; } catch(e) { obj = {}; }
      var proj = (obj.dhss_project_number || '').toString().trim();
      if (proj !== key) return;
      var gc = (obj.general_contractor || '').toString().trim();
      if (!gc) return;
      var norm = gc.toLowerCase();
      if (seen.has(norm)) return; seen.add(norm);
      var opt = document.createElement('option'); opt.value = gc; opt.textContent = gc; clientSel.appendChild(opt);
    });
    if (selectedValue) { try { clientSel.value = selectedValue; } catch(e){} }
    try { clientSel.onchange = function(){ try { applyGcWinnerHighlight(projectKey, this.value); } catch(e){} }; } catch(e){}
    try { applyGcWinnerHighlight(projectKey, clientSel.value); } catch(e){}
  } catch(e) { console.warn('populateClientWinners_fallback failed', e); }
}

// Highlight GC cells that match the chosen Client Winner for a project
function applyGcWinnerHighlight(projectKey, selectedGc) {
  try {
    // remove previous highlights
    Array.from(document.querySelectorAll('#bidsTable td[data-col="general_contractor"].gc-winner-highlight')).forEach(function(td){ td.classList.remove('gc-winner-highlight'); });

    if (!projectKey || !selectedGc) return;
    var normSel = selectedGc.toString().trim().toLowerCase();
    var rows = Array.from(document.querySelectorAll('#bidsTable tbody tr[data-bid]'));
    rows.forEach(function(r){
      var raw = r.getAttribute('data-bid') || '';
      var match = false;
      try {
        var obj = JSON.parse(raw || '{}') || {};
        var proj = (obj.dhss_project_number || '').toString().trim();
        var td = r.querySelector('td[data-col="general_contractor"]');
        var gc = (obj.general_contractor || (td ? td.textContent : '') || '').toString().trim();
        if (proj === projectKey && gc && gc.toLowerCase() === normSel) match = true;
      } catch(e) {
        // fallback: inspect cell text
        try {
          var td = r.querySelector('td[data-col="general_contractor"]');
          var projTd = r.querySelector('td[data-col="dhss_project_number"]');
          var projVal = projTd ? projTd.textContent.trim() : '';
          var gcText = td ? td.textContent.trim() : '';
          if (projVal === projectKey && gcText.toLowerCase() === normSel) match = true;
        } catch(ignore){}
      }

      if (match) {
        try {
          var target = r.querySelector('td[data-col="general_contractor"]');
          if (target) target.classList.add('gc-winner-highlight');
        } catch(e){}
      }
    });
  } catch(e) { console.warn('applyGcWinnerHighlight failed', e); }
}

// Sync the displayed General Contractor cell values for projects using the general_contractor table
function syncGcDisplayForProjects() {
  try {
    var rows = Array.from(document.querySelectorAll('#bidsTable tbody tr[data-bid]'));
    if (!rows.length) return;
    var projects = Array.from(new Set(rows.map(function(r){ try { var obj = JSON.parse(r.getAttribute('data-bid') || '{}') || {}; return (obj.dhss_project_number || '').toString().trim(); } catch(e) { return ''; } }).filter(function(x){ return x; })));
    projects.forEach(function(proj){
      try {
        fetch('../../api/get_general_contractors.php?dhss_project_number=' + encodeURIComponent(proj), { credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
              .then(function(j){
            try {
                  // Determine which contractor to show in table cells.
                  // If multiple contractors exist for the project, prefer the one marked `winner`.
                  // If only one contractor exists, show that contractor's info.
                  var chosen = null;
                  if (j && Array.isArray(j.contractors) && j.contractors.length) {
                    if (j.contractors.length === 1) {
                      chosen = j.contractors[0];
                    } else {
                      // multiple contractors: prefer winner, otherwise fallback to first
                      chosen = j.contractors.find(function(c){ return c.winner && (c.winner == 1 || c.winner === '1' || c.winner === true); }) || j.contractors[0];
                    }
                  }
                  rows.forEach(function(r){
                    try {
                      var obj = JSON.parse(r.getAttribute('data-bid') || '{}') || {};
                      if ((obj.dhss_project_number || '').toString().trim() === proj) {
                        if (chosen) {
                          var gcVal = (chosen.general_contractor || chosen.general_contractor_name || '').toString().trim();
                          var nameVal = (chosen.general_contractor_name || '').toString().trim();
                          var numVal = (chosen.general_contractor_number || '').toString().trim();
                          var mailVal = (chosen.general_contractor_email || '').toString().trim();
                          var addrVal = (chosen.general_contractor_address || '').toString().trim();
                          // set both long and short column names to cover different schema/column setups
                          var cellMap = [
                            {k:'general_contractor', v:gcVal}, {k:'gc_name', v:nameVal}, {k:'general_contractor_name', v:nameVal},
                            {k:'gc_number', v:numVal}, {k:'general_contractor_number', v:numVal},
                            {k:'general_contractor_email', v:mailVal}, {k:'general_contractor_address', v:addrVal}
                          ];
                          cellMap.forEach(function(it){
                            try { var td = r.querySelector('td[data-col="' + it.k + '"]'); if (td) td.textContent = it.v || ''; } catch(e){}
                          });
                        } else {
                          // no contractors: clear common GC-related cells
                          ['general_contractor','gc_name','general_contractor_name','gc_number','general_contractor_number','general_contractor_email','general_contractor_address'].forEach(function(k){
                            try { var td = r.querySelector('td[data-col="' + k + '"]'); if (td) td.textContent = ''; } catch(e){}
                          });
                        }
                      }
                    } catch(e) {}
                  });
            } catch(e) {}
          }).catch(function(){});
      } catch(e) {}
    });
  } catch(e) { console.warn('syncGcDisplayForProjects failed', e); }
}



          tbody.addEventListener('click', function(e){
            var tr = e.target && e.target.closest ? e.target.closest('tr[data-bid]') : null;
            if (!tr) return;
            // prevent header dropdown clicks from triggering row (extra safe)
            if (e.target && (e.target.id === 'yearFilter' || e.target.id === 'statusFilter')) return;
            try {
              var bidObj = JSON.parse(tr.getAttribute('data-bid'));
              openEditModal(bidObj);
            } catch(err) {
              console.error('Row JSON parse failed', err);
            }
          });

          if (yearFilterEl) yearFilterEl.addEventListener('change', applyFiltersAndGrouping);
          if (statusFilterEl) {
            // Restore saved status filter if present
            try {
              var savedStatus = null;
              try { savedStatus = localStorage.getItem('bidTracking_statusFilter'); } catch(e) { savedStatus = null; }
              if (savedStatus !== null && savedStatus !== undefined && statusFilterEl.querySelector('option[value="' + savedStatus + '"]')) {
                statusFilterEl.value = savedStatus;
              }
            } catch(e) {}
            statusFilterEl.addEventListener('change', function(){ try { localStorage.setItem('bidTracking_statusFilter', this.value || ''); applyFiltersAndGrouping(); } catch(e){} });
          }

            applyFiltersAndGrouping();
            // Ensure money fields in modal are wrapped and table cells prefixed
            try { wrapMoneyInputs(); } catch(e){}
            try { applyDollarPrefixToTableCells(); } catch(e){}
        } catch(e) { console.warn('filters+grouping failed', e); }

        // Make modal subsections collapsible
        try {
          function initModalCollapsibles() {
            var sections = document.querySelectorAll('.modal-section');
            sections.forEach(function(sec){
              var header = sec.querySelector('.header');
              var toggle = sec.querySelector('.toggle');
              if (!header) return;
              header.addEventListener('click', function(){
                var isCollapsed = sec.classList.toggle('collapsed');
                try { header.setAttribute('aria-expanded', !isCollapsed); } catch(e){}
                if (toggle) toggle.querySelector('.chev').style.transform = isCollapsed ? 'rotate(-90deg)' : 'rotate(0deg)';
              });
            });
          }
          initModalCollapsibles();
          var addGcBtn = document.getElementById('addGcBtn');
          // existing-contractor select removed; keep add-new flow only

          if (addGcBtn) {
            addGcBtn.addEventListener('click', function(e){
              e.stopPropagation();
              var container = document.getElementById('newGcContainer');
              if (!container) return;
              var row = document.createElement('div'); row.className = 'new-gc-row';
              row.style.display = 'flex'; row.style.gap = '8px'; row.style.alignItems = 'center';
              row.innerHTML = '<input name="new_gc_general" placeholder="general contractor" style="flex:1;padding:8px;border:1px solid #cbd5e1;border-radius:6px;" />'
                + '<input name="new_gc_name" placeholder="gc name" style="flex:1;padding:8px;border:1px solid #cbd5e1;border-radius:6px;" />'
                + '<input name="new_gc_number" placeholder="gc number" style="flex:1;padding:8px;border:1px solid #cbd5e1;border-radius:6px;" />'
                + '<button type="button" class="remove-gc" style="background:#fff;border:1px solid #e6edf0;padding:6px 8px;border-radius:6px;cursor:pointer;">Remove</button>';
              container.appendChild(row);
              row.querySelector('.remove-gc').addEventListener('click', function(){ container.removeChild(row); });
            });
          }
        } catch(e){ console.warn('initModalCollapsibles failed', e); }

        // -----------------------------
        // Email notifications modal
        // -----------------------------
        try {
          var enableEmailBtn = document.getElementById('enableEmailBtn');
          var emailModal = document.getElementById('emailSettingsModal');
          var closeEmailBtn = document.getElementById('closeEmailSettings');
          var cancelEmailBtn = document.getElementById('cancelEmailSettings');
          var saveEmailBtn = document.getElementById('saveEmailSettings');
          var emailDaysList = document.getElementById('emailDaysList');

          // Only allow 1 through 5 days as options
          var allowedDays = [1,2,3,4,5];
          var maxSelect = 5;

          function buildEmailDays() {
            if (!emailDaysList) return;
            emailDaysList.innerHTML = '';
            allowedDays.forEach(function(d){
              var id = 'email_day_' + d;
              var wrap = document.createElement('label');
              wrap.style.display = 'flex'; wrap.style.alignItems = 'center'; wrap.style.gap = '8px'; wrap.style.padding = '6px'; wrap.style.border = '1px solid transparent'; wrap.style.borderRadius = '6px';
              var chk = document.createElement('input'); chk.type = 'checkbox'; chk.value = String(d); chk.id = id; chk.name = 'email_days';
              var span = document.createElement('span'); span.textContent = d + ' day' + (d === 1 ? '' : 's'); span.style.color = '#0f172a'; span.style.fontWeight = '600';
              wrap.appendChild(chk); wrap.appendChild(span);
              emailDaysList.appendChild(wrap);
              chk.addEventListener('change', function(){
                var checked = Array.from(emailDaysList.querySelectorAll('input[type=checkbox]:checked'));
                if (checked.length > maxSelect) { this.checked = false; showToast('You may select up to ' + maxSelect + ' days', 'error'); }
              });
            });
          }

          function openEmailModal() {
            try { buildEmailDays(); } catch(e){}
            // restore saved selections
            try {
              var raw = localStorage.getItem('bids_email_days');
              if (raw) {
                var arr = JSON.parse(raw || '[]');
                Array.from((emailDaysList || document).querySelectorAll('input[type=checkbox]')).forEach(function(c){ c.checked = (arr.indexOf(parseInt(c.value,10)) !== -1); });
              }
            } catch(e){}
            if (emailModal) emailModal.style.display = 'flex';
          }

          function closeEmailModal() { if (emailModal) emailModal.style.display = 'none'; }

          if (enableEmailBtn) enableEmailBtn.addEventListener('click', openEmailModal);
          if (closeEmailBtn) closeEmailBtn.addEventListener('click', closeEmailModal);
          if (cancelEmailBtn) cancelEmailBtn.addEventListener('click', closeEmailModal);
          if (saveEmailBtn) {
            saveEmailBtn.addEventListener('click', function(){
              try {
                var sel = Array.from(emailDaysList.querySelectorAll('input[type=checkbox]:checked')).map(function(c){ return parseInt(c.value,10); });
                if (sel.length > maxSelect) return showToast('You may select up to ' + maxSelect + ' days', 'error');
                localStorage.setItem('bids_email_days', JSON.stringify(sel));

                // Persist server-side and trigger confirmation email
                var fd = new FormData();
                fd.append('opted_in', '1');
                fd.append('preferred_days', JSON.stringify(sel));

                fetch('/api/save_email_preferences.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                  .then(function(resp){ return resp.json(); })
                  .then(function(data){
                    if (data && data.success) {
                      showToast('Email preferences saved — confirmation sent', 'success');
                      closeEmailModal();
                    } else {
                      console.error('Save email prefs failed', data);
                      showToast('Failed to save preferences', 'error');
                    }
                  })
                  .catch(function(err){ console.error(err); showToast('Failed to save preferences', 'error'); });

              } catch(e){ showToast('Failed to save', 'error'); }
            });
          }
        } catch(e) { console.warn('email modal init failed', e); }

        // -----------------------------
        // Manage Columns modal (unchanged)
        // -----------------------------
        try {
          var manageBtn = document.getElementById('manageColumnsBtn');
          var manageModal = document.getElementById('manageColumnsModal');
          var manageList = document.getElementById('manageColumnsList');
          var closeManageBtn = document.getElementById('closeManageColumns');
          var resetBtn = document.getElementById('resetColumnsBtn');
          var cancelBtn = document.getElementById('cancelColumnsBtn');
          var saveBtn = document.getElementById('saveColumnsBtn');

          var originalConfig = (function(){
            try {
              var ths = document.querySelectorAll('#bidsTable thead th');
              var arr = [];
              ths.forEach(function(th){ var k = th.getAttribute('data-col') || null; arr.push({ name: k, visible: (th.style.display !== 'none') }); });
              return arr;
            } catch(e) { return allTableColumns.map(function(c){ return { name: c, visible: true }; }); }
          })();
          var defaultConfig = originalConfig.slice();

          function getSavedConfig(){
            try { var s = localStorage.getItem('bidsColumnConfig'); return s ? JSON.parse(s) : null; } catch(e){ return null; }
          }

          function buildManageList(config){
            if (!manageList) return;
            manageList.innerHTML = '';
            config.forEach(function(item){
              var li = document.createElement('li'); li.dataset.col = item.name;
              li.style.display = 'flex'; li.style.alignItems = 'center'; li.style.justifyContent = 'space-between'; li.style.gap = '8px';
              li.style.padding = '8px'; li.style.border = '1px solid rgba(14,20,26,0.04)'; li.style.borderRadius = '8px'; li.style.background = '#fff';
              var left = document.createElement('div'); left.style.display = 'flex'; left.style.alignItems = 'center'; left.style.gap = '10px';
              var chk = document.createElement('input'); chk.type = 'checkbox'; chk.checked = !!item.visible; chk.style.width = '16px'; chk.style.height = '16px'; chk.dataset.col = item.name;
              var lbl = document.createElement('div');
              var displayLabel = (function(k){
                if (!k) return '';
                if (k === 'dhss_project_number') return 'DHSS Project #';
                if (k === 'gc_name') return 'General Contractor Name';
                if (k === 'gc_number') return 'General Contractor Number';
                if (/gc/i.test(k) || k === 'general_contractor') return 'General Contractor';
                return k.replace(/_/g,' ').replace(/\b\w/g, function(ch){ return ch.toUpperCase(); });
              })(item.name);
              lbl.textContent = displayLabel;
              lbl.style.color = '#0f172a'; lbl.style.fontWeight = 700; lbl.style.fontSize = '13px';
              left.appendChild(chk); left.appendChild(lbl);
              var grip = document.createElement('div'); grip.textContent = '≡'; grip.className = 'drag-grip'; grip.style.opacity = '0.6';
              var origIndex = originalConfig.findIndex(function(x){ return x.name === item.name; });
              var locked = (origIndex !== -1 && origIndex < 4);
              if (locked) {
                chk.checked = true;
                chk.disabled = true;
                li.dataset.locked = '1';
                grip.setAttribute('draggable','false');
                grip.style.opacity = '0.3';
                grip.style.cursor = 'not-allowed';
              } else {
                grip.setAttribute('draggable','true');
              }
              li.appendChild(left); li.appendChild(grip);
              manageList.appendChild(li);
            });
            attachDragHandlers();
          }

          function attachDragHandlers(){
            var dragging = null;
            var placeholder = null;

            function createPlaceholder(){
              var ph = document.createElement('li');
              ph.className = 'drop-placeholder';
              var inner = document.createElement('div'); inner.className = 'placeholder-inner'; inner.textContent = 'Drop here';
              ph.appendChild(inner);
              return ph;
            }

            manageList.addEventListener('dragstart', function(e){
              var grip = e.target.closest ? e.target.closest('.drag-grip') : null;
              if (!grip) { e.preventDefault(); return; }
              if (grip.getAttribute('draggable') === 'false') { e.preventDefault(); return; }
              var li = grip.closest('li');
              if (!li || li.dataset.locked) { e.preventDefault(); return; }
              dragging = li;
              li.classList.add('dragging');
              placeholder = createPlaceholder();
              li.parentNode.insertBefore(placeholder, li.nextSibling);
              try { e.dataTransfer.setData('text/plain',''); } catch(ex){}
              e.dataTransfer.effectAllowed = 'move';
            });

            manageList.addEventListener('dragend', function(){
              if (dragging) dragging.classList.remove('dragging');
              dragging = null;
              if (placeholder && placeholder.parentNode) placeholder.parentNode.removeChild(placeholder);
              placeholder = null;
            });

            manageList.addEventListener('dragover', function(e){
              e.preventDefault();
              if (!dragging) return;
              var targetLi = e.target.closest ? e.target.closest('li') : null;
              if (targetLi && targetLi === placeholder) return;

              if (targetLi && !targetLi.dataset.locked) {
                if (placeholder.parentNode !== manageList || placeholder.nextSibling !== targetLi) {
                  manageList.insertBefore(placeholder, targetLi);
                }
              } else {
                var lastLocked = null;
                var kids = Array.from(manageList.querySelectorAll('li'));
                for (var m = 0; m < kids.length; m++) { if (kids[m].dataset.locked) lastLocked = kids[m]; }
                if (lastLocked) {
                  if (lastLocked.nextSibling !== placeholder) manageList.insertBefore(placeholder, lastLocked.nextSibling);
                } else {
                  if (placeholder.parentNode !== manageList) manageList.appendChild(placeholder);
                }
              }
            });

            manageList.addEventListener('drop', function(e){
              e.preventDefault();
              if (!dragging) return;
              if (placeholder && placeholder.parentNode) {
                manageList.insertBefore(dragging, placeholder);
                placeholder.parentNode.removeChild(placeholder);
              }
              if (dragging) dragging.classList.remove('dragging');
              dragging = null; placeholder = null;
            });
          }

          function openManageModal(){
            var saved = getSavedConfig();
            var cfg = saved ? saved : defaultConfig.slice();
            buildManageList(cfg);
            if (manageModal) manageModal.style.display = 'flex';
          }

          function resetManageList(){ buildManageList(originalConfig.slice()); }
          function closeManage(){ if (manageModal) manageModal.style.display = 'none'; }

          function saveManage(){
            if (!manageList) return;
            var items = Array.from(manageList.querySelectorAll('li')).map(function(li){
              var name = li.dataset.col; var chk = li.querySelector('input[type="checkbox"]');
              return { name: name, visible: !!(chk && chk.checked), locked: !!li.dataset.locked };
            });

            var lockedFront = originalConfig.slice(0,4).map(function(x){ return x.name; }).filter(Boolean);
            var ordered = [];
            lockedFront.forEach(function(k){
              var it = items.find(function(i){ return i.name === k; });
              if (!it) { ordered.push({ name: k, visible: true, locked: true }); }
              else { it.visible = true; it.locked = true; ordered.push(it); }
            });
            items.forEach(function(i){ if (lockedFront.indexOf(i.name) === -1) ordered.push(i); });

            try { localStorage.setItem('bidsColumnConfig', JSON.stringify(ordered)); } catch(e){}
            applyColumnConfig(ordered);
            closeManage();
          }

          function applyColumnConfig(cfg){
            if (!cfg || !cfg.length) return;
            var theadRow = document.querySelector('#bidsTable thead tr');
            if (!theadRow) return;
            var thMap = {};
            Array.from(theadRow.querySelectorAll('th')).forEach(function(th){ var k = th.getAttribute('data-col'); if (k) thMap[k] = th; });

            var frag = document.createDocumentFragment();
            cfg.forEach(function(item){
              var th = thMap[item.name];
              if (th) { th.style.display = item.visible ? '' : 'none'; frag.appendChild(th); }
            });
            Array.from(theadRow.querySelectorAll('th')).forEach(function(th){
              var k = th.getAttribute('data-col');
              if (!k) return;
              if (!cfg.find(function(x){ return x.name === k; })) frag.appendChild(th);
            });
            theadRow.innerHTML = '';
            theadRow.appendChild(frag);

          
            var rows = document.querySelectorAll('#bidsTable tbody tr');
rows.forEach(function(tr){

  // ✅ Keep the spacer row's TD so separator line doesn't disappear
  if (tr.classList && tr.classList.contains('group-spacer')) {
    var td = tr.querySelector('td');
    if (!td) {
      td = document.createElement('td');
      tr.appendChild(td);
    }
    // update colspan to visible columns
    td.colSpan = cfg.filter(function(x){ return x.visible; }).length || 1;
    td.style.display = '';
    return;
  }

  var tdMap = {};
  Array.from(tr.querySelectorAll('td')).forEach(function(td){
    var k = td.getAttribute('data-col');
    if (k) tdMap[k] = td;
  });

  var df = document.createDocumentFragment();
  cfg.forEach(function(item){
    var td = tdMap[item.name];
    if (td) {
      td.style.display = item.visible ? '' : 'none';
      df.appendChild(td);
    }
  });

  Object.keys(tdMap).forEach(function(k){
    if (!cfg.find(function(x){ return x.name === k; })) df.appendChild(tdMap[k]);
  });

  tr.innerHTML = '';
  tr.appendChild(df);
});
            try { window.setupStickyColumns && window.setupStickyColumns(4); } catch(e){}
            try { window.syncTopScroller && window.syncTopScroller(); } catch(e){}
            // re-apply GC highlight if modal is open after column reflow
            try {
              var modalOpen = document.getElementById('editBidModal') && document.getElementById('editBidModal').style.display === 'flex';
              if (modalOpen) {
                var pk = document.getElementById('editDhssProjectNumber') ? document.getElementById('editDhssProjectNumber').value.trim() : '';
                var cw = document.getElementById('editClientWinner') ? document.getElementById('editClientWinner').value : '';
                try { applyGcWinnerHighlight(pk, cw); } catch(e){}
              }
            } catch(e){}
          }

          if (manageBtn) manageBtn.addEventListener('click', openManageModal);
          if (closeManageBtn) closeManageBtn.addEventListener('click', closeManage);
          if (resetBtn) resetBtn.addEventListener('click', function(){ resetManageList(); });
          if (cancelBtn) cancelBtn.addEventListener('click', function(){ closeManage(); });
          if (saveBtn) saveBtn.addEventListener('click', function(){ saveManage(); });

          try { var saved = getSavedConfig(); if (saved) applyColumnConfig(saved); } catch(e){}
        } catch(e){ console.warn('manage columns init failed', e); }

      });
    })();
  </script>
</body>
</html>
