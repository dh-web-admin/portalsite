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

    /* Row separators and hover state */
    #bidsTable tbody tr {
      border-bottom: 1px solid #f1f5f9;
      transition: background .12s ease;
    }
    #bidsTable tbody tr:hover { background: #f8fafc; }

    /* Notes column exception (keep allowing it to be wider) */
    #bidsTable td.notes-col, #bidsTable th.notes-col { max-width: 420px; }

    /* Group spacer (leave intact but slightly tuned to match new visual language) */
    .group-spacer td {
      padding: 0;
      height: 12px;
      background: linear-gradient(90deg, rgba(203,213,225,0.06), rgba(230,238,240,0));
      border-top: 2px solid rgba(229,231,235,0.9);
      border-bottom: 1px solid rgba(229,231,235,0.6);
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);
    }

    /* Ensure sticky column cells retain solid backgrounds while scrolling */
    #bidsTable th[style*="position: sticky"], #bidsTable td[style*="position: sticky"] {
      background: rgba(255,255,255,0.98) !important;
      -webkit-backdrop-filter: none;
      backdrop-filter: none;
    }

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
                          // Insert an empty header cell before DHSS project # for the status pill (no header text)
                            if ($col === 'dhss_project_number') {
                              echo '<th class="col-status" data-col="status"></th>';
                            }
                            // Build a human-friendly, title-cased label. Special cases for DHSS and GC columns.
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
                            if ($col === 'dhss_project_number') {
                              echo '<th class="col-dhss" data-col="' . htmlspecialchars($col) . '">' . htmlspecialchars($label) . '</th>';
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
                    <?php foreach ($bidRows as $r) { ?>
                      <tr data-bid='<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8"); ?>' style="cursor:pointer;">
                      <?php
                        foreach ($bidColumns as $col) {
                          if ($col === 'status') continue;
                          if ($col === 'dhss_project_number') {
                            $statusRaw = isset($r['status']) ? $r['status'] : '';
                            $statusKey = strtolower(trim((string)$statusRaw));
                            $normalized = preg_replace('/[^a-z0-9]/', '', $statusKey);

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
                    // reserve dhss_project_number and bid_date to be shown in the top area
                    if ($col === 'dhss_project_number' || $col === 'bid_date') continue;
                    $lc = strtolower($col);

                    // Project Location specific fields
                    if (strpos($lc, 'project_city') !== false || strpos($lc, 'project_county') !== false || strpos($lc, 'project_state') !== false) {
                      $locFields[] = $col;
                      continue;
                    }

                    // General Contractor related fields
                    if (strpos($lc, 'gc') !== false || strpos($lc, 'general_contractor') !== false) {
                      $gcFields[] = $col;
                      continue;
                    }

                    // Material type or price fields should live in specifications
                    if (preg_match('/material|material_type/i', $lc) || preg_match('/total.*price|price|total_price/i', $lc)) {
                      $specFields[] = $col;
                      continue;
                    }

                    // Project specification related fields (fallback)
                    if (strpos($lc, 'dhss_project_number') !== false || strpos($lc, 'project_') === 0 || strpos($lc, 'bid_date') !== false || preg_match('/square|ton|dimension|area|spec/i', $lc)) {
                      $specFields[] = $col;
                      continue;
                    }

                    // Everything else
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
                  /* Make the General Contractor section use three columns so its fields fit on one row */
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
                      // Title-case labels and handle special cases
                      if ($col === 'gc_name') { $label = 'General Contractor Name'; }
                      elseif ($col === 'gc_number') { $label = 'General Contractor Number'; }
                      elseif (strpos(strtolower($col),'gc') !== false || $col === 'general_contractor') { $label = 'General Contractor'; }
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
                    <?php foreach ($gcFields as $col) {
                      // GC-specific label mapping: distinct labels for name/number
                      if ($col === 'gc_name') { $label = 'General Contractor Name'; }
                      elseif ($col === 'gc_number') { $label = 'General Contractor Number'; }
                      elseif (strpos(strtolower($col),'gc') !== false || $col === 'general_contractor') { $label = 'General Contractor'; }
                      elseif ($col === 'dhss_project_number') { $label = 'DHSS Project #'; }
                      else { $label = ucwords(str_replace('_',' ',$col)); }
                    ?>
                      <div class="field">
                        <label><?php echo htmlspecialchars($label); ?></label>
                        <input type="text" data-col="<?php echo htmlspecialchars($col); ?>" name="<?php echo htmlspecialchars($col); ?>" />
                      </div>
                    <?php } ?>
                    <!-- container for any new GC entries the user adds -->
                    <div id="newGcContainer" style="grid-column:1/-1;display:flex;flex-direction:column;gap:8px;margin-top:8px;"></div>
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
                      // We'll render notes separately below; skip it here so it doesn't appear twice
                      if ($col === 'notes') continue;
                      if ($col === 'gc_name') { $label = 'General Contractor Name'; }
                      elseif ($col === 'gc_number') { $label = 'General Contractor Number'; }
                      elseif (strpos(strtolower($col),'gc') !== false || $col === 'general_contractor') { $label = 'General Contractor'; }
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

                <!-- Notes: shown as a full-width textarea at the end of the modal -->
                <?php if (in_array('notes', $bidColumns, true)) { ?>
                  <div style="margin-top:12px;">
                    <label style="font-weight:600;color:#475569;display:block;margin-bottom:6px;">Notes</label>
                    <textarea id="editNotes" name="notes" data-col="notes" style="width:100%;min-height:120px;padding:10px;border:1px solid #cbd5e1;border-radius:6px;"></textarea>
                  </div>
                <?php } ?>

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px;">
                  <button type="button" id="closeEditBid" style="background:#fff;border:1px solid #e6edf0;color:#0f172a;padding:10px 14px;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
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

        modal.style.display = 'flex';
      }

      function closeEditModal() {
        var modal = document.getElementById('editBidModal');
        if (!modal) return;
        modal.style.display = 'none';
      }

      document.addEventListener('DOMContentLoaded', function(){
        var rows = document.querySelectorAll('table tbody tr[data-bid]');
        rows.forEach(function(r){
          r.addEventListener('click', function(){
            try {
              var bidObj = JSON.parse(r.getAttribute('data-bid'));
              openEditModal(bidObj);
            } catch(e) {
              console.error('Row JSON parse failed', e);
            }
          });
        });

        var closeBtn = document.getElementById('closeEditBid');
        if (closeBtn) closeBtn.addEventListener('click', closeEditModal);

        // update color when user changes selection
        var statusEl = document.getElementById('editStatus');
        if (statusEl) {
          statusEl.addEventListener('change', function(){ setStatusColor(this.value); });
        }

        var editForm = document.getElementById('editBidForm');
        if (editForm) {
          // Handle GC clone creation before submitting the normal update
          editForm.addEventListener('submit', function(e){
            e.preventDefault();

            var fd = new FormData(editForm);

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
                // only include if at least one field provided
                if (obj.general_contractor || obj.gc_name || obj.gc_number) newClones.push(obj);
              });
            }

            // Debug
            try {
              var obj = {};
              fd.forEach(function(v,k){ obj[k] = v; });
              console.log('Submitting update_bid:', obj);
            } catch(err) {}

            var saveBtn = document.getElementById('saveEditBid');
            if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving...'; }

            // Use the environment-safe `updateUrl` defined earlier instead of hardcoded path
            var theUpdateUrl = (typeof updateUrl !== 'undefined' && updateUrl) ? updateUrl : '../../api/update_bid.php';
            console.log('Final update URL used:', theUpdateUrl);

            // If there are new GC clones, POST them first to clone the bid row(s)
            (new Promise(function(resolve, reject){
              if (!newClones.length) return resolve(null);
              var cloneUrl = '../../api/clone_bid_with_gc.php';
              console.log('Posting clones to', cloneUrl, newClones);
              fetch(cloneUrl, { method: 'POST', credentials: 'same-origin', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ bid_id: fd.get('bid_id'), clones: newClones }) })
                .then(function(r){ return r.json(); })
                .then(function(data){ if (data && data.success) resolve(data); else reject(data); })
                .catch(function(err){ reject(err); });
            })).then(function(cloneResult){
              if (cloneResult) console.log('Clone result', cloneResult);
              // proceed with normal update
              return fetch(theUpdateUrl, { method: 'POST', credentials: 'same-origin', body: fd });
            }).then(function(r){
                console.log('HTTP status:', r.status);
                var ct = (r.headers.get('content-type') || '').toLowerCase();
                console.log('content-type:', ct);
                return r.text().then(function(text){
                  console.log('raw response (first 300 chars):', (text || '').slice(0,300));
                  // If server returned HTML (likely a login redirect), handle gracefully
                  if (ct.indexOf('text/html') !== -1 || String(text || '').trim().toLowerCase().indexOf('<!doctype html') === 0) {
                    console.warn('update_bid: received HTML response, likely redirect to login or server error');
                    // Show specific message and redirect to login so user can re-authenticate
                    try { showToast('Session expired - please sign in again', 'error'); } catch(e){ console.warn('showToast missing', e); }
                    setTimeout(function(){ window.location.href = '/auth/login.php'; }, 900);
                    throw new Error('Non-JSON HTML response');
                  }
                  try {
                    return JSON.parse(text);
                  } catch (e) {
                    console.error('JSON parse error:', e, 'raw:', (text||'').slice(0,300));
                    throw e;
                  }
                });
              })
              .then(function(data){
                console.log('update_bid response', data);
                if (data && data.success) {
                  console.log('update_bid: success — closing modal then showing toast');
                  try { closeEditModal(); } catch(e) { console.warn('closeEditModal error', e); }
                  try { showToast('Saved', 'success'); } catch(e) { console.warn('showToast error', e); }
                  setTimeout(function(){ window.location.reload(); }, 800);
                } else {
                  var msg = (data && data.message) ? data.message : 'Failed to save';
                  console.warn('update_bid: server returned failure', msg);
                  try { showToast(msg, 'error'); } catch(e) { console.warn('showToast error', e); }
                }
              })
              .catch(function(err){
                console.error('update_bid fetch error', err);
                showToast('Failed to save', 'error');
              })
              .finally(function(){
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }
              });
          });
        }

        // -----------------------------
        // Top scrollbar sync + sticky columns
        // -----------------------------
        try {
          var container = document.getElementById('tableContainer');
          var table = document.getElementById('bidsTable');
          var topScroller = document.getElementById('tableTopScroller');
          var topInner = document.getElementById('tableTopScrollerInner');

          function syncTopScroller() {
            if (!container || !table || !topScroller || !topInner) return;
            topInner.style.width = table.scrollWidth + 'px';
            topScroller.style.display = (table.scrollWidth > container.clientWidth) ? 'block' : 'none';
          }

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
            // reset
            table.querySelectorAll('th, td').forEach(function(cell){ cell.style.position = ''; cell.style.left = ''; cell.style.zIndex = ''; cell.style.background = ''; cell.style.boxShadow = ''; });

            var cumulative = 0;
            for (var i=0;i<count && i < headCells.length;i++) {
              var th = headCells[i];
              var w = Math.ceil(th.getBoundingClientRect().width);
              th.style.position = 'sticky'; th.style.left = cumulative + 'px'; th.style.top = '0'; th.style.zIndex = 60; th.style.background = '#fff';
              // apply to body cells
              table.querySelectorAll('tbody tr').forEach(function(row){
                var cells = row.querySelectorAll('td');
                if (cells && cells[i]) {
                  var td = cells[i]; td.style.position = 'sticky'; td.style.left = cumulative + 'px'; td.style.zIndex = 50; td.style.background = '#fff';
                }
              });
              cumulative += w;
            }
          }

          // init sticky for first 4 columns
          setTimeout(function(){ try { setupStickyColumns(4); } catch(e){} }, 120);
          window.addEventListener('resize', function(){ try { setupStickyColumns(4); syncTopScroller(); } catch(e){} });
        } catch(e) { console.warn('sticky/topScroller init failed', e); }

        // Group projects by dhss_project_number and sort rows within group by nearest bid_date to today
        try {
          function applyProjectGrouping() {
            var tbody = document.querySelector('#tableContainer table tbody');
            var table = document.getElementById('bidsTable');
            if (!tbody || !table) return;

            var rows = Array.from(tbody.querySelectorAll('tr[data-bid]'));
            var groups = new Map();
            var now = new Date();

            rows.forEach(function(r){
              try {
                var obj = JSON.parse(r.getAttribute('data-bid')) || {};
                var key = (obj.dhss_project_number || '').toString();
                var dateVal = null;
                if (obj.bid_date) {
                  var d = new Date(obj.bid_date);
                  if (!isNaN(d)) dateVal = d;
                }
                if (!groups.has(key)) groups.set(key, []);
                groups.get(key).push({ row: r, date: dateVal });
              } catch(e) { console.warn('group parse error', e); }
            });

            // compute group nearest distance and sort members
            var groupEntries = Array.from(groups.entries()).map(function(ent){
              var key = ent[0];
              var items = ent[1];
              items.forEach(function(it){
                it.dist = it.date ? Math.abs(it.date - now) : Number.POSITIVE_INFINITY;
              });
              items.sort(function(a,b){ return a.dist - b.dist; });
              var nearest = items.length ? items[0].dist : Number.POSITIVE_INFINITY;
              return { key: key, items: items, nearest: nearest };
            });

            // sort groups by nearest date ascending
            groupEntries.sort(function(a,b){ return a.nearest - b.nearest; });

            // rebuild tbody with spacer rows between groups
            var frag = document.createDocumentFragment();
            var headerCount = table.querySelectorAll('thead th').length || 1;
            groupEntries.forEach(function(g, gi){
              // spacer for groups except first
              if (gi !== 0) {
                var spr = document.createElement('tr'); spr.className = 'group-spacer';
                var td = document.createElement('td'); td.colSpan = headerCount; spr.appendChild(td);
                frag.appendChild(spr);
              }
              g.items.forEach(function(it){ frag.appendChild(it.row); });
            });

            // clear and append
            tbody.innerHTML = '';
            tbody.appendChild(frag);
            try { setupStickyColumns(4); syncTopScroller(); } catch(e){}
          }

          // apply grouping now and whenever table content changes (basic)
          applyProjectGrouping();
        } catch(e) { console.warn('applyProjectGrouping failed', e); }

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
          // init add GC button
          var addGcBtn = document.getElementById('addGcBtn');
          if (addGcBtn) {
            addGcBtn.addEventListener('click', function(e){
              e.stopPropagation();
              var container = document.getElementById('newGcContainer');
              if (!container) return;
              // create new-gc-row
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
        // Manage Columns modal
        // -----------------------------
        try {
          var manageBtn = document.getElementById('manageColumnsBtn');
          var manageModal = document.getElementById('manageColumnsModal');
          var manageList = document.getElementById('manageColumnsList');
          var closeManageBtn = document.getElementById('closeManageColumns');
          var resetBtn = document.getElementById('resetColumnsBtn');
          var cancelBtn = document.getElementById('cancelColumnsBtn');
          var saveBtn = document.getElementById('saveColumnsBtn');

          // capture the original header order & visibility from DOM at init
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
              // friendly display label for Manage Columns list
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
              // determine if this item is locked (one of the first 4 original columns)
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
            // Tacky drag-reorder implementation using grip-only draggable elements
            var dragging = null;
            var placeholder = null;
            var lockedCount = (originalConfig && originalConfig.length) ? Math.min(4, originalConfig.length) : 0;

            function createPlaceholder(){
              var ph = document.createElement('li');
              ph.className = 'drop-placeholder';
              var inner = document.createElement('div'); inner.className = 'placeholder-inner'; inner.textContent = 'Drop here';
              ph.appendChild(inner);
              return ph;
            }

            // dragstart delegated from grips only
            manageList.addEventListener('dragstart', function(e){
              var grip = e.target.closest ? e.target.closest('.drag-grip') : null;
              if (!grip) { e.preventDefault(); return; }
              // if grip explicitly marked non-draggable (locked), block
              if (grip.getAttribute('draggable') === 'false') { e.preventDefault(); return; }
              var li = grip.closest('li');
              if (!li || li.dataset.locked) { e.preventDefault(); return; }
              dragging = li;
              li.classList.add('dragging');
              // create placeholder after the dragged item initially
              placeholder = createPlaceholder();
              li.parentNode.insertBefore(placeholder, li.nextSibling);
              try { e.dataTransfer.setData('text/plain',''); } catch(ex){}
              e.dataTransfer.effectAllowed = 'move';
            });

            manageList.addEventListener('dragend', function(e){
              if (dragging) dragging.classList.remove('dragging');
              dragging = null;
              if (placeholder && placeholder.parentNode) placeholder.parentNode.removeChild(placeholder);
              placeholder = null;
            });

            // live preview movement
            manageList.addEventListener('dragover', function(e){
              e.preventDefault();
              if (!dragging) return;
              var targetLi = e.target.closest ? e.target.closest('li') : null;
              // If hovering over the placeholder itself, do nothing
              if (targetLi && targetLi === placeholder) return;

              // compute allowed insertion point: can't insert before locked items
              var children = Array.from(manageList.querySelectorAll('li')).filter(function(n){ return n !== dragging && n !== placeholder; });
              // find index where to insert (before targetLi), otherwise at end
              var insertBeforeNode = null;
              if (targetLi && targetLi !== placeholder) {
                // do not allow dropping on or before locked items
                var idx = children.indexOf(targetLi);
                // if target is locked or its index < lockedCount, set to first non-locked item
                var targetLocked = !!targetLi.dataset.locked;
                if (targetLocked) {
                  // find first child after locked block
                  for (var i = 0; i < children.length; i++) {
                    if (!children[i].dataset.locked) { insertBeforeNode = children[i]; break; }
                  }
                } else {
                  insertBeforeNode = targetLi.nextSibling === placeholder ? targetLi.nextSibling : targetLi;
                }
              } else {
                // if no target, append at end but ensure not before locked block
                // find first non-locked child to insert before, otherwise append
                insertBeforeNode = null;
                for (var j = 0; j < children.length; j++) {
                  if (!children[j].dataset.locked) { insertBeforeNode = null; }
                }
              }

              // enforce placeholder not to move above lockedCount
              var firstNonLocked = null;
              var allChildren = Array.from(manageList.querySelectorAll('li'));
              for (var k = 0; k < allChildren.length; k++) {
                if (!allChildren[k].dataset.locked) { firstNonLocked = allChildren[k]; break; }
              }

              // place placeholder intelligently: before targetLi if target is non-locked, else after locked block
              if (targetLi && !targetLi.dataset.locked) {
                // insert placeholder before targetLi
                if (placeholder.parentNode !== manageList || placeholder.nextSibling !== targetLi) {
                  manageList.insertBefore(placeholder, targetLi);
                }
              } else {
                // insert after last locked item
                var lastLocked = null;
                var kids = Array.from(manageList.querySelectorAll('li'));
                for (var m = 0; m < kids.length; m++) { if (kids[m].dataset.locked) lastLocked = kids[m]; }
                if (lastLocked) {
                  if (lastLocked.nextSibling !== placeholder) manageList.insertBefore(placeholder, lastLocked.nextSibling);
                } else {
                  // no locked items - append to end
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
              // cleanup
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
            // Ensure locked first-4 stay at the front in original order and are visible
            var lockedFront = originalConfig.slice(0,4).map(function(x){ return x.name; }).filter(Boolean);
            var ordered = [];
            lockedFront.forEach(function(k){ var it = items.find(function(i){ return i.name === k; }); if (!it) { ordered.push({ name: k, visible: true, locked: true }); } else { it.visible = true; it.locked = true; ordered.push(it); } });
            // append remaining items in current order
            items.forEach(function(i){ if (lockedFront.indexOf(i.name) === -1) ordered.push(i); });
            try { localStorage.setItem('bidsColumnConfig', JSON.stringify(ordered)); } catch(e) { console.warn('save columns failed', e); }
            applyColumnConfig(ordered);
            closeManage();
          }

          function applyColumnConfig(cfg){
            if (!cfg || !cfg.length) return;
            var theadRow = document.querySelector('#bidsTable thead tr');
            if (!theadRow) return;
            var thMap = {};
            Array.from(theadRow.querySelectorAll('th')).forEach(function(th){ var k = th.getAttribute('data-col'); if (k) thMap[k] = th; });
            // build new header order
            var frag = document.createDocumentFragment();
            cfg.forEach(function(item){ var th = thMap[item.name]; if (th) {
              th.style.display = item.visible ? '' : 'none'; frag.appendChild(th);
            }});
            // append any headers not included in cfg at end
            Array.from(theadRow.querySelectorAll('th')).forEach(function(th){ var k = th.getAttribute('data-col'); if (!k) return; if (!cfg.find(function(x){ return x.name === k; })) frag.appendChild(th); });
            theadRow.innerHTML = '';
            theadRow.appendChild(frag);

            // apply to body rows
            var rows = document.querySelectorAll('#bidsTable tbody tr');
            rows.forEach(function(tr){
              var tdMap = {};
              Array.from(tr.querySelectorAll('td')).forEach(function(td){ var k = td.getAttribute('data-col'); if (k) tdMap[k] = td; });
              var df = document.createDocumentFragment();
              cfg.forEach(function(item){ var td = tdMap[item.name]; if (td) { td.style.display = item.visible ? '' : 'none'; df.appendChild(td); } });
              // append any tds not in cfg
              Object.keys(tdMap).forEach(function(k){ if (!cfg.find(function(x){ return x.name === k; })) df.appendChild(tdMap[k]); });
              tr.innerHTML = '';
              tr.appendChild(df);
            });
            try { // ensure sticky columns recalculated
              setupStickyColumns(4); syncTopScroller();
            } catch(e){}
          }

          if (manageBtn) manageBtn.addEventListener('click', openManageModal);
          if (closeManageBtn) closeManageBtn.addEventListener('click', closeManage);
          if (resetBtn) resetBtn.addEventListener('click', function(){ resetManageList(); });
          if (cancelBtn) cancelBtn.addEventListener('click', function(){ closeManage(); });
          if (saveBtn) saveBtn.addEventListener('click', function(){ saveManage(); });

          // on load apply saved config if present
          try { var saved = getSavedConfig(); if (saved) applyColumnConfig(saved); } catch(e){}
        } catch(e){ console.warn('manage columns init failed', e); }
      });
    })();
  </script>
</body>
</html>
