document.getElementById('edit_tire_id').value = btn.getAttribute('data-tire_id') || '';
                document.getElementById('edit_equipment_id').value = btn.getAttribute('data-equipment_id') || '';

                <input type="hidden" name="equipment_id" id="edit_equipment_id">

                // If a new tire_id was created, update the hidden field for future saves
                if (data.tire_id) {
                    document.getElementById('edit_tire_id').value = data.tire_id;

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
    <title>All Tires</title>
    <link rel="stylesheet" href="../../assets/css/base.css" />
    <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
    <link rel="stylesheet" href="../../assets/css/dashboard.css" />
    <style>
    .engine-table-area {
        overflow-x: auto;
        margin: 0 auto;
        max-width: 98vw;
    }
    .engine-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
        min-width: 1200px;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(30,41,59,0.08);
        overflow: hidden;
    }
    .engine-table thead th {
        position: sticky;
        top: 0;
        background: #f3f4f6;
        color: #22223b;
        font-size: 15px;
        font-weight: 700;
        padding: 16px 10px;
        border-bottom: 2px solid #e5e7eb;
        z-index: 2;
        text-align: left;
        letter-spacing: 0.01em;
    }
    .engine-table tbody td {
        padding: 14px 10px;
        font-size: 15px;
        color: #22223b;
        border-bottom: 1px solid #f1f1f1;
        background: #fff;
    }
    .engine-table tbody tr:nth-child(even) td {
        background: #f8fafc;
    }
    .engine-table tbody tr:hover td {
        background: #e0e7ff;
        transition: background 0.2s;
    }
    .engine-table tbody tr:last-child td {
        border-bottom: none;
    }
    @media (max-width: 1300px) {
        .engine-table { min-width: 900px; }
    }
    .engine-table-scroll-x {
        overflow-x: scroll !important;
        width: 100%;
        margin-bottom: 8px;
        height: 18px;
        background: #f3f4f6;
        border-radius: 8px 8px 0 0;
    }
    .engine-table-scroll-x::-webkit-scrollbar {
        height: 12px;
    }
    .engine-table-scroll-x::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 8px;
    }
    .engine-table-scroll-x::-webkit-scrollbar-track {
        background: #f3f4f6;
        border-radius: 8px;
    }
    .equipment-btn--secondary {
        padding: 10px 28px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 15px;
        background: #f3f4f6;
        color: #6b7280;
        border: none;
        text-decoration: none;
        display: inline-block;
        margin: 18px 0 0 0;
        transition: background 0.2s;
    }
    .equipment-btn--secondary:hover {
        background: #e5e7eb !important;
        color: #374151 !important;
        text-decoration: none !important;
    }
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
    .download-print-btn:last-child {
        margin-right: 0;
    }
    .download-print-btn .icon {
        font-size: 20px;
        display: inline-block;
        vertical-align: middle;
        transition: color 0.18s;
    }
    .download-print-btn:active, .download-print-btn.active {
        background: #667eea !important;
        color: #fff !important;
        box-shadow: 0 2px 8px #0001;
    }
    .download-print-btn:active .icon, .download-print-btn.active .icon {
        color: #fff !important;
        stroke: #fff !important;
    }
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
    .edit-tire-btn {
        display: none;
        background: #9ca3af;
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 10px 32px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(156, 163, 175, 0.15);
        transition: background 0.15s, transform 0.1s;
        margin: 0 auto;
    }
    .edit-tire-btn:hover {
        background: #6b7280;
        transform: translateY(-1px);
    }
    .engine-table tbody tr:hover .edit-tire-btn {
        display: inline-block;
    }
    </style>
