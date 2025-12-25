<?php
require_once __DIR__ . '/../../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

require_once __DIR__ . '/../../config/config.php';

// Get user role for sidebar
$email = $_SESSION['email'];
$roleStmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$roleStmt->bind_param('s', $email);
$roleStmt->execute();
$roleRes = $roleStmt->get_result();
$user = $roleRes ? $roleRes->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';

// Check if developer is previewing as another role
if ($role === 'developer' && isset($_GET['preview_role'])) {
    $role = $_GET['preview_role'];
}

$roleStmt->close();

// Preserve preview mode in URLs
$previewParam = '';
if (isset($_GET['preview_role'])) {
    $previewParam = '?preview_role=' . urlencode($_GET['preview_role']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
    <meta name="theme-color" content="#667eea" />
    <title>All Filters</title>
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
    }
    .filter-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-bottom: 18px;
        min-width: 260px;
        max-width: 100%;
        overflow: hidden;
    }
    .filter-table th, .filter-table td {
        font-size: 15px;
        font-weight: 400;
        text-align: left;
        border-bottom: 1px solid #f0f0f0;
        color: #222;
        padding: 18px 18px 18px 18px;
        line-height: 1.7;
    }
    .filter-table th {
        background: #f7f7f7;
        font-weight: 600;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
    }
    .filter-table tr:last-child td {
        border-bottom: none;
    }
    /* --- Edit Filter Button Styles --- */
    .filter-card {
        position: relative;
        border-radius: 10px;
        box-shadow: 0 2px 8px #0001;
        background: #fff;
        overflow: hidden;
        transition: box-shadow 0.15s;
    }
    .filter-card .edit-filter-btn {
        display: none;
        position: absolute;
        top: 10px;
        right: 10px;
        background: #f3f4f6;
        color: #374151;
        border: none;
        border-radius: 6px;
        padding: 4px 14px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 1px 4px #0001;
        z-index: 2;
        transition: background 0.15s, color 0.15s;
    }
    .filter-card:hover .edit-filter-btn {
        display: block;
    }
    /* --- Edit Filter Modal Styles --- */
    #editFilterModal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0,0,0,0.18);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    #editFilterModal .modal-content {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 32px #0002;
        padding: 32px 28px;
        min-width: 340px;
        max-width: 96vw;
    }
    </style>
                        <!-- Edit Filter Modal -->
                        <div id="editFilterModal">
                            <div class="modal-content">
                                <h3 style="margin-bottom:18px; font-size:1.18rem; font-weight:700; color:#374151;">Edit Filter</h3>
                                <form id="editFilterForm">
                                    <input type="hidden" name="filter_id" id="edit_filter_id">
                                    <div style="margin-bottom:12px;">
                                        <label>Filter Date</label><br>
                                        <input type="date" name="filter_date" id="edit_filter_date" style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;">
                                    </div>
                                    <div style="margin-bottom:12px;">
                                        <label>Filter Hours</label><br>
                                        <input type="number" step="0.1" name="hours" id="edit_hours" style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;">
                                    </div>
                                    <div style="margin-bottom:12px;">
                                        <label>Filter Part Number</label><br>
                                        <input type="text" name="part_number" id="edit_part_number" style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;">
                                    </div>
                                    <div style="margin-bottom:18px;">
                                        <label>Filter Make</label><br>
                                        <input type="text" name="make" id="edit_make" style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;">
                                    </div>
                                    <div style="display:flex;gap:16px;justify-content:flex-end;">
                                        <button type="button" id="cancelEditFilterBtn" style="background:#e5e7eb;color:#374151;border:none;border-radius:6px;padding:8px 22px;font-size:15px;font-weight:600;cursor:pointer;">Cancel</button>
                                        <button type="submit" style="background:#43b77a;color:#fff;border:none;border-radius:6px;padding:8px 22px;font-size:15px;font-weight:600;cursor:pointer;">Save</button>
                                    </div>
                                </form>
                            </div>
                        </div>
