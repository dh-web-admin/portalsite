<?php
require_once __DIR__ . '/../../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../partials/permissions.php';
// Hide edit/mutating UI elements for users without edit permission on this module
if (!can_edit_page('equipments')) {
        echo <<<'HTML'
<style>.admin-only, .edit-filter-btn, .edit-dimension-btn, .edit-tire-btn, .upload-btn, #uploadImagesBtn, .editEquipmentBtn, .delete-equipment, .uploadFilterBtn, .add-equipment-btn { display: none !important; }</style>
<script>
(function(){
    var patterns=[/\bedit\b/i,/\bupload\b/i,/\bdelete\b/i,/\badd\b/i,/\bremove\b/i,/\bsave\b/i];
    function hideIfMatch(el){
        var text=(el.innerText||el.value||'').trim();
        var title=(el.getAttribute && (el.getAttribute('title')||el.getAttribute('aria-label')))||'';
        if(!text && !title) return;
        var combined = (text + ' ' + title).trim();
        for(var i=0;i<patterns.length;i++){ if(patterns[i].test(combined)){ el.style.display='none'; return; } }
    }
    document.addEventListener('DOMContentLoaded', function(){
        var els=document.querySelectorAll('a,button,input[type=button],input[type=submit]');
        els.forEach(hideIfMatch);
    });
})();
</script>
HTML;
}

// Get user role for sidebar
$email = $_SESSION['email'];
$roleStmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$roleStmt->bind_param('s', $email);
$roleStmt->execute();
$roleRes = $roleStmt->get_result();
$user = $roleRes ? $roleRes->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$roleStmt->close();

