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
    .filter-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .filter-card .edit-filter-btn {
        display: none;
        position: absolute;
        top: 10px;
        right: 10px;
        background: #9ca3af;
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: 6px 16px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(156, 163, 175, 0.3);
        z-index: 2;
        transition: background 0.15s, transform 0.1s;
    }
    .filter-card .edit-filter-btn:hover {
        background: #6b7280;
        transform: translateY(-1px);
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
        background: rgba(0,0,0,0.4);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    #editFilterModal .modal-content {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        padding: 32px 28px;
        min-width: 400px;
        max-width: 96vw;
    }
    </style>
</head>
<body class="admin-page">
    <!-- Edit Filter Modal -->
    <div id="editFilterModal">
        <div class="modal-content">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                <h3 id="editModalFilterName" style="font-size:1.3rem; font-weight:700; color:#374151;margin:0;">Filter Name</h3>
                <button type="button" id="editFilterNameBtn" style="background:#f3f4f6;border:none;border-radius:6px;padding:6px 10px;cursor:pointer;display:flex;align-items:center;gap:6px;font-size:14px;font-weight:600;color:#374151;" title="Edit Filter Name">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Edit
                </button>
            </div>
            <form id="editFilterForm">
                <input type="hidden" name="filter_id" id="edit_filter_id">
                <div id="filterNameEditDiv" style="display:none;margin-bottom:12px;">
                    <label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Filter Name</label>
                    <input type="text" name="filter_name" id="edit_filter_name" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Filter Date</label>
                    <input type="date" name="filter_date" id="edit_filter_date" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Filter Hours</label>
                    <input type="number" step="0.1" name="hours" id="edit_hours" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Filter Part Number</label>
                    <input type="text" name="part_number" id="edit_part_number" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;">
                </div>
                <div style="margin-bottom:18px;">
                    <label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Filter Make</label>
                    <input type="text" name="make" id="edit_make" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;">
                </div>
                <div style="display:flex;gap:16px;justify-content:flex-end;">
                    <button type="button" id="cancelEditFilterBtn" style="background:#e5e7eb;color:#374151;border:none;border-radius:6px;padding:10px 24px;font-size:15px;font-weight:600;cursor:pointer;transition:background 0.15s;">Cancel</button>
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
                <div class="main-content" style="display: flex; flex-direction: row; gap: 32px; align-items: flex-start;">
                    <div style="flex: 0 0 340px; max-width: 340px; min-width: 240px; background: #f8fafc; border-radius: 14px; box-shadow: 0 2px 8px #0001; padding: 24px 12px 24px 18px; height: 80vh; overflow-y: auto;">
                        <h2 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 18px; color: #374151;">Select an equipment.</h2>
                        <ul id="equipmentList" style="list-style: none; padding: 0; margin: 0;">
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
                        <div id="addFilterModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;">
                            <div style="background:#fff; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,0.2); padding:32px 28px; min-width:400px; max-width:96vw;">
                                <h3 style="margin-bottom:18px; font-size:1.18rem; font-weight:700; color:#374151;">Add Filter</h3>
                                <form id="addFilterForm">
                                    <div style="margin-bottom:12px;">
                                        <label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Filter Name</label>
                                        <input type="text" name="filter_name" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;">
                                    </div>
                                    <div style="margin-bottom:12px;">
                                        <label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Filter Date</label>
                                        <input type="date" name="filter_date" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;">
                                    </div>
                                    <div style="margin-bottom:12px;">
                                        <label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Filter Hours</label>
                                        <input type="number" step="0.1" name="hours" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;">
                                    </div>
                                    <div style="margin-bottom:12px;">
                                        <label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Filter Part Number</label>
                                        <input type="text" name="part_number" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;">
                                    </div>
                                    <div style="margin-bottom:18px;">
                                        <label style="font-weight:600;color:#374151;margin-bottom:4px;display:block;">Filter Make</label>
                                        <input type="text" name="make" style="width:100%;padding:10px 12px;border-radius:6px;border:1px solid #d1d5db;font-size:15px;">
                                    </div>
                                    <div style="display:flex;gap:16px;justify-content:flex-end;">
                                        <button type="button" id="cancelAddFilterBtn" style="background:#e5e7eb;color:#374151;border:none;border-radius:6px;padding:10px 24px;font-size:15px;font-weight:600;cursor:pointer;transition:background 0.15s;">Cancel</button>
                                        <button type="submit" style="background:#43b77a;color:#fff;border:none;border-radius:6px;padding:10px 24px;font-size:15px;font-weight:600;cursor:pointer;transition:background 0.15s;">Save</button>
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
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            modal.style.display = 'none';
                            form.reset();
                        }
                    });
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
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
                                // Refresh the current equipment view
                                if (selectedBtn) {
                                    selectedBtn.click();
                                }
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

            // Edit Filter Modal logic
            document.addEventListener('DOMContentLoaded', function() {
                var editModal = document.getElementById('editFilterModal');
                var cancelEditBtn = document.getElementById('cancelEditFilterBtn');
                var editForm = document.getElementById('editFilterForm');
                var editFilterNameBtn = document.getElementById('editFilterNameBtn');
                var filterNameEditDiv = document.getElementById('filterNameEditDiv');
                var editModalFilterName = document.getElementById('editModalFilterName');
                
                if (editFilterNameBtn && filterNameEditDiv) {
                    editFilterNameBtn.addEventListener('click', function() {
                        if (filterNameEditDiv.style.display === 'none') {
                            filterNameEditDiv.style.display = 'block';
                        } else {
                            filterNameEditDiv.style.display = 'none';
                        }
                    });
                }
                
                if (editModal && cancelEditBtn && editForm) {
                    cancelEditBtn.addEventListener('click', function() {
                        editModal.style.display = 'none';
                        editForm.reset();
                        filterNameEditDiv.style.display = 'none';
                    });
                    editModal.addEventListener('click', function(e) {
                        if (e.target === editModal) {
                            editModal.style.display = 'none';
                            editForm.reset();
                            filterNameEditDiv.style.display = 'none';
                        }
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
                                alert('Filter updated successfully!');
                                editModal.style.display = 'none';
                                editForm.reset();
                                filterNameEditDiv.style.display = 'none';
                                // Refresh filter list for selected equipment
                                var selectedBtn = document.querySelector('.equipment-list-btn.selected');
                                if (selectedBtn) {
                                    selectedBtn.click();
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
            });

            // Highlight selected equipment and load details
            document.querySelectorAll('.equipment-list-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.equipment-list-btn').forEach(function(b) {
                        b.classList.remove('selected');
                        b.style.background = '#fff';
                        b.style.color = '#374151';
                        b.style.fontWeight = '500';
                    });
                    btn.classList.add('selected');
                    btn.style.background = '#e5e7eb';
                    btn.style.color = '#374151';
                    btn.style.fontWeight = '700';
                    
                    var eqid = btn.getAttribute('data-eqid');
                    var label = btn.getAttribute('data-label');
                    
                    document.getElementById('equipmentDetailsPlaceholder').style.display = 'none';
                    
                    // AJAX fetch filters info for this equipment
                    fetch('../../api/fetch_equipment_filters.php?equipment_id=' + encodeURIComponent(eqid))
                        .then(response => response.json())
                        .then(data => {
                            let tableHtml = '<div style="font-size:1.1rem;color:#374151;margin-bottom:18px;font-weight:600;">' + label + '</div>';
                            tableHtml += '<div style="max-height:70vh;overflow-y:auto;padding-right:8px;">';
                            
                            if (data && data.length > 0) {
                                tableHtml += '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;">';
                                data.forEach(row => {
                                    tableHtml += '<div class="filter-card" ' +
                                        'data-filter_id="' + (row.filter_id || '') + '" ' +
                                        'data-filter_name="' + (row.filter_name || 'Filter') + '" ' +
                                        'data-filter_date="' + (row.filter_date || '') + '" ' +
                                        'data-hours="' + (row.hours || '') + '" ' +
                                        'data-part_number="' + (row.part_number || '') + '" ' +
                                        'data-make="' + (row.make || '') + '" ' +
                                        'style="min-width:280px;">';
                                    
                                    // Edit button (hidden by default, shown on hover)
                                    tableHtml += '<button class="edit-filter-btn" title="Edit Filter">Edit</button>';
                                    
                                    tableHtml += '<div style="background:#f3f4f6;font-weight:600;padding:14px 18px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:1.15rem;">' + (row.filter_name || 'Filter') + '</div>';
                                    tableHtml += '<table style="width:100%;border-collapse:collapse;font-size:15px;">';
                                    tableHtml += '<tbody>';
                                    tableHtml += '<tr>' +
                                        '<th style="padding:12px 20px;text-align:left;width:45%;color:#374151;font-weight:600;">Date</th>' +
                                        '<td style="padding:12px 16px;">' + (row.filter_date || '<span style="color:#999;font-style:italic;">N/A</span>') + '</td>' +
                                    '</tr>';
                                    tableHtml += '<tr>' +
                                        '<th style="padding:12px 20px;text-align:left;color:#374151;font-weight:600;">Hours</th>' +
                                        '<td style="padding:12px 16px;">' + (row.hours || '<span style="color:#999;font-style:italic;">N/A</span>') + '</td>' +
                                    '</tr>';
                                    tableHtml += '<tr>' +
                                        '<th style="padding:12px 20px;text-align:left;color:#374151;font-weight:600;">Part Number</th>' +
                                        '<td style="padding:12px 16px;">' + (row.part_number || '<span style="color:#999;font-style:italic;">N/A</span>') + '</td>' +
                                    '</tr>';
                                    tableHtml += '<tr>' +
                                        '<th style="padding:12px 20px;text-align:left;color:#374151;font-weight:600;">Make</th>' +
                                        '<td style="padding:12px 16px;">' + (row.make || '<span style="color:#999;font-style:italic;">N/A</span>') + '</td>' +
                                    '</tr>';
                                    tableHtml += '</tbody></table>';
                                    tableHtml += '</div>'; // filter-card end
                                });
                                tableHtml += '</div>';
                            } else {
                                tableHtml += '<div style="text-align:center;color:#888;font-size:1.1rem;padding:32px 0;">No filters available for this equipment.</div>';
                            }
                            
                            tableHtml += '</div>';
                            document.getElementById('equipmentTables').innerHTML = tableHtml;
                            
                            // Attach Edit button event listeners after rendering
                            document.querySelectorAll('.filter-card .edit-filter-btn').forEach(function(editBtn) {
                                editBtn.addEventListener('click', function(e) {
                                    e.stopPropagation();
                                    var card = editBtn.closest('.filter-card');
                                    if (!card) return;
                                    
                                    // Update modal title with filter name
                                    var filterName = card.getAttribute('data-filter_name') || 'Filter';
                                    document.getElementById('editModalFilterName').textContent = filterName;
                                    
                                    // Prefill modal fields
                                    document.getElementById('edit_filter_id').value = card.getAttribute('data-filter_id') || '';
                                    document.getElementById('edit_filter_name').value = filterName;
                                    document.getElementById('edit_filter_date').value = card.getAttribute('data-filter_date') || '';
                                    document.getElementById('edit_hours').value = card.getAttribute('data-hours') || '';
                                    document.getElementById('edit_part_number').value = card.getAttribute('data-part_number') || '';
                                    document.getElementById('edit_make').value = card.getAttribute('data-make') || '';
                                    
                                    // Hide filter name edit div by default
                                    document.getElementById('filterNameEditDiv').style.display = 'none';
                                    
                                    document.getElementById('editFilterModal').style.display = 'flex';
                                });
                            });
                        });
                });
            });

            // On page load, show filters for the first equipment (if any)
            document.addEventListener('DOMContentLoaded', function() {
                var firstBtn = document.querySelector('.equipment-list-btn');
                if (firstBtn) {
                    firstBtn.click();
                }
            });
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
</body>
</html>