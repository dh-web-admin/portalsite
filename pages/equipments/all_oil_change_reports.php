<?php
require_once __DIR__ . '/../../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../partials/permissions.php';
// Hide admin-only UI elements for users without edit permission on this module
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
    <title>All Oil Change Reports</title>
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
    /* Card & table styles (match all_engine_reports) */
    .equipment-card { background: #ffffff; border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 10px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .equipment-card-title { font-size: 13px; font-weight: 800; color: #0f172a; margin-bottom: 10px; text-transform: uppercase; }
    .equipment-history-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .equipment-history-table th,
    .equipment-history-table td {
        padding: 10px;
        text-align: left;
    }
    .equipment-history-table th {
        background: #f8fafc;
        border-bottom: 2px solid #e5e7eb;
        font-size: 11px;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
    }
    .equipment-history-table td {
        border-bottom: 1px solid #eef2f7;
    }
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
                    <h1 class="admin-page-title" style="text-align:center;margin-top:6px;margin-bottom:18px;font-size:26px;font-weight:800;">All Oil Change Reports</h1>
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
                                <div class="equipment-card-title">Oil Change History</div>
                                <div id="historyPanel">
                                    <!-- History table will be injected here -->
<?php
// Determine selected equipment id from GET or default
$selected = isset($_GET['selected']) ? (int)$_GET['selected'] : $firstId;
if ($selected > 0) {
    $historyStmt = $conn->prepare('SELECT id, equipment_id, oil_part_id, part, fluid_type, change_date, equipment_hours, changed_by, created_at FROM fluid_reports WHERE equipment_id = ? ORDER BY change_date DESC, id DESC');
    $historyStmt->bind_param('i', $selected);
    $historyStmt->execute();
    $historyRes = $historyStmt->get_result();

    echo '<div class="history-table-area">';
    echo '<div class="history-table-scroll-x"><div style="height:1px;width:1200px;"></div></div>';
    echo '<div class="history-table-wrap" style="overflow:auto;">';
    echo '<table class="equipment-history-table" id="historyTable">';
    echo '<thead><tr><th>Report #</th><th>Part</th><th>Fluid Type</th><th>Change Date</th><th>Equipment Hours</th><th>Changed By</th><th>Recorded At</th></tr></thead>';
    echo '<tbody>';

    if ($historyRes && $historyRes->num_rows > 0) {
        while ($row = $historyRes->fetch_assoc()) {
            $changeDate = $row['change_date'] ? htmlspecialchars($row['change_date']) : '';
            $createdAt = $row['created_at'] ? htmlspecialchars($row['created_at']) : '';
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['id']) . '</td>';
            echo '<td>' . htmlspecialchars($row['part'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['fluid_type'] ?? '') . '</td>';
            echo '<td>' . $changeDate . '</td>';
            echo '<td>' . htmlspecialchars($row['equipment_hours'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['changed_by'] ?? '') . '</td>';
            echo '<td>' . $createdAt . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="7" style="text-align: center; padding: 24px; color: #94a3b8;">No oil change records yet. Select an equipment to view its history.</td></tr>';
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
            var rows = Array.from(table.querySelectorAll('tr'));
            var csv = rows.map(function(row){
                var cells = Array.from(row.querySelectorAll('th,td'));
                return cells.map(function(cell){
                    var text = cell.innerText.replace(/\n/g,' ').trim();
                    return '"' + text.replace(/"/g,'""') + '"';
                }).join(',');
            }).join('\n');
            var blob = new Blob([csv], { type: 'text/csv' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url; a.download = 'oil_change_reports.csv';
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });

        // Print table
        var pr = document.getElementById('printTableBtn');
        if (pr) pr.addEventListener('click', function(){
            var table = document.getElementById('historyTable');
            if (!table) return alert('No table to print');
            var win = window.open('', '_blank');
            win.document.write('<html><head><title>Print Oil Change Reports</title>');
            win.document.write('<link rel="stylesheet" href="../../assets/css/base.css"/>' );
            win.document.write('<style>table{width:100%; border-collapse:collapse;} th,td{padding:8px; border:1px solid #ddd;}</style>');
            win.document.write('</head><body>');
            win.document.write(table.outerHTML);
            win.document.write('</body></html>');
            win.document.close();
            setTimeout(function(){ win.print(); win.close(); }, 400);
        });
    })();
    </script>
</body>
</html>

