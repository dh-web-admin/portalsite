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

// Determine if current user can edit this page
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

// Process columns for GC fields
if ($bidTableExists) {
  $existing = $bidColumns;
  $gcBlock = ['general_contractor','general_contractor_name','general_contractor_number','general_contractor_email','general_contractor_address'];
  $insertAt = null;
  foreach ($existing as $i => $c) {
    $lc = strtolower($c);
    if ($lc === 'general_contractor' || $lc === 'client_winner' || strpos($lc,'gc') === 0 || strpos($lc,'gc_') === 0 || strpos($lc,'general_contractor') !== false) { $insertAt = $i; break; }
  }
  if ($insertAt === null) $insertAt = count($existing);

  $newCols = [];
  for ($i=0;$i<$insertAt;$i++) { $newCols[] = $existing[$i]; }
  foreach ($gcBlock as $gcol) {
    $found = false;
    foreach ($existing as $ec) { 
      if (strtolower($ec) === strtolower($gcol) || 
          strtolower($ec) === 'gc_name' && $gcol==='general_contractor_name' || 
          strtolower($ec) === 'gc_number' && $gcol==='general_contractor_number') { 
        $found = $ec; 
        break; 
      } 
    }
    if ($found === false) {
      $newCols[] = $gcol;
    } else {
      $newCols[] = $found;
    }
  }
  for ($i=$insertAt;$i<count($existing);$i++) {
    if (!in_array($existing[$i], $newCols, true)) $newCols[] = $existing[$i];
  }
  $bidColumns = $newCols;
}

$gcCanonical = [
  'general_contractor' => ['general_contractor','client_winner'],
  'general_contractor_name' => ['general_contractor_name','gc_name','gcname'],
  'general_contractor_number' => ['general_contractor_number','gc_number','gcnumber'],
  'general_contractor_email' => ['general_contractor_email','gc_email','gcemail'],
  'general_contractor_address' => ['general_contractor_address','gc_address','gcaddress'],
];
$gcColMap = [];
foreach ($gcCanonical as $canon => $alts) {
  $found = null;
  foreach ($bidColumns as $bc) {
    $lc = strtolower($bc);
    foreach ($alts as $a) { if ($lc === strtolower($a)) { $found = $bc; break 2; } }
  }
  $gcColMap[$canon] = $found !== null ? $found : $canon;
}

// Prefetch GC data
$gcMap = [];
$gcByProject = [];
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
  } catch (Throwable $e) {}
}

