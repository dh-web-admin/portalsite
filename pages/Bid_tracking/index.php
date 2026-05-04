
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

// Dynamically load columns from general_contractor table
$gcBlock = [];
try {
  $gcTableCheck = $conn->query("SHOW TABLES LIKE 'general_contractor'");
  if ($gcTableCheck && $gcTableCheck->num_rows) {
    $gcColResult = $conn->query("SHOW COLUMNS FROM general_contractor");
    if ($gcColResult) {
      while ($gc = $gcColResult->fetch_assoc()) {
        $gcField = $gc['Field'];
        // Exclude metadata, id, and join key columns
        if (in_array($gcField, ['id','created_at','updated_at','dhss_project_number'], true)) continue;
        $gcBlock[] = $gcField;
      }
    }
  }
} catch (Throwable $ex) {
  // Fallback to hardcoded GC columns if query fails
  $gcBlock = ['general_contractor','general_contractor_name','general_contractor_number','general_contractor_email','general_contractor_address','client_win_price'];
}

// Ensure GC columns are treated as first-class UI columns and enforce desired ordering
if ($bidTableExists && !empty($gcBlock)) {
  // normalize existing column keys
  $existing = $bidColumns;
  // desired placement: keep existing order but inject GC block after project_state
  // find insertion index: after project_state if present, otherwise first GC/client_winner, else end
  $insertAt = null;
  foreach ($existing as $i => $c) {
    $lc = strtolower($c);
    if ($lc === 'project_state') { $insertAt = $i + 1; break; }
  }
  if ($insertAt === null) {
    foreach ($existing as $i => $c) {
      $lc = strtolower($c);
      if ($lc === 'general_contractor' || $lc === 'client_winner' || strpos($lc,'gc') === 0 || strpos($lc,'gc_') === 0 || strpos($lc,'general_contractor') !== false) { $insertAt = $i; break; }
    }
  }
  if ($insertAt === null) $insertAt = count($existing); // append at end

  $newCols = [];
  // append before insertAt
  for ($i=0;$i<$insertAt;$i++) { $newCols[] = $existing[$i]; }
  // inject canonical GC block (avoid duplicates)
  foreach ($gcBlock as $gcol) {
    // if canonical name already exists in existing, use existing variant (preserve original key)
    $found = false;
    foreach ($existing as $ec) { if (strtolower($ec) === strtolower($gcol) || strtolower($ec) === 'gc_name' && $gcol==='general_contractor_name' || strtolower($ec) === 'gc_number' && $gcol==='general_contractor_number') { $found = $ec; break; } }
    if ($found === false) {
      $newCols[] = $gcol;
    } else {
      $newCols[] = $found;
    }
  }
  // append remaining original columns after insertAt
  for ($i=$insertAt;$i<count($existing);$i++) {
    // skip any that are already included in newCols (avoid dupes)
    if (!in_array($existing[$i], $newCols, true)) $newCols[] = $existing[$i];
  }
  $bidColumns = array_values(array_unique($newCols));
}

// Build a dynamic lookup for GC fields (including any new columns added to general_contractor table)
$gcCanonical = [
  'general_contractor' => ['general_contractor','client_winner'],
  'general_contractor_name' => ['general_contractor_name','gc_name','gcname'],
  'general_contractor_number' => ['general_contractor_number','gc_number','gcnumber'],
  'general_contractor_email' => ['general_contractor_email','gc_email','gcemail'],
  'general_contractor_address' => ['general_contractor_address','gc_address','gcaddress'],
  'client_win_price' => ['client_win_price'],
];

// Add any dynamically discovered GC columns to the canonical map
foreach ($gcBlock as $gcCol) {
  if (!isset($gcCanonical[$gcCol])) {
    $gcCanonical[$gcCol] = [$gcCol];
  }
}

$gcColMap = [];
foreach ($gcCanonical as $canon => $alts) {
  $found = null;
  foreach ($bidColumns as $bc) {
    $lc = strtolower($bc);
    foreach ($alts as $a) { if ($lc === strtolower($a)) { $found = $bc; break 2; } }
  }
  $gcColMap[$canon] = $found !== null ? $found : $canon;
}

// Build a unique, ordered list of columns for the Manage Columns modal (status first)
$allTableColumns = ['status'];
foreach ($bidColumns as $c) {
  if ($c === 'status') continue;
  if (!in_array($c, $allTableColumns, true)) $allTableColumns[] = $c;
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
    /* Highlight applied to the selected Client Winner in the table */
    .gc-winner-highlight,
    .gc-winner-highlight * {
      color: #10b981 !important;
      font-weight: 700 !important;
    }
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
    .status-pill.status-completed { background: rgba(2,6,23,0.04); color:#0f172a; }
    .status-pill.status-lost { background: rgba(239,68,68,0.08); color:#7f1d1d; }
    .status-pill.status-pending { background: rgba(99,102,241,0.04); color:#334155; }
    .status-pill.status-bidding { background: rgba(59,130,246,0.08); color:#1e40af; }

    /* Ensure the bids table can expand to its content width */
    #tableContainer { overflow-x: auto; overflow-y: auto; box-sizing:border-box; }
    /* Make the table container fill available viewport height so table area remains tall even when empty */
    #tableContainer { min-height: calc(100vh - 220px); }
    #bidsTable { display: inline-table; width: -webkit-max-content; width: -moz-max-content; width: max-content; table-layout: auto; }
    /* Make the scroll area stretch full width while allowing the table to be wider */
    #tableTopScroller { box-sizing: border-box; }

    /* TABLE HEADER — modern, elevated look
       Adjusted: solid white background and tighter padding so headers don't show margins */
    #bidsTable thead th {
      padding: 22px 14px !important;
      background: #ffffff !important;
      border-bottom: 1px solid #e5e7eb;
      font-weight: 800;
      letter-spacing: .02em;
      box-shadow: none !important;
      color: #334155;
      font-size: 14px;
      text-align: left;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      position: sticky;
      top: 0;
      z-index: 20;
      margin: 0 !important;
    }

    /* Ensure floating header (if present) and modal section headers use solid white background and no extra margin/padding */
    #floatingHeader, #floatingHeader table, #floatingHeader th, .modal-section .header {
      background: #ffffff !important;
    }
    #floatingHeader th { padding: 22px 14px !important; font-size:14px; }
    .modal-section .header { padding: 8px 12px !important; margin: 0 !important; }

    /* increase fixed column widths by ~30% to expand header width */
    #bidsTable thead th.col-status, #bidsTable tbody td.col-status { width: 156px; }
    #bidsTable thead th.col-dhss, #bidsTable tbody td.col-dhss { width: 117px; text-align: center; }

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
    /* Force-hide the JS-created floating header to avoid duplicate headers when using an inner table scroller */
    #floatingHeader { display: none !important; }
    #floatingHeader table { border-collapse: collapse; width: 100%; background: rgba(249,250,251,0.98); }
    #floatingHeader th { padding: 22px 14px; font-weight:800; color:#334155; text-align:left; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:14px; }

    /* Top scroller visuals: ensure visible track/thumb for better discoverability */
    #tableTopScroller { -webkit-overflow-scrolling: touch; }
    #tableTopScroller::-webkit-scrollbar { height: 14px; }
    #tableTopScroller::-webkit-scrollbar-track { background: #f3f4f6; border-radius:8px; }
    #tableTopScroller::-webkit-scrollbar-thumb { background: #6b7280; border-radius:8px; }
    #tableTopScroller::-webkit-scrollbar-thumb:hover { background: #4b5563; }

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
  height: 18px;                 /* keep spacing for the separator */
  background: transparent !important;
  position: relative;
}
/* Draw a full-width horizontal rule between project groups */
#bidsTable tbody tr.group-spacer td::before {
  content: '';
  display: block;
  position: absolute;
  left: 0;
  right: 0;
  top: 50%;
  height: 1px;
  background: rgba(188,190,194,0.9);
}

/* Project boundary separator — applied to rows where the next visible row has a different DHSS project # */
/* project-break kept for compatibility, but hide its border when detail rows exist
   We'll rely on the group-spacer horizontal rule for a single clear separator between projects */
#bidsTable tbody tr.project-break td {
  border-bottom: none !important;
}

#bidsTable tbody tr.project-break:hover td {
  box-shadow: none !important;
}