if (!can_access($role, 'equipments')) {
    header('Location: /pages/dashboard/');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
    <meta name="theme-color" content="#667eea" />
    <title>All Engine Reports</title>
    <link rel="stylesheet" href="../../assets/css/base.css" />
    <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
    <link rel="stylesheet" href="../../assets/css/dashboard.css" />
    <style>
    .download-print-btn {
        padding: 10px 22px 10px 16px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 15px;
        cursor: pointer;
        border: none;
        background: #667eea;
        color: #fff;
        margin-right: 18px;
        transition: background 0.18s, color 0.18s, box-shadow 0.18s, transform 0.1s;
        box-shadow: 0 2px 8px #0001;
        outline: none;
        text-align: center;
        min-width: 140px;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }
    .download-print-btn:last-child { margin-right: 0; }
    .download-print-btn .icon { font-size:20px; display:inline-block; vertical-align: middle; transition: color 0.18s; }
    .download-print-btn:active, .download-print-btn.active { background: #667eea !important; color: #fff !important; box-shadow: 0 2px 8px #0001; }
    .download-print-btn:hover, .download-print-btn:focus {
        background: #f3f4f6 !important;
        color: #3b4cca !important;
        box-shadow: 0 4px 16px #0002;
        transform: translateY(-2px) scale(1.04);
        text-decoration: none;
    }
    .download-print-btn:hover .icon, .download-print-btn:focus .icon {
        color: #3b4cca !important;
        stroke: #3b4cca !important;
    }
    @media print { .no-print, .no-print * { display:none !important; } }
    /* History table styles (match equipment.php) */
    .equipment-card { background: #ffffff; border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 10px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .equipment-card-title { font-size: 13px; font-weight: 800; color: #0f172a; margin-bottom: 10px; text-transform: uppercase; }
    .equipment-history-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .equipment-history-table th { padding: 10px; text-align: left; background: #f8fafc; border-bottom: 2px solid #e5e7eb; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; }
    .equipment-history-table td { padding: 10px; border-bottom: 1px solid #eef2f7; }
    .equipment-edited-copy-badge { display: inline-block; background: #dbeafe; color: #1e40af; font-size: 0.85em; font-weight: 600; padding: 3px 8px; border-radius: 4px; white-space: nowrap; margin-right: 6px; border: 1px solid #93c5fd; vertical-align: middle; }
    .equipment-history-copy-row { background: #f8fafc; }
    .equipment-history-original-hidden { display: none; }
    .equipment-history-edit-cell { padding: 10px !important; width: auto; min-width: 120px; text-align: left; white-space: nowrap; }
        /* Top scrollbar for wide tables */
        .history-table-area { overflow-x: auto; }
        .history-table-scroll-x {
            overflow-x: scroll !important;
            width: 100%;
            margin-bottom: 8px;
            height: 18px;
            background: #f3f4f6;
            border-radius: 8px 8px 0 0;
        }
        .history-table-scroll-x::-webkit-scrollbar { height: 12px; }
        .history-table-scroll-x::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
        .history-table-scroll-x::-webkit-scrollbar-track { background: #f3f4f6; border-radius: 8px; }
    </style>
</head>
<body class="admin-page">
    <div class="admin-container">
        <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
        <div class="admin-layout">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
            <main class="content-area">
                <div class="main-content">
                    <h1 class="admin-page-title" style="text-align:center;margin-top:6px;margin-bottom:18px;font-size:26px;font-weight:800;">All Engine Reports</h1>
                    <div style="display:flex; gap:24px; align-items:flex-start;">
                        <div style="flex:0 0 320px; max-width:320px;">
                            <div style="margin-bottom: 16px;">
                                <a href="index.php" class="equipment-btn equipment-btn--secondary" style="padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 14px; background: #f3f4f6; color: #6b7280; border: none; text-decoration: none; display: inline-block; margin: 0; transition: background 0.2s;">&larr; Back to Equipments</a>
                            </div>

                            <div class="equipment-card">
                                <div class="equipment-card-title">Equipments</div>
                                <div style="max-height:60vh; overflow:auto;">
                                    <ul id="equipmentList" style="list-style:none; padding:0; margin:0;">
<?php
// Fetch equipments list (oldest first by insertion id)
$eqStmt = $conn->prepare('SELECT equipment_id, dhss_equipment_number, dhcst_equipment_number, type FROM equipments ORDER BY equipment_id ASC');
$eqStmt->execute();
$eqRes = $eqStmt->get_result();
$firstId = 0;
while ($eq = $eqRes->fetch_assoc()) {
    if ($firstId === 0) $firstId = (int)$eq['equipment_id'];
    $label = htmlspecialchars(trim($eq['dhss_equipment_number'] ?: $eq['dhcst_equipment_number'] ?: ('#'.$eq['equipment_id'])));
    echo '<li style="margin-bottom:8px;">';
    echo '<button class="select-equipment-btn" data-eid="' . (int)$eq['equipment_id'] . '" style="width:100%; text-align:left; padding:10px; border-radius:6px; border:1px solid #e6e8eb; background:#fff; cursor:pointer;">' . $label . '<div style="font-size:11px; color:#556;">' . htmlspecialchars($eq['type'] ?? '') . '</div></button>';
    echo '</li>';
}
$eqStmt->close();
?>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div style="flex:1 1 auto;">
                            <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
                                <div style="display:flex; gap:12px;">
                                    <button id="downloadCsvBtn" class="download-print-btn" style="margin-right: 0;">
                                        <span class="icon" aria-hidden="true" style="display:inline-flex;align-items:center;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                        </span>
                                        <span style="font-size:14px;">Download CSV</span>
                                    </button>
                                    <button id="printTableBtn" class="download-print-btn" style="margin-right: 0;">
                                        <span class="icon" aria-hidden="true" style="display:inline-flex;align-items:center;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="9" width="12" height="7" rx="2"/><path d="M6 17v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2"/><polyline points="6 9 6 4 18 4 18 9"/><line x1="9" y1="13" x2="15" y2="13"/></svg>
                                        </span>
                                        <span style="font-size:14px;">Print Table</span>
                                    </button>
                                </div>
                            </div>
                            <div class="equipment-card">
                                <div class="equipment-card-title">Equipment History</div>
                                <div id="historyPanel">
                                    <!-- History table will be injected here -->
<?php
// Determine selected equipment id from GET or default
$selected = isset($_GET['selected']) ? (int)$_GET['selected'] : $firstId;
if ($selected > 0) {
    $historyStmt = $conn->prepare('SELECT id, date_reported, reported_issues, reported_by, equipment_location, operating_condition, mechanic_diagnosis, date_repaired, repair_mechanic, parts_fixed, pictures, is_edited_copy, original_issue_id FROM equipment_history WHERE equipment_id = ? ORDER BY date_reported DESC, id DESC');
    $historyStmt->bind_param('i', $selected);
    $historyStmt->execute();
    $historyRes = $historyStmt->get_result();

    // Build arrays to track edited chains like equipment.php
    $allRows = [];
    $rowsById = [];
    $hasNewerVersion = [];
    if ($historyRes && $historyRes->num_rows > 0) {
        while ($row = $historyRes->fetch_assoc()) {
            $allRows[] = $row;
            $rowsById[$row['id']] = $row;
            if (!empty($row['is_edited_copy']) && !empty($row['original_issue_id'])) {
                $hasNewerVersion[$row['original_issue_id']] = true;
            }
        }
    }

    echo '<div class="history-table-area">';
    echo '<div class="history-table-scroll-x"><div style="height:1px;width:1200px;"></div></div>';
    echo '<div class="history-table-wrap" style="overflow:auto;">';
    echo '<table class="equipment-history-table" id="historyTable">';
    echo '<thead><tr><th>Issue #</th><th>Reported Issues</th><th>Reported By</th><th>Date Reported</th><th>Location</th><th>Operating Condition</th><th>Mechanic Diagnosis</th><th>Date Repaired</th><th>Repair Mechanic</th><th>Parts Fixed</th></tr></thead>';
    echo '<tbody>';
    if (count($allRows) > 0) {
        foreach ($allRows as $row) {
            $rowData = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
            $hasNewer = isset($hasNewerVersion[$row['id']]);

            $rowClass = 'equipment-history-row';
            if (!empty($row['is_edited_copy'])) {
                $rowClass .= ' equipment-history-copy-row';
            }
            if ($hasNewer) {
                $rowClass .= ' equipment-history-original-hidden';
            }

            echo '<tr class="' . $rowClass . '" data-row="' . $rowData . '"';
            if (!empty($row['is_edited_copy']) && !empty($row['original_issue_id'])) {
                echo ' data-original-id="' . htmlspecialchars($row['original_issue_id']) . '"';
            }
            if ($hasNewer) {
                echo ' data-issue-id="' . htmlspecialchars($row['id']) . '"';
            }
            echo '>';

            echo '<td class="equipment-history-edit-cell">';
            if (!empty($row['is_edited_copy'])) {
                echo '<span class="equipment-edited-copy-badge" title="Edited">edited</span> ';
            }
            echo htmlspecialchars($row['id']);
            echo '</td>';

            echo '<td>' . nl2br(htmlspecialchars($row['reported_issues'] ?? '')) . '</td>';
            echo '<td>' . htmlspecialchars($row['reported_by'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['date_reported'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['equipment_location'] ?? '') . '</td>';
            $opCondition = strtolower(trim($row['operating_condition'] ?? ''));
            $opConditionDisplay = '';
            if ($opCondition === 'green') {
                $opConditionDisplay = 'Fully operable';
            } elseif ($opCondition === 'yellow') {
                $opConditionDisplay = 'minor issue|operable';
            } elseif ($opCondition === 'red') {
                $opConditionDisplay = 'inoperable';
            } else {
                $opConditionDisplay = htmlspecialchars($row['operating_condition'] ?? '');
            }
            echo '<td>' . htmlspecialchars($opConditionDisplay) . '</td>';
            echo '<td>' . nl2br(htmlspecialchars($row['mechanic_diagnosis'] ?? '')) . '</td>';
            echo '<td>' . htmlspecialchars($row['date_repaired'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['repair_mechanic'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['parts_fixed'] ?? '') . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="10" style="text-align: center; padding: 24px; color: #94a3b8;">No history records yet. Select an equipment to view its history.</td></tr>';
    }

    echo '</tbody></table>';
    echo '</div></div>'; // close history-table-wrap and history-table-area
    $historyStmt->close();
} else {
    echo '<div style="color:#667; padding:18px;">Select an equipment to view its history.</div>';
}
?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
    (function(){
        // Toggle users sub-nav
        var usersToggle = document.getElementById('usersToggle');
        var usersGroup = document.getElementById('usersGroup');
        if (usersToggle && usersGroup) {
            usersToggle.addEventListener('click', function(){
                usersGroup.classList.toggle('open');
            });
        }
    })();
    </script>
    <script src="../../assets/js/mobile-menu.js"></script>
    <script src="../../assets/js/logout-confirm.js"></script>
    <script>
    // Wire equipment selection, print and download actions
    (function(){
        // equipment selection
        document.querySelectorAll('.select-equipment-btn').forEach(function(b){
            b.addEventListener('click', function(){
                var id = b.getAttribute('data-eid');
                if (!id) return;
                var url = new URL(window.location.href);
                url.searchParams.set('selected', id);
                window.location.href = url.toString();
            });
        });

        // Download CSV for history table
        var dl = document.getElementById('downloadCsvBtn');
        if (dl) dl.addEventListener('click', function(){
            var table = document.getElementById('historyTable');
            if (!table) return alert('No table to download');
            // Build CSV: include header from THEAD (if present), then visible TBODY rows
            var csvRows = [];
            var thead = table.querySelectorAll('thead th');
            if (thead && thead.length > 0) {
                var header = Array.from(thead).map(function(th){ return '"' + th.innerText.replace(/\n/g,' ').trim().replace(/"/g,'""') + '"'; }).join(',');
                csvRows.push(header);
            }
            var bodyRows = Array.from(table.querySelectorAll('tbody tr'));
            bodyRows.forEach(function(row){
                // only include rows that are visible
                if (row.offsetParent === null) return;
                var cells = Array.from(row.querySelectorAll('th,td'));
                var line = cells.map(function(cell){
                    var text = cell.innerText.replace(/\n/g,' ').trim();
                    return '"' + text.replace(/"/g,'""') + '"';
                }).join(',');
                csvRows.push(line);
            });
            var csv = csvRows.join('\n');
            var blob = new Blob([csv], { type: 'text/csv' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url; a.download = 'equipment_history.csv';
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });

        // Print table
        var pr = document.getElementById('printTableBtn');
        if (pr) pr.addEventListener('click', function(){
            var table = document.getElementById('historyTable');
            if (!table) return alert('No table to print');
            var win = window.open('', '_blank');
            win.document.write('<html><head><title>Print Equipment History</title>');
            win.document.write('<link rel="stylesheet" href="../../assets/css/base.css"/>');
            win.document.write('<style>table{width:100%; border-collapse:collapse;} th,td{padding:8px; border:1px solid #ddd;}</style>');
            win.document.write('</head><body>');
            win.document.write(table.outerHTML);
            win.document.write('</body></html>');
            win.document.close();
            setTimeout(function(){ win.print(); win.close(); }, 400);
        });

        // View pictures: open first image in new tab (normalize relative paths)
        document.querySelectorAll('.view-pictures-btn').forEach(function(b){
            b.addEventListener('click', function(){
                var data = b.getAttribute('data-pictures') || '[]';
                try { var list = JSON.parse(data); } catch(e){ return; }
                if (!Array.isArray(list) || list.length === 0) return;
                var u = String(list[0] || '').trim();
                if (!/^https?:\/\//i.test(u) && !u.startsWith('/')) u = '/PortalSite/' + u.replace(/^\/+/, '');
                window.open(u, '_blank');
            });
        });

        // Sync top scrollbar with table horizontal scroll for history table
        (function(){
            var topScroll = document.querySelector('.history-table-scroll-x');
            var tableWrap = document.querySelector('.history-table-wrap');
            if (!topScroll || !tableWrap) return;
            var table = tableWrap.querySelector('table');
            if (!table) return;
            var adjust = function(){
                try {
                    var desired = Math.max(table.scrollWidth, tableWrap.clientWidth + 1);
                    topScroll.firstElementChild.style.width = desired + 'px';
                    // sync initial positions
                    topScroll.scrollLeft = tableWrap.scrollLeft;
                } catch(e){}
            };
            // Run immediately and slightly after to allow layout to settle
            adjust();
            setTimeout(adjust, 100);
            window.addEventListener('load', function(){ setTimeout(adjust, 50); });
            topScroll.onscroll = function(){ tableWrap.scrollLeft = topScroll.scrollLeft; };
            tableWrap.onscroll = function(){ topScroll.scrollLeft = tableWrap.scrollLeft; };
            window.addEventListener('resize', function(){ setTimeout(adjust, 50); });
        })();
    })();
    </script>
</body>
</html>