$projKeys = [];
foreach ($bidRows as $br) {
  $pk = isset($br['dhss_project_number']) ? trim((string)$br['dhss_project_number']) : '';
  if ($pk !== '') $projKeys[] = $pk;
}
$projKeys = array_values(array_unique($projKeys));
if (!empty($projKeys)) {
  $inList = implode(',', array_map(function($v){ return "'".addslashes($v)."'"; }, $projKeys));
  try {
    $gres2 = $conn->query("SELECT id, general_contractor, general_contractor_name, general_contractor_number, general_contractor_email, general_contractor_address, dhss_project_number, IFNULL(winner,0) AS winner FROM general_contractor WHERE dhss_project_number IN (" . $inList . ") ORDER BY IFNULL(winner,0) DESC, id ASC");
    if ($gres2) {
      $seenPerProject = [];
      while ($g = $gres2->fetch_assoc()) {
        $key = isset($g['dhss_project_number']) ? $g['dhss_project_number'] : '';
        if ($key === '') continue;
        if (!isset($gcByProject[$key])) $gcByProject[$key] = [];
        if (!isset($seenPerProject[$key])) $seenPerProject[$key] = [];

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
      }
    }
  } catch (Throwable $e) {}
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
    .gc-winner-highlight,
    .gc-winner-highlight * {
      color: #10b981 !important;
      font-weight: 700 !important;
    }
    #addProjectBtn:disabled { opacity: 0.6; cursor: not-allowed; }
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
    .status-pill { display:inline-block; padding:6px 12px; border-radius:999px; font-weight:700; font-size:13px; line-height:1; background: #f1f5f9; color:#0f172a; box-shadow: 0 1px 0 rgba(255,255,255,0.6) inset; }
    .status-pill.status-won { background: rgba(16,185,129,0.12); color:#065f46; }
    .status-pill.status-completed { background: rgba(59,130,246,0.08); color:#1e40af; }
    .status-pill.status-lost { background: rgba(239,68,68,0.08); color:#7f1d1d; }
    .status-pill.status-pending { background: rgba(99,102,241,0.04); color:#334155; }
    #tableContainer { overflow-x: auto; overflow-y: auto; box-sizing:border-box; min-height: calc(100vh - 220px); }
    #bidsTable { display: inline-table; width: max-content; table-layout: auto; }
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
    #bidsTable thead th.col-status, #bidsTable tbody td.col-status { width: 120px; text-align: right; }
    #bidsTable thead th.col-dhss, #bidsTable tbody td.col-dhss { width: 90px; text-align: center; }
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
    #bidsTable tbody tr[data-bid] td { border-bottom: 0 !important; }
    #bidsTable tbody tr[data-bid]:nth-child(even) td { background: rgba(248, 250, 252, 0.55); }
    #bidsTable tbody tr.group-spacer td {
      padding: 6px 0 !important;
      border: 0 !important;
      height: 18px;
      background: transparent !important;
      position: relative;
    }
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
    #bidsTable tbody tr.gc-detail-row td {
      border-top: none !important;
      border-bottom: none !important;
      white-space: normal !important;
      padding: 8px 16px !important;
      color: #0f172a;
      font-size: 13px;
      vertical-align: top;
      background: #ffffff;
    }
    #bidsTable tbody tr.gc-detail-row { background: rgba(249,250,251,0.6); font-size:12px; }
    #bidsTable tbody tr.primary-row td {
      background: #e5e7eb !important;
      font-weight: 600;
    }
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
                          if ($col === 'status') continue;

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

                          if ($col === 'dhss_project_number') {
                            $label = 'DHSS Project #';
                          } elseif ($col === 'gc_name' || $col === 'general_contractor_name') {
                            $label = 'General Contractor Name';
                          } elseif ($col === 'gc_number' || $col === 'general_contractor_number') {
                            $label = 'General Contractor Number';
                          } elseif ($col === 'general_contractor_email' || $col === 'gc_email') {
                            $label = 'General Contractor Email';
                          } elseif ($col === 'general_contractor_address' || $col === 'gc_address') {
                            $label = 'General Contractor Address';
                          } elseif (strpos(strtolower($col), 'gc') !== false || $col === 'general_contractor' || $col === 'client_winner') {
                            $label = 'General Contractor';
                          } else {
                            $label = ucwords(str_replace('_',' ',$col));
                          }

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
                  <?php } else { 
                    foreach ($bidRows as $r) {
                  ?>
                      <tr class="primary-row" data-bid='<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8"); ?>' style="cursor:pointer;">
                      <?php
                        $projKey = isset($r['dhss_project_number']) ? trim((string)$r['dhss_project_number']) : '';
                        $gcs = ($projKey !== '' && isset($gcByProject[$projKey])) ? $gcByProject[$projKey] : [];
                        $primaryGc = null;
                        if (!empty($gcs)) {
                          foreach ($gcs as $g) { if (!empty($g['winner']) && (int)$g['winner'] === 1) { $primaryGc = $g; break; } }
                          if ($primaryGc === null && count($gcs) > 0) {
                            $primaryGc = $gcs[0];
                          }
                        }
                        $primaryDisplayName = '';
                        $primaryDisplayNumber = '';
                        if ($primaryGc !== null) {
                          $primaryDisplayName = strtolower(trim((string)($primaryGc['general_contractor_name'] ?? $primaryGc['general_contractor'] ?? '')));
                          $primaryDisplayNumber = strtolower(trim((string)($primaryGc['general_contractor_number'] ?? '')));
                        } else {
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

                          $isGcCol = in_array($col, array_values($gcColMap), true);
                          if ($isGcCol) {
                            $canon = null;
                            foreach ($gcColMap as $k => $v) { if ($v === $col) { $canon = $k; break; } }
                            $val = '';
                            if ($primaryGc !== null) {
                              if ($canon === 'general_contractor' || $canon === 'general_contractor_name') {
                                $val = !empty($primaryGc['general_contractor_name']) ? $primaryGc['general_contractor_name'] : (isset($primaryGc['general_contractor']) ? $primaryGc['general_contractor'] : '');
                              } elseif ($canon === 'general_contractor_number') {
                                $val = isset($primaryGc['general_contractor_number']) ? $primaryGc['general_contractor_number'] : '';
                              } elseif ($canon === 'general_contractor_email') {
                                $val = isset($primaryGc['general_contractor_email']) ? $primaryGc['general_contractor_email'] : '';
                              } elseif ($canon === 'general_contractor_address') {
                                $val = isset($primaryGc['general_contractor_address']) ? $primaryGc['general_contractor_address'] : '';
                              }
                            }
                            if ($val === '' || $val === null) {
                              if ($canon === 'general_contractor' || $canon === 'general_contractor_name') {
                                if (!empty($r['general_contractor_name'])) $val = $r['general_contractor_name'];
                                elseif (!empty($r['gc_name'])) $val = $r['gc_name'];
                                elseif (!empty($r['general_contractor'])) $val = $r['general_contractor'];
                                elseif (!empty($r['client_winner']) && is_numeric($r['client_winner']) && isset($gcMap[(int)$r['client_winner']])) $val = $gcMap[(int)$r['client_winner']];
                              } elseif ($canon === 'general_contractor_number') {
                                if (!empty($r['general_contractor_number'])) $val = $r['general_contractor_number'];
                                elseif (!empty($r['gc_number'])) $val = $r['gc_number'];
                              } elseif ($canon === 'general_contractor_email') {
                                if (!empty($r['general_contractor_email'])) $val = $r['general_contractor_email'];
                                elseif (!empty($r['gc_email'])) $val = $r['gc_email'];
                              } elseif ($canon === 'general_contractor_address') {
                                if (!empty($r['general_contractor_address'])) $val = $r['general_contractor_address'];
                                elseif (!empty($r['gc_address'])) $val = $r['gc_address'];
                              }
                            }
                            $hasWinnerFlag = false;
                            if ($primaryGc !== null && isset($primaryGc['winner']) && (int)$primaryGc['winner'] === 1) {
                              $hasWinnerFlag = true;
                            } elseif (!empty($r['client_winner']) && $primaryGc !== null && isset($primaryGc['id']) && is_numeric($r['client_winner']) && (int)$r['client_winner'] === (int)$primaryGc['id']) {
                              $hasWinnerFlag = true;
                            }
                            $style = '';
                            if ($canon === 'general_contractor' && $val !== '' && $hasWinnerFlag) {
                              $style = 'style="color:#10b981;font-weight:700;"';
                            }
                            echo '<td data-col="' . htmlspecialchars($col) . '" ' . $style . '>' . htmlspecialchars($val) . '</td>';
                          } else {
                            $cellVal = isset($r[$col]) ? $r[$col] : '';
                            echo '<td data-col="' . htmlspecialchars($col) . '">' . htmlspecialchars($cellVal) . '</td>';
                          }
                        }
                      ?>
                      </tr>
                      <?php
                        $projKey = isset($r['dhss_project_number']) ? trim((string)$r['dhss_project_number']) : '';
                        $gcs = ($projKey !== '' && isset($gcByProject[$projKey])) ? $gcByProject[$projKey] : [];
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

                        $parentId = isset($r['bid_id']) ? $r['bid_id'] : '';
                        foreach ($gcs as $g) {
                          $skip = false;
                          if ($primaryGc !== null) {
                            if (isset($primaryGc['id']) && isset($g['id']) && (int)$primaryGc['id'] === (int)$g['id']) {
                              $skip = true;
                            } else {
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
                              echo '<td class="col-status" data-col="status"></td>';
                              echo '<td class="col-dhss" data-col="' . htmlspecialchars($col) . '"></td>';
                              continue;
                            }
                            $isGcCol = in_array($col, array_values($gcColMap), true);
                            if ($isGcCol) {
                              $canon = null;
                              foreach ($gcColMap as $k => $v) { if ($v === $col) { $canon = $k; break; } }
                              $out = '';
                              if ($canon === 'general_contractor' || $canon === 'general_contractor_name') $out = !empty($g['general_contractor_name']) ? $g['general_contractor_name'] : (isset($g['general_contractor']) ? $g['general_contractor'] : '');
                              elseif ($canon === 'general_contractor_number') $out = isset($g['general_contractor_number']) ? $g['general_contractor_number'] : '';
                              elseif ($canon === 'general_contractor_email') $out = isset($g['general_contractor_email']) ? $g['general_contractor_email'] : '';
                              elseif ($canon === 'general_contractor_address') $out = isset($g['general_contractor_address']) ? $g['general_contractor_address'] : '';
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
                  .modal-top { display:grid; grid-template-columns:1fr 2fr 1fr; gap:8px; align-items:start; margin-bottom:12px; }
                  .modal-top label { font-size:11px; margin-bottom:6px; color:#475569; }
                  .modal-top input { font-size:12px !important; padding:6px 8px !important; height:36px !important; border-radius:6px !important; }
                  #editDhssProjectNumber, #editProjectName, #editBidDate { font-size:12px !important; padding:6px 8px !important; height:36px !important; }
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

          <div id="projectToast" role="status" aria-live="polite">
            <div class="msg"></div>
            <button class="close" aria-label="Dismiss">×</button>
          </div>

        </div>
      </main>
    </div>
  </div>

  <script>
    // Minimal modal + toolbar JS copied from index.php
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
                  fd.set('bid_date', raw);
                }
              }
            }
          } catch(e) { }

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

    (function(){
      var bidColumns = <?php echo json_encode($bidColumns); ?> || [];
      var allTableColumns = <?php echo json_encode(array_merge(['status'], $bidColumns)); ?> || [];

      function pad(n){ return (n < 10) ? ('0' + n) : String(n); }
      function formatDateMMDDYYYY(input) {
        if (!input && input !== 0) return '';
        try {
          if (input instanceof Date) {
            if (isNaN(input.getTime())) return '';
            return pad(input.getMonth()+1) + '/' + pad(input.getDate()) + '/' + input.getFullYear();
          }
          var s = String(input).trim();
          if (!s) return '';
          var isoMatch = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
          if (isoMatch) return pad(parseInt(isoMatch[2],10)) + '/' + pad(parseInt(isoMatch[3],10)) + '/' + isoMatch[1];
          var m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/);
          if (m) {
            var year = m[3].length === 2 ? (2000 + parseInt(m[3],10)) : parseInt(m[3],10);
            return pad(parseInt(m[1],10)) + '/' + pad(parseInt(m[2],10)) + '/' + year;
          }
          var d = new Date(s);
          if (!isNaN(d.getTime())) return pad(d.getMonth()+1) + '/' + pad(d.getDate()) + '/' + d.getFullYear();
        } catch(e){}
        return '';
      }

      function toIsoDate(input) {
        if (!input) return '';
        var s = String(input).trim();
        if (!s) return '';
        var iso = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (iso) return iso[1] + '-' + iso[2] + '-' + iso[3];
        var m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/);
        if (m) {
          var mm = pad(parseInt(m[1],10));
          var dd = pad(parseInt(m[2],10));
          var yyyy = (m[3].length === 2) ? String(2000 + parseInt(m[3],10)) : m[3];
          return yyyy + '-' + mm + '-' + dd;
        }
        var d = new Date(s);
        if (!isNaN(d.getTime())) return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate());
        return '';
      }

      try {
        var enableEmailBtn = document.getElementById('enableEmailBtn');
        var emailModal = document.getElementById('emailSettingsModal');
        var closeEmailBtn = document.getElementById('closeEmailSettings');
        var cancelEmailBtn = document.getElementById('cancelEmailSettings');
        var saveEmailBtn = document.getElementById('saveEmailSettings');
        var emailDaysList = document.getElementById('emailDaysList');

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

              var fd = new FormData();
              fd.append('opted_in', '1');
              fd.append('preferred_days', JSON.stringify(sel));

              fetch('/api/save_email_preferences.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(resp){ return resp.json(); })
                .then(function(data){
                  if (data && data.success) {
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
            try {
              var ensure = ['general_contractor_email','general_contractor_address'];
              ensure.forEach(function(c){ if (!arr.find(function(x){ return x.name === c; })) arr.push({ name: c, visible: true }); });
            } catch(e) {}
            try {
              var finalArr = [];
              if (Array.isArray(allTableColumns) && allTableColumns.length) {
                allTableColumns.forEach(function(colName){
                  var found = arr.find(function(x){ return x.name === colName; });
                  if (found) finalArr.push(found);
                  else finalArr.push({ name: colName, visible: true });
                });
                arr.forEach(function(it){ if (!finalArr.find(function(x){ return x.name === it.name; })) finalArr.push(it); });
                return finalArr;
              }
            } catch(e) {}
            return arr;
          } catch(e) { return allTableColumns.map(function(c){ return { name: c, visible: true }; }); }
        })();
        var defaultConfig = originalConfig.slice();

        function getSavedConfig(){ try { var s = localStorage.getItem('bidsColumnConfig'); return s ? JSON.parse(s) : null; } catch(e){ return null; } }

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
            var displayLabel = (function(k){ if (!k) return ''; var map = { 'dhss_project_number': 'DHSS Project #', 'gc_name': 'General Contractor Name', 'general_contractor_name': 'General Contractor Name', 'gc_number': 'General Contractor Number', 'general_contractor_number': 'General Contractor Number', 'general_contractor': 'General Contractor', 'client_winner': 'Client Winner', 'client_win_price': 'Client Win Price', 'stabilizer_bid_win_price': 'Stabilizer Bid Win Price', 'stabilizer_winner': 'Stabilizer Winner', 'project_city': 'Project City', 'project_county': 'Project County', 'project_state': 'Project State', 'material_type': 'Material Type', 'total_price': 'Total Price', 'award_date': 'Award Date', 'bid_tabs': 'Bid Tabs', 'project_square_yards': 'Project Square Yards', 'project_tons': 'Project Tons', 'estimator': 'Estimator', 'notes': 'Notes', 'general_contractor_email': 'General Contractor Email', 'general_contractor_address': 'General Contractor Address', 'status': 'Status' }; if (map[k]) return map[k]; return k.replace(/_/g,' ').replace(/\b\w/g, function(ch){ return ch.toUpperCase(); }); })(item.name);
            lbl.textContent = displayLabel; lbl.style.color = '#0f172a'; lbl.style.fontWeight = '700'; lbl.style.fontSize = '13px';
            left.appendChild(chk); left.appendChild(lbl);
            var grip = document.createElement('div'); grip.textContent = '≡'; grip.className = 'drag-grip'; grip.style.opacity = '0.6';
            var origIndex = originalConfig.findIndex(function(x){ return x.name === item.name; });
            var locked = (origIndex !== -1 && origIndex < 4);
            if (locked) {
              chk.checked = true; chk.disabled = true; li.dataset.locked = '1'; grip.setAttribute('draggable','false'); grip.style.opacity = '0.3'; grip.style.cursor = 'not-allowed';
            } else {
              grip.setAttribute('draggable','true');
            }
            li.appendChild(left); li.appendChild(grip); manageList.appendChild(li);
          });
        }

        function openManageModal(){
          var saved = getSavedConfig();
          var cfg = defaultConfig.slice();
          try {
            if (saved && Array.isArray(saved)) {
              var visMap = {};
              saved.forEach(function(s){ if (s && s.name) visMap[s.name] = !!s.visible; });
              cfg = cfg.map(function(it){ return { name: it.name, visible: (visMap.hasOwnProperty(it.name) ? visMap[it.name] : !!it.visible), locked: !!it.locked }; });
            }
          } catch(e) {}
          buildManageList(cfg);
          if (manageModal) manageModal.style.display = 'flex';
        }

        function resetManageList(){ buildManageList(originalConfig.slice()); }
        function closeManage(){ if (manageModal) manageModal.style.display = 'none'; }

        function saveManage(){
          if (!manageList) return;
          var items = Array.from(manageList.querySelectorAll('li')).map(function(li){ var name = li.dataset.col; var chk = li.querySelector('input[type="checkbox"]'); return { name: name, visible: !!(chk && chk.checked), locked: !!li.dataset.locked }; });
          var lockedFront = originalConfig.slice(0,4).map(function(x){ return x.name; }).filter(Boolean);
          var ordered = [];
          lockedFront.forEach(function(k){ var it = items.find(function(i){ return i.name === k; }); if (!it) { ordered.push({ name: k, visible: true, locked: true }); } else { it.visible = true; it.locked = true; ordered.push(it); } });
          items.forEach(function(i){ if (lockedFront.indexOf(i.name) === -1) ordered.push(i); });
          try { localStorage.setItem('bidsColumnConfig', JSON.stringify(ordered)); } catch(e){}
          ordered.forEach(function(it){ try { var th = document.querySelector('#bidsTable thead th[data-col="' + it.name + '"]'); if (th) th.style.display = it.visible ? '' : 'none'; var tds = document.querySelectorAll('#bidsTable td[data-col="' + it.name + '"]'); tds.forEach(function(td){ td.style.display = it.visible ? '' : 'none'; }); } catch(e){} });
          closeManage();
        }

        if (manageBtn) manageBtn.addEventListener('click', openManageModal);
        if (closeManageBtn) closeManageBtn.addEventListener('click', closeManage);
        if (resetBtn) resetBtn.addEventListener('click', function(){ resetManageList(); });
        if (cancelBtn) cancelBtn.addEventListener('click', function(){ closeManage(); });
        if (saveBtn) saveBtn.addEventListener('click', function(){ saveManage(); });
      } catch(e){ console.warn('manage columns init failed', e); }

      try {
        var rows = document.querySelectorAll('tr.primary-row');
        rows.forEach(function(r){ r.addEventListener('click', function(){ try { var bid = r.getAttribute('data-bid'); if (!bid) return; var obj = JSON.parse(bid || '{}'); var pn = document.getElementById('editProjectName'); if (pn) pn.value = obj.project_name || ''; var idInput = document.getElementById('editBidId'); if (idInput) idInput.value = obj.bid_id || ''; var dhss = document.getElementById('editDhssProjectNumber'); if (dhss) dhss.value = obj.dhss_project_number || ''; var bd = document.getElementById('editBidDate'); if (bd) { var iso = toIsoDate(obj.bid_date || ''); if (iso) bd.value = iso; } var modal = document.getElementById('editBidModal'); if (modal) modal.style.display = 'flex'; } catch(e){} }); });
      } catch(e){}

    })();
  </script>
</body>
</html>