</head>
<body class="admin-page">
    <div class="admin-container">
        <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
        <div class="admin-layout">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
            <main class="content-area">
                <div class="main-content" style="display: flex; flex-direction: row; gap: 32px; align-items: flex-start;">
                    <div style="flex: 0 0 340px; max-width: 340px; min-width: 240px; background: #f8fafc; border-radius: 14px; box-shadow: 0 2px 8px #0001; padding: 24px 12px 24px 18px; height: 80vh; overflow-y: auto;">
                        <h2 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 18px; color: #374151;">Select an equipment.</h2>
                        <ul id="equipmentList" style="list-style: none; padding: 0; margin: 0;">
                <!-- Edit/Save buttons removed -->
                        <?php
                        $equipments = [];
                        $sql = "SELECT equipment_id, dhcst_equipment_number, dhss_equipment_number, type, vehicle_year, make, model FROM equipments ORDER BY equipment_id DESC";
                        $res = $conn->query($sql);
                        if ($res) {
                            while ($row = $res->fetch_assoc()) {
                                $equipments[] = $row;
                            }
                        }
                        foreach ($equipments as $eq):
                            $label = htmlspecialchars(trim(($eq['dhcst_equipment_number'] ?? ''))) . ' ' .
                                     htmlspecialchars(trim(($eq['dhss_equipment_number'] ?? ''))) . ' ' .
                                     htmlspecialchars(trim(($eq['type'] ?? ''))) . ' ' .
                                     htmlspecialchars(trim(($eq['vehicle_year'] ?? ''))) . ' ' .
                                     htmlspecialchars(trim(($eq['make'] ?? ''))) . ' ' .
                                     htmlspecialchars(trim(($eq['model'] ?? '')));
                        ?>
                            <li>
                                <button class="equipment-list-btn" data-eqid="<?php echo (int)$eq['equipment_id']; ?>" data-label="<?php echo htmlspecialchars($label); ?>" style="width:100%;text-align:left;padding:12px 10px;margin-bottom:8px;border-radius:8px;border:none;background:#fff;font-size:15px;font-weight:500;cursor:pointer;transition:background 0.15s;box-shadow:0 1px 4px #0001;">
                                    <?php echo $label; ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    </div>
                    <div id="equipmentDetailsArea" style="flex: 1 1 0; min-width: 0; background: #fff; border-radius: 14px; box-shadow: 0 2px 8px #0001; padding: 32px; min-height: 400px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <h2 style="font-size: 1.3rem; font-weight: 700; color: #374151;">All Filters Cheat-Sheet</h2>
                            <button id="addFilterBtn" style="background: #6c7ae0; color: #fff; border: none; border-radius: 6px; padding: 8px 22px; font-size: 16px; font-weight: 600; cursor: pointer;">Add Filter</button>
                        </div>
                        <div id="addFilterModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:9999; align-items:center; justify-content:center;">
                            <div style="background:#fff; border-radius:12px; box-shadow:0 8px 32px #0002; padding:32px 28px; min-width:340px; max-width:96vw;">
                                <h3 style="margin-bottom:18px; font-size:1.18rem; font-weight:700; color:#374151;">Add Filter</h3>
                                <form id="addFilterForm">
                                    <div style="margin-bottom:12px;">
                                        <label>Filter Name</label><br>
                                        <input type="text" name="filter_name" style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;">
                                    </div>
                                    <div style="margin-bottom:12px;">
                                        <label>Filter Date</label><br>
                                        <input type="date" name="filter_date" style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;">
                                    </div>
                                    <div style="margin-bottom:12px;">
                                        <label>Filter Hours</label><br>
                                        <input type="number" step="0.1" name="hours" style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;">
                                    </div>
                                    <div style="margin-bottom:12px;">
                                        <label>Filter Part Number</label><br>
                                        <input type="text" name="part_number" style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;">
                                    </div>
                                    <div style="margin-bottom:18px;">
                                        <label>Filter Make</label><br>
                                        <input type="text" name="make" style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;">
                                    </div>
                                    <div style="display:flex;gap:16px;justify-content:flex-end;">
                                        <button type="button" id="cancelAddFilterBtn" style="background:#e5e7eb;color:#374151;border:none;border-radius:6px;padding:8px 22px;font-size:15px;font-weight:600;cursor:pointer;">Cancel</button>
                                        <button type="submit" style="background:#43b77a;color:#fff;border:none;border-radius:6px;padding:8px 22px;font-size:15px;font-weight:600;cursor:pointer;">Save</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div id="equipmentDetailsPlaceholder" style="color:#888; margin-top:24px;">Select an equipment from the left to view details here.</div>
                        <div id="equipmentTables"></div>
                    </div>
                </div>
            <script>
            // Add Filter Modal logic
            document.addEventListener('DOMContentLoaded', function() {
                var addBtn = document.getElementById('addFilterBtn');
                var modal = document.getElementById('addFilterModal');
                var cancelBtn = document.getElementById('cancelAddFilterBtn');
                var form = document.getElementById('addFilterForm');
                if (addBtn && modal && cancelBtn && form) {
                    addBtn.addEventListener('click', function() {
                        modal.style.display = 'flex';
                    });
                    cancelBtn.addEventListener('click', function() {
                        modal.style.display = 'none';
                        form.reset();
                    });
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        // Get selected equipment id
                        var selectedBtn = document.querySelector('.equipment-list-btn.selected');
                        var equipment_id = selectedBtn ? selectedBtn.getAttribute('data-eqid') : null;
                        if (!equipment_id) {
                            alert('Please select an equipment first.');
                            return;
                        }
                        var formData = new FormData(form);
                        formData.append('equipment_id', equipment_id);
                        fetch('../../api/add_filter_info.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(resp => resp.json())
                        .then(data => {
                            if (data.success) {
                                alert('Filter saved successfully!');
                                modal.style.display = 'none';
                                form.reset();
                            } else {
                                alert('Error saving filter: ' + (data.error || 'Unknown error'));
                            }
                        })
                        .catch(() => {
                            alert('Network error.');
                        });
                    });
                }
            });
            // Highlight selected equipment and load details (show full label)
            document.querySelectorAll('.equipment-list-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.equipment-list-btn').forEach(function(b) {
                        b.classList.remove('selected');
                        b.style.background = '#f3f4f6';
                        b.style.color = '#374151';
                        b.style.fontWeight = '500';
                    });
                    btn.classList.add('selected');
                    btn.style.background = '#e5e7eb'; // lighter gray
                    btn.style.color = '#374151';
                    btn.style.fontWeight = '700';
                    // Show full label
                    var label = btn.getAttribute('data-label');
                    document.getElementById('equipmentDetailsPlaceholder').style.display = 'none';
                    // AJAX fetch filters info for this equipment
                    fetch('../../api/fetch_equipment_filters.php?equipment_id=' + encodeURIComponent(eqid))
                        .then(response => response.json())
                        .then(data => {
                            // Fetch all filter names
                            fetch('fetch_all_filter_names.php')
                              .then(resp => resp.json())
                              .then(filterNames => {
                                let tableHtml = '<div style="font-size:1.1rem;color:#374151;margin-bottom:18px;">' + label + '</div>';
                                tableHtml += '<div style="max-height:70vh;overflow-y:auto;padding-right:8px;">';
                                filterNames.forEach(fn => {
                                  // Find filter_info for this filter_id
                                  let info = (data || []).filter(row => row.filter_id == fn.filter_id);
                                  tableHtml += '<div style="background:#f9fafb;box-shadow:0 1px 4px #0001;border-radius:8px;overflow:hidden;margin-bottom:24px;">';
                                  tableHtml += '<div style="background:#f3f4f6;font-weight:600;padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:1.08rem;">' + fn.filter_name + '</div>';
                                  tableHtml += '<table class="filter-table" style="width:100%;border-collapse:collapse;">';
                                  tableHtml += '<thead><tr>' +
                                    '<th style="padding:6px 8px;font-size:13px;">Date</th>' +
                                    '<th style="padding:6px 8px;font-size:13px;">Hours</th>' +
                                    '<th style="padding:6px 8px;font-size:13px;">Part Number</th>' +
                                    '<th style="padding:6px 8px;font-size:13px;">Make</th>' +
                                '</tr></thead>';
                                  tableHtml += '<tbody>';
                                                                    let rows = info.length > 0 ? info : [];
                                                                    let minRows = 1;
                                                                    for (let i = 0; i < Math.max(rows.length, minRows); i++) {
                                                                        let row = rows[i];
                                                                        if (!row || Object.keys(row).length === 0) {
                                                                            tableHtml += '<tr>' +
                                                                                '<td style="padding:6px 8px; color:#bbb; font-style:italic;">N/A</td>' +
                                                                                '<td style="padding:6px 8px; color:#bbb; font-style:italic;">N/A</td>' +
                                                                                '<td style="padding:6px 8px; color:#bbb; font-style:italic;">N/A</td>' +
                                                                                '<td style="padding:6px 8px; color:#bbb; font-style:italic;">N/A</td>' +
                                                                            '</tr>';
                                                                        } else {
                                                                            tableHtml += '<tr>' +
                                                                                '<td style="padding:6px 8px;">' + (row.filter_date && row.filter_date.trim() ? row.filter_date : '<span style="color:#bbb;font-style:italic;">N/A</span>') + '</td>' +
                                                                                '<td style="padding:6px 8px;">' + (row.hours && row.hours.trim() ? row.hours : '<span style="color:#bbb;font-style:italic;">N/A</span>') + '</td>' +
                                                                                '<td style="padding:6px 8px;">' + (row.part_number && row.part_number.trim() ? row.part_number : '<span style="color:#bbb;font-style:italic;">N/A</span>') + '</td>' +
                                                                                '<td style="padding:6px 8px;">' + (row.make && row.make.trim() ? row.make : '<span style="color:#bbb;font-style:italic;">N/A</span>') + '</td>' +
                                                                            '</tr>';
                                                                        }
                                                                    }
                                  tableHtml += '</tbody></table>';
                                  tableHtml += '</div>';
                                });
                                tableHtml += '</div>';
                                tableHtml += '<button id="addFiltersBtn" class="download-print-btn" style="margin-top:18px;">Add Filters</button>';
                                document.getElementById('equipmentTables').innerHTML = tableHtml;
                              });
                        });
                });
            });
                // Edit/Save logic removed
            </script>
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
    document.getElementById('downloadCsvBtn').addEventListener('click', function() {
        var table = document.querySelector('table');
        if (!table) return;
        var rows = Array.from(table.querySelectorAll('tr'));
        var csv = rows.map(function(row) {
            return Array.from(row.querySelectorAll('th,td')).map(function(cell) {
                var text = cell.innerText.replace(/\n/g, ' ').replace(/"/g, '""');
                return '"' + text + '"';
            }).join(',');
        }).join('\n');
        var blob = new Blob([csv], { type: 'text/csv' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'all_filters_cheat_sheet.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
    document.getElementById('printTableBtn').addEventListener('click', function() {
        var table = document.querySelector('table');
        if (!table) return;
        var printWindow = window.open('', '', 'height=600,width=1200');
        printWindow.document.write('<html><head><title>Print Table</title>');
        printWindow.document.write('<link rel="stylesheet" href="../../assets/css/base.css">');
        printWindow.document.write('<link rel="stylesheet" href="../../assets/css/admin-layout.css">');
        printWindow.document.write('<link rel="stylesheet" href="../../assets/css/dashboard.css">');
        printWindow.document.write('</head><body >');
        printWindow.document.write(table.outerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    });
    </script>
    <script>
    // Always show all filter tables with empty cells on page load
    function renderFilterTables(filterNames, data, label) {
        let tableHtml = '';
        if (label) {
            tableHtml += '<div style="font-size:1.1rem;color:#374151;margin-bottom:18px;">' + label + '</div>';
        }
        tableHtml += '<div style="max-height:70vh;overflow-y:auto;padding-right:8px;display:grid;grid-template-columns:repeat(4,1fr);gap:18px;">';
        // Only display filters with data for this equipment
        if (data && data.length > 0) {
            data.forEach(row => {
                // Add data-* attributes for edit modal
                tableHtml += '<div class="filter-card" ' +
                    'data-filter_id="' + (row.filter_id || '') + '" ' +
                    'data-filter_date="' + (row.filter_date || '') + '" ' +
                    'data-hours="' + (row.hours || '') + '" ' +
                    'data-part_number="' + (row.part_number || '') + '" ' +
                    'data-make="' + (row.make || '') + '" ' +
                    '>'; // filter-card start
                // Edit button (hidden by default, shown on hover)
                tableHtml += '<button class="edit-filter-btn" title="Edit Filter">Edit</button>';
                tableHtml += '<div style="background:#f3f4f6;font-weight:600;padding:10px 14px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:1.08rem;">' + (row.filter_name || 'Filter') + '</div>';
                tableHtml += '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
                tableHtml += '<tbody>';
                tableHtml += '<tr>' +
                    '<th style="padding:8px 12px;text-align:left;width:40%;color:#374151;">Date</th>' +
                    '<td style="padding:8px 12px;">' + (row.filter_date || 'N/A') + '</td>' +
                '</tr>';
                tableHtml += '<tr>' +
                    '<th style="padding:8px 12px;text-align:left;color:#374151;">Hours</th>' +
                    '<td style="padding:8px 12px;">' + (row.hours || 'N/A') + '</td>' +
                '</tr>';
                tableHtml += '<tr>' +
                    '<th style="padding:8px 12px;text-align:left;color:#374151;">Part Number</th>' +
                    '<td style="padding:8px 12px;">' + (row.part_number || 'N/A') + '</td>' +
                '</tr>';
                tableHtml += '<tr>' +
                    '<th style="padding:8px 12px;text-align:left;color:#374151;">Make</th>' +
                    '<td style="padding:8px 12px;">' + (row.make || 'N/A') + '</td>' +
                '</tr>';
                tableHtml += '</tbody></table>';
                tableHtml += '</div>'; // filter-card end
            });
        } else {
            tableHtml += '<div style="grid-column:span 4;text-align:center;color:#888;font-size:1.1rem;padding:32px 0;">No filters available for this equipment.</div>';
        }
        tableHtml += '</div>';
        document.getElementById('equipmentTables').innerHTML = tableHtml;
        // Attach Edit button event listeners after rendering
        document.querySelectorAll('.filter-card .edit-filter-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var card = btn.closest('.filter-card');
                if (!card) return;
                // Prefill modal fields
                document.getElementById('edit_filter_id').value = card.getAttribute('data-filter_id') || '';
                document.getElementById('edit_filter_date').value = card.getAttribute('data-filter_date') || '';
                document.getElementById('edit_hours').value = card.getAttribute('data-hours') || '';
                document.getElementById('edit_part_number').value = card.getAttribute('data-part_number') || '';
                document.getElementById('edit_make').value = card.getAttribute('data-make') || '';
                document.getElementById('editFilterModal').style.display = 'flex';
            });
        });
    }
    // Edit Filter Modal logic
    document.addEventListener('DOMContentLoaded', function() {
        var editModal = document.getElementById('editFilterModal');
        var cancelEditBtn = document.getElementById('cancelEditFilterBtn');
        var editForm = document.getElementById('editFilterForm');
        if (editModal && cancelEditBtn && editForm) {
            cancelEditBtn.addEventListener('click', function() {
                editModal.style.display = 'none';
                editForm.reset();
            });
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                var formData = new FormData(editForm);
                fetch('../../api/update_filter_info.php', {
                    method: 'POST',
                    body: formData
                })
                .then(resp => resp.json())
                .then(data => {
                    if (data.success) {
                        editModal.style.display = 'none';
                        editForm.reset();
                        // Refresh filter list for selected equipment
                        var selectedBtn = document.querySelector('.equipment-list-btn.selected');
                        if (selectedBtn) {
                            selectedBtn.click();
                        } else {
                            location.reload();
                        }
                    } else {
                        alert('Error updating filter: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(() => {
                    alert('Network error.');
                });
            });
        }
        // Close modal on overlay click (optional UX)
        if (editModal) {
            editModal.addEventListener('click', function(e) {
                if (e.target === editModal) {
                    editModal.style.display = 'none';
                    editForm.reset();
                }
            });
        }
    });

        // On page load, show filters for the first equipment (if any)
        document.addEventListener('DOMContentLoaded', function() {
                var firstBtn = document.querySelector('.equipment-list-btn');
                if (firstBtn) {
                        firstBtn.click();
                }
        });

    // On equipment select, show filter tables with data
    document.querySelectorAll('.equipment-list-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.equipment-list-btn').forEach(function(b) {
                b.style.background = '#f3f4f6';
                b.style.color = '#374151';
                b.style.fontWeight = '500';
            });
            btn.style.background = '#e5e7eb';
            btn.style.color = '#374151';
            btn.style.fontWeight = '700';
            var eqid = btn.getAttribute('data-eqid');
            var label = btn.getAttribute('data-label');
            fetch('../../api/fetch_equipment_filters.php?equipment_id=' + encodeURIComponent(eqid))
              .then(response => response.json())
              .then(data => {
                fetch('fetch_all_filter_names.php')
                  .then(resp => resp.json())
                  .then(filterNames => {
                    renderFilterTables(filterNames, data, label);
                  });
              });
        });
    });
    </script>
</body>
</html>