/* Ensure GC detail rows do not show borders between the main and detail rows */
#bidsTable tbody tr.gc-detail-row td {
  border-top: none !important;
  border-bottom: none !important;
}

    /* Layout adjustments: keep page from scrolling and confine vertical scroll to the table container */
    html, body { height: 100%; }
    body { overflow: hidden; }
    .admin-container { display: flex; flex-direction: column; height: 100vh; }
    .admin-layout { flex: 1 1 auto; display: flex; overflow: hidden; }
    main.content-area { flex: 1 1 auto; overflow: hidden; }
    .main-content { display: flex; flex-direction: column; height: 100%; overflow: hidden; }
    /* pageBody wraps the table area and sits beneath the toolbar */
    #pageBody { flex: 1 1 auto; display: flex; flex-direction: column; overflow: hidden; }
    /* make only the table container scroll vertically */
    #tableContainer { flex: 1 1 auto; min-height: 0; overflow-y: auto; overflow-x: auto; }
    #dhStabilizerTotalBar {
      flex: 0 0 auto;
      position: sticky;
      bottom: 0;
      z-index: 80;
      display:block;
      text-align:left;
      margin: 10px 0 0 0;
      padding: 12px 16px;
      border: 2px solid #0f172a;
      border-radius: 10px;
      background:#e0f2fe;
      color:#0f172a;
      font-weight:800;
      box-shadow: 0 8px 20px rgba(2,6,23,0.12);
    }
    #dhStabilizerTotalLabel {
      position: relative;
      display:inline-block;
      font-size:14px;
      color:#0f172a;
      font-weight:800;
      text-align:left;
    }
    #dhStabilizerTotalValue {
      position:absolute;
      top:50%;
      transform:translate(-50%, -50%);
      left:50%;
      font-size:16px;
      color:#0c4a6e;
      font-weight:800;
      font-variant-numeric: tabular-nums;
    }
    /* pagination bar */
    #paginationControls { flex: 0 0 auto; }
    #paginationBar { flex: 0 0 auto; display:flex; align-items:center; gap:10px; padding:8px 40px 4px 40px; border-bottom: 1px solid #e2e8f0; }
    #paginationBar .pg-label { font-size:12px; color:#64748b; font-weight:500; letter-spacing:0.03em; text-transform:uppercase; margin-right:2px; }
    #paginationBar .pg-current { font-size:14px; font-weight:700; color:#0f172a; }
    #paginationBar .pg-sep { font-size:13px; color:#94a3b8; margin: 0 1px; }
    #paginationBar .pg-total { font-size:14px; font-weight:600; color:#475569; }
    #paginationBar .pg-count { font-size:12px; color:#64748b; margin-left:8px; padding-left:10px; border-left:1px solid #e2e8f0; }
    #paginationBar .pg-count strong { color:#0f172a; font-weight:700; }
    #paginationPrev, #paginationNext { background:#fff; border:1px solid #cbd5e1; color:#334155; padding:5px 14px; border-radius:7px; font-weight:600; font-size:13px; cursor:pointer; transition:background 0.15s,border-color 0.15s,color 0.15s; margin-left:4px; }
    #paginationPrev:hover:not([disabled]), #paginationNext:hover:not([disabled]) { background:#f1f5f9; border-color:#94a3b8; color:#0f172a; }
    #paginationPrev[disabled], #paginationNext[disabled] { opacity:0.38; cursor:not-allowed; }
    /* Ensure sticky table headers stick to the top of the scroller */
    #tableContainer thead th { position: sticky; top: 0; z-index: 20; }

    /* GC info / detail row styling — make multi-line contractor details readable */
    #bidsTable tbody tr.gc-info-row td,
    #bidsTable tbody tr.gc-detail-row td {
      white-space: normal !important;
      padding: 8px 16px !important;
      color: #0f172a;
      font-size: 13px;
      vertical-align: top;
      background: #ffffff;
    }
    #bidsTable tbody tr.gc-info-row td a, #bidsTable tbody tr.gc-detail-row td a { color: #0f5a8a; text-decoration:underline; }
    #bidsTable tbody tr.gc-detail-row { background: rgba(249,250,251,0.6); font-size:12px; }
    #bidsTable tbody tr.primary-row td {
      background: #e5e7eb !important; /* darker gray for primary rows */
      font-weight: 600;
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
    #bidsTable thead th.col-status { padding: 10px 12px; text-align: right; }

    #bidsTable td.col-status {
      text-align: right;
    }

    /* Add .primary-row class to main project rows and new CSS for a darker gray background */
    #bidsTable tbody tr.primary-row {
      background: #e5e7eb !important; /* darker gray for primary rows */
      font-weight: 600;
    }

    /* Multi-select dropdown (collapsible) used for status/year filters */
    .multi-select {
      position: relative;
      display: inline-block;
    }
    .multi-select-button {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 10px;
      border-radius: 8px;
      border: 1px solid rgba(15,23,42,0.08);
      background: #fff;
      cursor: pointer;
      min-width: 110px;
      font-weight:700;
      color:#334155;
      height:34px;
    }
    .multi-select-menu {
      position: absolute;
      top: calc(100% + 6px);
      left: 0;
      background: #fff;
      border: 1px solid rgba(15,23,42,0.08);
      box-shadow: 0 6px 20px rgba(2,6,23,0.08);
      padding: 8px;
      border-radius: 8px;
      z-index: 4000;
      min-width: 160px;
      max-height: 260px;
      overflow-y: auto;
      display: none;
    }
    .multi-select-menu.show { display: block; }
    .multi-select-menu label { display:flex; align-items:center; gap:8px; padding:6px 8px; cursor:pointer; }
    .multi-select-menu label:hover { background:#f8fafc; }
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
              <?php } ?>
              <button id="manageColumnsBtn" class="btn" style="padding:8px 12px;border:1px solid #e6edf0;border-radius:8px;font-weight:700;">Manage Columns</button>
              <!-- Compact top filters placed inline in toolbar between Manage Columns and Email Notifications (visible to all roles) -->
              <div id="compactFilters" style="display:flex;align-items:center;gap:10px;padding:6px 12px;border-radius:10px;background:#f3f4f6;border:1px solid rgba(15,23,42,0.06);margin-left:8px;flex:1 1 auto;">
                <div style="display:flex;align-items:center;gap:8px;padding:4px 8px;border-right:1px solid rgba(15,23,42,0.06);">
                  <label for="statusFilterTop" style="font-weight:700;color:#0f172a;margin-right:6px;font-size:13px;">Status</label>
                  <select id="statusFilterTop" multiple size="6" style="font-weight:700;color:#334155;padding:6px 10px;border-radius:8px;border:1px solid rgba(15,23,42,0.08);background:#fff;font-size:13px;min-width:110px;"> 
                    <option value="all">All</option>
                    <option value="won">Won</option>
                    <option value="lost">Lost</option>
                    <option value="bidding">Bidding</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                  </select>
                </div>
                <div style="display:flex;align-items:center;gap:8px;padding:4px 8px;border-right:1px solid rgba(15,23,42,0.06);">
                  <label for="yearFilterTop" style="font-weight:700;color:#0f172a;margin-right:6px;font-size:13px;">DHSS Project#</label>
                  <select id="yearFilterTop" multiple size="6" style="font-weight:700;color:#334155;padding:6px 10px;border-radius:8px;border:1px solid rgba(15,23,42,0.08);background:#fff;font-size:13px;min-width:120px;"></select>
                </div>
                <div style="display:flex;align-items:center;gap:8px;padding:4px 8px;flex:1 1 240px;position:relative;min-width:180px;max-width:400px;">
                  <input id="globalProjectSearch" type="text" placeholder="Search projects..." style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid #cbd5e1;font-size:13px;flex:1 1 auto;min-width:120px;max-width:400px;" autocomplete="off" />
                  <div id="globalProjectSearchSuggestions" style="position:absolute;top:38px;left:0;width:100%;z-index:3000;"></div>
                </div>
                </style>
                <style>
                .global-search-suggestions-list {
                  background: #fff;
                  border: 1px solid #cbd5e1;
                  border-radius: 6px;
                  max-height: 220px;
                  overflow-y: auto;
                  width: 100%;
                  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                  margin-top: 2px;
                  padding: 0;
                  list-style: none;
                }
                .global-search-suggestion-item {
                  padding: 8px 12px;
                  cursor: pointer;
                  border-bottom: 1px solid #f1f5f9;
                }
                .global-search-suggestion-item:last-child {
                  border-bottom: none;
                }
                .global-search-suggestion-item:hover, .global-search-suggestion-item.active {
                  background: #f1f5f9;
                }
                </style>
                <script>
                // Optionally scroll to or highlight the project row in the table
                function highlightProjectRow(dhssProjectNumber) {
                  if (!dhssProjectNumber) return;
                  const rows = document.querySelectorAll('#bidsTable tbody tr');
                  for (const row of rows) {
                    const cell = row.querySelector('td[data-col="dhss_project_number"]');
                    if (cell && (cell.textContent || '').trim() === dhssProjectNumber) {
                      row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                      row.classList.add('highlight-search');
                      setTimeout(() => row.classList.remove('highlight-search'), 2000);
                      break;
                    }
                  }
                }
                </script>
                <style>
                /* Highlighting for matched search results */
                .search-row-match { background: rgba(59,130,246,0.04); }
                .search-cell-match { background: rgba(59,130,246,0.12); font-weight:600; }
                .search-term-hit { background:#fde047; color:#111827; border-radius:2px; padding:0 1px; }
                </style>
                <div style="display:flex;align-items:center;gap:8px;padding:4px 8px;margin-left:auto;">
                  <label for="orderBySelect" style="font-weight:700;color:#0f172a;margin-right:6px;font-size:13px;">order by:</label>
                  <select id="orderBySelect" style="font-weight:700;color:#334155;padding:6px 10px;border-radius:8px;border:1px solid rgba(15,23,42,0.08);background:#fff;appearance:none;height:34px;font-size:13px;min-width:140px;">
                    <option value="grouped">Default</option>
                    <option value="date_asc">Bid Date: Low → High</option>
                    <option value="projectnum_asc">Project #: Low → High</option>
                    <option value="projectname_asc">Project Name: A → Z</option>
                  </select>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                  <button id="clearFiltersBtn" type="button" style="background:#fff;border:1px solid rgba(15,23,42,0.06);color:#0f172a;padding:6px 12px;border-radius:8px;font-weight:700;cursor:pointer;height:34px;font-size:13px;">Clear</button>
                </div>
              </div>
            <?php $imgBase = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/PortalSite/assets/images' : '/assets/images'; ?>
            <button id="enableEmailBtn" class="btn" style="margin-left:auto;padding:8px;border:1px solid #c7d5e8;border-radius:50%;background:#eef3fb;display:flex;align-items:center;justify-content:center;width:40px;height:40px;min-width:40px;min-height:40px;box-shadow:0 2px 8px rgba(91,127,163,0.10);">
              <img src="<?php echo $imgBase; ?>/bell.svg" alt="" style="width:20px;height:20px;display:inline-block;vertical-align:middle;" />
            </button>
            <button id="printBtn" class="btn" style="padding:8px;border:1px solid #c7d5e8;border-radius:50%;background:#eef3fb;display:flex;align-items:center;justify-content:center;width:40px;height:40px;min-width:40px;min-height:40px;box-shadow:0 2px 8px rgba(91,127,163,0.10);margin-left:8px;">
              <img src="<?php echo $imgBase; ?>/print.svg" alt="" style="width:20px;height:20px;display:inline-block;vertical-align:middle;" />
            </button>
            </div>

          <div id="paginationBar">
            <span class="pg-label">Page</span>
            <span id="pgCurrent" class="pg-current">1</span>
            <span class="pg-sep">of</span>
            <span id="pgTotal" class="pg-total">1</span>
            <span id="pgCount" class="pg-count">0 projects loaded</span>
            <button id="paginationPrev" type="button" disabled>← Prev</button>
            <button id="paginationNext" type="button" disabled>Next →</button>
          </div>

          <div id="pageBody" style="padding:16px 40px;">

            

            <div id="tableTopScroller" style="position:relative;height:26px;overflow-x:auto;overflow-y:hidden;margin-bottom:14px;border-radius:6px;width:100%;z-index:30;background:#fff;border:1px solid rgba(15,23,42,0.04);">
              <div id="tableTopScrollerInner" style="height:100%;display:block;"></div>
            </div>

            <div id="tableContainer" style="overflow:auto;border:1px solid #e6edf0;border-radius:8px;padding:8px;background:#fff;">
              <div id="filterIndicator" style="display:none;padding:10px 14px;background:#f0f9ff;border:1px solid #bfdbfe;border-radius:6px;margin-bottom:12px;font-size:13px;color:#1e40af;font-weight:500;"></div>
              <?php
                // Prefetch General Contractor names for any client_winner ids to display friendly names in the table
                $gcMap = [];
                $cwIds = [];
                foreach ($bidRows as $br) {
                  if (!empty($br['client_winner']) && is_numeric($br['client_winner'])) $cwIds[] = (int)$br['client_winner'];
                }
                $cwIds = array_values(array_unique($cwIds));
                if (!empty($cwIds)) {
                  $in = implode(',', array_map('intval', $cwIds));
                  try {
                    $gres = $conn->query("SELECT id, COALESCE(general_contractor_name, general_contractor, '') AS name FROM general_contractor WHERE id IN (" . $in . ")");
                    if ($gres) {
                      while ($g = $gres->fetch_assoc()) {
                        $gcMap[(int)$g['id']] = $g['name'];
                      }
                    }
                  } catch (Throwable $e) { /* ignore lookup failures */ }
                }
                // Also prefetch contractors grouped by project so we can render contractor details under each bid row
                $gcByProject = [];
                $projKeys = [];
                foreach ($bidRows as $br) {
                  $pk = isset($br['dhss_project_number']) ? trim((string)$br['dhss_project_number']) : '';
                  if ($pk !== '') {
                    $projKeys[] = $pk;
                    $pkTrim = ltrim($pk, '0');
                    if ($pkTrim === '') $pkTrim = '0';
                    $projKeys[] = $pkTrim;
                  }
                }
                $projKeys = array_values(array_unique($projKeys));
                if (!empty($projKeys)) {
                  // Build a safe IN list
                  $inList = implode(',', array_map(function($v){ return "'".addslashes($v)."'"; }, $projKeys));
                  try {
                    // Fetch all columns from general_contractor table (including dynamic columns like client_win_price, is_union, etc.)
                    // Normalize dhss_project_number to avoid whitespace mismatches
                    $gres2 = $conn->query("SELECT * FROM general_contractor WHERE TRIM(dhss_project_number) IN (" . $inList . ") OR TRIM(LEADING '0' FROM dhss_project_number) IN (" . $inList . ") ORDER BY IFNULL(winner,0) DESC, id ASC");
                    if ($gres2) {
                      // keep per-project seen set to dedupe contractors by id or normalized name+number
                      $seenPerProject = [];
                      while ($g = $gres2->fetch_assoc()) {
                        $key = isset($g['dhss_project_number']) ? trim((string)$g['dhss_project_number']) : '';
                        if ($key === '') continue;
                        $keyTrim = ltrim($key, '0');
                        if ($keyTrim === '') $keyTrim = '0';
                        if (!isset($gcByProject[$key])) $gcByProject[$key] = [];
                        if (!isset($gcByProject[$keyTrim])) $gcByProject[$keyTrim] = [];
                        if (!isset($seenPerProject[$key])) $seenPerProject[$key] = [];
                        if (!isset($seenPerProject[$keyTrim])) $seenPerProject[$keyTrim] = [];

                        // create a dedupe key: prefer id when present, otherwise normalized name|number
                        $duKey = '';
                        if (!empty($g['id'])) {
                          $duKey = 'id:' . (string)$g['id'];
                        } else {
                          $nm = strtolower(trim((string)($g['general_contractor_name'] ?? $g['general_contractor'] ?? '')));
                          $nn = strtolower(trim((string)($g['general_contractor_number'] ?? '')));
                          $duKey = 'nm:' . $nm . '|num:' . $nn;
                        }
                        if (in_array($duKey, $seenPerProject[$key], true)) continue;
                        $seenPerProject[$key][] = $duKey;
                        $gcByProject[$key][] = $g;
                        if (!in_array($duKey, $seenPerProject[$keyTrim], true)) {
                          $seenPerProject[$keyTrim][] = $duKey;
                          $gcByProject[$keyTrim][] = $g;
                        }
                      }
                    }
                  } catch (Throwable $e) { /* ignore */ }
                }
                // Decide which column should show GC info (prefer explicit general_contractor column or gc_name)
                $gcDisplayCol = null;
                foreach ($bidColumns as $c) {
                  $lc = strtolower($c);
                  if (strpos($lc, 'general_contractor') !== false || $lc === 'gc_name' || strpos($lc, 'gc_') === 0 || strpos($lc, 'gc') !== false) { $gcDisplayCol = $c; break; }
                }
                if ($gcDisplayCol === null && in_array('client_winner', $bidColumns, true)) $gcDisplayCol = 'client_winner';
              ?>
              <?php
                if (!function_exists('fetch_gcs_for_project')) {
                  function fetch_gcs_for_project($conn, $projKey) {
                    $projKey = trim((string)$projKey);
                    if ($projKey === '') return [];
                    $projKeyEsc = $conn->real_escape_string($projKey);
                    $projKeyNum = ctype_digit($projKey) ? (int)$projKey : null;
                    $sql = "SELECT * FROM general_contractor WHERE TRIM(dhss_project_number) = '" . $projKeyEsc . "' OR TRIM(LEADING '0' FROM dhss_project_number) = '" . $projKeyEsc . "' OR dhss_project_number = '" . $projKeyEsc . "'";
                    if ($projKeyNum !== null) {
                      $sql .= " OR CAST(dhss_project_number AS UNSIGNED) = " . $projKeyNum;
                    }
                    $sql .= " ORDER BY IFNULL(winner,0) DESC, id ASC";

                    $res = $conn->query($sql);
                    if (!$res) return [];

                    $list = [];
                    $seen = [];
                    while ($g = $res->fetch_assoc()) {
                      $duKey = '';
                      if (!empty($g['id'])) {
                        $duKey = 'id:' . (string)$g['id'];
                      } else {
                        $nm = strtolower(trim((string)($g['general_contractor_name'] ?? $g['general_contractor'] ?? '')));
                        $nn = strtolower(trim((string)($g['general_contractor_number'] ?? '')));
                        $duKey = 'nm:' . $nm . '|num:' . $nn;
                      }
                      if (in_array($duKey, $seen, true)) continue;
                      $seen[] = $duKey;
                      $list[] = $g;
                    }
                    return $list;
                  }
                }
              ?>
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
                                      <select id="statusFilter" class="th-filter" title="Filter status" multiple size="6" hidden style="margin-top:2px;display:none;">
                                        <option value="all">All</option>
                                        <option value="won">Won</option>
                                        <option value="lost">Lost</option>
                                        <option value="bidding">Bidding</option>
                                        <option value="pending">Pending</option>
                                        <option value="completed">Completed</option>
                                      </select>
                                  </th>';
                          }

                          // Build a human-friendly, title-cased label.
                          if ($col === 'dhss_project_number') {
                            $label = 'DHSS Project #';
                          } elseif ($col === 'dh_stabilizer_price') {
                            $label = 'DH Stabilizer Price';
                          } elseif ($col === 'gc_name' || $col === 'general_contractor_name') {
                            $label = 'General Contractor Name';
                          } elseif ($col === 'gc_number' || $col === 'general_contractor_number') {
                            $label = 'General Contractor Number';
                          } elseif ($col === 'general_contractor_email' || $col === 'gc_email') {
                            $label = 'General Contractor Email';
                          } elseif ($col === 'general_contractor_address' || $col === 'gc_address') {
                            $label = 'General Contractor Address';
                          } elseif ($col === 'client_winner') {
                            $label = 'Client Winner';
                          } elseif (strpos(strtolower($col), 'gc') !== false || $col === 'general_contractor') {
                            $label = 'General Contractor';
                          } else {
                            $label = ucwords(str_replace('_',' ',$col));
                          }

                          // NEW: year filter dropdown embedded in DHSS Project # header
                          if ($col === 'dhss_project_number') {
                            echo '<th class="col-dhss" data-col="' . htmlspecialchars($col) . '">
                                    <div class="th-with-filter" style="flex-direction:column;align-items:flex-start;gap:2px;">
                                      <span class="th-label">' . htmlspecialchars($label) . '</span>
                                      <select id="yearFilter" class="th-filter" title="Filter by year" multiple size="6" hidden style="margin-top:2px;display:none;"></select>
                                    </div>
                                  </th>';
                          } else {
                            echo '<th data-col="' . htmlspecialchars($col) . '">' . htmlspecialchars($label) . '</th>';
                          }
                          // If this column represents the GC number, also render email + address headers (if not already present)
                          if ($col === 'gc_number' || $col === 'general_contractor_number') {
                            if (!in_array('general_contractor_email', $bidColumns, true) && !in_array('gc_email', $bidColumns, true)) {
                              echo '<th data-col="general_contractor_email">General Contractor Email</th>';
                            }
                            if (!in_array('general_contractor_address', $bidColumns, true) && !in_array('gc_address', $bidColumns, true)) {
                              echo '<th data-col="general_contractor_address">General Contractor Address</th>';
                            }
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
                      <tr class="primary-row" data-bid='<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8"); ?>' style="cursor:pointer;">
                      <?php
                        // Determine project key and contractors for this bid
                        $projKey = isset($r['dhss_project_number']) ? trim((string)$r['dhss_project_number']) : '';
                        $projKeyTrim = ltrim($projKey, '0');
                        if ($projKeyTrim === '') $projKeyTrim = '0';
                        $gcs = [];
                        if ($projKey !== '' && isset($gcByProject[$projKey])) $gcs = $gcByProject[$projKey];
                        else if ($projKeyTrim !== '' && isset($gcByProject[$projKeyTrim])) $gcs = $gcByProject[$projKeyTrim];
                        if (empty($gcs)) {
                          $gcs = fetch_gcs_for_project($conn, $projKey);
                        }
                        // pick the primary contractor per rules: winner if present, otherwise the first-added contractor (if any)
                        $primaryGc = null;
                        if (!empty($gcs)) {
                          // prefer explicit winner flag
                          foreach ($gcs as $g) { if (!empty($g['winner']) && (int)$g['winner'] === 1) { $primaryGc = $g; break; } }
                          // if no winner, choose the first contractor in the list as primary
                          if ($primaryGc === null && count($gcs) > 0) {
                            $primaryGc = $gcs[0];
                          }
                        }
                        // Determine normalized display values for the chosen primary GC (used for skipping duplicates)
                        $primaryDisplayName = '';
                        $primaryDisplayNumber = '';
                        if ($primaryGc !== null) {
                          $primaryDisplayName = strtolower(trim((string)($primaryGc['general_contractor_name'] ?? $primaryGc['general_contractor'] ?? '')));
                          $primaryDisplayNumber = strtolower(trim((string)($primaryGc['general_contractor_number'] ?? '')));
                        } else {
                          // fallback to values from the bid row if no primary GC found
                          $primaryDisplayName = strtolower(trim((string)($r['general_contractor_name'] ?? $r['gc_name'] ?? $r['general_contractor'] ?? '')));
                          $primaryDisplayNumber = strtolower(trim((string)($r['general_contractor_number'] ?? $r['gc_number'] ?? '')));
                        }

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
                            echo '<td class="col-status" data-col="status"><span class="status-pill status-' . htmlspecialchars($normalized) . '">' . htmlspecialchars($label) . '</span></td>';
                            echo '<td class="col-dhss" data-col="' . htmlspecialchars($col) . '">' . htmlspecialchars(isset($r[$col]) ? $r[$col] : '') . '</td>';
                            continue;
                          }

                          // Check if this column is from the general_contractor table
                          $isGcCol = false;
                          foreach ($gcBlock as $gcField) {
                            if (strtolower($col) === strtolower($gcField)) {
                              $isGcCol = true;
                              break;
                            }
                          }
                          
                          if ($isGcCol) {
                            // Get value from primary GC record (case-insensitive keys)
                            $val = '';
                            $lcCol = strtolower($col);
                            if ($primaryGc !== null && is_array($primaryGc)) {
                              $gLower = array_change_key_case($primaryGc, CASE_LOWER);
                              if (isset($gLower[$lcCol])) {
                                $val = $gLower[$lcCol];
                              } else {
                                // alternate key fallbacks
                                if ($lcCol === 'general_contractor_name' && isset($gLower['gc_name'])) $val = $gLower['gc_name'];
                                elseif ($lcCol === 'general_contractor_number' && isset($gLower['gc_number'])) $val = $gLower['gc_number'];
                                elseif ($lcCol === 'general_contractor_email' && isset($gLower['gc_email'])) $val = $gLower['gc_email'];
                                elseif ($lcCol === 'general_contractor_address' && isset($gLower['gc_address'])) $val = $gLower['gc_address'];
                                elseif ($lcCol === 'general_contractor' && isset($gLower['general_contractor_name'])) $val = $gLower['general_contractor_name'];
                              }
                            }

                            // Transform winner 0/1 to No/Yes
                            if ($lcCol === 'winner' && $val !== '') {
                              $val = ($val == '1') ? 'Yes' : 'No';
                            }
                            
                            // Fallback: if no primary GC value, try to use values from the bid row itself
                            if ($val === '' || $val === null) {
                              $rLower = array_change_key_case($r, CASE_LOWER);
                              if ($lcCol === 'general_contractor' || $lcCol === 'general_contractor_name') {
                                if (!empty($rLower['general_contractor_name'])) $val = $rLower['general_contractor_name'];
                                elseif (!empty($rLower['gc_name'])) $val = $rLower['gc_name'];
                                elseif (!empty($rLower['general_contractor'])) $val = $rLower['general_contractor'];
                                elseif (!empty($r['client_winner']) && is_numeric($r['client_winner']) && isset($gcMap[(int)$r['client_winner']])) $val = $gcMap[(int)$r['client_winner']];
                              } elseif ($lcCol === 'general_contractor_number' || $lcCol === 'gc_number') {
                                if (!empty($rLower['general_contractor_number'])) $val = $rLower['general_contractor_number'];
                                elseif (!empty($rLower['gc_number'])) $val = $rLower['gc_number'];
                              } elseif ($lcCol === 'general_contractor_email' || $lcCol === 'gc_email') {
                                if (!empty($rLower['general_contractor_email'])) $val = $rLower['general_contractor_email'];
                                elseif (!empty($rLower['gc_email'])) $val = $rLower['gc_email'];
                              } elseif ($lcCol === 'general_contractor_address' || $lcCol === 'gc_address') {
                                if (!empty($rLower['general_contractor_address'])) $val = $rLower['general_contractor_address'];
                                elseif (!empty($rLower['gc_address'])) $val = $rLower['gc_address'];
                              } elseif (isset($rLower[$lcCol])) {
                                $val = $rLower[$lcCol];
                              }
                            }
                            
                            // style winner values green only when the primary contractor is actually marked winner
                            $hasWinnerFlag = false;
                            if ($primaryGc !== null && isset($primaryGc['winner']) && (int)$primaryGc['winner'] === 1) {
                              $hasWinnerFlag = true;
                            } elseif (!empty($r['client_winner']) && $primaryGc !== null && isset($primaryGc['id']) && is_numeric($r['client_winner']) && (int)$r['client_winner'] === (int)$primaryGc['id']) {
                              // also treat as winner when bid row explicitly references this contractor as client_winner
                              $hasWinnerFlag = true;
                            }
                            // Only style the compact/general_contractor column as the winner (keep name/number/email/address black)
                            $style = '';
                            if ($lcCol === 'general_contractor' && $val !== '' && $hasWinnerFlag) {
                              $style = 'style="color:#10b981;font-weight:700;"';
                            }
                            echo '<td data-col="' . htmlspecialchars($col) . '" ' . $style . '>' . htmlspecialchars($val) . '</td>';
                          } else {
                            // regular non-GC column: show value from bids table
                            $cellVal = isset($r[$col]) ? $r[$col] : '';
                            // Transform 0/1 to No/Yes for winner column
                            if ($col === 'winner') {
                              $cellVal = ($cellVal == '1') ? 'Yes' : 'No';
                            }
                            echo '<td data-col="' . htmlspecialchars($col) . '">' . htmlspecialchars($cellVal) . '</td>';
                          }
                        }
                      ?>
                      </tr>
                      <?php
                        // Render a contractor details row directly beneath the bid row.
                        $projKey = isset($r['dhss_project_number']) ? trim((string)$r['dhss_project_number']) : '';
                        $projKeyTrim = ltrim($projKey, '0');
                        if ($projKeyTrim === '') $projKeyTrim = '0';
                        $gcs = [];
                        if ($projKey !== '' && isset($gcByProject[$projKey])) $gcs = $gcByProject[$projKey];
                        else if ($projKeyTrim !== '' && isset($gcByProject[$projKeyTrim])) $gcs = $gcByProject[$projKeyTrim];
                        if (empty($gcs)) {
                          $gcs = fetch_gcs_for_project($conn, $projKey);
                        }
                        // Fallback: if no contractors found via project lookup, attempt to build details from the bid row itself
                        if (empty($gcs)) {
                          $fallback = [];
                          $name = '';
                          if (!empty($r['general_contractor_name'])) $name = $r['general_contractor_name'];
                          elseif (!empty($r['gc_name'])) $name = $r['gc_name'];
                          elseif (!empty($r['general_contractor'])) $name = $r['general_contractor'];
                          elseif (!empty($r['client_winner']) && is_numeric($r['client_winner']) && isset($gcMap[(int)$r['client_winner']])) $name = $gcMap[(int)$r['client_winner']];

                          $num = '';
                          if (!empty($r['general_contractor_number'])) $num = $r['general_contractor_number'];
                          elseif (!empty($r['gc_number'])) $num = $r['gc_number'];

                          $em = '';
                          if (!empty($r['general_contractor_email'])) $em = $r['general_contractor_email'];
                          elseif (!empty($r['gc_email'])) $em = $r['gc_email'];

                          $addr = '';
                          if (!empty($r['general_contractor_address'])) $addr = $r['general_contractor_address'];
                          elseif (!empty($r['gc_address'])) $addr = $r['gc_address'];

                          if ($name !== '' || $num !== '' || $em !== '' || $addr !== '') {
                            $fallback[] = [
                              'general_contractor_name' => $name,
                              'general_contractor_number' => $num,
                              'general_contractor_email' => $em,
                              'general_contractor_address' => $addr,
                            ];
                          }
                          if (!empty($fallback)) $gcs = $fallback;
                        }
                      ?>
                      <?php
                        // Render one contractor-detail row per contractor for this project
                        $parentId = isset($r['bid_id']) ? $r['bid_id'] : '';
                        foreach ($gcs as $g) {
                          // skip rendering the primary contractor in the details to avoid duplicate display
                          $skip = false;
                          if ($primaryGc !== null) {
                            if (isset($primaryGc['id']) && isset($g['id']) && (int)$primaryGc['id'] === (int)$g['id']) {
                              $skip = true;
                            } else {
                              // fallback comparison by normalized, case-insensitive name+number when IDs are not available
                              $gname = strtolower(trim((string)($g['general_contractor_name'] ?? $g['general_contractor'] ?? '')));
                              $gnum = strtolower(trim((string)($g['general_contractor_number'] ?? '')));
                              if ($primaryDisplayName !== '' && $gname !== '' && $primaryDisplayName === $gname) $skip = true;
                              if (!$skip && $primaryDisplayNumber !== '' && $gnum !== '' && $primaryDisplayNumber === $gnum) $skip = true;
                            }
                          }
                          if ($skip) continue;

                          echo '<tr class="gc-detail-row" data-parent-bid-id="' . htmlspecialchars($parentId) . '" style="background:rgba(249,250,251,0.6);font-size:12px;">';
                          foreach ($bidColumns as $col) {
                            if ($col === 'status') continue;
                            if ($col === 'dhss_project_number') {
                              // keep status and dhss cells empty for detail rows
                              echo '<td class="col-status" data-col="status"></td>';
                              echo '<td class="col-dhss" data-col="' . htmlspecialchars($col) . '"></td>';
                              continue;
                            }
                            // Determine if this is a GC cell - check if column exists in the $g array
                            $isGcCol = false;
                            foreach ($gcBlock as $gcField) {
                              if (strtolower($col) === strtolower($gcField)) {
                                $isGcCol = true;
                                break;
                              }
                            }
                            
                            if ($isGcCol) {
                              // Get value directly from the GC record (case-insensitive)
                              $lcCol = strtolower($col);
                              $gLower = array_change_key_case($g, CASE_LOWER);
                              $out = isset($gLower[$lcCol]) ? $gLower[$lcCol] : '';
                              if ($out === '') {
                                if ($lcCol === 'general_contractor_name' && isset($gLower['gc_name'])) $out = $gLower['gc_name'];
                                elseif ($lcCol === 'general_contractor_number' && isset($gLower['gc_number'])) $out = $gLower['gc_number'];
                                elseif ($lcCol === 'general_contractor_email' && isset($gLower['gc_email'])) $out = $gLower['gc_email'];
                                elseif ($lcCol === 'general_contractor_address' && isset($gLower['gc_address'])) $out = $gLower['gc_address'];
                                elseif ($lcCol === 'general_contractor' && isset($gLower['general_contractor_name'])) $out = $gLower['general_contractor_name'];
                              }
                              // Transform winner 0/1 to No/Yes
                              if ($lcCol === 'winner' && $out !== '') {
                                $out = ($out == '1') ? 'Yes' : 'No';
                              }
                              echo '<td data-col="' . htmlspecialchars($col) . '">' . htmlspecialchars($out) . '</td>';
                            } else {
                              echo '<td data-col="' . htmlspecialchars($col) . '"></td>';
                            }
                          }
                          echo '</tr>';
                        }
                      ?>
                    <?php } ?>
                  <?php } ?>
                </tbody>
              </table>
            </div>
            <div id="dhStabilizerTotalBar">
              <span id="dhStabilizerTotalLabel">Total</span>
              <span id="dhStabilizerTotalValue">$0</span>
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
          <div id="editBidModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.35);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);align-items:center;justify-content:center;z-index:10000;padding:0;overflow-y:auto;">
            <div style="background:#fff;border-radius:12px;padding:16px;max-width:95vw;width:95vw;max-height:92vh;height:92vh;box-shadow:0 8px 30px rgba(2,6,23,0.12);overflow-y:auto;margin:16px;">
              <form id="editBidForm" style="display:block;">
                <input type="hidden" id="editBidId" name="bid_id" />
                <!-- general_contractor_id removed: GC info stored in general_contractor table only -->
                <div style="margin-bottom:12px;text-align:center;">
                  <select id="editStatus" name="status" style="min-width:90px;padding:6px 36px 6px 6px;border:0;background:transparent;appearance:none;-webkit-appearance:none;-moz-appearance:none;color:#374151;font-weight:600;background-image:url('data:image/svg+xml;utf8,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' width=\'16\' height=\'16\'%3E%3Cpath fill=\'currentColor\' d=\'M7 10l5 5 5-5z\'/%3E%3C/svg%3E');background-repeat:no-repeat;background-position:right 10px center;background-size:16px;">
                    <option value="won" style="color:#10b981;">won</option>
                    <option value="lost" style="color:#ef4444;">lost</option>
                    <option value="bidding" style="color:#1e40af;">bidding</option>
                    <option value="pending" selected style="color:#334155;">pending</option>
                    <option value="didn't bid" style="color:#f97316;">didn't bid</option>
                    <option value="completed" style="color:#0f172a;">completed</option>
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
                    // Move Client Win Price into Additional Information (not Project Specifications)
                    if ($lc === 'client_win_price') {
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
                  .gc-suggest { position:absolute; background:#ffffff; border:1px solid #e6edf0; border-radius:8px; box-shadow:0 10px 24px rgba(2,6,23,0.12); z-index:20050; max-height:180px; overflow:auto; }
                  .gc-suggest-item { padding:6px 10px; cursor:pointer; font-size:12px; color:#0f172a; }
                  .gc-suggest-item:hover { background:#f8fafc; }
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
                      elseif ($col === 'dh_stabilizer_price') { $label = 'DH Stabilizer Price'; }
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
                      <button type="button" id="editGcToggleBtn" class="add-gc-btn" style="background:#fff;border:1px solid #cbd5e1;">Edit contractor info</button>
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
                      elseif ($col === 'dh_stabilizer_price') { $label = 'DH Stabilizer Price'; }
                      elseif (strpos(strtolower($col),'gc') !== false || $col === 'general_contractor') { $label = 'General Contractor'; }
                      elseif ($col === 'dhss_project_number') { $label = 'DHSS Project #'; }
                      else { $label = ucwords(str_replace('_',' ',$col)); }
                      $isDate = preg_match('/date/i', $col);
                    ?>
                      <div class="field">
                        <label><?php echo htmlspecialchars($label); ?></label>
                        <input type="<?php echo ($isDate ? 'date' : 'text'); ?>" data-col="<?php echo htmlspecialchars($col); ?>" name="<?php echo htmlspecialchars($col); ?>" />
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
                      // hide fields duplicated in the General Contractor section
                      $lcOther = strtolower($col);
                      if (in_array($lcOther, ['client_win_price', 'is_union', 'winner'], true)) continue;
                      if ($col === 'gc_name') { $label = 'General Contractor Name'; }
                      elseif ($col === 'gc_number') { $label = 'General Contractor Number'; }
                      elseif ($col === 'dh_stabilizer_price') { $label = 'DH Stabilizer Price'; }
                      elseif (strpos(strtolower($col),'gc') !== false || $col === 'general_contractor') { $label = 'General Contractor'; }
                      elseif ($col === 'dhss_project_number') { $label = 'DHSS Project #'; }
                      else { $label = ucwords(str_replace('_',' ',$col)); }

                      // Render a custom UI for some specific columns
                      if ($col === 'reason') {
                        // Use static list of reasons provided by user
                        $reasonOpts = [
                          'CANCELLED STABILIZATION',
                          'CONTRACTOR WAS HIGH',
                          "DON'T KNOW GC",
                          'HIGH',
                          'DID NOT BID',
                          'DID NOT BID (UNION)',
                          'PROJECT DIDNOT GO',
                          'DID IT THEMSELVES',
                          'GOING TO REBID',
                          'NOTHING TO BID'
                        ];
                        ?>
                        <div class="field">
                          <label><?php echo htmlspecialchars($label); ?></label>
                          <select class="reason-select" data-col="reason" name="reason" id="editReasonSelect" style="padding:8px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;width:100%;box-sizing:border-box;">
                            <option value=""></option>
                            <?php foreach ($reasonOpts as $rOpt): ?>
                              <option value="<?php echo htmlspecialchars($rOpt); ?>"><?php echo htmlspecialchars($rOpt); ?></option>
                            <?php endforeach; ?>
                            <option value="Other">Other</option>
                          </select>
                          <input type="text" data-col="reason_other" name="reason_other" id="editReasonOther" placeholder="Specify other reason" style="display:none;margin-top:8px;padding:8px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;width:100%;box-sizing:border-box;" autocomplete="off" />
                        </div>
                        <?php
                      } else {
                        // Make award_date a date selector
                        $inputType = ($col === 'award_date') ? 'date' : 'text';
                        ?>
                        <div class="field">
                          <label><?php echo htmlspecialchars($label); ?></label>
                          <input type="<?php echo $inputType; ?>" data-col="<?php echo htmlspecialchars($col); ?>" name="<?php echo htmlspecialchars($col); ?>" />
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
                  <button type="button" id="closeEditBid" style="background:#fff;border:1px solid #e6edf0;color:#0f172a;padding:10px 14px;border-radius:8px;font-weight:600;cursor:pointer;">Close</button>
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
              <div id="emailToggleRow" style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;padding:8px 10px;border:1px solid #e6edf0;border-radius:8px;background:#f8fafc;">
                <div id="emailToggleText" style="font-weight:700;color:#0f172a;">Turn on</div>
                <button type="button" id="emailToggleBtn" style="background:#10b981;border:none;color:#fff;padding:6px 12px;border-radius:999px;font-weight:700;cursor:pointer;">Turn On</button>
              </div>
              <div id="emailSettingsContent" style="padding:8px;border:1px solid #e6edf0;border-radius:8px;background:#fbfdfe;">
                <p style="margin:0 0 8px 0;color:#475569;">Select how many days prior to a bid you want to receive reminders. You may select multiple values, up to 5.</p>
                <div id="emailDaysList" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding-top:6px;">
                  <!-- checkboxes populated by JS -->
                </div>
              </div>
              <div id="emailSettingsActions" style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px;">
                <button type="button" id="cancelEmailSettings" style="background:#fff;border:1px solid #e6edf0;color:#0f172a;padding:8px 12px;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
                <button type="button" id="saveEmailSettings" style="background:#10b981;border:none;color:#fff;padding:8px 12px;border-radius:8px;font-weight:700;cursor:pointer;">Save</button>
              </div>
            </div>
          </div>

          <!-- Print Modal (full viewport) -->
          <div id="printModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.35);align-items:stretch;justify-content:stretch;z-index:4700;padding:0;">
            <div style="position:fixed;inset:0;background:#fff;border-radius:0;padding:12px;box-shadow:none;overflow:hidden;display:flex;gap:12px;font-size:70%;box-sizing:border-box;width:100vw;height:100vh;">
              <div style="flex:0 0 320px;overflow:auto;padding:16px;max-width:360px;">
                <div style="font-weight:800;color:#0f172a;font-size:16px;margin-bottom:8px;">Print Options</div>
                <div style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">
                  <label style="font-weight:700;color:#0f172a;">Status</label>
                  <select id="printStatus" style="padding:6px;border-radius:8px;border:1px solid #e6edf0;">
                    <option value="all">All</option>
                    <option value="won">Won</option>
                    <option value="lost">Lost</option>
                    <option value="bidding">Bidding</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                  </select>
                </div>
                <div style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">
                  <label style="font-weight:700;color:#0f172a;">DHSS Project#</label>
                  <select id="printYear" style="padding:6px;border-radius:8px;border:1px solid #e6edf0;"></select>
                </div>
                <div style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">
                  <label style="font-weight:700;color:#0f172a;">Order By</label>
                  <select id="printOrder" style="padding:6px;border-radius:8px;border:1px solid #e6edf0;min-width:160px;">
                    <option value="grouped">Default</option>
                    <option value="date_asc">Bid Date: Low → High</option>
                    <option value="projectnum_asc">Project #: Low → High</option>
                  </select>
                </div>

                <div style="margin-top:12px;font-weight:800;color:#0f172a;margin-bottom:8px;">Columns</div>
                <div id="printColumnsList" style="display:grid;grid-template-columns:1fr;gap:6px;max-height:360px;overflow:auto;padding-right:6px;border:1px solid #eef2f7;border-radius:6px;padding:8px;background:#fbfdfe;"></div>
                <div style="display:flex;justify-content:space-between;gap:8px;margin-top:12px;">
                  <button id="printCancel" class="btn" style="background:#fff;border:1px solid #e6edf0;padding:8px 12px;border-radius:8px;font-weight:700;">Cancel</button>
                  <button id="printConfirm" class="btn" style="background:#10b981;border:none;color:#fff;padding:8px 12px;border-radius:8px;font-weight:700;">Print</button>
                </div>
              </div>
              <div style="flex:1 1 auto;overflow:auto;border-left:1px solid #e6edf0;padding-left:12px;padding-right:12px;">
                <div style="font-weight:800;color:#0f172a;margin-bottom:8px;">Preview</div>
                <div id="printPreview" style="border:1px solid #e6edf0;border-radius:6px;overflow:auto;padding:8px;min-height:320px;background:#fff;"></div>
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
      if (type === 'success') {
        t.classList.add('success','centered');
      } else if (type === 'success-top') {
        t.classList.add('success');
        t.style.top = '24px';
        t.style.right = '24px';
        t.style.left = 'auto';
        t.style.transform = 'none';
      } else if (type === 'error') {
        t.classList.add('error');
      }

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

      function normalizeNumericLikeValue(raw) {
        var s = (raw === null || typeof raw === 'undefined') ? '' : String(raw).trim();
        if (!s) return s;
        // Accept values like 123,123.4565 (or plain numeric values) and normalize to DB-friendly numeric text.
        var numericLike = /^\$?\s*[+-]?\d[\d,]*(?:\.\d+)?\s*$/;
        if (!numericLike.test(s)) return s;
        var cleaned = s.replace(/\$/g, '').replace(/,/g, '').replace(/\s+/g, '');
        if (/^[+-]?\d+(?:\.\d+)?$/.test(cleaned)) return cleaned;
        return s;
      }

      function normalizeFormDataNumericLike(fd, skipKeys) {
        if (!fd || !fd.entries) return;
        var skip = skipKeys || {};
        Array.from(fd.entries()).forEach(function(pair){
          var k = pair[0];
          var v = pair[1];
          if (skip[k]) return;
          if (typeof v !== 'string') return;
          var nv = normalizeNumericLikeValue(v);
          if (nv !== v) fd.set(k, nv);
        });
      }

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
          // Ensure bid_date is converted from mm/dd/yyyy (user-facing) to ISO yyyy-mm-dd for backend
          try {
            var bdEl = document.getElementById('bidDate');
            if (bdEl) {
              var raw = (bdEl.value || '').toString().trim();
              if (raw) {
                var m = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/);
                if (m) {
                  var mm = (m[1].length===1?('0'+m[1]):m[1]);
                  var dd = (m[2].length===1?('0'+m[2]):m[2]);
                  var yyyy = (m[3].length===2?String(2000+parseInt(m[3],10)):m[3]);
                  fd.set('bid_date', yyyy + '-' + mm + '-' + dd);
                } else {
                  // keep original if not parsable
                  fd.set('bid_date', raw);
                }
              }
            }
          } catch(e) { }

          // Normalize comma-separated decimal inputs across all fields (except date fields).
          try { normalizeFormDataNumericLike(fd, { bid_date: true }); } catch(e) {}

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
      var allTableColumns = <?php echo json_encode($allTableColumns); ?> || [];
      var gcColumns = <?php echo json_encode(array_values($gcBlock)); ?> || [];
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
        else if (s === 'bidding') { color = '#334155'; }
        else { font = 'Arial, sans-serif'; }
        var el = document.getElementById('editStatus');
        if (el) {
          el.style.color = color;
          el.style.fontFamily = font;
        }
      }

      // Columns that should show a dollar sign prefix in the UI (but not modify stored value)
      // Make `total_price` behave the same as other money columns (display $ prefix, wrapped input, and sanitized on submit)
      var moneyCols = ['client_win_price','stabilizer_bid_win_price','dh_stabilizer_price','total_price'];

      // Columns that look like dates (any column name containing "date")
      var dateCols = (bidColumns || []).filter(function(c){ return /date/i.test(c || ''); });

      function pad(n){ return (n < 10) ? ('0' + n) : String(n); }

      // Accepts either a Date object, an ISO string (yyyy-mm-dd) or mm/dd/yyyy string and returns mm/dd/yyyy
      function formatDateMMDDYYYY(input) {
        if (!input && input !== 0) return '';
        try {
          if (input instanceof Date) {
            if (isNaN(input.getTime())) return '';
            return pad(input.getMonth()+1) + '/' + pad(input.getDate()) + '/' + input.getFullYear();
          }
          var s = String(input).trim();
          if (!s) return '';
          // ISO-like yyyy-mm-dd or datetime
          var isoMatch = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
          if (isoMatch) return pad(parseInt(isoMatch[2],10)) + '/' + pad(parseInt(isoMatch[3],10)) + '/' + isoMatch[1];
          // mm/dd/yyyy or variations
          var m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/);
          if (m) {
            var year = m[3].length === 2 ? (2000 + parseInt(m[3],10)) : parseInt(m[3],10);
            return pad(parseInt(m[1],10)) + '/' + pad(parseInt(m[2],10)) + '/' + year;
          }
          // Fallback: try Date parser
          var d = new Date(s);
          if (!isNaN(d.getTime())) return pad(d.getMonth()+1) + '/' + pad(d.getDate()) + '/' + d.getFullYear();
        } catch(e) {}
        return '';
      }

      // Convert mm/dd/yyyy or yyyy-mm-dd to ISO yyyy-mm-dd for backend consumption
      function toIsoDate(input) {
        if (!input) return '';
        var s = String(input).trim();
        if (!s) return '';
        // already ISO
        var iso = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (iso) return iso[1] + '-' + iso[2] + '-' + iso[3];
        var m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/);
        if (m) {
          var mm = pad(parseInt(m[1],10));
          var dd = pad(parseInt(m[2],10));
          var yyyy = (m[3].length === 2) ? String(2000 + parseInt(m[3],10)) : m[3];
          return yyyy + '-' + mm + '-' + dd;
        }
        // try Date parse
        var d = new Date(s);
        if (!isNaN(d.getTime())) return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate());
        return '';
      }

      function formatForDisplay(col, val) {
        if (!val && val !== 0 && val !== '0') return '';
        var s = (val === null || val === undefined) ? '' : String(val);
        if (moneyCols.indexOf(col) !== -1) {
          return '$' + formatNumericWithGrouping(s);
        }
        if (dateCols.indexOf(col) !== -1) {
          var out = formatDateMMDDYYYY(s);
          return out || s;
        }
        return s;
      }

      function formatNumericWithGrouping(raw) {
        var s = (raw === null || typeof raw === 'undefined') ? '' : String(raw).trim();
        if (!s) return '';
        var cleaned = s.replace(/\$/g, '').replace(/,/g, '').replace(/\s+/g, '');
        if (!/^[+-]?\d+(?:\.\d+)?$/.test(cleaned)) return s;
        var sign = '';
        var body = cleaned;
        if (body.charAt(0) === '-' || body.charAt(0) === '+') {
          sign = body.charAt(0);
          body = body.slice(1);
        }
        var parts = body.split('.');
        var intPart = parts[0] || '0';
        var fracPart = parts.length > 1 ? parts[1] : '';
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return sign + intPart + (fracPart !== '' ? ('.' + fracPart) : '');
      }

      function normalizeNumericLikeValue(raw) {
        var s = (raw === null || typeof raw === 'undefined') ? '' : String(raw).trim();
        if (!s) return s;
        // Accept values like 123,123.4565 and normalize them for numeric DB columns.
        var numericLike = /^\$?\s*[+-]?\d[\d,]*(?:\.\d+)?\s*$/;
        if (!numericLike.test(s)) return s;
        var cleaned = s.replace(/\$/g, '').replace(/,/g, '').replace(/\s+/g, '');
        if (/^[+-]?\d+(?:\.\d+)?$/.test(cleaned)) return cleaned;
        return s;
      }

      function normalizeFormDataNumericLike(fd, skipKeys) {
        if (!fd || !fd.entries) return;
        var skip = skipKeys || {};
        Array.from(fd.entries()).forEach(function(pair){
          var k = pair[0];
          var v = pair[1];
          if (skip[k]) return;
          if (typeof v !== 'string') return;
          var nv = normalizeNumericLikeValue(v);
          if (nv !== v) fd.set(k, nv);
        });
      }

      // Format any existing table cells for date columns on load
      function formatTableDates() {
        try {
          dateCols.forEach(function(col){
            var tds = Array.from(document.querySelectorAll('#bidsTable td[data-col="' + col + '"]'));
            tds.forEach(function(td){
              try {
                var txt = (td.textContent || '').toString().trim();
                if (!txt) return;
                var formatted = formatDateMMDDYYYY(txt);
                if (formatted) td.textContent = formatted;
              } catch(e){}
            });
          });
        } catch(e) { console.warn('formatTableDates failed', e); }
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

      var gcEditEnabled = false;

      function setGcEditState(enabled) {
        gcEditEnabled = !!enabled;
        try {
          var btn = document.getElementById('editGcToggleBtn');
          if (btn) btn.textContent = gcEditEnabled ? 'Save contractor info' : 'Edit contractor info';
        } catch(e) {}
        // Always enable Add Contractor button, regardless of edit mode
        try {
          var addBtn = document.getElementById('addGcBtn');
          if (addBtn) addBtn.disabled = false;
        } catch(e) {}
        try {
          var list = document.getElementById('gcTableList');
          if (list) {
            Array.from(list.querySelectorAll('input[data-field], select[data-field]')).forEach(function(el){
              if (el.tagName && el.tagName.toLowerCase() === 'select') {
                el.disabled = !gcEditEnabled;
                el.style.cursor = gcEditEnabled ? 'auto' : 'pointer';
              } else {
                el.readOnly = !gcEditEnabled;
                el.style.cursor = gcEditEnabled ? 'auto' : 'pointer';
              }
            });
            Array.from(list.querySelectorAll('button[title="Remove contractor"]')).forEach(function(btn){
              btn.disabled = !gcEditEnabled;
              btn.style.opacity = gcEditEnabled ? '1' : '0.4';
              btn.style.cursor = gcEditEnabled ? 'pointer' : 'not-allowed';
            });
          }
        } catch(e) {}
        try {
          var newGc = document.getElementById('newGcContainer');
          if (newGc) {
            Array.from(newGc.querySelectorAll('input, select, button.remove-gc')).forEach(function(el){
              if (el.tagName && el.tagName.toLowerCase() === 'select') {
                el.disabled = !gcEditEnabled;
                el.style.cursor = gcEditEnabled ? 'auto' : 'pointer';
              } else if (el.classList && el.classList.contains('remove-gc')) {
                el.disabled = !gcEditEnabled;
                el.style.opacity = gcEditEnabled ? '1' : '0.4';
                el.style.cursor = gcEditEnabled ? 'pointer' : 'not-allowed';
              } else {
                el.readOnly = !gcEditEnabled;
                el.style.cursor = gcEditEnabled ? 'auto' : 'pointer';
              }
            });
          }
        } catch(e) {}
      }

      var gcDirectoryCache = null;
      var gcDirectoryPromise = null;

      function normText(v) {
        return (v || '').toString().trim().toLowerCase();
      }

      function unionToValue(v) {
        var s = normText(v);
        if (!s) return '';
        if (s === '1' || s === 'true' || s === 'yes' || s === 'union') return '1';
        if (s === '0' || s === 'false' || s === 'no' || s === 'non-union' || s === 'nonunion') return '0';
        return '';
      }

      function findClientByNameOrCompany(clients, name, company) {
        var n = normText(name);
        var comp = normText(company);
        if (n) {
          var byName = (clients || []).find(function(c){ return normText(c.client_name) === n; });
          if (byName) return byName;
        }
        if (comp) {
          var matches = (clients || []).filter(function(c){ return normText(c.current_employer) === comp; });
          if (matches.length === 1) return matches[0];
        }
        return null;
      }

      function openClientProfile(client) {
        if (!client || !client.client_id) return;
        var base = '';
        try {
          var path = window.location.pathname || '';
          var idx = path.toLowerCase().indexOf('/pages/');
          base = (idx >= 0) ? path.slice(0, idx) : '';
        } catch(e) { base = ''; }
        var url = base + '/pages/client_profile/index.php?client_id=' + encodeURIComponent(client.client_id);
        try { window.open(url, '_blank'); } catch(e) { window.location.href = url; }
      }

      function openClientProfileForRow(nameVal, companyVal) {
        fetchGcDirectory().then(function(clients){
          var client = findClientByNameOrCompany(clients, nameVal, companyVal);
          if (client) openClientProfile(client);
        });
      }

      function fetchGcDirectory() {
        if (gcDirectoryCache) return Promise.resolve(gcDirectoryCache);
        if (gcDirectoryPromise) return gcDirectoryPromise;
        gcDirectoryPromise = fetch('../../api/get_gc_clients.php', { credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(j){
            if (j && j.success && Array.isArray(j.clients)) {
              gcDirectoryCache = j.clients;
            } else {
              gcDirectoryCache = [];
            }
            return gcDirectoryCache;
          })
          .catch(function(){
            gcDirectoryCache = [];
            return gcDirectoryCache;
          })
          .finally(function(){ gcDirectoryPromise = null; });
        return gcDirectoryPromise;
      }

      function getCompanyList(clients) {
        var seen = new Set();
        var list = [];
        (clients || []).forEach(function(c){
          var emp = (c.current_employer || '').toString().trim();
          if (!emp) return;
          var key = emp.toLowerCase();
          if (seen.has(key)) return;
          seen.add(key);
          list.push(emp);
        });
        return list;
      }

      function isKnownCompany(company, companies) {
        var cmp = normText(company);
        if (!cmp) return false;
        return (companies || []).some(function(c){ return normText(c) === cmp; });
      }

      function removeSuggest(input) {
        try {
          if (input && input._gcSuggestEl && input._gcSuggestEl.parentNode) {
            input._gcSuggestEl.parentNode.removeChild(input._gcSuggestEl);
          }
          if (input) input._gcSuggestEl = null;
        } catch(e) {}
      }

      function showSuggest(input, items, renderLabel, onPick) {
        if (!input) return;
        removeSuggest(input);
        if (!items || !items.length) return;
        var box = document.createElement('div');
        box.className = 'gc-suggest';
        items.slice(0, 8).forEach(function(item){
          var label = renderLabel ? renderLabel(item) : String(item);
          var row = document.createElement('div');
          row.className = 'gc-suggest-item';
          row.textContent = label;
          row.addEventListener('mousedown', function(ev){
            ev.preventDefault();
            try { onPick && onPick(item); } catch(e) {}
            removeSuggest(input);
          });
          box.appendChild(row);
        });
        var rect = input.getBoundingClientRect();
        box.style.left = (rect.left + window.scrollX) + 'px';
        box.style.top = (rect.bottom + window.scrollY + 4) + 'px';
        box.style.width = rect.width + 'px';
        document.body.appendChild(box);
        input._gcSuggestEl = box;
      }

      function wireGcRowAutocomplete(companyInput, nameInput, numberInput, emailInput, addressInput, unionInput) {
        if (companyInput && !companyInput.dataset.gcSuggestWired) {
          companyInput.dataset.gcSuggestWired = '1';
          var handleCompanySuggest = function(){
            fetchGcDirectory().then(function(clients){
              var companies = getCompanyList(clients);
              var q = (companyInput.value || '').toString().trim();
              if (!q) { removeSuggest(companyInput); return; }
              var matches = companies.filter(function(c){ return normText(c).indexOf(normText(q)) !== -1; });
              showSuggest(companyInput, matches, function(x){ return x; }, function(sel){
                companyInput.value = sel;
                if (nameInput) nameInput.focus();
              });
            });
          };
          companyInput.addEventListener('input', handleCompanySuggest);
          companyInput.addEventListener('focus', handleCompanySuggest);
          companyInput.addEventListener('blur', function(){ setTimeout(function(){ removeSuggest(companyInput); }, 140); });
        }

        if (companyInput && !companyInput.dataset.gcLinkWired) {
          companyInput.dataset.gcLinkWired = '1';
          companyInput.addEventListener('click', function(){
            var companyVal = companyInput.value || '';
            fetchGcDirectory().then(function(clients){
              var client = findClientByNameOrCompany(clients, '', companyVal);
              if (client) openClientProfile(client);
            });
          });
        }

        if (nameInput && !nameInput.dataset.gcSuggestWired) {
          nameInput.dataset.gcSuggestWired = '1';
          var handleNameSuggest = function(){
            fetchGcDirectory().then(function(clients){
              var companies = getCompanyList(clients);
              var companyVal = companyInput ? (companyInput.value || '') : '';
              if (!isKnownCompany(companyVal, companies)) { removeSuggest(nameInput); return; }
              var q = (nameInput.value || '').toString().trim();
              var people = (clients || []).filter(function(c){ return normText(c.current_employer) === normText(companyVal); });
              if (q) {
                people = people.filter(function(c){ return normText(c.client_name).indexOf(normText(q)) !== -1; });
              }
              showSuggest(nameInput, people, function(c){ return (c && c.client_name) ? c.client_name : ''; }, function(sel){
                if (!sel) return;
                if (sel.client_name) nameInput.value = sel.client_name;
                if (emailInput) emailInput.value = sel.client_email ? sel.client_email : '';
                if (addressInput) addressInput.value = sel.client_address ? sel.client_address : '';
                if (numberInput) numberInput.value = sel.contact_phone ? sel.contact_phone : '';
                if (unionInput) {
                  var u = unionToValue(sel.union_status);
                  unionInput.value = u !== '' ? u : unionInput.value;
                }
              });
            });
          };
          nameInput.addEventListener('input', handleNameSuggest);
          nameInput.addEventListener('focus', handleNameSuggest);
          nameInput.addEventListener('blur', function(){ setTimeout(function(){ removeSuggest(nameInput); }, 140); });
        }

        if (nameInput && !nameInput.dataset.gcLinkWired) {
          nameInput.dataset.gcLinkWired = '1';
          nameInput.addEventListener('click', function(){
            var nameVal = nameInput.value || '';
            var companyVal = companyInput ? (companyInput.value || '') : '';
            fetchGcDirectory().then(function(clients){
              var client = findClientByNameOrCompany(clients, nameVal, companyVal);
              if (client) openClientProfile(client);
            });
          });
        }
      }

      function attachGcDirectoryAutocomplete(container) {
        if (!container) return;
        var companyInputs = container.querySelectorAll('input[data-field="general_contractor"][data-id]');
        companyInputs.forEach(function(ci){
          var id = ci.getAttribute('data-id');
          if (!id) return;
          var nameInput = container.querySelector('input[data-field="general_contractor_name"][data-id="' + id + '"]') ||
                         container.querySelector('input[data-field="gc_name"][data-id="' + id + '"]');
          var numberInput = container.querySelector('input[data-field="general_contractor_number"][data-id="' + id + '"]') ||
                           container.querySelector('input[data-field="gc_number"][data-id="' + id + '"]');
          var emailInput = container.querySelector('input[data-field="general_contractor_email"][data-id="' + id + '"]') ||
                          container.querySelector('input[data-field="gc_email"][data-id="' + id + '"]');
          var addressInput = container.querySelector('input[data-field="general_contractor_address"][data-id="' + id + '"]') ||
                            container.querySelector('input[data-field="gc_address"][data-id="' + id + '"]');
          var unionInput = container.querySelector('select[data-field="is_union"][data-id="' + id + '"]') ||
                           container.querySelector('select[data-field="union"][data-id="' + id + '"]');
          wireGcRowAutocomplete(ci, nameInput, numberInput, emailInput, addressInput, unionInput);
        });
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
                  container.innerHTML = '<div style="padding:12px;color:#ef4444">No contractors found for this project.</div>';
                  return;
                }
                var table = document.createElement('div');
                table.style.display = 'grid';
                /* add an actions column on the right for remove 'X' buttons; include Union column before actions */
                table.style.gridTemplateColumns = '2fr 2fr 1.5fr 2fr 2fr 1.2fr 1fr 48px';
                table.style.gap = '8px';
                table.style.alignItems = 'center';
                // header row
                var hdrs = ['General Contractor','Name','Number','Email','Address','Client Win Price','Union'];
                hdrs.forEach(function(h){ var e = document.createElement('div'); e.style.fontWeight = '600'; e.style.padding = '6px 8px'; e.style.color = '#374151'; e.textContent = h; e.style.position = 'sticky'; e.style.top = '0'; e.style.background = '#ffffff'; e.style.zIndex = '4'; e.style.borderBottom = '1px solid #e6edf0'; e.style.textAlign = 'left'; table.appendChild(e); });
                // Add actions header (empty but keeps layout consistent)
                var actHdr = document.createElement('div'); actHdr.style.padding = '6px 8px'; actHdr.style.position = 'sticky'; actHdr.style.top = '0'; actHdr.style.background = '#ffffff'; actHdr.style.zIndex = '4'; actHdr.style.borderBottom = '1px solid #e6edf0'; actHdr.style.textAlign = 'left'; actHdr.textContent = '';
                table.appendChild(actHdr);
                // ensure container is a positioned scroll container so sticky headers work
                container.style.position = 'relative';

                var seenGc = new Set();
                items.forEach(function(it){
                  var id = it.id || '';
                  var gc = it.general_contractor || '';
                  var name = it.general_contractor_name || '';
                  var num = it.general_contractor_number || '';
                  // dedupe identical contractor entries by normalized name+number to avoid repeated rows
                  try {
                    var nm = (gc || name || '').toString().trim().toLowerCase();
                    var nn = (num || '').toString().trim().toLowerCase();
                    var key = nm + '|' + nn;
                    if (seenGc.has(key)) return; seenGc.add(key);
                  } catch(e) {}
                  var mail = it.general_contractor_email || '';
                  var addr = it.general_contractor_address || ''; 
                  var unionVal = (typeof it.is_union !== 'undefined') ? it.is_union : ((typeof it.union !== 'undefined') ? it.union : (it.general_contractor_union || ''));
                  var cwpRaw = (typeof it.client_win_price !== 'undefined') ? it.client_win_price : '';
                  var cwp = formatNumericWithGrouping(cwpRaw);
                  var isWinner = (it.winner && (it.winner == 1 || it.winner === '1' || it.winner === true));

                  function makeCellInput(val, nameAttr, placeholder, highlightColor) {
                    var wrapper = document.createElement('div');
                    wrapper.style.padding = '6px 8px';
                    wrapper.style.borderBottom = '1px solid #eef2f7';
                    wrapper.style.textAlign = 'left';
                    wrapper.setAttribute('data-gc-id', id);
                    wrapper.setAttribute('data-gc-company', gc || '');
                    wrapper.setAttribute('data-gc-name', name || '');
                    wrapper.style.cursor = gcEditEnabled ? 'auto' : 'pointer';
                    wrapper.addEventListener('click', function(){
                      if (gcEditEnabled) return;
                      openClientProfileForRow(name || '', gc || '');
                    });

                    // Render a select for union fields, otherwise a regular text input
                    if (nameAttr === 'is_union' || nameAttr === 'union' || (nameAttr || '').toString().toLowerCase().indexOf('union') !== -1) {
                      var sel = document.createElement('select');
                      sel.style.width = '100%';
                      sel.style.border = '0';
                      sel.style.background = 'transparent';
                      sel.style.fontWeight = highlightColor ? '600' : '400';
                      sel.setAttribute('data-field', nameAttr === 'union' ? 'union' : 'is_union');
                      sel.setAttribute('data-id', id);
                      var opt1 = document.createElement('option'); opt1.value = '1'; opt1.textContent = 'Union';
                      var opt0 = document.createElement('option'); opt0.value = '0'; opt0.textContent = 'Non-union';
                      sel.appendChild(opt1); sel.appendChild(opt0);
                      try { sel.value = (val === 1 || val === '1' || String(val).toLowerCase() === 'true') ? '1' : '0'; } catch(e) { sel.value = '0'; }
                      if (highlightColor) sel.style.color = highlightColor; else sel.style.color = '#ef4444';
                      wrapper.appendChild(sel);
                      return wrapper;
                    }

                    var inp = document.createElement('input');
                    inp.type = 'text';
                    inp.value = val;
                    inp.placeholder = placeholder || '';
                    inp.autocomplete = 'off';
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
                  table.appendChild(makeCellInput(cwp, 'client_win_price', 'Client win price', winnerColor));
                  // Union column: editable select
                  table.appendChild(makeCellInput(unionVal, 'is_union', 'Union', winnerColor));

                  // Action cell: remove 'X' button on the right
                  var actionCell = document.createElement('div');
                  actionCell.style.padding = '6px 8px';
                  actionCell.style.borderBottom = '1px solid #eef2f7';
                  actionCell.style.display = 'flex';
                  actionCell.style.alignItems = 'center';
                  actionCell.style.justifyContent = 'flex-end';
                  actionCell.setAttribute('data-gc-id', id);
                  actionCell.style.cursor = gcEditEnabled ? 'auto' : 'pointer';
                  actionCell.addEventListener('click', function(){
                    if (gcEditEnabled) return;
                    openClientProfileForRow(name || '', gc || '');
                  });
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
                try { attachGcDirectoryAutocomplete(container); } catch(e) {}
                try { setGcEditState(gcEditEnabled); } catch(e) {}
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
        // Reset GC edit state and button label on modal open
        setGcEditState(false);

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

        // Clear all modal-bound fields first so missing keys in bidObj cannot leak prior values.
        Array.from(modal.querySelectorAll('[data-col]')).forEach(function(el){
          var tag = (el.tagName || '').toLowerCase();
          if (tag === 'input' || tag === 'textarea' || tag === 'select') el.value = '';
        });

        // fill other fields (supports inputs, selects, and textareas)
        bidColumns.forEach(function(col){
          if (col === 'project_name') return;
          var els = modal.querySelectorAll('[data-col="' + col + '"]');
          els.forEach(function(el){
            var tag = (el.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') {
              try {
                if (dateCols.indexOf(col) !== -1) {
                  var raw = (bidObj[col] !== undefined && bidObj[col] !== null) ? bidObj[col] : '';
                  var iso = toIsoDate(raw || '');
                  // If input is a native date input, it expects ISO yyyy-mm-dd
                  if (el.type === 'date') {
                    el.value = iso || '';
                  } else {
                    el.value = iso ? formatDateMMDDYYYY(iso) : '';
                  }
                } else if (moneyCols.indexOf(col) !== -1) {
                  var mv = (bidObj[col] !== undefined && bidObj[col] !== null) ? bidObj[col] : '';
                  el.value = formatNumericWithGrouping(mv);
                } else {
                  el.value = (bidObj[col] !== undefined && bidObj[col] !== null) ? bidObj[col] : '';
                }
              } catch(e) { el.value = (bidObj[col] !== undefined && bidObj[col] !== null) ? bidObj[col] : ''; }
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

        // Ensure date companion inputs are synced for modal fields
        try {
          dateCols.forEach(function(col){
            try {
              var el = modal.querySelector('[data-col="' + col + '"]') || modal.querySelector('[name="' + col + '"]');
              if (!el) return;
              // set visible value to mm/dd/yyyy (already done earlier) and ensure native companion has ISO value
              var visVal = el.value || '';
              var iso = toIsoDate(visVal || '');
              var comp = modal.querySelector('[data-col="' + col + '"]') ? document.querySelector('#' + (el.id || '') + '_native') : null;
              // fallback: look for next sibling native input
              if (!comp && el.nextElementSibling && el.nextElementSibling.type === 'date') comp = el.nextElementSibling;
              if (comp && iso) comp.value = iso;
            } catch(e){}
          });
        } catch(e) {}


        modal.style.display = 'flex';
      }

      function closeEditModal() {
        var modal = document.getElementById('editBidModal');
        if (!modal) return;
        // If GC section is in edit mode, save contractor info before closing
        if (window.gcEditEnabled) {
          var saveBtn = document.getElementById('editGcToggleBtn');
          if (saveBtn && saveBtn.textContent.indexOf('Save contractor info') !== -1) {
            saveBtn.click(); // trigger save if in edit mode
          }
          setGcEditState(false); // ensure GC section is not in edit mode next time
        }
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

            function collectGcClientPayload() {
              var gcContainer = document.getElementById('gcTableList');
              if (!gcContainer) return [];
              var projId = '';
              try {
                var dhssEl = document.getElementById('editDhssProjectNumber');
                projId = dhssEl ? (dhssEl.value || '').toString().trim() : '';
              } catch(e) { projId = ''; }
              var inputs = gcContainer.querySelectorAll('input[data-field][data-id], select[data-field][data-id]');
              var groups = {};
              inputs.forEach(function(inp){
                var id = inp.getAttribute('data-id');
                var field = inp.getAttribute('data-field');
                if (!id || !field) return;
                groups[id] = groups[id] || {};
                groups[id][field] = inp.value || '';
              });
              var list = [];
              Object.keys(groups).forEach(function(id){
                var g = groups[id] || {};
                var name = (g.general_contractor_name || '').toString().trim();
                var gc = (g.general_contractor || '').toString().trim();
                if (!name && !gc) return;
                list.push({
                  general_contractor: gc,
                  general_contractor_name: name,
                  general_contractor_number: g.general_contractor_number || '',
                  general_contractor_email: g.general_contractor_email || '',
                  general_contractor_address: g.general_contractor_address || '',
                  is_union: (typeof g.is_union !== 'undefined') ? g.is_union : (g.union || ''),
                  dhss_project_number: projId
                });
              });
              return list;
            }

            function syncGcClientsToDirectory() {
              try {
                var payload = collectGcClientPayload();
                if (!payload.length) return Promise.resolve(null);
                return fetch('../../api/save_gc_clients.php', {
                  method: 'POST',
                  credentials: 'same-origin',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ clients: payload })
                }).then(function(r){ return r.json(); }).catch(function(err){
                  console.warn('save_gc_clients failed', err);
                  return null;
                });
              } catch (e) {
                console.warn('save_gc_clients exception', e);
                return Promise.resolve(null);
              }
            }

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
                var email = r.querySelector('input[name="new_gc_email"]');
                var addr = r.querySelector('input[name="new_gc_address"]');
                var cwp = r.querySelector('input[name="new_gc_client_win_price"]');
                var unionSel = r.querySelector('select[name="new_gc_union"]');
                var obj = {};
                if (gc) obj['general_contractor'] = gc.value || null;
                if (name) obj['gc_name'] = name.value || null;
                if (num) obj['gc_number'] = num.value || null;
                if (email) obj['general_contractor_email'] = email.value || null;
                if (addr) obj['general_contractor_address'] = addr.value || null;
                if (cwp) {
                  var rawCwp = cwp.value || '';
                  var cleanedCwp = ('' + rawCwp).replace(/[^0-9.\-]/g, '');
                  obj['client_win_price'] = cleanedCwp || null;
                }
                if (unionSel) { var uval = (unionSel.value === '1') ? '1' : '0'; obj['union'] = uval; obj['is_union'] = uval; }
                if (obj.general_contractor || obj.gc_name || obj.gc_number || obj.general_contractor_email || obj.general_contractor_address || obj.client_win_price || obj.union || obj.is_union) newClones.push(obj);
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
                      try {
                        var shouldAdd = true;
                        var gcContainerCheck = document.getElementById('gcTableList');
                        if (gcContainerCheck) {
                          // look for any existing contractor row inputs with a matching name or number
                          var existingInputs = gcContainerCheck.querySelectorAll('input[data-field][data-id], select[data-field][data-id]');
                          existingInputs.forEach(function(ei){
                            try {
                              var f = ei.getAttribute('data-field');
                              var v = (ei.value || '').toString().trim().toLowerCase();
                              if (!v) return;
                              if ((f === 'general_contractor' || f === 'general_contractor_name') && v === mgc.toLowerCase()) { shouldAdd = false; }
                              if (f === 'general_contractor_number' && v === mgc_num.toLowerCase()) { shouldAdd = false; }
                            } catch(e){}
                          });
                        }
                        // avoid duplicate if already queued in newClones
                        var existsQueued = newClones.find(function(x){ return x.general_contractor && x.general_contractor.toString().trim().toLowerCase() === mgc.toLowerCase(); });
                        if (!existsQueued && shouldAdd) {
                          // also collect email/address/union from modal fields if present
                          var mgc_email = '';
                          var mgc_addr = '';
                          var mgc_union = undefined;
                          try {
                            var emailEl = modalGc.querySelector('[data-col="general_contractor_email"]') || modalGc.querySelector('[name="general_contractor_email"]');
                            if (emailEl) mgc_email = (emailEl.value || '').toString().trim();
                          } catch(e){}
                          try {
                            var addrEl = modalGc.querySelector('[data-col="general_contractor_address"]') || modalGc.querySelector('[name="general_contractor_address"]');
                            if (addrEl) mgc_addr = (addrEl.value || '').toString().trim();
                          } catch(e){}
                          try {
                            var unionEl = modalGc.querySelector('[data-col="is_union"]') || modalGc.querySelector('[name="is_union"]') || modalGc.querySelector('[name="union"]');
                            if (unionEl) mgc_union = (unionEl.value === '1' || unionEl.value === 1) ? '1' : '0';
                          } catch(e){}

                          var obj = { general_contractor: mgc, gc_name: mgc_name || null, gc_number: mgc_num || null };
                          if (mgc_email) obj.general_contractor_email = mgc_email;
                          if (mgc_addr) obj.general_contractor_address = mgc_addr;
                          if (typeof mgc_union !== 'undefined') obj.is_union = mgc_union;
                          newClones.push(obj);
                        }
                      } catch(e) {}
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
              var dhss = document.getElementById('editDhssProjectNumber') ? document.getElementById('editDhssProjectNumber').value.trim() : '';
                var tasks = newClones.map(function(c){
                var form = new FormData();
                if (c.general_contractor) form.append('general_contractor', c.general_contractor);
                if (c.gc_name) form.append('general_contractor_name', c.gc_name);
                if (c.gc_number) form.append('general_contractor_number', c.gc_number);
                if (c.general_contractor_email) form.append('general_contractor_email', c.general_contractor_email);
                if (c.general_contractor_address) form.append('general_contractor_address', c.general_contractor_address);
                if (c.client_win_price) form.append('client_win_price', c.client_win_price);
                if (typeof c.is_union !== 'undefined') form.append('is_union', c.is_union);
                else if (typeof c.union !== 'undefined') form.append('is_union', c.union);
                if (dhss) form.append('dhss_project_number', dhss);
                return fetch('../../api/add_general_contractor.php', { method: 'POST', credentials: 'same-origin', body: form }).then(function(r){ return r.json(); });
              });
              Promise.all(tasks).then(function(results){
                // If any failed, reject
                var bad = results.find(function(x){ return !x || !x.success; });
                if (bad) return reject(bad);

                // Build created records map from results and the original newClones (same order)
                var created = [];
                try {
                  for (var i = 0; i < results.length; i++) {
                    var res = results[i] || {};
                    var nc = newClones[i] || {};
                    if (res && res.success && res.id) {
                      created.push({ id: res.id, name: (nc.gc_name || nc.general_contractor || '').toString().trim(), number: (nc.gc_number || '').toString().trim() });
                    }
                  }
                } catch(e) { created = []; }

                // Refresh the GC list for this project so newly-created contractors appear in the DOM
                try { if (typeof loadGcList === 'function') loadGcList(dhss); } catch(e){}

                // After refreshing, try to attach returned IDs to the corresponding inputs in the GC table.
                // Wait a short time for loadGcList to render; then match rows by name/number and set data-id attributes.
                setTimeout(function(){
                  try {
                    var gcContainer = document.getElementById('gcTableList');
                    if (gcContainer && created.length) {
                      created.forEach(function(c){
                        if (!c || !c.id) return;
                        var normName = (c.name || '').toString().trim().toLowerCase();
                        var normNum = (c.number || '').toString().trim().toLowerCase();
                        // find any input elements that match by name or number
                        var candidates = Array.from(gcContainer.querySelectorAll('input[data-field], select[data-field]'));
                        for (var j = 0; j < candidates.length; j++) {
                          var el = candidates[j];
                          try {
                            var f = el.getAttribute('data-field') || '';
                            var v = (el.value || '').toString().trim().toLowerCase();
                            if (!v) continue;
                            var match = false;
                            if ((f === 'general_contractor' || f === 'general_contractor_name') && normName && v === normName) match = true;
                            if (f === 'general_contractor_number' && normNum && v === normNum) match = true;
                            if (match) {
                              // set data-id on all inputs/selects that belong to this contractor row
                              var rowElems = gcContainer.querySelectorAll('[data-gc-id]');
                              // Prefer elements with same visible text in the same row; try to walk up to parent cell
                              // Simpler: set data-id on this element and try to set on siblings that share same parent row
                              try { el.setAttribute('data-id', String(c.id)); } catch(e){}
                              try {
                                var parent = el.parentNode;
                                if (parent) {
                                  var siblings = parent.parentNode ? parent.parentNode.querySelectorAll('[data-field]') : [];
                                  siblings.forEach(function(s){ try { if (!s.getAttribute('data-id')) s.setAttribute('data-id', String(c.id)); } catch(e){} });
                                }
                              } catch(e){}
                              // also set on any remove button action cell in the same row
                              try {
                                var rem = el.parentNode && el.parentNode.parentNode ? el.parentNode.parentNode.querySelector('button[title="Remove contractor"]') : null;
                                if (rem && !rem.getAttribute('data-id')) rem.setAttribute('data-id', String(c.id));
                              } catch(e){}
                              // once matched, stop scanning candidates for this created record
                              break;
                            }
                          } catch(e){}
                        }
                      });
                    }
                  } catch(e) {}
                  resolve(results);
                }, 450);
              }).catch(function(err){ reject(err); });
            })).then(function(){
              // Now collect edits made in the GC list and send updates for existing rows
              return new Promise(function(resolveUpdates, rejectUpdates){
                try {
                  var gcContainer = document.getElementById('gcTableList');
                  var updateTasks = [];
                  if (gcContainer) {
                    var inputs = gcContainer.querySelectorAll('input[data-field][data-id], select[data-field][data-id]');
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
                      if (g.client_win_price !== undefined) form.append('client_win_price', g.client_win_price);
                      if (g.is_union !== undefined) form.append('is_union', g.is_union);
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

                  // Convert any user-facing date fields (mm/dd/yyyy) to ISO (yyyy-mm-dd) before sending
                  try {
                    if (Array.isArray(dateCols) && dateCols.length) {
                      dateCols.forEach(function(col){
                        try {
                          var el = document.querySelector('#editBidModal [name="' + col + '"]') || document.querySelector('#editBidModal [data-col="' + col + '"]');
                          var raw = el ? (el.value || '') : (fd.get(col) || '');
                          if (raw) {
                            var iso = toIsoDate(raw);
                            if (iso) fd.set(col, iso);
                          }
                        } catch(e){}
                      });
                    }
                  } catch(e) {}

                    // Sanitize monetary inputs for all moneyCols: allow users to type $ and commas, but send clean numeric values to server
                    try {
                      if (Array.isArray(moneyCols) && moneyCols.length) {
                        moneyCols.forEach(function(col){
                          try {
                            var v = null;
                            try { v = fd.get(col); } catch(e) { v = null; }
                            if (v === null || typeof v === 'undefined') {
                              try { var el = editForm.querySelector('[name="' + col + '"], [data-col="' + col + '"]'); if (el && typeof el.value !== 'undefined') v = el.value; } catch(e) { v = null; }
                            }
                            if (v !== null && typeof v !== 'undefined') {
                              var cleaned = ('' + v).replace(/[^0-9.\-]/g, '');
                              if (cleaned === '') { fd.delete(col); } else { fd.set(col, cleaned); }
                            }
                          } catch(e) {}
                        });
                      }
                    } catch(e) { /* ignore sanitization failures */ }

                    // Normalize comma-separated decimal values for all submitted fields except date fields.
                    try {
                      var skipKeys = {};
                      if (Array.isArray(dateCols)) {
                        dateCols.forEach(function(dc){ skipKeys[dc] = true; });
                      }
                      normalizeFormDataNumericLike(fd, skipKeys);
                    } catch(e) {}

                    return syncGcClientsToDirectory().then(function(){
                      return fetch(theUpdateUrl, { method: 'POST', credentials: 'same-origin', body: fd });
                    });
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
                  // Save reason value to bid_reasons table so it appears in future dropdowns
                  try {
                    var reasonInputEl = document.getElementById('editReasonInput');
                    var newReasonVal = reasonInputEl ? (reasonInputEl.value || '').trim() : '';
                    if (newReasonVal) {
                      var rf = new FormData();
                      rf.append('reason', newReasonVal);
                      fetch('../../api/save_bid_reason.php', { method: 'POST', credentials: 'same-origin', body: rf })
                        .catch(function(){});
                    }
                  } catch(e){}
                  // Keep the edit modal open after saving so users can continue editing.
                  try { showToast('Saved', 'success'); } catch(e){}
                  try {
                    // Update the in-memory originalRows and the row DOM so changes persist without a full reload
                    var newBid = data.bid || null;
                    if (newBid && originalRows && originalRows.length) {
                      var found = originalRows.find(function(it){ return it && it.obj && (it.obj.bid_id && it.obj.bid_id.toString() === (newBid.bid_id || '').toString()); });
                      if (found) {
                        // Preserve user-selected status values that may not round-trip through the DB (e.g., new 'bidding' state)
                        try {
                          var modal = document.getElementById('editBidModal');
                          if (modal) {
                            var statusInput = modal.querySelector('[name="status"]');
                            var userStatus = statusInput ? (statusInput.value || '') : '';
                            if (userStatus && userStatus.toString().toLowerCase() === 'bidding') {
                              // If server didn't persist unknown status, reflect user's choice in the local model
                              newBid.status = userStatus;
                            }
                          }
                        } catch(e) {}
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
                      // If any GC rows were removed in the modal, delete them from the DB now
                      (function(){
                        try {
                          var modalEl = document.getElementById('editBidModal');
                          if (!modalEl) return;
                          var dels = Array.from(modalEl.querySelectorAll('input[name="delete_general_contractor_ids[]"]')).map(function(i){ return i.value; }).filter(function(x){ return x; });
                          if (!dels.length) return;
                          var tasks = dels.map(function(id){
                            try {
                              var f = new FormData(); f.append('id', id);
                              return fetch('../../api/delete_general_contractor.php', { method: 'POST', credentials: 'same-origin', body: f }).then(function(r){ return r.json ? r.json() : null; }).catch(function(){ return null; });
                            } catch(e){ return null; }
                          });
                          Promise.all(tasks).then(function(results){
                            // Remove any hidden delete inputs so they won't be processed again
                            try { dels.forEach(function(id){ var inp = modalEl.querySelector('input[name="delete_general_contractor_ids[]"][value="' + id.replace(/"/g,'\"') + '"]'); if (inp && inp.parentNode) inp.parentNode.removeChild(inp); }); } catch(e){}
                            // Refresh GC display across the table
                            try { gcProjectCache = {}; } catch(e){}
                            try { if (typeof syncGcDisplayForProjects === 'function') syncGcDisplayForProjects(); } catch(e){}
                          }).catch(function(){ try { gcProjectCache = {}; } catch(e){}; try { if (typeof syncGcDisplayForProjects === 'function') syncGcDisplayForProjects(); } catch(e){} });
                        } catch(e) { console.warn('delete gc ids failed', e); }
                      })();

                      // Ensure GC display and highlights are refreshed immediately
                      try { gcProjectCache = {}; } catch(e){}
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
                try {
                  var newGcContainer = document.getElementById('newGcContainer');
                  if (newGcContainer) newGcContainer.innerHTML = '';
                } catch(e) {}
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

              // Offer an option to delete the entire project (all bids + contractors) or only this bid
              var dhss = (document.getElementById('editDhssProjectNumber') ? document.getElementById('editDhssProjectNumber').value.trim() : '');
              var doProject = false;
              if (dhss) {
                doProject = confirm('Delete the entire project (all bids and contractors) for DHSS ' + dhss + '?\n\nOK = delete entire project. Cancel = delete only this bid.');
              } else {
                if (!confirm('Delete this bid? This action cannot be undone.')) return;
              }

              deleteBtn.disabled = true; deleteBtn.textContent = 'Deleting...';

              if (doProject) {
                var fd = new FormData(); fd.append('dhss_project_number', dhss);
                fetch('../../api/delete_project_by_dhss.php', { method: 'POST', credentials: 'same-origin', body: fd })
                  .then(function(r){ return r.json ? r.json() : r.text(); })
                  .then(function(data){
                    if (data && data.success) {
                      try { showToast('Project and related data deleted', 'success'); } catch(e){}
                      try { closeEditModal(); } catch(e){}
                      setTimeout(function(){ window.location.reload(); }, 600);
                    } else {
                      var msg = (data && data.message) ? data.message : 'Delete failed';
                      try { showToast(msg, 'error'); } catch(e){}
                      deleteBtn.disabled = false; deleteBtn.textContent = 'Delete';
                    }
                  }).catch(function(){ try { showToast('Delete failed', 'error'); } catch(e){}; deleteBtn.disabled = false; deleteBtn.textContent = 'Delete'; });
              } else {
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
              }
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
          var topThumb = document.getElementById('tableTopCustomThumb');

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
            try {
              // Make inner width track the table's scrollWidth so native scrollbar thumb matches table width
              topInner.style.width = (table.scrollWidth || 0) + 'px';
              // Match inner height to scroller so clicking anywhere on the track is interactive
              try { topInner.style.height = (topScroller.clientHeight || 26) + 'px'; } catch(e){}
              // Ensure the scroller is visible and interactive
              topScroller.style.visibility = 'visible';
              topScroller.style.pointerEvents = 'auto';
              // Keep top scroller position in sync with container
              try { topScroller.scrollLeft = container.scrollLeft || 0; } catch(e){}
            } catch(e) { console.warn('syncTopScroller error', e); }
          }
          window.syncTopScroller = syncTopScroller; // expose for later calls

            if (topScroller && container) {
            // Two-way native scroll sync
            topScroller.addEventListener('scroll', function(){ try { container.scrollLeft = topScroller.scrollLeft; } catch(e){} });
            container.addEventListener('scroll', function(){ try { topScroller.scrollLeft = container.scrollLeft; syncTopScroller(); } catch(e){} });

            window.addEventListener('resize', syncTopScroller);
            // Initial sync shortly after init
            setTimeout(syncTopScroller, 60);
            // Extra sync after window load and a longer timeout to cover late layout changes
            window.addEventListener('load', function(){ try { setTimeout(syncTopScroller, 120); setTimeout(syncTopScroller, 600); } catch(e){} });
            setTimeout(syncTopScroller, 600);
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
          var yearFilterTopEl = document.getElementById('yearFilterTop');
          var statusFilterTopEl = document.getElementById('statusFilterTop');
          var orderByEl = document.getElementById('orderBySelect');
          var bidTrackingSearchTerm = '';
          var globalSearchInputEl = document.getElementById('globalProjectSearch');
          var paginationControlsEl = document.getElementById('paginationControls');
          var currentPage = 1;
          var pageSize = 50;
          var totalPages = 1;
          var refreshMultiSelectById = function(){};
          var searchRefreshTimer = null;
          var gcProjectCache = {};
          var gcProjectInFlight = {};

          // Build last 5 years dropdown (auto updates each year)
        (function initYearOptions(){
  if (!yearFilterEl) return;
  var nowY = new Date().getFullYear();
  var nowYShort = String(nowY).slice(-2);

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

  // Default to All Years unless session value exists (session restore runs after this)
  yearFilterEl.value = '';
  // trigger filtering without persisting across refresh
  yearFilterEl.addEventListener('change', function(){ try { applyFiltersAndGrouping(); } catch(e){} });
})();
          // Mirror year options to the compact top selector (if present)
          try {
            if (yearFilterTopEl && yearFilterEl) {
              yearFilterTopEl.innerHTML = yearFilterEl.innerHTML;
              // keep multi-select behavior in sync
              function syncSelectValues(fromEl, toEl){ try { if(!fromEl||!toEl) return; var vals = Array.from(fromEl.selectedOptions).map(function(o){return o.value;}); Array.from(toEl.options).forEach(function(opt){ opt.selected = vals.indexOf(opt.value) !== -1; }); if (!Array.from(toEl.options).some(function(o){ return o.selected; }) && toEl.options.length) toEl.options[0].selected = true; } catch(e){} }
              // Initialize selection
              syncSelectValues(yearFilterEl, yearFilterTopEl);
              yearFilterTopEl.addEventListener('change', function(){ try { if (yearFilterEl) { syncSelectValues(yearFilterTopEl, yearFilterEl); yearFilterEl.dispatchEvent(new Event('change')); } refreshMultiSelectById('yearFilter'); saveTopFiltersToSession(); } catch(e){} });
              // Keep header year select in sync when header changes
              yearFilterEl.addEventListener('change', function(){ try { if (yearFilterTopEl) syncSelectValues(yearFilterEl, yearFilterTopEl); refreshMultiSelectById('yearFilterTop'); } catch(e){} });
            }
          } catch(e){}

          // Mirror status options to the compact top selector (if present)
          try {
            if (statusFilterTopEl && statusFilterEl) {
              statusFilterTopEl.innerHTML = statusFilterEl.innerHTML;
              // initialize selection sync
              function syncSelectValues2(fromEl, toEl){ try { if(!fromEl||!toEl) return; var vals = Array.from(fromEl.selectedOptions).map(function(o){return o.value;}); Array.from(toEl.options).forEach(function(opt){ opt.selected = vals.indexOf(opt.value) !== -1; }); if (!Array.from(toEl.options).some(function(o){ return o.selected; }) && toEl.options.length) toEl.options[0].selected = true; } catch(e){} }
              syncSelectValues2(statusFilterEl, statusFilterTopEl);
              statusFilterTopEl.addEventListener('change', function(){ try { if (statusFilterEl) { syncSelectValues2(statusFilterTopEl, statusFilterEl); statusFilterEl.dispatchEvent(new Event('change')); } refreshMultiSelectById('statusFilter'); saveTopFiltersToSession(); } catch(e){} });
              statusFilterEl.addEventListener('change', function(){ try { if (statusFilterTopEl) syncSelectValues2(statusFilterEl, statusFilterTopEl); refreshMultiSelectById('statusFilterTop'); } catch(e){} });
            }
          } catch(e){}

          // Convert visible multi-select <select> elements into collapsible checkbox menus
          try {
            var multiSelectInstances = {};

            function updateMultiSelectLabel(btn, sel, fallbackLabel){
              try {
                var vals = Array.from(sel.selectedOptions)
                  .map(function(o){ return (o.textContent || o.value || '').trim(); })
                  .filter(function(v){ return v !== '' && v.toLowerCase() !== 'all years'; });
                if (!vals.length) {
                  btn.textContent = fallbackLabel || 'All';
                  return;
                }
                btn.textContent = vals.slice(0, 3).join(', ') + (vals.length > 3 ? '…' : '');
              } catch(e){}
            }

            function refreshMultiSelectInstance(instance){
              if (!instance || !instance.sel || !instance.menu || !instance.btn) return;
              try {
                var selectedSet = {};
                Array.from(instance.sel.selectedOptions).forEach(function(opt){ selectedSet[opt.value] = true; });
                Array.from(instance.menu.querySelectorAll('input[type=checkbox]')).forEach(function(chk){
                  chk.checked = !!selectedSet[chk.value];
                });
                updateMultiSelectLabel(instance.btn, instance.sel, instance.placeholder);
              } catch(e){}
            }

            refreshMultiSelectById = function(selId){
              try { refreshMultiSelectInstance(multiSelectInstances[selId]); } catch(e){}
            };

            function buildMultiSelectFromSelect(selId, containerAfterId, placeholder) {
              var sel = document.getElementById(selId);
              if (!sel) return null;
              // hide original select (keep in DOM for filtering logic)
              sel.style.display = 'none';
              try {
                var hasInitialSelected = Array.from(sel.options).some(function(o){ return o.selected; });
                if (!hasInitialSelected && sel.options.length) sel.options[0].selected = true;
              } catch(e){}

              // create wrapper
              var wrap = document.createElement('div'); wrap.className = 'multi-select';
              var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'multi-select-button'; btn.textContent = placeholder || 'Select';
              wrap.appendChild(btn);

              var menu = document.createElement('div'); menu.className = 'multi-select-menu';
              // populate options
              Array.from(sel.options).forEach(function(opt){
                var lab = document.createElement('label');
                var chk = document.createElement('input'); chk.type = 'checkbox'; chk.value = opt.value; chk.checked = opt.selected;
                lab.appendChild(chk);
                var span = document.createElement('span'); span.textContent = opt.textContent || opt.value; span.style.fontWeight='600'; span.style.color='#0f172a';
                lab.appendChild(span);
                menu.appendChild(lab);
                chk.addEventListener('change', function(){
                  // sync back to original select
                  try {
                    var isAllStatus = (opt.value === 'all');
                    var isAllYears = (opt.value === '');
                    if (isAllStatus || isAllYears) {
                      // selecting 'all' clears others
                      if (chk.checked) {
                        Array.from(menu.querySelectorAll('input[type=checkbox]')).forEach(function(c){ if (c !== chk) c.checked = false; });
                      }
                    } else {
                      // uncheck catch-all options if another value is selected
                      if (chk.checked) {
                        var allStatusChk = Array.from(menu.querySelectorAll('input[type=checkbox]')).find(function(c){ return c.value === 'all'; });
                        var allYearsChk = Array.from(menu.querySelectorAll('input[type=checkbox]')).find(function(c){ return c.value === ''; });
                        if (allStatusChk) allStatusChk.checked = false;
                        if (allYearsChk) allYearsChk.checked = false;
                      }
                    }
                    Array.from(menu.querySelectorAll('input[type=checkbox]')).forEach(function(c){
                      for (var i=0;i<sel.options.length;i++){ if (sel.options[i].value === c.value) sel.options[i].selected = c.checked; }
                    });
                    // if user unselects everything, default back to first option
                    var anySelected = Array.from(sel.options).some(function(o){ return o.selected; });
                    if (!anySelected && sel.options.length) {
                      sel.options[0].selected = true;
                    }
                    // update button label
                    updateMultiSelectLabel(btn, sel, placeholder || 'All');
                    sel.dispatchEvent(new Event('change'));
                  } catch(e) {}
                });
              });

              wrap.appendChild(menu);
              // insert after the original select
              sel.parentNode.insertBefore(wrap, sel.nextSibling);

              // toggle menu
              btn.addEventListener('click', function(ev){ ev.stopPropagation(); menu.classList.toggle('show'); });
              menu.addEventListener('click', function(ev){ ev.stopPropagation(); });
              document.addEventListener('click', function(ev){
                try {
                  if (wrap && !wrap.contains(ev.target)) menu.classList.remove('show');
                } catch(e) {
                  menu.classList.remove('show');
                }
              });

              // helper to set initial label
              updateMultiSelectLabel(btn, sel, placeholder || 'All');

              var instance = { wrap:wrap, sel:sel, menu:menu, btn:btn, placeholder:(placeholder || 'All') };
              multiSelectInstances[selId] = instance;

              sel.addEventListener('change', function(){ refreshMultiSelectInstance(instance); });

              return instance;
            }

            // init the four filter selects (top + header) if present
            buildMultiSelectFromSelect('statusFilterTop', null, 'All');
            buildMultiSelectFromSelect('yearFilterTop', null, 'All Years');
            buildMultiSelectFromSelect('statusFilter', null, 'All');
            buildMultiSelectFromSelect('yearFilter', null, 'All Years');
          } catch(e) { console.warn('multi-select init failed', e); }

          // Top-filter session persistence (per-browser-session; not global/local)
          var TOP_STATUS_KEY = 'bidTracking_top_status';
          var TOP_YEAR_KEY = 'bidTracking_top_year';
          var TOP_ORDER_KEY = 'bidTracking_top_orderBy';

          function saveTopFiltersToSession() {
            try {
              if (window.sessionStorage) {
                if (statusFilterTopEl) {
                  try { var ss = Array.from(statusFilterTopEl.selectedOptions).map(function(o){return o.value;}); sessionStorage.setItem(TOP_STATUS_KEY, JSON.stringify(ss)); } catch(e) { sessionStorage.setItem(TOP_STATUS_KEY, JSON.stringify(['all'])); }
                }
                if (yearFilterTopEl) {
                  try { var ys = Array.from(yearFilterTopEl.selectedOptions).map(function(o){return o.value;}); sessionStorage.setItem(TOP_YEAR_KEY, JSON.stringify(ys)); } catch(e) { sessionStorage.setItem(TOP_YEAR_KEY, JSON.stringify([''])); }
                }
                if (orderByEl) sessionStorage.setItem(TOP_ORDER_KEY, orderByEl.value || 'date_asc');
              }
            } catch(e) { }
          }

          function restoreTopFiltersFromSession() {
            try {
              if (window.sessionStorage) {
                var s = sessionStorage.getItem(TOP_STATUS_KEY);
                var y = sessionStorage.getItem(TOP_YEAR_KEY);
                var o = sessionStorage.getItem(TOP_ORDER_KEY);
                if (s !== null && statusFilterTopEl) {
                  try {
                    var sval = JSON.parse(s);
                    Array.from(statusFilterTopEl.options).forEach(function(opt){ opt.selected = (sval.indexOf(opt.value) !== -1); });
                    if (statusFilterEl) { Array.from(statusFilterEl.options).forEach(function(opt){ opt.selected = (sval.indexOf(opt.value) !== -1); }); statusFilterEl.dispatchEvent(new Event('change')); }
                    refreshMultiSelectById('statusFilterTop');
                    refreshMultiSelectById('statusFilter');
                  } catch(e) { statusFilterTopEl.value = s; if (statusFilterEl) { statusFilterEl.value = s; statusFilterEl.dispatchEvent(new Event('change')); } }
                }
                if (y !== null && yearFilterTopEl) {
                  try {
                    var yval = JSON.parse(y);
                    Array.from(yearFilterTopEl.options).forEach(function(opt){ opt.selected = (yval.indexOf(opt.value) !== -1); });
                    if (yearFilterEl) { Array.from(yearFilterEl.options).forEach(function(opt){ opt.selected = (yval.indexOf(opt.value) !== -1); }); yearFilterEl.dispatchEvent(new Event('change')); }
                    refreshMultiSelectById('yearFilterTop');
                    refreshMultiSelectById('yearFilter');
                  } catch(e) { yearFilterTopEl.value = y; if (yearFilterEl) { yearFilterEl.value = y; yearFilterEl.dispatchEvent(new Event('change')); } }
                }
                if (o !== null && orderByEl) {
                  orderByEl.value = o;
                  try { localStorage.setItem('bidTracking_orderBy', o); } catch(e){}
                }
              }
            } catch(e) {}
          }

          function clearTopFiltersSessionDefaults() {
            try {
              if (window.sessionStorage) {
                sessionStorage.removeItem(TOP_STATUS_KEY);
                sessionStorage.removeItem(TOP_YEAR_KEY);
                sessionStorage.removeItem(TOP_ORDER_KEY);
              }
              if (statusFilterTopEl) {
                // clear multi-select to default: select 'all'
                Array.from(statusFilterTopEl.options).forEach(function(opt){ opt.selected = (opt.value === 'all'); });
                if (statusFilterEl) { Array.from(statusFilterEl.options).forEach(function(opt){ opt.selected = (opt.value === 'all'); }); statusFilterEl.dispatchEvent(new Event('change')); }
              }
              if (yearFilterTopEl) {
                Array.from(yearFilterTopEl.options).forEach(function(opt){ opt.selected = (opt.value === ''); });
                if (yearFilterEl) { Array.from(yearFilterEl.options).forEach(function(opt){ opt.selected = (opt.value === ''); }); yearFilterEl.dispatchEvent(new Event('change')); }
              }
              if (orderByEl) {
                orderByEl.value = 'grouped';
                try { localStorage.setItem('bidTracking_orderBy', 'grouped'); } catch(e){}
              }
              if (globalSearchInputEl) globalSearchInputEl.value = '';
              bidTrackingSearchTerm = '';
              refreshMultiSelectById('statusFilterTop');
              refreshMultiSelectById('statusFilter');
              refreshMultiSelectById('yearFilterTop');
              refreshMultiSelectById('yearFilter');
              applyFiltersAndGrouping();
            } catch(e) {}
          }

          // Wire Clear button (if present)
          try {
            var clearBtn = document.getElementById('clearFiltersBtn');
            if (clearBtn) clearBtn.addEventListener('click', function(){ try { clearTopFiltersSessionDefaults(); saveTopFiltersToSession(); } catch(e){} });
          } catch(e) {}

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

          function ensurePaginationControls() {
            return document.getElementById('paginationBar') || null;
          }

          // Wire pagination button clicks once
          (function wirePaginationClicks(){
            var prev = document.getElementById('paginationPrev');
            var next = document.getElementById('paginationNext');
            if (prev) prev.addEventListener('click', function(){
              if (currentPage > 1) { currentPage--; applyFiltersAndGrouping(); }
            });
            if (next) next.addEventListener('click', function(){
              if (currentPage < totalPages) { currentPage++; applyFiltersAndGrouping(); }
            });
          })();

          function renderPaginationControls(totalItems) {
            totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            var prev = document.getElementById('paginationPrev');
            var next = document.getElementById('paginationNext');
            var pgCurrent = document.getElementById('pgCurrent');
            var pgTotal   = document.getElementById('pgTotal');
            var pgCount   = document.getElementById('pgCount');

            if (pgCurrent) pgCurrent.textContent = currentPage;
            if (pgTotal)   pgTotal.textContent   = totalPages;
            if (pgCount)   pgCount.textContent   = totalItems + ' projects loaded';
            if (prev) { prev.disabled = (currentPage <= 1); }
            if (next) { next.disabled = (currentPage >= totalPages); }
          }

          var lastFilterSignature = '';

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
              // capture detail rows immediately following this bid row (until next data-bid row or group-spacer)
              var details = [];
              try {
                var n = r.nextElementSibling;
                while (n && !n.hasAttribute('data-bid') && !n.classList.contains('group-spacer')) { details.push(n); n = n.nextElementSibling; }
              } catch(e) { details = []; }
              var projName = (obj.project_name || '').toString().trim();
              var searchText = '';
              try {
                var parts = [];
                // Search over raw bid object values (all bid columns)
                parts.push(String(proj || ''));
                parts.push(String(projName || ''));
                parts.push(JSON.stringify(obj || {}));
                // Search over rendered primary row text (what user sees in table)
                parts.push((r.textContent || ''));
                // Search over rendered contractor/detail rows linked to this bid row
                if (details && details.length) {
                  details.forEach(function(dr){
                    try { parts.push((dr && dr.textContent) ? dr.textContent : ''); } catch(_e) {}
                  });
                }
                searchText = parts.join(' ').toLowerCase().replace(/\s+/g, ' ').trim();
              } catch(e) {}
              return { row: r, detailRows: details, obj: obj, project: proj, project_name: projName, yearPrefix: yearPrefix, status: st, date: dateVal, search_text: searchText };
            });
          })();

          function parseNumericLoose(raw) {
            var s = (raw === null || typeof raw === 'undefined') ? '' : String(raw).trim();
            if (!s) return NaN;
            var cleaned = s.replace(/\$/g, '').replace(/,/g, '').replace(/\s+/g, '');
            if (!/^[+-]?\d+(?:\.\d+)?$/.test(cleaned)) return NaN;
            var n = parseFloat(cleaned);
            return isNaN(n) ? NaN : n;
          }

          function updateDhStabilizerTotalBar(items) {
            try {
              var totalEl = document.getElementById('dhStabilizerTotalValue');
              if (!totalEl) return;
              var sum = 0;
              (items || []).forEach(function(it) {
                var v = '';
                try {
                  if (it && it.obj && typeof it.obj.dh_stabilizer_price !== 'undefined' && it.obj.dh_stabilizer_price !== null) {
                    v = it.obj.dh_stabilizer_price;
                  }
                } catch(e) {}
                if ((v === '' || v === null || typeof v === 'undefined') && it && it.row) {
                  try {
                    var td = it.row.querySelector('td[data-col="dh_stabilizer_price"]');
                    if (td) v = td.textContent || '';
                  } catch(e) {}
                }
                var n = parseNumericLoose(v);
                if (!isNaN(n)) sum += n;
              });
              var rounded = Math.round((sum + Number.EPSILON) * 10000) / 10000;
              totalEl.textContent = '$' + formatNumericWithGrouping(String(rounded));
            } catch(e) {}
          }

          function syncDhStabilizerTotalPosition() {
            try {
              var bar = document.getElementById('dhStabilizerTotalBar');
              var totalEl = document.getElementById('dhStabilizerTotalValue');
              if (!bar || !totalEl) return;
              var th = document.querySelector('#bidsTable thead th[data-col="dh_stabilizer_price"]');
              if (!th) { totalEl.style.left = '50%'; return; }
              var barRect = bar.getBoundingClientRect();
              var thRect = th.getBoundingClientRect();
              var centerX = (thRect.left + thRect.right) / 2;
              var leftPx = centerX - barRect.left;
              var min = 70;
              var max = Math.max(min, barRect.width - 70);
              if (leftPx < min) leftPx = min;
              if (leftPx > max) leftPx = max;
              totalEl.style.left = leftPx + 'px';
            } catch(e) {}
          }

function applyFiltersAndGrouping() {
  if (!tbody || !table) return;
  try {
    if (globalSearchInputEl) {
      bidTrackingSearchTerm = (globalSearchInputEl.value || '').toString().trim().toLowerCase();
    }
  } catch(e) {}

  function getSelectedValues(sel){ if(!sel) return []; try { return Array.from(sel.selectedOptions).map(function(o){ return o.value; }); } catch(e){ return []; } }

  var selectedYears = yearFilterEl ? getSelectedValues(yearFilterEl) : [];
  var selectedStatuses = statusFilterEl ? getSelectedValues(statusFilterEl) : ['all'];
  // normalize status selection to canonical lowercase/alphanumeric (match normStatus used for rows)
  function normalizeStatusVal(s){ try { return (s || '').toString().trim().toLowerCase().replace(/[^a-z0-9]/g,''); } catch(e){ return s; } }
  try {
    if (selectedStatuses && selectedStatuses.length) {
      selectedStatuses = selectedStatuses.map(normalizeStatusVal).filter(function(x){ return x !== null && typeof x !== 'undefined'; });
      // accept common aliases: treat 'won' and 'win' as equivalent
      if (selectedStatuses.indexOf('won') !== -1 && selectedStatuses.indexOf('win') === -1) selectedStatuses.push('win');
      if (selectedStatuses.indexOf('win') !== -1 && selectedStatuses.indexOf('won') === -1) selectedStatuses.push('won');
    }
  } catch(e) {}

  // Always show status filter (including when 'All Years' is selected)
  if (statusFilterEl) statusFilterEl.hidden = false;

  // 1) Year filter (projects starting with YY)
  var filtered = originalRows.filter(function(it){
    // if no years selected or 'All Years' selected (empty string present), include all
    if (!selectedYears || !selectedYears.length) return true;
    if (selectedYears.indexOf('') !== -1) return true;
    try {
      if (!it.project) return false;
      for (var yi=0; yi<selectedYears.length; yi++) {
        var yv = selectedYears[yi]; if (!yv) continue;
        if (String(it.project).indexOf(yv) === 0) return true;
      }
      return false;
    } catch(e){ return false; }
  });

  // 2) Status filter (applies only within selected year set)
  if (selectedStatuses && selectedStatuses.length && selectedStatuses.indexOf('all') === -1) {
    filtered = filtered.filter(function(it){ return selectedStatuses.indexOf(it.status) !== -1; });
  }

  // 2.5) Search-term filter: if a global search term is present, further
  // restrict the filtered set to items that contain the term in any field.
  try {
    if (typeof bidTrackingSearchTerm === 'string' && bidTrackingSearchTerm.trim()) {
      var _term = bidTrackingSearchTerm.trim().toLowerCase();
      filtered = filtered.filter(function(it){
        try {
          var hay = it.search_text || '';
          if (!hay) hay = (String(it.project || '') + ' ' + String(it.project_name || '') + ' ' + JSON.stringify(it.obj || {})).toLowerCase();
          return hay.indexOf(_term) !== -1;
        } catch(e) { return false; }
      });
    }
  } catch(e) {}
  try { updateDhStabilizerTotalBar(filtered); } catch(e) {}
  try { syncDhStabilizerTotalPosition(); } catch(e) {}

  // 3) Default rendering: group rows by status in the requested order
  //    and sort rows within each status by bid_date ascending (nulls last).
  //    Status rendering order: bidding -> pending -> won/win -> lost -> completed
  var statusOrder = ['bidding','pending','won','win','lost','completed'];
  function statusIndex(s) { var idx = statusOrder.indexOf((s||'').toString()); return idx === -1 ? statusOrder.length : idx; }

  // Determine current ordering preference; but default rendering groups by status
  var _order = (orderByEl && orderByEl.value) ? orderByEl.value : (localStorage.getItem ? localStorage.getItem('bidTracking_orderBy') || 'date_asc' : 'date_asc');
  try { if (orderByEl) { try { orderByEl.value = _order; } catch(e){} } } catch(e){}

  var hasYearFilter = !!(selectedYears && selectedYears.length && selectedYears.indexOf('') === -1);
  var hasStatusFilter = !!(selectedStatuses && selectedStatuses.length && selectedStatuses.indexOf('all') === -1);
  var filterSignature = [
    (hasYearFilter ? selectedYears.slice().sort().join(',') : 'all-years'),
    (hasStatusFilter ? selectedStatuses.slice().sort().join(',') : 'all-status'),
    (bidTrackingSearchTerm || '').trim().toLowerCase(),
    _order || 'grouped'
  ].join('|');
  if (filterSignature !== lastFilterSignature) {
    currentPage = 1;
    lastFilterSignature = filterSignature;
  }

  // Flattened list of items (preserve detailRows reference)
  var flatItems = filtered.map(function(it){ return { it: it, date: it.date }; });

  try { console.debug('applyFiltersAndGrouping: years=', selectedYears, 'statuses=', selectedStatuses, 'filteredBeforeStatus=', filtered.length); } catch(e) {}

  // If no filters are applied (year == '' and status == 'all'), show the
  // legacy grouped-by-status view. Otherwise respect the user's order selection
  // and perform a global sort.
  var doGlobalSort = ((orderByEl && orderByEl.value && orderByEl.value !== 'grouped') || hasYearFilter || hasStatusFilter);

  // helpers for comparisons
  function cmpDateItems(pa, pb){
    var ad = pa && pa.date ? (pa.date instanceof Date ? pa.date.getTime() : new Date(pa.date).getTime()) : Number.POSITIVE_INFINITY;
    var bd = pb && pb.date ? (pb.date instanceof Date ? pb.date.getTime() : new Date(pb.date).getTime()) : Number.POSITIVE_INFINITY;
    return ad - bd;
  }
  function cmpProjectItems(pa, pb){
    var aproj = (pa.it && (pa.it.project || pa.it.dhss_project_number)) ? String(pa.it.project || pa.it.dhss_project_number).trim() : '';
    var bproj = (pb.it && (pb.it.project || pb.it.dhss_project_number)) ? String(pb.it.project || pb.it.dhss_project_number).trim() : '';
    var na = parseFloat(aproj);
    var nb = parseFloat(bproj);
    if (!isNaN(na) && !isNaN(nb)) return na - nb;
    return aproj.localeCompare(bproj);
  }
  function cmpProjectNameItems(pa, pb){
    var an = (pa.it && pa.it.project_name) ? String(pa.it.project_name).trim() : '';
    var bn = (pb.it && pb.it.project_name) ? String(pb.it.project_name).trim() : '';
    return an.localeCompare(bn);
  }
  function escapeRegExp(s){
    return String(s || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }
  function clearSearchTermHighlights(el){
    if (!el || !el.querySelectorAll) return;
    try {
      var hits = el.querySelectorAll('span.search-term-hit');
      hits.forEach(function(hit){
        var parent = hit.parentNode;
        if (!parent) return;
        parent.replaceChild(document.createTextNode(hit.textContent || ''), hit);
        try { parent.normalize(); } catch(_e) {}
      });
    } catch(e) {}
  }
  function highlightTermInNode(node, re){
    if (!node || !re) return;
    if (node.nodeType === 3) {
      var text = node.nodeValue || '';
      if (!text) return;
      re.lastIndex = 0;
      if (!re.test(text)) return;
      re.lastIndex = 0;
      var frag = document.createDocumentFragment();
      var lastIdx = 0;
      var m;
      while ((m = re.exec(text)) !== null) {
        var idx = m.index;
        var val = m[0];
        if (idx > lastIdx) frag.appendChild(document.createTextNode(text.slice(lastIdx, idx)));
        var span = document.createElement('span');
        span.className = 'search-term-hit';
        span.textContent = val;
        frag.appendChild(span);
        lastIdx = idx + val.length;
        if (val.length === 0) break;
      }
      if (lastIdx < text.length) frag.appendChild(document.createTextNode(text.slice(lastIdx)));
      if (node.parentNode) node.parentNode.replaceChild(frag, node);
      return;
    }
    if (node.nodeType !== 1) return;
    var tag = (node.tagName || '').toUpperCase();
    if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'NOSCRIPT') return;
    if (node.classList && node.classList.contains('search-term-hit')) return;
    Array.from(node.childNodes || []).forEach(function(child){ highlightTermInNode(child, re); });
  }
  function highlightTermInCell(cell, term){
    if (!cell) return false;
    clearSearchTermHighlights(cell);
    if (!term) return false;
    var re = new RegExp(escapeRegExp(term), 'ig');
    highlightTermInNode(cell, re);
    return !!cell.querySelector('span.search-term-hit');
  }
  function applySearchHighlights(item, term){
    try {
      var targets = [];
      if (item && item.row) targets.push(item.row);
      if (item && item.detailRows && item.detailRows.length) {
        item.detailRows.forEach(function(dr){ if (dr) targets.push(dr); });
      }
      targets.forEach(function(tr){
        try {
          tr.classList.remove('search-row-match');
          tr.querySelectorAll('td').forEach(function(td){
            if (td.classList) td.classList.remove('search-cell-match');
            clearSearchTermHighlights(td);
          });
        } catch(e) {}
      });
      if (!term) return;
      targets.forEach(function(tr){
        var any = false;
        try {
          tr.querySelectorAll('td').forEach(function(td){
            try {
              if ((td.textContent || '').toLowerCase().indexOf(term) !== -1) {
                if (td.classList) td.classList.add('search-cell-match');
                any = true;
              }
              var hasWordHit = highlightTermInCell(td, term);
              if (hasWordHit) any = true;
            } catch(e) {}
          });
          if (any) tr.classList.add('search-row-match');
        } catch(e) {}
      });
    } catch(e) {}
  }

  // Build fragment in sorted order
  var frag = document.createDocumentFragment();
  var colCount = (typeof getVisibleHeaderCount === 'function') ? getVisibleHeaderCount() : (table && table.querySelectorAll('thead th').length) || 1;

  if (doGlobalSort) {
    // perform global sort according to _order
    flatItems.sort(function(a,b){
      try {
        if (typeof _order === 'string' && _order.indexOf('projectname') === 0) {
          var pn = cmpProjectNameItems(a,b);
          return (_order.indexOf('_desc') !== -1) ? -pn : pn;
        }
        if (typeof _order === 'string' && _order.indexOf('projectnum') === 0) {
          var pr = cmpProjectItems(a,b);
          return (_order.indexOf('_desc') !== -1) ? -pr : pr;
        }
        var dcmp = cmpDateItems(a,b);
        return (_order === 'date_desc') ? -dcmp : dcmp;
      } catch(e) { return cmpDateItems(a,b); }
    });

    var totalItems = flatItems.length;
    var totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    var startIndex = (currentPage - 1) * pageSize;
    var pagedItems = flatItems.slice(startIndex, startIndex + pageSize);

    var prevProj = null;
    pagedItems.forEach(function(w, idx){
      var curProj = (w.it.project || '').toString();
      if (idx !== 0 && prevProj !== curProj) {
        var spr = document.createElement('tr'); spr.className = 'group-spacer'; var td = document.createElement('td'); td.colSpan = colCount; spr.appendChild(td); frag.appendChild(spr);
      }
      try {
        var _t = (typeof bidTrackingSearchTerm === 'string' && bidTrackingSearchTerm.trim()) ? bidTrackingSearchTerm.trim().toLowerCase() : '';
        applySearchHighlights(w.it, _t);
      } catch(e) {}
      frag.appendChild(w.it.row);
      try { if (w.it.detailRows && w.it.detailRows.length) w.it.detailRows.forEach(function(d){ frag.appendChild(d); }); } catch(e) {}
      prevProj = curProj;
    });

    renderPaginationControls(totalItems);
  } else {
    // grouped-by-status (legacy behavior)
    var prevProj = null;
    // Sort by status order, then date ascending
    flatItems.sort(function(a,b){
      var si = statusIndex(a.it.status);
      var sj = statusIndex(b.it.status);
      if (si !== sj) return si - sj;
      return cmpDateItems(a,b);
    });
    var totalItems = flatItems.length;
    var totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    var startIndex = (currentPage - 1) * pageSize;
    var pagedItems = flatItems.slice(startIndex, startIndex + pageSize);

    pagedItems.forEach(function(w, idx){
      var curProj = (w.it.project || '').toString();
      if (idx !== 0 && prevProj !== curProj) {
        var spr = document.createElement('tr'); spr.className = 'group-spacer'; var td = document.createElement('td'); td.colSpan = colCount; spr.appendChild(td); frag.appendChild(spr);
      }
      try {
        var _t2 = (typeof bidTrackingSearchTerm === 'string' && bidTrackingSearchTerm.trim()) ? bidTrackingSearchTerm.trim().toLowerCase() : '';
        applySearchHighlights(w.it, _t2);
      } catch(e) {}
      frag.appendChild(w.it.row);
      try { if (w.it.detailRows && w.it.detailRows.length) w.it.detailRows.forEach(function(d){ frag.appendChild(d); }); } catch(e) {}
      prevProj = curProj;
    });

    renderPaginationControls(totalItems);
  }

  tbody.innerHTML = '';
  tbody.appendChild(frag);
  try { syncGcDisplayForProjects(); } catch(e){}

  try { window.setupStickyColumns && window.setupStickyColumns(4); } catch(e){}
  try { window.syncTopScroller && window.syncTopScroller(); } catch(e){}

  // Update project separators after tbody rebuild
  try { updateProjectSeparators(); } catch(e) { console.warn('updateProjectSeparators failed', e); }
  // Update filter indicator
  try { updateFilterIndicator(); } catch(e){}
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

function updateFilterIndicator() {
  try {
    var indicatorDiv = document.getElementById('filterIndicator');
    if (!indicatorDiv) return;

    var selectedOrder = orderByEl ? orderByEl.value : 'grouped';

    var selYears = [];
    try { if (yearFilterEl) selYears = Array.from(yearFilterEl.selectedOptions).map(function(o){return o.value;}); } catch(e) { selYears = []; }
    var selStatuses = [];
    try { if (statusFilterEl) selStatuses = Array.from(statusFilterEl.selectedOptions).map(function(o){return o.value;}); } catch(e) { selStatuses = ['all']; }

    var filterParts = [];

    // Check if any filters are applied
    if (selStatuses && selStatuses.length && selStatuses.indexOf('all') === -1) {
      filterParts.push(selStatuses.join(', ') + ' only');
    }

    if (selYears && selYears.length && selYears.indexOf('') === -1) {
      filterParts.push(selYears.join(', ') + ' only');
    }

    if (selectedOrder && selectedOrder !== 'grouped') {
      var orderLabel = '';
      if (selectedOrder === 'date_asc') orderLabel = 'ordered by bid date (low → high)';
      else if (selectedOrder === 'date_desc') orderLabel = 'ordered by bid date (high → low)';
      else if (selectedOrder === 'projectnum_asc') orderLabel = 'ordered by project # (low → high)';
      else if (selectedOrder === 'projectnum_desc') orderLabel = 'ordered by project # (high → low)';
      else if (selectedOrder === 'projectname_asc') orderLabel = 'ordered by project name (a → z)';
      else if (selectedOrder === 'projectname_desc') orderLabel = 'ordered by project name (z → a)';

      filterParts.push(orderLabel);
    }

    if (filterParts.length > 0) {
      var displayText = 'Showing: ' + filterParts.join('. ');
      indicatorDiv.textContent = displayText;
      indicatorDiv.style.display = 'block';
    } else {
      indicatorDiv.style.display = 'none';
    }
  } catch(e) {
    console.warn('updateFilterIndicator error:', e);
  }
}

          // Wire global search inside the same scope so filtering is always synced
          try {
            if (globalSearchInputEl) {
              var runSearchRefresh = function(){
                try {
                  bidTrackingSearchTerm = (this.value || '').toString().toLowerCase().trim();
                  applyFiltersAndGrouping();
                } catch(e){}
              };
              var runSearchRefreshDebounced = function(){
                try {
                  clearTimeout(searchRefreshTimer);
                  var self = this;
                  searchRefreshTimer = setTimeout(function(){ runSearchRefresh.call(self); }, 140);
                } catch(e){}
              };
              globalSearchInputEl.addEventListener('input', runSearchRefreshDebounced);
              globalSearchInputEl.addEventListener('change', runSearchRefresh);
              globalSearchInputEl.addEventListener('search', runSearchRefresh);
              globalSearchInputEl.addEventListener('keyup', function(e){
                try {
                  if (e && e.key === 'Enter') runSearchRefresh.call(this);
                } catch(err){}
              });
            }
          } catch(e){}

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
            try { if (key) gcProjectCache[key] = j || {}; } catch(e){}
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
                clientSel.onchange = function(){ try { var selOpt = clientSel.options[clientSel.selectedIndex]; var name = selOpt ? (selOpt.getAttribute('data-name') || selOpt.textContent) : ''; var id = clientSel.value || ''; if (!id) { /* clear winners for project */ try { if (key && gcProjectCache) delete gcProjectCache[key]; } catch(e) {} fetch('../../api/set_winner_general_contractor.php', { method:'POST', credentials:'same-origin', body: new FormData() }).catch(function(){}); } else { var fd = new FormData(); fd.append('id', id); fd.append('dhss_project_number', key); fetch('../../api/set_winner_general_contractor.php', { method:'POST', credentials:'same-origin', body: fd }).then(function(r){ return r.json(); }).then(function(res){ if (res && res.success) { try { if (key && gcProjectCache) delete gcProjectCache[key]; loadGcList(key); if (typeof syncGcDisplayForProjects === 'function') syncGcDisplayForProjects(); applyGcWinnerHighlight(key, name); showToast && showToast('Winner updated', 'success'); } catch(e){} } else { showToast && showToast('Failed to set winner', 'error'); } }).catch(function(){ showToast && showToast('Failed to set winner', 'error'); }); } } catch(e){} };
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
    populateClientWinners_fallback(projectKey, selectedValue);
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
    // remove previous highlights from any GC-related cell
    Array.from(document.querySelectorAll('#bidsTable td.gc-winner-highlight')).forEach(function(td){ td.classList.remove('gc-winner-highlight'); });

    if (!projectKey || !selectedGc) return;
    var normSel = selectedGc.toString().trim().toLowerCase();
    var rows = Array.from(document.querySelectorAll('#bidsTable tbody tr[data-bid]'));
    rows.forEach(function(r){
      var raw = r.getAttribute('data-bid') || '';
      var match = false;
      try {
        var obj = JSON.parse(raw || '{}') || {};
        var proj = (obj.dhss_project_number || '').toString().trim();
        // collect candidate GC strings from the bid object and from possible GC cells
        var candidates = [];
        try { if (obj.general_contractor) candidates.push(String(obj.general_contractor).trim()); } catch(e){}
        try { if (obj.general_contractor_name) candidates.push(String(obj.general_contractor_name).trim()); } catch(e){}
        try { if (obj.gc_name) candidates.push(String(obj.gc_name).trim()); } catch(e){}
        try { if (obj.general_contractor_number) candidates.push(String(obj.general_contractor_number).trim()); } catch(e){}
        var cellKeys = ['general_contractor','gc_name','general_contractor_name','gc_number','general_contractor_number'];
        cellKeys.forEach(function(k){ try { var td = r.querySelector('td[data-col="' + k + '"]'); if (td && td.textContent) candidates.push(String(td.textContent).trim()); } catch(e){} });
        for (var ci = 0; ci < candidates.length; ci++) {
          try { var c = (candidates[ci] || '').toString().toLowerCase(); if (proj === projectKey && c === normSel) { match = true; break; } } catch(e){}
        }
      } catch(e) {
        // fallback: inspect multiple GC cells for a match
        try {
          var projTd = r.querySelector('td[data-col="dhss_project_number"]');
          var projVal = projTd ? projTd.textContent.trim() : '';
          var fallbackKeys = ['general_contractor','gc_name','general_contractor_name','gc_number','general_contractor_number'];
          for (var ki = 0; ki < fallbackKeys.length; ki++) {
            try {
              var tdc = r.querySelector('td[data-col="' + fallbackKeys[ki] + '"]');
              var txt = tdc ? (tdc.textContent || '').trim() : '';
              if (projVal === projectKey && txt && txt.toLowerCase() === normSel) { match = true; break; }
            } catch(e){}
          }
        } catch(ignore){}
      }

        if (match) {
          try {
            // Highlight only the compact/general contractor column cell for winners.
            var tdToHighlight = r.querySelector('td[data-col="general_contractor"]');
            if (!tdToHighlight) {
              // Find header index for the "General Contractor" column as a fallback
              var headerIndex = null;
              try {
                var ths = Array.from(document.querySelectorAll('#bidsTable thead th')) || [];
                for (var hi = 0; hi < ths.length; hi++) {
                  try { var thtxt = (ths[hi].textContent || '').toString().trim().toLowerCase(); if (thtxt === 'general contractor') { headerIndex = hi; break; } } catch(e){}
                }
              } catch(e) { headerIndex = null; }
              if (headerIndex !== null) {
                try { var tds = r.querySelectorAll('td'); if (tds && tds.length > headerIndex) tdToHighlight = tds[headerIndex]; } catch(e){}
              }
            }
            if (tdToHighlight) {
              tdToHighlight.classList.add('gc-winner-highlight');
              try { tdToHighlight.style.color = '#10b981'; tdToHighlight.style.fontWeight = '700'; } catch(e){}
            }
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
    var gcSet = new Set((gcColumns || []).map(function(c){ return (c || '').toString().toLowerCase(); }));
    var gcFallback = ['general_contractor','general_contractor_name','gc_name','general_contractor_number','gc_number','general_contractor_email','general_contractor_address','gc_email','gc_address','client_win_price','is_union','winner'];
    gcFallback.forEach(function(c){ gcSet.add(c); });

    function findCell(row, colKey) {
      var key = (colKey || '').toString().toLowerCase();
      var cells = row ? row.querySelectorAll('td[data-col]') : [];
      for (var i = 0; i < cells.length; i++) {
        var k = (cells[i].getAttribute('data-col') || '').toString().toLowerCase();
        if (k === key) return cells[i];
      }
      return null;
    }

    function getGcVal(gc, colKey) {
      if (!gc) return '';
      var lower = {};
      Object.keys(gc || {}).forEach(function(k){ lower[k.toLowerCase()] = gc[k]; });
      var key = (colKey || '').toString().toLowerCase();
      if (lower.hasOwnProperty(key)) return lower[key];
      if (key === 'general_contractor_name' && lower.hasOwnProperty('gc_name')) return lower['gc_name'];
      if (key === 'general_contractor_number' && lower.hasOwnProperty('gc_number')) return lower['gc_number'];
      if (key === 'general_contractor_email' && lower.hasOwnProperty('gc_email')) return lower['gc_email'];
      if (key === 'general_contractor_address' && lower.hasOwnProperty('gc_address')) return lower['gc_address'];
      if (key === 'general_contractor' && lower.hasOwnProperty('general_contractor_name')) return lower['general_contractor_name'];
      return '';
    }
    var projectRowsMap = {};
    rows.forEach(function(r){
      try {
        var obj = JSON.parse(r.getAttribute('data-bid') || '{}') || {};
        var pk = (obj.dhss_project_number || '').toString().trim();
        if (!pk) return;
        if (!projectRowsMap[pk]) projectRowsMap[pk] = [];
        projectRowsMap[pk].push({ row: r, obj: obj });
      } catch(e){}
    });

    var projects = Object.keys(projectRowsMap);
    var headerCols = Array.from(document.querySelectorAll('#bidsTable thead th')).map(function(th){ return th.getAttribute('data-col') || ''; });
    var clearCellKeys = ['general_contractor','gc_name','general_contractor_name','gc_number','general_contractor_number','general_contractor_email','gc_email','general_contractor_address','gc_address','client_win_price','is_union','winner'];
    var cellMap = [
      'general_contractor','gc_name','general_contractor_name',
      'gc_number','general_contractor_number',
      'general_contractor_email','gc_email',
      'general_contractor_address','gc_address',
      'client_win_price','is_union','winner'
    ];

    function renderProjectGcData(proj, payload) {
      try {
        var rowSet = projectRowsMap[proj] || [];
        if (!rowSet.length) return;

        var chosen = null;
        if (payload && Array.isArray(payload.contractors) && payload.contractors.length) {
          if (payload.contractors.length === 1) chosen = payload.contractors[0];
          else chosen = payload.contractors.find(function(c){ return c.winner && (c.winner == 1 || c.winner === '1' || c.winner === true); }) || payload.contractors[0];
        }

        rowSet.forEach(function(item){
          try {
            var r = item.row;
            var obj = item.obj || {};
            if (chosen) {
              cellMap.forEach(function(k){
                try {
                  var td = findCell(r, k);
                  if (!td) return;
                  var v = getGcVal(chosen, k);
                  if ((k || '').toLowerCase() === 'winner' && v !== '') v = (v == 1 || v === '1') ? 'Yes' : 'No';
                  if ((k || '').toLowerCase() === 'client_win_price') v = formatNumericWithGrouping(v);
                  td.textContent = (v || '').toString();
                } catch(e){}
              });

              try {
                var parentId = obj.bid_id || '';
                var contractorsToShow = Array.isArray(payload.contractors) ? payload.contractors.slice() : [];
                if (chosen && chosen.id) {
                  contractorsToShow = contractorsToShow.filter(function(c){ try { return !(c && c.id && String(c.id) === String(chosen.id)); } catch(e){ return true; } });
                }

                var detailRows = [];
                if (parentId) {
                  detailRows = Array.from(r.parentNode.querySelectorAll('tr.gc-detail-row[data-parent-bid-id="' + parentId + '"]')) || [];
                }

                if (contractorsToShow.length > detailRows.length) {
                  var toCreate = contractorsToShow.length - detailRows.length;
                  for (var ci = 0; ci < toCreate; ci++) {
                    var newTr = document.createElement('tr');
                    newTr.className = 'gc-detail-row';
                    if (parentId) newTr.setAttribute('data-parent-bid-id', parentId);
                    newTr.style.background = 'rgba(249,250,251,0.6)';
                    newTr.style.fontSize = '12px';
                    for (var hc = 0; hc < headerCols.length; hc++) {
                      var colKey = headerCols[hc] || '';
                      var td = document.createElement('td');
                      if (colKey) td.setAttribute('data-col', colKey);
                      newTr.appendChild(td);
                    }
                    if (detailRows.length) {
                      var ref = detailRows[detailRows.length - 1];
                      ref.parentNode.insertBefore(newTr, ref.nextSibling);
                    } else {
                      r.parentNode.insertBefore(newTr, r.nextSibling);
                    }
                    detailRows.push(newTr);
                  }
                }

                for (var di = 0; di < Math.max(detailRows.length, contractorsToShow.length); di++) {
                  var dr = detailRows[di];
                  var gc = contractorsToShow[di] || null;
                  if (!dr) continue;
                  headerCols.forEach(function(colName){
                    try {
                      var td = findCell(dr, colName);
                      if (!td) return;
                      var isGc = gcSet.has((colName || '').toString().toLowerCase());
                      if (!isGc) return;
                      if (!gc) { td.textContent = ''; return; }
                      var v = getGcVal(gc, colName);
                      if ((colName || '').toLowerCase() === 'winner' && v !== '') v = (v == 1 || v === '1') ? 'Yes' : 'No';
                      if ((colName || '').toLowerCase() === 'client_win_price') v = formatNumericWithGrouping(v);
                      td.textContent = (v || '').toString();
                    } catch(e){}
                  });
                }
              } catch(e) {}
            } else {
              clearCellKeys.forEach(function(k){
                try { var td = findCell(r, k); if (td) td.textContent = ''; } catch(e){}
              });
            }
          } catch(e){}
        });
      } catch(e) {}
    }

    projects.forEach(function(proj){
      try {
        if (gcProjectCache.hasOwnProperty(proj)) {
          renderProjectGcData(proj, gcProjectCache[proj]);
          return;
        }
        if (gcProjectInFlight[proj]) return;

        gcProjectInFlight[proj] = true;
        fetch('../../api/get_general_contractors.php?dhss_project_number=' + encodeURIComponent(proj), { credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(j){
            gcProjectCache[proj] = j || {};
            renderProjectGcData(proj, gcProjectCache[proj]);
          })
          .catch(function(){})
          .finally(function(){ delete gcProjectInFlight[proj]; });
      } catch(e) {}
    });
  } catch(e) { console.warn('syncGcDisplayForProjects failed', e); }
}



          <?php if (!empty($canEditBidTracking)) { ?>
          if (table) table.addEventListener('click', function(e){
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
          <?php } ?>

          if (statusFilterEl) {
            // Restore saved status filter if present (use single-value fallback)
            try {
              var savedStatus = null;
              try { savedStatus = localStorage.getItem('bidTracking_statusFilter'); } catch(e) { savedStatus = null; }
              if (savedStatus !== null && savedStatus !== undefined) {
                try { Array.from(statusFilterEl.options).forEach(function(opt){ opt.selected = (opt.value === savedStatus); }); } catch(e) {}
              }
            } catch(e) {}

            // Mirror status to the compact top status selector handled earlier; ensure localStorage stores first selected value
            statusFilterEl.addEventListener('change', function(){ try { var first = (this.selectedOptions && this.selectedOptions.length) ? this.selectedOptions[0].value : ''; localStorage.setItem('bidTracking_statusFilter', first || ''); applyFiltersAndGrouping(); } catch(e){} });
            try { if (statusFilterTopEl) statusFilterTopEl.addEventListener('change', function(){ try { var first = (this.selectedOptions && this.selectedOptions.length) ? this.selectedOptions[0].value : ''; localStorage.setItem('bidTracking_statusFilter', first || ''); saveTopFiltersToSession(); } catch(e){} }); } catch(e){}
          }

          // Order-by control: restore and wire change events
          try {
            if (orderByEl) {
              var savedOrder = null;
              try { savedOrder = localStorage.getItem('bidTracking_orderBy'); } catch(e) { savedOrder = null; }
              if (savedOrder) orderByEl.value = savedOrder; else orderByEl.value = 'grouped';
              orderByEl.addEventListener('change', function(){ try { localStorage.setItem('bidTracking_orderBy', this.value || 'grouped'); saveTopFiltersToSession(); applyFiltersAndGrouping(); } catch(e){} });
            }
          } catch(e) {}

            // Restore any top-filter session values (per-user session)
            try { restoreTopFiltersFromSession(); } catch(e){}
            // Format any pre-rendered table date cells to mm/dd/yyyy
            try { formatTableDates(); } catch(e){}
            applyFiltersAndGrouping();
            try { syncDhStabilizerTotalPosition(); } catch(e){}
            // Clear saved filter keys (localStorage/sessionStorage) after initial apply
            // so a browser refresh does not reapply the previously selected filters.
            try {
              (function clearSavedFiltersOnce(){
                try {
                  if (window.localStorage) {
                    try { localStorage.removeItem('bidTracking_yearFilter'); } catch(e){}
                    try { localStorage.removeItem('bidTracking_statusFilter'); } catch(e){}
                    try { localStorage.removeItem('bidTracking_orderBy'); } catch(e){}
                  }
                } catch(e) {}
                try {
                  if (window.sessionStorage) {
                    try { sessionStorage.removeItem(TOP_STATUS_KEY); } catch(e){}
                    try { sessionStorage.removeItem(TOP_YEAR_KEY); } catch(e){}
                    try { sessionStorage.removeItem(TOP_ORDER_KEY); } catch(e){}
                  }
                } catch(e) {}
              })();
            } catch(e) {}
            // Ensure money fields in modal are wrapped and table cells prefixed
            try { wrapMoneyInputs(); } catch(e){}
            try { applyDollarPrefixToTableCells(); } catch(e){}
            try {
              window.addEventListener('resize', function(){ try { syncDhStabilizerTotalPosition(); } catch(e){} });
              var tc = document.getElementById('tableContainer');
              if (tc) tc.addEventListener('scroll', function(){ try { syncDhStabilizerTotalPosition(); } catch(e){} });
            } catch(e){}
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
          var editGcToggleBtn = document.getElementById('editGcToggleBtn');
          // existing-contractor select removed; keep add-new flow only

          try { setGcEditState(false); } catch(e) {}

          if (editGcToggleBtn) {
            editGcToggleBtn.addEventListener('click', function(e){
              e.stopPropagation();
              setGcEditState(!gcEditEnabled);
            });
          }

          if (addGcBtn) {
            addGcBtn.addEventListener('click', function(e){
              e.stopPropagation();
              var container = document.getElementById('newGcContainer');
              if (!container) return;
              var row = document.createElement('div'); row.className = 'new-gc-row';
              row.style.display = 'flex'; row.style.gap = '8px'; row.style.alignItems = 'center';
              row.innerHTML = '<input name="new_gc_general" autocomplete="off" placeholder="general contractor" style="flex:1;padding:8px;border:1px solid #cbd5e1;border-radius:6px;" />'
                + '<input name="new_gc_name" autocomplete="off" placeholder="gc name" style="flex:1;padding:8px;border:1px solid #cbd5e1;border-radius:6px;" />'
                + '<input name="new_gc_number" autocomplete="off" placeholder="gc number" style="flex:1;padding:8px;border:1px solid #cbd5e1;border-radius:6px;" />'
                + '<input name="new_gc_email" autocomplete="off" placeholder="email" style="flex:1;padding:8px;border:1px solid #cbd5e1;border-radius:6px;" />'
                + '<input name="new_gc_address" autocomplete="off" placeholder="address" style="flex:1;padding:8px;border:1px solid #cbd5e1;border-radius:6px;" />'
                + '<div class="money-wrapper" style="display:flex;align-items:center;gap:8px;border:1px solid #cbd5e1;border-radius:6px;padding:4px 8px;background:#fff;flex:1;">'
                    + '<span style="color:#374151;font-weight:700;flex:0 0 auto;">$</span>'
                    + '<input name="new_gc_client_win_price" placeholder="client win price" style="flex:1;border:0;padding:6px 0;margin:0;background:transparent;" />'
                  + '</div>'
                + '<select name="new_gc_union" style="padding:8px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;margin-left:6px;">'
                  + '<option value="1">Union</option>'
                  + '<option value="0" selected>Non-union</option>'
                + '</select>'
                + '<button type="button" class="remove-gc" style="background:#fff;border:1px solid #e6edf0;padding:6px 8px;border-radius:6px;cursor:pointer;margin-left:6px;">Remove</button>';
              container.appendChild(row);
              try {
                var companyInput = row.querySelector('input[name="new_gc_general"]');
                var nameInput = row.querySelector('input[name="new_gc_name"]');
                var numberInput = row.querySelector('input[name="new_gc_number"]');
                var emailInput = row.querySelector('input[name="new_gc_email"]');
                var addressInput = row.querySelector('input[name="new_gc_address"]');
                var unionInput = row.querySelector('select[name="new_gc_union"]');
                wireGcRowAutocomplete(companyInput, nameInput, numberInput, emailInput, addressInput, unionInput);
              } catch(e) {}
              try { setGcEditState(gcEditEnabled); } catch(e) {}
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
          var emailToggleBtn = document.getElementById('emailToggleBtn');
          var emailToggleText = document.getElementById('emailToggleText');
          var emailToggleRow = document.getElementById('emailToggleRow');
          var emailSettingsContent = document.getElementById('emailSettingsContent');
          var emailSettingsActions = document.getElementById('emailSettingsActions');

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

          function syncEmailToggleUi(enabled){
            if (emailToggleText) emailToggleText.textContent = enabled ? 'Notifications on' : 'Email notifications are currently turned off';
            if (emailToggleBtn) emailToggleBtn.textContent = enabled ? 'Turn Off' : 'Turn On';
            if (emailSettingsContent) emailSettingsContent.style.display = enabled ? 'block' : 'none';
            if (emailSettingsActions) emailSettingsActions.style.display = enabled ? 'flex' : 'none';
            if (emailToggleRow) emailToggleRow.style.marginBottom = enabled ? '10px' : '0';
          }

          function safeParseJson(text){
            try { return JSON.parse(text); } catch(e) {
              try {
                var s = (text || '').toString();
                var end = s.lastIndexOf('}');
                var start = s.lastIndexOf('{');
                if (start !== -1 && end !== -1 && end > start) {
                  var slice = s.slice(start, end + 1);
                  return JSON.parse(slice);
                }
              } catch(_e) {}
              return null;
            }
          }

          function setEmailEnabled(enabled, opts){
            var sendToServer = !(opts && opts.skipServer);
            var preferredDays = (opts && Array.isArray(opts.preferredDays)) ? opts.preferredDays : null;
            try { localStorage.setItem('bids_email_enabled', enabled ? '1' : '0'); } catch(e){}
            syncEmailToggleUi(enabled);
            if (enabled) {
              try { buildEmailDays(); } catch(e){}
              try {
                var arr = preferredDays;
                if (!arr) {
                  var raw = localStorage.getItem('bids_email_days');
                  arr = raw ? JSON.parse(raw || '[]') : [];
                }
                Array.from((emailDaysList || document).querySelectorAll('input[type=checkbox]')).forEach(function(c){ c.checked = (arr.indexOf(parseInt(c.value,10)) !== -1); });
              } catch(e){}
            }
            if (!sendToServer) return;

            var fd = new FormData();
            fd.append('opted_in', enabled ? '1' : '0');
            var days = [];
            try {
              if (preferredDays) days = preferredDays;
              else {
                var rawDays = localStorage.getItem('bids_email_days');
                days = rawDays ? JSON.parse(rawDays) : [];
              }
            } catch(e){ days = []; }
            fd.append('preferred_days', JSON.stringify(days));

            fetch('../../api/save_email_preferences.php', { method: 'POST', body: fd, credentials: 'same-origin' })
              .then(function(resp){ return resp.text(); })
              .then(function(text){
                var data = safeParseJson(text);
                if (!data) {
                  showToast('Failed to update email notifications', 'error');
                  console.error('Non-JSON response:', text);
                  return;
                }
                if (data && data.success) {
                  if (enabled) {
                    if (data.email_sent) showToast('Email notifications turned on — confirmation sent', 'success');
                    else showToast('Notifications on, but confirmation email failed', 'error');
                  } else {
                    showToast('Email notifications turned off', 'success-top');
                  }
                } else {
                  showToast('Failed to update email notifications', 'error');
                }
              })
              .catch(function(){ showToast('Failed to update email notifications', 'error'); });
          }

          function fetchEmailPrefs(cb){
            fetch('../../api/get_email_preferences.php', { credentials: 'same-origin' })
              .then(function(r){ return r.text(); })
              .then(function(text){
                var data = safeParseJson(text);
                if (!data) {
                  if (cb) cb(new Error('Non-JSON response'), null);
                  console.error('Non-JSON response:', text);
                  return;
                }
                if (cb) cb(null, data);
              })
              .catch(function(err){ if (cb) cb(err, null); });
          }

          function openEmailModal() {
            fetchEmailPrefs(function(err, data){
              if (!data || !data.success) {
                // fallback to local state
                var enabled = false;
                try { enabled = localStorage.getItem('bids_email_enabled') === '1'; } catch(e){}
                setEmailEnabled(enabled, { skipServer: true });
                if (emailModal) emailModal.style.display = 'flex';
                return;
              }

              if (data.exists) {
                var pref = Array.isArray(data.preferred_days) ? data.preferred_days : [];
                try { localStorage.setItem('bids_email_days', JSON.stringify(pref)); } catch(e){}
                try { localStorage.setItem('bids_email_enabled', '1'); } catch(e){}
                setEmailEnabled(true, { skipServer: true, preferredDays: pref });
              } else {
                try { localStorage.removeItem('bids_email_days'); } catch(e){}
                try { localStorage.setItem('bids_email_enabled', '0'); } catch(e){}
                setEmailEnabled(false, { skipServer: true });
              }
              if (emailModal) emailModal.style.display = 'flex';
            });
          }

          function closeEmailModal() { if (emailModal) emailModal.style.display = 'none'; }

          if (enableEmailBtn) enableEmailBtn.addEventListener('click', openEmailModal);
          if (emailToggleBtn) {
            emailToggleBtn.addEventListener('click', function(){
              fetchEmailPrefs(function(err, data){
                var exists = !!(data && data.success && data.exists);
                if (exists) {
                  // Turning off: delete entry
                  setEmailEnabled(false);
                  try { localStorage.removeItem('bids_email_days'); } catch(e){}
                } else {
                  // Turning on: default to [1,2], auto-save, then show modal
                  var defaultDays = [1,2];
                  try { localStorage.setItem('bids_email_days', JSON.stringify(defaultDays)); } catch(e){}
                  setEmailEnabled(true, { preferredDays: defaultDays });
                  if (emailModal) emailModal.style.display = 'flex';
                }
              });
            });
          }
          if (closeEmailBtn) closeEmailBtn.addEventListener('click', closeEmailModal);
          if (cancelEmailBtn) cancelEmailBtn.addEventListener('click', closeEmailModal);
          if (saveEmailBtn) {
            saveEmailBtn.addEventListener('click', function(){
              try {
                var sel = Array.from(emailDaysList.querySelectorAll('input[type=checkbox]:checked')).map(function(c){ return parseInt(c.value,10); });
                if (sel.length > maxSelect) return showToast('You may select up to ' + maxSelect + ' days', 'error');
                localStorage.setItem('bids_email_days', JSON.stringify(sel));
                try { localStorage.setItem('bids_email_enabled', '1'); } catch(e){}

                // Persist server-side and trigger confirmation email
                var fd = new FormData();
                fd.append('opted_in', '1');
                fd.append('preferred_days', JSON.stringify(sel));

                fetch('../../api/save_email_preferences.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                  .then(function(resp){ return resp.text(); })
                  .then(function(text){
                    var data = safeParseJson(text);
                    if (!data) {
                      showToast('Failed to save preferences', 'error');
                      console.error('Non-JSON response:', text);
                      return;
                    }
                    if (data && data.success) {
                      // API saved preferences; check whether confirmation email sent
                      if (data.email_sent) {
                        showToast('Email preferences saved — confirmation sent', 'success');
                      } else {
                        showToast('Preferences saved, but confirmation email failed', 'error');
                        console.error('Confirmation email failed for', data);
                      }
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
        // Print modal
        // -----------------------------
        try {
          var printBtn = document.getElementById('printBtn');
          var printModal = document.getElementById('printModal');
          var printCancel = document.getElementById('printCancel');
          var printConfirm = document.getElementById('printConfirm');
          var printColumnsList = document.getElementById('printColumnsList');
          var printPreview = document.getElementById('printPreview');
          var printStatus = document.getElementById('printStatus');
          var printYear = document.getElementById('printYear');
          var printOrder = document.getElementById('printOrder');

          function buildPrintColumns() {
            if (!printColumnsList) return;
            printColumnsList.innerHTML = '';
            try {
              (bidColumns || []).forEach(function(col){
                var id = 'pc_' + col;
                var wrap = document.createElement('label');
                wrap.style.display = 'flex'; wrap.style.alignItems = 'center'; wrap.style.gap = '8px';
                var chk = document.createElement('input'); chk.type = 'checkbox'; chk.id = id; chk.value = col; chk.checked = true; chk.style.width='16px'; chk.style.height='16px';
                var span = document.createElement('span'); span.textContent = (col === 'dhss_project_number') ? 'DHSS Project #' : (col === 'gc_name' || col === 'general_contractor_name') ? 'General Contractor Name' : col.replace(/_/g,' ');
                span.style.fontWeight = '600'; span.style.color = '#0f172a';
                wrap.appendChild(chk); wrap.appendChild(span);
                printColumnsList.appendChild(wrap);
              });
            } catch(e) { printColumnsList.innerHTML = '<div style="color:#ef4444">Failed to build columns</div>'; }
          }

          function buildPrintYearOptions(){
            if (!printYear) return;
            printYear.innerHTML = '';
            var nowY = new Date().getFullYear();
            var ph = document.createElement('option'); ph.value = ''; ph.textContent = 'All Years'; printYear.appendChild(ph);
            for (var i=0;i<5;i++){ var y = nowY - i; var opt = document.createElement('option'); opt.value = String(y).slice(-2); opt.textContent = String(y); printYear.appendChild(opt); }
          }

          function openPrintModal(){
            if (!printModal) return;
            buildPrintColumns(); buildPrintYearOptions();
            // default filter values mirror first current selections (multi-selects -> pick first)
            try { if (printStatus && statusFilterEl) { var ssel = Array.from(statusFilterEl.selectedOptions).map(function(o){return o.value;}); printStatus.value = (ssel && ssel.length) ? ssel[0] : 'all'; } } catch(e){}
            try { if (printYear && yearFilterEl) { var ysel = Array.from(yearFilterEl.selectedOptions).map(function(o){return o.value;}); printYear.value = (ysel && ysel.length) ? ysel[0] : ''; } } catch(e){}
            try { if (printOrder && orderByEl) printOrder.value = orderByEl.value || 'grouped'; } catch(e){}
            // build initial preview
            buildPrintPreview();
            printModal.style.display = 'flex';
          }

          function closePrintModal(){ if (printModal) printModal.style.display = 'none'; }

          function buildPrintPreview(){
            if (!printPreview) return;
            // clone the table and apply filters + column visibility + ordering
              try {
              var table = document.getElementById('bidsTable');
              if (!table) { printPreview.innerHTML = '<div style="color:#ef4444">No table</div>'; return; }
              // collect selected columns
              var selectedCols = Array.from((printColumnsList || document).querySelectorAll('input[type=checkbox]:checked')).map(function(i){ return i.value; });
              // ensure status/dhss column stay if no columns selected
              if (selectedCols.length === 0) { selectedCols = bidColumns.slice(0,3); }

              // If none selected, pick a sensible default subset
              if (selectedCols.length === 0) selectedCols = ['dhss_project_number','project_name','bid_date','project_city'];

              // treat any GC-related column (name/number/email/address/etc) as part of the GC block
              var gcColsRe = /(^gc[_a-z]*|general_contractor)/i;

              // filter visible data rows according to modal filters
              var stat = (printStatus && printStatus.value) ? printStatus.value : 'all';
              var yr = (printYear && printYear.value) ? printYear.value : '';
              // collect data rows and their detail rows
              var rows = Array.from(table.querySelectorAll('tbody tr'));
              var keep = [];
              for (var i=0;i<rows.length;i++){
                var r = rows[i];
                if (r.classList && r.classList.contains('group-spacer')) continue;
                var isData = r.hasAttribute('data-bid');
                if (!isData) continue;
                var obj = {};
                try { obj = JSON.parse(r.getAttribute('data-bid')||'{}'); } catch(e){}
                var rowStatus = (obj.status||'').toString().toLowerCase().replace(/[^a-z0-9]/g,'') || 'pending';
                var proj = (obj.dhss_project_number||'').toString().trim();
                var yearMatch = true; if (yr) yearMatch = proj.indexOf(yr) === 0;
                var statusMatch = (stat === 'all') || (rowStatus === stat);
                if (yearMatch && statusMatch) keep.push(r);
              }

              // Build items array (use the captured `originalRows` so we also have detailRows)
              var order = (printOrder && printOrder.value) ? printOrder.value : 'grouped';
              var items = (typeof originalRows !== 'undefined' ? originalRows.slice() : []).filter(function(it){ return keep.indexOf(it.row) !== -1; }).map(function(it){
                return {
                  row: it.row,
                  obj: it.obj || {},
                  date: it.date || null,
                  status: (it.status||'').toString().toLowerCase().replace(/[^a-z0-9]/g,'') || 'pending',
                  detailRows: it.detailRows || []
                };
              });

              function cmpDate(a,b){ var ad = a.date? a.date.getTime():Number.POSITIVE_INFINITY; var bd = b.date? b.date.getTime():Number.POSITIVE_INFINITY; return ad-bd; }
              function cmpProj(a,b){ var pa = (a.obj.dhss_project_number||'').toString().trim(); var pb = (b.obj.dhss_project_number||'').toString().trim(); var na=parseFloat(pa), nb=parseFloat(pb); if(!isNaN(na)&&!isNaN(nb)) return na-nb; return pa.localeCompare(pb); }

              if (order === 'date_asc' || order === 'date_desc') {
                items.sort(function(a,b){ return cmpDate(a,b); });
                if (order === 'date_desc') items.reverse();
              } else if (order.indexOf('projectnum') === 0) {
                items.sort(function(a,b){ return cmpProj(a,b); });
                if (order.indexOf('_desc') !== -1) items.reverse();
              } else {
                // grouped default: group by status (desired default order) and sort each group by date asc
                var statusOrder = ['bidding','pending','win','lost','completed'];
                var grouped = [];
                statusOrder.forEach(function(s){
                  var group = items.filter(function(it){ return it.status === s; });
                  group.sort(function(a,b){ return cmpDate(a,b); });
                  grouped = grouped.concat(group);
                });
                // append any items with unknown statuses at the end
                var others = items.filter(function(it){ return statusOrder.indexOf(it.status) === -1; });
                others.sort(function(a,b){ return cmpDate(a,b); });
                items = grouped.concat(others);
              }

              // Build a simple, Excel-like table for preview
              var tbl = document.createElement('table');
              tbl.style.borderCollapse = 'collapse';
              tbl.style.width = '100%';
              tbl.style.fontSize = '8px';
              tbl.style.tableLayout = 'auto';

              // header
              var thead = document.createElement('thead');
              var htr = document.createElement('tr');
              selectedCols.forEach(function(c){
                var th = document.createElement('th');
                var label = (function(k){ if (!k) return ''; if (k === 'dhss_project_number') return 'DHSS Project #'; if (k === 'gc_name' || k === 'general_contractor_name') return 'General Contractor Name'; if (k === 'gc_number' || k === 'general_contractor_number') return 'General Contractor Number'; if (k === 'bid_date') return 'Bid Date'; return k.replace(/_/g,' '); })(c);
                // split header words onto separate lines to reduce column width
                var parts = String(label).split(/\s+/).filter(function(p){ return p.length>0; });
                th.innerHTML = parts.join('<br>');
                th.style.border='1px solid #bfc7cc'; th.style.padding='6px 8px'; th.style.background='#f8fafc'; th.style.fontWeight='700'; th.style.whiteSpace='normal'; th.style.wordBreak='break-word'; th.style.overflow='visible';
                htr.appendChild(th);
              });
              thead.appendChild(htr); tbl.appendChild(thead);

              var tbody = document.createElement('tbody');
              // Add rows with compact cell height (no spacer rows)
              items.forEach(function(it){
                var obj = it.obj || {};
                var baseRow = it.row || null;
                var tr = document.createElement('tr');
                tr.style.borderSpacing = '0';
                selectedCols.forEach(function(col){
                  var td = document.createElement('td');
                  var v = '';
                  try {
                    if (gcColsRe.test(col)) {
                      // For any GC-related column, always mirror what is visibly rendered in the table cell
                      var src = baseRow && baseRow.querySelector ? baseRow.querySelector('td[data-col="' + col + '"]') : null;
                      v = src ? (src.textContent || '').trim() : '';
                    } else if (col === 'bid_date' && obj[col]) {
                      v = formatDateMMDDYYYY(obj[col]);
                    } else {
                      v = (obj[col] !== undefined && obj[col] !== null) ? String(obj[col]) : '';
                    }
                  } catch(e) { v = '' }
                  td.textContent = v;
                  td.style.padding = '2px 6px';
                  td.style.border = '1px solid #d1d5db';
                  td.style.whiteSpace = 'normal';
                  td.style.wordBreak = 'break-word';
                  td.style.overflow = 'visible';
                  td.style.height = '20px';
                  tr.appendChild(td);
                });
                tbody.appendChild(tr);

                // If there are contractor detail rows for this project, add them beneath the main row
                try {
                  if (it.detailRows && it.detailRows.length) {
                    it.detailRows.forEach(function(dtr){
                      try {
                        var detTr = document.createElement('tr');
                        selectedCols.forEach(function(col){
                          var td = document.createElement('td');
                          // non-GC columns remain empty for detail rows
                          if (!gcColsRe.test(col)) {
                            td.textContent = '';
                          } else {
                            var src = dtr.querySelector('td[data-col="' + col + '"]');
                            td.textContent = src ? (src.textContent || '').trim() : '';
                          }
                          td.style.padding = '2px 6px';
                          td.style.border = '1px solid #d1d5db';
                          td.style.borderTop = '0';
                          td.style.whiteSpace = 'normal';
                          td.style.wordBreak = 'break-word';
                          td.style.overflow = 'visible';
                          td.style.height = '20px';
                          detTr.appendChild(td);
                        });
                        tbody.appendChild(detTr);
                      } catch(e){}
                    });
                  }
                } catch(e) {}
              });
              tbl.appendChild(tbody);

              var tableWrap = document.createElement('div'); tableWrap.style.overflow = 'auto'; tableWrap.appendChild(tbl);
              printPreview.innerHTML = '';
              printPreview.appendChild(tableWrap);
            } catch(e) { printPreview.innerHTML = '<div style="color:#ef4444">Preview failed</div>'; }
          }

          // wire events
          if (printBtn) printBtn.addEventListener('click', openPrintModal);
          if (printCancel) printCancel.addEventListener('click', closePrintModal);
          if (printColumnsList) printColumnsList.addEventListener('change', buildPrintPreview);
          if (printStatus) printStatus.addEventListener('change', buildPrintPreview);
          if (printYear) printYear.addEventListener('change', buildPrintPreview);
          if (printOrder) printOrder.addEventListener('change', buildPrintPreview);

          if (printConfirm) {
            printConfirm.addEventListener('click', function(){
              try {
                // create a print window and write preview content
                var w = window.open('','_blank');
                if (!w) { showToast && showToast('Pop-up blocked', 'error'); return; }
                // Build a clean, print-optimized table so browser printing fits columns
                try {
                  var tableSrc = document.getElementById('bidsTable');
                  var selectedCols = Array.from((printColumnsList || document).querySelectorAll('input[type=checkbox]:checked')).map(function(i){ return i.value; });
                  if (!selectedCols || selectedCols.length === 0) selectedCols = ['dhss_project_number','project_name','bid_date','project_city'];
                  var stat = (printStatus && printStatus.value) ? printStatus.value : 'all';
                  var yr = (printYear && printYear.value) ? printYear.value : '';
                  // Use originalRows so we can include contractor detailRows in print output
                  var keepRows = (typeof originalRows !== 'undefined' ? originalRows.slice() : []).filter(function(it){
                    var proj = (it.project || '').toString().trim();
                    var rowStatus = (it.status || '').toString().toLowerCase().replace(/[^a-z0-9]/g,'') || 'pending';
                    var yearMatch = true; if (yr) yearMatch = proj.indexOf(yr) === 0;
                    var statusMatch = (stat === 'all') || (rowStatus === stat);
                    return yearMatch && statusMatch;
                  });
                  var order = (printOrder && printOrder.value) ? printOrder.value : 'grouped';
                  function cmpDateObj(a,b){
                    var ad = a && a.date ? (a.date instanceof Date ? a.date.getTime() : new Date(a.date).getTime()) : Number.POSITIVE_INFINITY;
                    var bd = b && b.date ? (b.date instanceof Date ? b.date.getTime() : new Date(b.date).getTime()) : Number.POSITIVE_INFINITY;
                    return ad - bd;
                  }
                  function cmpProjObj(a,b){
                    var pa = (a && (a.project || (a.obj && a.obj.dhss_project_number))) ? String(a.project || a.obj.dhss_project_number).trim() : '';
                    var pb = (b && (b.project || (b.obj && b.obj.dhss_project_number))) ? String(b.project || b.obj.dhss_project_number).trim() : '';
                    var na = parseFloat(pa), nb = parseFloat(pb);
                    if (!isNaN(na) && !isNaN(nb)) return na - nb;
                    return pa.localeCompare(pb);
                  }
                  if (order === 'date_asc' || order === 'date_desc') {
                    keepRows.sort(cmpDateObj);
                    if (order === 'date_desc') keepRows.reverse();
                  } else if (typeof order === 'string' && order.indexOf('projectnum') === 0) {
                    keepRows.sort(cmpProjObj);
                    if (order.indexOf('_desc') !== -1) keepRows.reverse();
                  } else {
                    // grouped default: mirror page grouping order by status and sort each group by date asc
                    var statusOrder = ['bidding','pending','win','lost','completed'];
                    var groupedKeep = [];
                    statusOrder.forEach(function(s){
                      var grp = keepRows.filter(function(o){
                        var st = (o.status||'').toString().toLowerCase().replace(/[^a-z0-9]/g,'');
                        return st === s;
                      });
                      grp.sort(function(a,b){ return cmpDateObj(a,b); });
                      groupedKeep = groupedKeep.concat(grp);
                    });
                    // include any others
                    var others = keepRows.filter(function(o){
                      var st = (o.status||'').toString().toLowerCase().replace(/[^a-z0-9]/g,'');
                      return statusOrder.indexOf(st) === -1;
                    });
                    others.sort(function(a,b){ return cmpDateObj(a,b); });
                    keepRows = groupedKeep.concat(others);
                  }
                  // build html table with fixed layout and equal column widths so it fits
                  var colWidth = Math.floor(100/selectedCols.length);
                  var colsHtml = '';
                  for (var ci=0; ci<selectedCols.length; ci++){ colsHtml += '<col style="width:' + colWidth + '%">'; }
                  var headerHtml = '';
                  for (var hi=0; hi<selectedCols.length; hi++){
                    var k = selectedCols[hi]; var label = (function(k2){ if (!k2) return ''; if (k2 === 'dhss_project_number') return 'DHSS Project #'; if (k2 === 'gc_name' || k2 === 'general_contractor_name') return 'General Contractor Name'; if (k2 === 'gc_number' || k2 === 'general_contractor_number') return 'General Contractor Number'; if (k2 === 'bid_date') return 'Bid Date'; return k2.replace(/_/g,' '); })(k);
                    var parts = String(label).split(/\s+/).filter(function(p){return p.length>0;}); headerHtml += '<th>' + parts.join('<br>') + '</th>';
                  }
                  var rowsHtml = '';
                  var gcColsRe = /(^gc[_a-z]*|general_contractor)/i;
                  for (var ri=0; ri<keepRows.length; ri++){
                    var rowInfo = keepRows[ri] || {};
                    var o = rowInfo.obj || {};
                    var baseRow = rowInfo.row || null;
                    // main row
                    rowsHtml += '<tr>';
                    for (var ci2=0; ci2<selectedCols.length; ci2++){
                      var col = selectedCols[ci2]; var v = '';
                      try {
                        if (gcColsRe.test(col)) {
                          // For GC-related columns, mirror whatever is displayed in the main table cell
                          var src = baseRow && baseRow.querySelector ? baseRow.querySelector('td[data-col="' + col + '"]') : null;
                          v = src ? (src.textContent || '').trim() : '';
                        } else if (col === 'bid_date' && o[col]) {
                          v = formatDateMMDDYYYY(o[col]);
                        } else {
                          v = (o[col]!==undefined && o[col]!==null) ? String(o[col]) : '';
                        }
                      } catch(e){ v=''; }
                      rowsHtml += '<td>' + (v||'') + '</td>';
                    }
                    rowsHtml += '</tr>';
                    // contractor detail rows (from originalRows entry)
                    try {
                      var det = (rowInfo && rowInfo.detailRows) ? rowInfo.detailRows : [];
                      for (var di=0; di<det.length; di++){
                        var dtr = det[di]; rowsHtml += '<tr class="print-detail">';
                            for (var cii=0; cii<selectedCols.length; cii++){
                              var coln = selectedCols[cii];
                              if (!gcColsRe.test(coln)) {
                                rowsHtml += '<td style="border-top:0;padding:6px;border-left:1px solid #e6edf0;border-right:1px solid #e6edf0;border-bottom:1px solid #e6edf0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></td>';
                              } else {
                                try {
                                  var src = dtr.querySelector ? dtr.querySelector('td[data-col="' + coln + '"]') : null;
                                  var val = src ? (src.textContent || '').trim() : '';
                                  rowsHtml += '<td style="border-top:0;padding:6px;border-left:1px solid #e6edf0;border-right:1px solid #e6edf0;border-bottom:1px solid #e6edf0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + (val||'') + '</td>';
                                } catch(e){ rowsHtml += '<td style="border-top:0;padding:6px;border-left:1px solid #e6edf0;border-right:1px solid #e6edf0;border-bottom:1px solid #e6edf0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></td>'; }
                              }
                            }
                        rowsHtml += '</tr>';
                      }
                    } catch(e){}
                  }
                  var tableHtml = '<table>' + colsHtml + '<thead><tr>' + headerHtml + '</tr></thead><tbody>' + rowsHtml + '</tbody></table>';
                  var html = '<!doctype html><html><head><title>Print</title>' +
                    '<style>@page{size:landscape;}body{font-family:Arial,Helvetica,sans-serif;padding:12px;color:#0f172a;font-size:70%}table{border-collapse:collapse;width:100%;table-layout:fixed}col{vertical-align:top}th,td{padding:6px;border:1px solid #e6edf0;text-align:left;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}th{background:#f8fafc;font-weight:700}</style>' +
                    '</head><body>' + tableHtml + '</body></html>';
                } catch(e) {
                  var html = '<!doctype html><html><head><title>Print</title>' +
                    '<style>@page{size:landscape;}body{font-family:Arial,Helvetica,sans-serif;padding:12px;color:#0f172a;font-size:70%}table{border-collapse:collapse;width:100%}th,td{padding:6px;border:1px solid #e6edf0;text-align:left}</style>' +
                    '</head><body>' + (printPreview ? printPreview.innerHTML : '') + '</body></html>';
                }
                w.document.open(); w.document.write(html); w.document.close();
                setTimeout(function(){ try { w.focus(); w.print(); } catch(e){} }, 300);
                closePrintModal();
              } catch(e){ showToast && showToast('Print failed', 'error'); }
            });
          }
        } catch(e) { console.warn('print modal init failed', e); }

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

          var originalConfig = [];
          var defaultConfig = [];

          function buildOriginalConfig(availableColumns){
            try {
              var ths = document.querySelectorAll('#bidsTable thead th');
              var arr = [];
              ths.forEach(function(th){ var k = th.getAttribute('data-col') || null; arr.push({ name: k, visible: (th.style.display !== 'none') }); });
              // Ensure GC Email/Address are present in the config even if the header was added dynamically
              try {
                var ensure = ['general_contractor_email','general_contractor_address'];
                ensure.forEach(function(c){ if (!arr.find(function(x){ return x.name === c; })) arr.push({ name: c, visible: true }); });
              } catch(e) {}

              // Ensure all available columns are present and preserve desired ordering
              try {
                var finalArr = [];
                var cols = Array.isArray(availableColumns) ? availableColumns : (Array.isArray(allTableColumns) ? allTableColumns : []);
                if (cols && cols.length) {
                  cols.forEach(function(colName){
                    var found = arr.find(function(x){ return x.name === colName; });
                    if (found) finalArr.push(found);
                    else finalArr.push({ name: colName, visible: true });
                  });
                  // append any unexpected entries from arr that weren't in cols
                  arr.forEach(function(it){ if (!finalArr.find(function(x){ return x.name === it.name; })) finalArr.push(it); });
                  return finalArr;
                }
              } catch(e) {}

              return arr;
            } catch(e) {
              var fallback = Array.isArray(allTableColumns) ? allTableColumns : [];
              return fallback.map(function(c){ return { name: c, visible: true }; });
            }
          }

          function setConfigsFromColumns(availableColumns){
            // Build originalConfig directly from available columns (all visible by default)
            // This ensures Reset always goes back to the PHP column order with everything visible
            originalConfig = (Array.isArray(availableColumns) && availableColumns.length) 
              ? availableColumns.map(function(c){ return { name: c, visible: true, locked: false }; })
              : buildOriginalConfig(availableColumns);
            defaultConfig = originalConfig.slice();
          }

          setConfigsFromColumns(allTableColumns);

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
                var map = {
                  'dhss_project_number': 'DHSS Project #',
                  'gc_name': 'General Contractor Name',
                  'general_contractor_name': 'General Contractor Name',
                  'gc_number': 'General Contractor Number',
                  'general_contractor_number': 'General Contractor Number',
                  'general_contractor': 'General Contractor',
                  'client_winner': 'Client Winner',
                  'client_win_price': 'Client Win Price',
                  'stabilizer_bid_win_price': 'Stabilizer Bid Win Price',
                  'dh_stabilizer_price': 'DH Stabilizer Price',
                  'stabilizer_winner': 'Stabilizer Winner',
                  'project_city': 'Project City',
                  'project_county': 'Project County',
                  'project_state': 'Project State',
                  'material_type': 'Material Type',
                  'total_price': 'Total Price',
                  'award_date': 'Award Date',
                  'bid_tabs': 'Bid Tabs',
                  'project_square_yards': 'Project Square Yards',
                  'project_tons': 'Project Tons',
                  'estimator': 'Estimator',
                  'notes': 'Notes',
                  'general_contractor_email': 'General Contractor Email',
                  'general_contractor_address': 'General Contractor Address',
                  'gc_email': 'General Contractor Email',
                  'gc_address': 'General Contractor Address',
                  'winner': 'Winner',
                  'is_union': 'Is Union',
                  'status': 'Status'
                };
                if (map[k]) return map[k];
                return k.replace(/_/g,' ').replace(/\b\w/g, function(ch){ return ch.toUpperCase(); });
              })(item.name);
              lbl.textContent = displayLabel;
              lbl.style.color = '#0f172a'; lbl.style.fontWeight = '700'; lbl.style.fontSize = '13px';
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

          function refreshColumnsFromDb(done){
            // Don't fetch from API - use the PHP-provided allTableColumns which includes GC columns
            // The PHP backend already merged bids + general_contractor columns in the correct order
            if (typeof done === 'function') done();
          }

          function openManageModal(){
            refreshColumnsFromDb(function(){
              var saved = getSavedConfig();
              // Start from the default config (ensures all expected columns present)
              var cfg = defaultConfig.slice();
              // If the user has a saved config, overlay visibility settings onto the default order
              try {
                if (saved && Array.isArray(saved)) {
                  var visMap = {};
                  saved.forEach(function(s){ if (s && s.name) visMap[s.name] = !!s.visible; });
                  // Apply visibility but keep defaultConfig order and ensure all columns present
                  cfg = cfg.map(function(it){ return { name: it.name, visible: (visMap.hasOwnProperty(it.name) ? visMap[it.name] : true), locked: !!it.locked }; });
                }
              } catch(e) { /* fallback to defaultConfig if merge fails */ }
              buildManageList(cfg);
              if (manageModal) manageModal.style.display = 'flex';
            });
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

          // On page load, use PHP column order but apply saved visibility preferences
          try {
            var saved = getSavedConfig();
            if (saved) {
              // Build config from defaultConfig (PHP order) but apply visibility from saved
              var visMap = {};
              saved.forEach(function(s){ if (s && s.name) visMap[s.name] = !!s.visible; });
              var mergedConfig = defaultConfig.map(function(it){ 
                return { 
                  name: it.name, 
                  visible: (visMap.hasOwnProperty(it.name) ? visMap[it.name] : !!it.visible), 
                  locked: !!it.locked 
                }; 
              });
              applyColumnConfig(mergedConfig);
            }
          } catch(e){}
        } catch(e){ console.warn('manage columns init failed', e); }

      });
    })();
  </script>
 