</head>
<body class="admin-page">
    <!-- Edit Tire Modal -->
    <div id="editTireModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:10000; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,0.2); padding:32px 28px; min-width:400px; max-width:96vw;">
            <h3 style="margin-bottom:18px; font-size:1.3rem; font-weight:700; color:#374151;">Edit Tire Info</h3>
            <form id="editTireForm">
                <input type="hidden" name="tire_id" id="edit_tire_id">
                <input type="hidden" name="equipment_id" id="edit_equipment_id">
                <div style="margin-bottom:12px;">
                    <label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Steer Tire Make</label>
                    <input type="text" name="steer_tire_make" id="edit_steer_tire_make" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Steer Tire Model</label>
                    <input type="text" name="steer_tire_model" id="edit_steer_tire_model" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Steer Tire Size</label>
                    <input type="text" name="steer_tire_size" id="edit_steer_tire_size" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Drive Tire Make</label>
                    <input type="text" name="drive_tire_make" id="edit_drive_tire_make" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Drive Tire Model</label>
                    <input type="text" name="drive_tire_model" id="edit_drive_tire_model" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;">
                </div>
                <div style="margin-bottom:18px;">
                    <label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Drive Tire Size</label>
                    <input type="text" name="drive_tire_size" id="edit_drive_tire_size" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;">
                </div>
                <div style="display:flex;gap:16px;justify-content:flex-end;">
                    <button type="button" id="cancelEditTireBtn" style="background:#e5e7eb;color:#374151;border:none;border-radius:6px;padding:10px 24px;font-size:15px;font-weight:600;cursor:pointer;transition:background 0.15s;">Cancel</button>
                    <button type="submit" style="background:#43b77a;color:#fff;border:none;border-radius:6px;padding:10px 24px;font-size:15px;font-weight:600;cursor:pointer;transition:background 0.15s;">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="admin-container">
        <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
        <div class="admin-layout">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
            <main class="content-area">
                <div class="main-content">
                    <h1 class="admin-page-title" style="text-align:center;margin-top:32px;margin-bottom:24px;">All Tires Cheat-Sheet</h1>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <div>
                            <a href="index.php" class="equipment-btn equipment-btn--secondary">Back ← </a>
                        </div>
                        <div>
                            <button id="downloadCsvBtn" class="download-print-btn">
                                <span class="icon" aria-hidden="true" style="display:inline-flex;align-items:center;">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                </span>
                                <span>Download CSV</span>
                            </button>
                            <button id="printTableBtn" class="download-print-btn">
                                <span class="icon" aria-hidden="true" style="display:inline-flex;align-items:center;">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="9" width="12" height="7" rx="2"/><path d="M6 17v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2"/><polyline points="6 9 6 4 18 4 18 9"/><line x1="9" y1="13" x2="15" y2="13"/></svg>
                                </span>
                                <span>Print Table</span>
                            </button>
                        </div>
                    </div>
                    <div class="table-area engine-table-area">
                        <div class="engine-table-scroll-x" style="overflow-x:auto;width:100%;margin-bottom:8px;">
                            <div style="height:1px;width:1200px;"></div>
                        </div>
                        <table class="project-table equipment-table engine-table">
                            <thead>
                                <tr>
                                    <th>DHSS #</th>
                                    <th>Type</th>
                                    <th>Year</th>
                                    <th>Make</th>
                                    <th>Model</th>
                                    <th>Steer Tire Make</th>
                                    <th>Steer Tire Model</th>
                                    <th>Steer Tire Size</th>
                                    <th>Drive Tire Make</th>
                                    <th>Drive Tire Model</th>
                                    <th>Drive Tire Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT e.equipment_id, e.dhcst_equipment_number, e.dhss_equipment_number, e.type, e.vehicle_year, e.make, e.model, t.tire_id, t.steer_tire_make, t.steer_tire_model, t.steer_tire_size, t.drive_tire_make, t.drive_tire_model, t.drive_tire_size FROM equipments e LEFT JOIN tire_info t ON e.equipment_id = t.equipment_id ORDER BY e.equipment_id DESC";
                                $result = $conn->query($sql);
                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($row['dhss_equipment_number'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['type'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['vehicle_year'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['make'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['model'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['steer_tire_make'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['steer_tire_model'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['steer_tire_size'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['drive_tire_make'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['drive_tire_model'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($row['drive_tire_size'] ?? '') . '</td>';
                                        echo '<td>';
                                        echo '<button class="edit-tire-btn" ' .
                                            'data-tire_id="' . htmlspecialchars($row['tire_id'] ?? '') . '" ' .
                                            'data-equipment_id="' . htmlspecialchars($row['equipment_id'] ?? '') . '" ' .
                                            'data-steer_tire_make="' . htmlspecialchars($row['steer_tire_make'] ?? '') . '" ' .
                                            'data-steer_tire_model="' . htmlspecialchars($row['steer_tire_model'] ?? '') . '" ' .
                                            'data-steer_tire_size="' . htmlspecialchars($row['steer_tire_size'] ?? '') . '" ' .
                                            'data-drive_tire_make="' . htmlspecialchars($row['drive_tire_make'] ?? '') . '" ' .
                                            'data-drive_tire_model="' . htmlspecialchars($row['drive_tire_model'] ?? '') . '" ' .
                                            'data-drive_tire_size="' . htmlspecialchars($row['drive_tire_size'] ?? '') . '">Edit</button>';
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="12">No equipment or tire info found.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
    // Download CSV functionality
    document.getElementById('downloadCsvBtn').addEventListener('click', function() {
        var table = document.querySelector('.engine-table');
        var rows = Array.from(table.querySelectorAll('tr'));
        var csv = rows.map(row => Array.from(row.querySelectorAll('th,td')).map(cell => '"' + cell.innerText.replace(/"/g, '""') + '"').join(',')).join('\n');
        var blob = new Blob([csv], { type: 'text/csv' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'tire_cheat_sheet.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });

    // Print Table functionality
    document.getElementById('printTableBtn').addEventListener('click', function() {
        var table = document.querySelector('.engine-table-area').innerHTML;
        var win = window.open('', '', 'width=1200,height=800');
        win.document.write('<html><head><title>Print Table</title>');
        win.document.write('<link rel="stylesheet" href="../../assets/css/base.css" />');
        win.document.write('<style>body{background:#fff;}table{width:100%;border-collapse:collapse;}th,td{padding:10px;border:1px solid #ccc;}th{background:#f3f4f6;}</style>');
        win.document.write('</head><body>');
        win.document.write(table);
        win.document.write('</body></html>');
        win.document.close();
        win.print();
    });

    // Edit Tire Modal logic
    document.querySelectorAll('.edit-tire-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_tire_id').value = btn.getAttribute('data-tire_id') || '';
            document.getElementById('edit_equipment_id').value = btn.getAttribute('data-equipment_id') || '';
            document.getElementById('edit_steer_tire_make').value = btn.getAttribute('data-steer_tire_make') || '';
            document.getElementById('edit_steer_tire_model').value = btn.getAttribute('data-steer_tire_model') || '';
            document.getElementById('edit_steer_tire_size').value = btn.getAttribute('data-steer_tire_size') || '';
            document.getElementById('edit_drive_tire_make').value = btn.getAttribute('data-drive_tire_make') || '';
            document.getElementById('edit_drive_tire_model').value = btn.getAttribute('data-drive_tire_model') || '';
            document.getElementById('edit_drive_tire_size').value = btn.getAttribute('data-drive_tire_size') || '';
            // Defensive: always set both hidden fields before open
            var tid = document.getElementById('edit_tire_id').value;
            var eid = btn.getAttribute('data-equipment_id') || '';
            document.getElementById('edit_equipment_id').value = eid;
            document.getElementById('editTireModal').style.display = 'flex';
        });
    });

    document.getElementById('cancelEditTireBtn').addEventListener('click', function() {
        document.getElementById('editTireModal').style.display = 'none';
        document.getElementById('editTireForm').reset();
    });

    document.getElementById('editTireModal').addEventListener('click', function(e) {
        if (e.target === this) {
            document.getElementById('editTireModal').style.display = 'none';
            document.getElementById('editTireForm').reset();
        }
    });

    document.getElementById('editTireForm').addEventListener('submit', function(e) {
        e.preventDefault();
        // Defensive: always set both hidden fields before submit
        var tid = document.getElementById('edit_tire_id').value;
        var eid = document.getElementById('edit_equipment_id').value;
        if (!eid) {
            alert('Missing equipment_id.');
            return;
        }
        var formData = new FormData(this);
        formData.set('tire_id', tid || '');
        formData.set('equipment_id', eid);
        fetch('../../api/update_tire_info.php', {
            method: 'POST',
            body: formData
        })
        .then(resp => resp.json())
        .then(data => {
            if (data.success) {
                // If a new tire_id was created, update the hidden field for future saves
                if (data.tire_id) {
                    document.getElementById('edit_tire_id').value = data.tire_id;
                }
                alert('Tire info updated successfully!');
                document.getElementById('editTireModal').style.display = 'none';
                document.getElementById('editTireForm').reset();
                window.location.reload();
            } else {
                alert('Error updating tire info: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(() => alert('Network error while updating'));
    });
    </script>
    <script src="../../assets/js/mobile-menu.js"></script>
    <script src="../../assets/js/logout-confirm.js"></script>
</body>
</html>