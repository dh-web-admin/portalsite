<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

// Auth check
if (!isset($_SESSION['email'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Admin check
$adminEmail = $_SESSION['email'];
$stmt = $conn->prepare("SELECT role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $adminEmail);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    header('Location: ../auth/login.php');
    exit();
}
$row = $res->fetch_assoc();
$actualRole = $row['role'];
$role = $actualRole;
$stmt->close();

// Admin Panel access check (role default or per-user override).
if (!function_exists('can_access') || !can_access((string)$role, 'admin_panel')) {
    header('Location: ../pages/dashboard/');
    exit();
}

// Fetch users
$sql = "SELECT id, name, role FROM users ORDER BY name ASC";
$result = $conn->query($sql);
$users = [];
if ($result) {
    while ($r = $result->fetch_assoc()) $users[] = $r;
}

$allPages = function_exists('portal_all_pages') ? portal_all_pages() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Permissions</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/admin-layout.css">
    <link rel="stylesheet" href="../assets/css/user-list.css">
    <style>
        .perm-help { 
            color: #64748b; 
            margin: 8px 0 24px; 
            font-size: 14px; 
            line-height: 1.5;
        }
        
        .user-table {
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .user-table thead th {
            background: #f8fafc;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.05em;
            color: #475569;
            padding: 12px 16px;
        }
        
        .user-table tbody td {
            padding: 14px 16px;
            font-size: 14px;
        }
        
        .user-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background-color 0.15s ease;
        }
        
        .user-table tbody tr:hover {
            background: #fafbfc;
        }
        
        .perm-btn { 
            padding: 7px 16px;
            font-weight: 600;
            font-size: 13px;
            border-radius: 6px;
            transition: all 0.2s ease;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .perm-btn:hover {
            background: #f8fafc;
            border-color: #3b82f6;
            color: #3b82f6;
        }
        
        .perm-btn svg {
            width: 14px;
            height: 14px;
        }

        .perm-modal { 
            display: none; 
            position: fixed; 
            inset: 0; 
            background: rgba(15, 23, 42, 0.75); 
            backdrop-filter: blur(4px);
            z-index: 9999; 
            align-items: center; 
            justify-content: center; 
            padding: 20px;
            animation: fadeIn 0.2s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(20px); 
            }
            to { 
                opacity: 1;
                transform: translateY(0); 
            }
        }
        
        .perm-modal.is-open { 
            display: flex; 
        }
        
        .perm-dialog { 
            width: 100%; 
            max-width: 800px; 
            background: #ffffff; 
            border-radius: 12px; 
            overflow: hidden; 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: slideUp 0.3s ease;
            display: flex;
            flex-direction: column;
            max-height: 90vh;
        }
        
        .perm-header { 
            padding: 20px 24px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        
        .perm-title { 
            font-weight: 700; 
            margin: 0; 
            font-size: 18px;
            color: #0f172a;
        }
        
        .perm-close { 
            background: transparent; 
            border: 0; 
            color: #94a3b8; 
            font-size: 24px; 
            cursor: pointer; 
            line-height: 1;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .perm-close:hover {
            background: #f1f5f9;
            color: #475569;
        }
        
        .perm-body { 
            padding: 20px 24px; 
            overflow-y: auto;
            flex: 1;
        }
        
        .perm-employee-info {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: #f8fafc;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .perm-employee-label {
            font-weight: 600;
            color: #64748b;
            font-size: 13px;
        }
        
        .perm-employee-value {
            color: #0f172a;
            font-weight: 600;
            font-size: 14px;
        }

        .perm-grid {
            display: grid;
            gap: 1px;
            background: #e2e8f0;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .perm-grid-header {
            display: grid;
            grid-template-columns: 1fr 100px 100px;
            background: #f8fafc;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #475569;
        }
        
        .perm-grid-header > div {
            padding: 12px 16px;
            background: #f8fafc;
        }
        
        .perm-grid-header > div:not(:first-child) {
            text-align: center;
        }
        
        .perm-grid-row {
            display: grid;
            grid-template-columns: 1fr 100px 100px;
            background: #ffffff;
            transition: background-color 0.15s ease;
        }
        
        .perm-grid-row:hover {
            background: #fafbfc;
        }
        
        .perm-grid-row > div {
            padding: 14px 16px;
            background: inherit;
            display: flex;
            align-items: center;
        }
        
        .perm-grid-row > div:not(:first-child) {
            justify-content: center;
        }
        
        .perm-grid-row.select-all-row {
            background: #f8fafc;
            font-weight: 600;
            font-size: 13px;
            color: #475569;
        }
        
        .perm-grid-row.select-all-row:hover {
            background: #f1f5f9;
        }

        .perm-grid-spacer {
            height: 10px;
            background: #e2e8f0;
        }

        /* Visually highlight Admin Panel row */
        .perm-grid-row.is-admin-panel {
            background: #f8fafc;
            box-shadow: inset 3px 0 0 #3b82f6;
        }

        .perm-grid-row.is-admin-panel .perm-page-label {
            font-weight: 700;
        }

        .perm-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #1e293b;
            background: #e2e8f0;
            border: 1px solid #cbd5e1;
            margin-left: 10px;
            flex: 0 0 auto;
        }
        
        .perm-page-label {
            font-weight: 500;
            color: #1e293b;
            font-size: 14px;
        }
        
        .perm-checkbox { 
            width: 18px; 
            height: 18px;
            cursor: pointer;
            accent-color: #3b82f6;
            margin: 0;
        }
        
        .perm-checkbox:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .perm-footer { 
            display: flex; 
            gap: 12px; 
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px; 
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
            flex-shrink: 0;
        }

        /* Admin Panel confirmation */
        .perm-confirm {
            position: absolute;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.55);
            z-index: 10000;
            padding: 16px;
        }

        .perm-confirm.is-open {
            display: flex;
        }

        .perm-confirm-card {
            width: 100%;
            max-width: 560px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 12px 28px rgba(0,0,0,0.14);
            padding: 18px 18px 14px;
        }

        .perm-confirm-title {
            margin: 0 0 10px;
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
        }

        .perm-confirm-text {
            margin: 0;
            font-size: 13px;
            line-height: 1.5;
            color: #334155;
        }

        .perm-confirm-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 14px;
        }

        .perm-confirm-actions .perm-footer-btn {
            min-width: auto;
            padding: 8px 12px;
            font-size: 13px;
        }

        .perm-confirm-allow {
            background: #3b82f6;
            color: #ffffff;
        }

        .perm-confirm-remove {
            background: #ffffff;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .perm-confirm-remove:hover {
            background: #f1f5f9;
        }
        
        .perm-status { 
            color: #64748b; 
            font-size: 13px;
            font-weight: 500;
        }
        
        .perm-footer-actions {
            display: flex;
            gap: 10px;
        }
        
        .perm-footer-btn {
            padding: 9px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 90px;
        }
        
        .perm-footer-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .perm-cancel-btn {
            background: #ffffff;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        
        .perm-cancel-btn:hover:not(:disabled) {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        
        .perm-save-btn {
            background: #3b82f6;
            color: #ffffff;
        }
        
        .perm-save-btn:hover:not(:disabled) {
            background: #2563eb;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }
        
        @media (max-width: 768px) {
            .perm-modal {
                padding: 12px;
            }
            
            .perm-dialog {
                max-height: 95vh;
            }
            
            .perm-grid-header,
            .perm-grid-row {
                grid-template-columns: 1fr 80px 80px;
            }
            
            .perm-grid-header > div,
            .perm-grid-row > div {
                padding: 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body class="admin-page">
    <div class="admin-container">
        <?php include __DIR__ . '/../partials/portalheader.php'; ?>
        <?php // Dev preview mode removed ?>

        <div class="admin-layout">
            <?php include __DIR__ . '/../partials/sidebar.php'; ?>

            <main class="content-area">
                <div class="main-content">
                    <h1>Permissions Management</h1>
                    <div class="perm-help">Control page access and editing privileges for each employee .</div>

                    <table class="user-table" id="permUserTable">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th>Role</th>
                                <th>Manage Access</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr data-user-id="<?php echo htmlspecialchars((string)$u['id']); ?>" data-user-name="<?php echo htmlspecialchars((string)$u['name']); ?>" data-user-role="<?php echo htmlspecialchars((string)$u['role']); ?>">
                                <td><?php echo htmlspecialchars((string)$u['name']); ?></td>
                                <td><?php echo htmlspecialchars((string)$u['role']); ?></td>
                                <td>
                                    <button type="button" class="perm-btn btn-manage-access">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        Edit Permissions
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="perm-modal" id="permModal" aria-hidden="true">
                        <div class="perm-dialog" role="dialog" aria-modal="true" aria-label="Edit permissions">
                            <div class="perm-header">
                                <h2 class="perm-title" id="permModalTitle">Manage Access Permissions</h2>
                                <button type="button" class="perm-close" id="permCloseBtn" aria-label="Close">×</button>
                            </div>
                            <div class="perm-body">
                                <input type="hidden" id="permUserId" value="" />
                                
                                <div class="perm-employee-info">
                                    <span class="perm-employee-label">Employee:</span>
                                    <span class="perm-employee-value" id="permEmployeeName"></span>
                                </div>

                                <div class="perm-grid" id="permPagesGrid">
                                    <div class="perm-grid-header">
                                        <div>Pages</div>
                                        <div>Access</div>
                                        <div>Edit</div>
                                    </div>
                                    <div id="permPagesBody"></div>
                                </div>
                            </div>
                            <div class="perm-footer">
                                <div class="perm-status" id="permStatus"></div>
                                <div class="perm-footer-actions">
                                    <button type="button" class="perm-footer-btn perm-cancel-btn" id="permCancelBtn">Cancel</button>
                                    <button type="button" class="perm-footer-btn perm-save-btn" id="permSaveBtn">Save Changes</button>
                                </div>
                            </div>

                            <div class="perm-confirm" id="permAdminPanelConfirm" aria-hidden="true">
                                <div class="perm-confirm-card" role="dialog" aria-modal="true" aria-label="Admin panel confirmation">
                                    <h3 class="perm-confirm-title">Admin Panel Access</h3>
                                    <p class="perm-confirm-text">You have selected admin panel access to this user. this will give them full control over the site settings. Please un-check this option if this was not intentional.</p>
                                    <div class="perm-confirm-actions">
                                        <button type="button" class="perm-footer-btn perm-confirm-remove" id="permAdminPanelRemoveBtn">Remove admin panel access</button>
                                        <button type="button" class="perm-footer-btn perm-confirm-allow" id="permAdminPanelAllowBtn">Allow admin panel access</button>
                                    </div>
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
    var usersToggle = document.getElementById('usersToggle');
    var usersGroup = document.getElementById('usersGroup');
    if (usersToggle && usersGroup) usersToggle.addEventListener('click', function(){ usersGroup.classList.toggle('open'); });

    var modal = document.getElementById('permModal');
    var closeBtn = document.getElementById('permCloseBtn');
    var cancelBtn = document.getElementById('permCancelBtn');
    var saveBtn = document.getElementById('permSaveBtn');
    var titleEl = document.getElementById('permModalTitle');
    var employeeNameEl = document.getElementById('permEmployeeName');
    var userIdInput = document.getElementById('permUserId');
    var statusEl = document.getElementById('permStatus');
    var pagesBody = document.getElementById('permPagesBody');
    var selectAllAccess = null;
    var selectAllEdit = null;

    var adminConfirm = document.getElementById('permAdminPanelConfirm');
    var adminAllowBtn = document.getElementById('permAdminPanelAllowBtn');
    var adminRemoveBtn = document.getElementById('permAdminPanelRemoveBtn');
    var pendingAdminDecision = null;

    function requestAdminPanelConfirm(cb) {
        pendingAdminDecision = cb;
        if (!adminConfirm) {
            // Safety fallback
            cb(false);
            return;
        }
        adminConfirm.classList.add('is-open');
        adminConfirm.setAttribute('aria-hidden', 'false');
    }

    function resolveAdminPanelConfirm(allow) {
        if (adminConfirm) {
            adminConfirm.classList.remove('is-open');
            adminConfirm.setAttribute('aria-hidden', 'true');
        }
        var cb = pendingAdminDecision;
        pendingAdminDecision = null;
        if (typeof cb === 'function') cb(!!allow);
    }

    if (adminAllowBtn) adminAllowBtn.addEventListener('click', function(){ resolveAdminPanelConfirm(true); });
    if (adminRemoveBtn) adminRemoveBtn.addEventListener('click', function(){ resolveAdminPanelConfirm(false); });
    if (adminConfirm) adminConfirm.addEventListener('click', function(e){ if (e.target === adminConfirm) resolveAdminPanelConfirm(false); });

    var pageKeys = <?php echo json_encode(array_values($allPages)); ?>;

    function prettyLabel(key) {
        if (!key) return '';
        return String(key).replace(/_/g, ' ').replace(/\b\w/g, function(c){ return c.toUpperCase(); });
    }

    function openModal(userId, name) {
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        userIdInput.value = userId;
        employeeNameEl.textContent = name;
        titleEl.textContent = 'Manage Access Permissions';
        if (statusEl) statusEl.textContent = 'Loading...';
        if (pagesBody) pagesBody.innerHTML = '';
        if (selectAllAccess) { selectAllAccess.checked = false; selectAllAccess.indeterminate = false; }
        if (selectAllEdit) { selectAllEdit.checked = false; selectAllEdit.indeterminate = false; }

        var url = '../api/get_user_permissions.php?user_id=' + encodeURIComponent(userId);
        fetch(url, { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(json){
                if (!json || !json.success) {
                    throw new Error((json && (json.error || json.message)) || 'Failed to load permissions');
                }
                if (statusEl) statusEl.textContent = '';
                renderRows(json.pages || []);
                syncSelectAllStates();
            })
            .catch(function(err){
                if (statusEl) statusEl.textContent = err.message || 'Failed to load permissions';
            });
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (statusEl) statusEl.textContent = '';
        if (pagesBody) pagesBody.innerHTML = '';
        userIdInput.value = '';
        selectAllAccess = null;
        selectAllEdit = null;
        pendingAdminDecision = null;
        if (adminConfirm) {
            adminConfirm.classList.remove('is-open');
            adminConfirm.setAttribute('aria-hidden', 'true');
        }
    }

    function syncSelectAllStates(){
        if (!pagesBody) return;
        if (!selectAllAccess) selectAllAccess = document.getElementById('permSelectAllAccess');
        if (!selectAllEdit) selectAllEdit = document.getElementById('permSelectAllEdit');
        var accessBoxes = Array.prototype.slice.call(pagesBody.querySelectorAll('input.perm-access'))
            .filter(function(b){
                var row = b.closest && b.closest('.perm-grid-row');
                return !(row && row.getAttribute('data-page-key') === 'admin_panel');
            });
        var editBoxes = Array.prototype.slice.call(pagesBody.querySelectorAll('input.perm-edit'));

        if (selectAllAccess) {
            var totalA = accessBoxes.length;
            var checkedA = accessBoxes.filter(function(b){ return b.checked; }).length;
            selectAllAccess.checked = totalA > 0 && checkedA === totalA;
            selectAllAccess.indeterminate = checkedA > 0 && checkedA < totalA;
        }

        if (selectAllEdit) {
            var totalE = editBoxes.length;
            var checkedE = editBoxes.filter(function(b){ return b.checked && !b.disabled; }).length;
            var eligibleE = editBoxes.filter(function(b){ return !b.disabled; }).length;
            selectAllEdit.checked = eligibleE > 0 && checkedE === eligibleE;
            selectAllEdit.indeterminate = checkedE > 0 && checkedE < eligibleE;
        }
    }

    function renderRows(pages) {
        if (!pagesBody) return;
        pagesBody.innerHTML = '';

        var byKey = {};
        pages.forEach(function(p){ byKey[p.page_key] = p; });

        // Admin Panel first (if present)
        function renderOne(key) {
            var p = byKey[key] || { page_key: key, can_access: false, can_edit: false };
            
            var row = document.createElement('div');
            row.className = 'perm-grid-row';
            row.setAttribute('data-page-key', key);
            if (key === 'admin_panel') {
                row.classList.add('is-admin-panel');
            }

            var labelDiv = document.createElement('div');
            var label = document.createElement('span');
            label.className = 'perm-page-label';
            label.textContent = prettyLabel(key);
            labelDiv.appendChild(label);

            if (key === 'admin_panel') {
                var badge = document.createElement('span');
                badge.className = 'perm-badge';
                badge.textContent = 'Admin';
                labelDiv.appendChild(badge);
            }

            var accessDiv = document.createElement('div');
            var accessCb = document.createElement('input');
            accessCb.type = 'checkbox';
            accessCb.className = 'perm-checkbox perm-access';
            accessCb.checked = !!p.can_access;
            accessDiv.appendChild(accessCb);

            // Confirmation when enabling Admin Panel access
            if (key === 'admin_panel') {
                accessCb.addEventListener('change', function(){
                    if (accessCb.checked) {
                        // Immediately revert and ask
                        accessCb.checked = false;
                        requestAdminPanelConfirm(function(allow){
                            if (allow) {
                                accessCb.checked = true;
                            }
                            syncSelectAllStates();
                        });
                    } else {
                        syncSelectAllStates();
                    }
                });
            }

            var editDiv = document.createElement('div');
            // Admin Panel is an access-only flag (no Edit checkbox).
            var editCb = null;
            if (key !== 'admin_panel') {
                editCb = document.createElement('input');
                editCb.type = 'checkbox';
                editCb.className = 'perm-checkbox perm-edit';
                editCb.checked = !!p.can_edit;

                if (!accessCb.checked) {
                    editCb.checked = false;
                    editCb.disabled = true;
                }

                accessCb.addEventListener('change', function(){
                    if (!accessCb.checked) {
                        editCb.checked = false;
                        editCb.disabled = true;
                    } else {
                        editCb.disabled = false;
                        // Do not auto-check Edit
                        editCb.checked = false;
                    }
                    syncSelectAllStates();
                });

                editCb.addEventListener('change', function(){
                    syncSelectAllStates();
                });

                editDiv.appendChild(editCb);
            } else {
                // keep the grid aligned
                editDiv.innerHTML = '';
            }

            row.appendChild(labelDiv);
            row.appendChild(accessDiv);
            row.appendChild(editDiv);
            pagesBody.appendChild(row);

            return { accessCb: accessCb, editCb: editCb };
        }

        if (pageKeys.indexOf('admin_panel') !== -1) {
            renderOne('admin_panel');

            var spacer = document.createElement('div');
            spacer.className = 'perm-grid-spacer';
            pagesBody.appendChild(spacer);
        }

        // Insert Select All row under Admin Panel
        var selectRow = document.createElement('div');
        selectRow.className = 'perm-grid-row select-all-row';

        var saLabel = document.createElement('div');
        saLabel.textContent = 'Select All';
        selectRow.appendChild(saLabel);

        var saAccessDiv = document.createElement('div');
        var saAccess = document.createElement('input');
        saAccess.type = 'checkbox';
        saAccess.className = 'perm-checkbox';
        saAccess.id = 'permSelectAllAccess';
        saAccess.setAttribute('aria-label', 'Select all access');
        saAccessDiv.appendChild(saAccess);
        selectRow.appendChild(saAccessDiv);

        var saEditDiv = document.createElement('div');
        var saEdit = document.createElement('input');
        saEdit.type = 'checkbox';
        saEdit.className = 'perm-checkbox';
        saEdit.id = 'permSelectAllEdit';
        saEdit.setAttribute('aria-label', 'Select all edit');
        saEditDiv.appendChild(saEdit);
        selectRow.appendChild(saEditDiv);

        pagesBody.appendChild(selectRow);

        // Wire select-all handlers (per render to avoid duplicate listeners)
        selectAllAccess = saAccess;
        selectAllEdit = saEdit;

        selectAllAccess.addEventListener('change', function(){
            if (!pagesBody) return;
            var accessBoxes = pagesBody.querySelectorAll('input.perm-access');
            var editBoxes = pagesBody.querySelectorAll('input.perm-edit');

            // Select All must never toggle Admin Panel.
            accessBoxes.forEach(function(b){
                var row = b.closest && b.closest('.perm-grid-row');
                var pageKey = row ? row.getAttribute('data-page-key') : '';
                if (pageKey === 'admin_panel') return;
                b.checked = !!selectAllAccess.checked;
            });

            editBoxes.forEach(function(eb){
                var pageKey = (eb.closest && eb.closest('.perm-grid-row') && eb.closest('.perm-grid-row').getAttribute('data-page-key')) || '';
                if (!selectAllAccess.checked) {
                    eb.checked = false;
                    eb.disabled = true;
                } else {
                    if (pageKey !== 'admin_panel') eb.disabled = false;
                }
            });
            syncSelectAllStates();
        });

        selectAllEdit.addEventListener('change', function(){
            if (!pagesBody) return;
            var accessBoxes = pagesBody.querySelectorAll('input.perm-access');
            var editBoxes = pagesBody.querySelectorAll('input.perm-edit');

            if (selectAllEdit.checked) {
                accessBoxes.forEach(function(ab){
                    var row = ab.closest && ab.closest('.perm-grid-row');
                    var pageKey = row ? row.getAttribute('data-page-key') : '';
                    if (pageKey === 'admin_panel') return;
                    ab.checked = true;
                });
                editBoxes.forEach(function(eb){
                    var row = eb.closest && eb.closest('.perm-grid-row');
                    var pageKey = row ? row.getAttribute('data-page-key') : '';
                    if (pageKey === 'admin_panel') return;
                    eb.disabled = false;
                    eb.checked = true;
                });
            } else {
                editBoxes.forEach(function(eb){ if (!eb.disabled) eb.checked = false; });
            }

            syncSelectAllStates();
        });

        // Render remaining pages (excluding admin_panel)
        pageKeys.forEach(function(key){
            if (key === 'admin_panel') return;
            renderOne(key);
        });
    }

    document.getElementById('permUserTable').addEventListener('click', function(e){
        var btn = e.target.closest('.btn-manage-access');
        if (!btn) return;
        var row = btn.closest('tr[data-user-id]');
        if (!row) return;
        openModal(row.getAttribute('data-user-id'), row.getAttribute('data-user-name') || '');
    });

    // Select-all handlers are attached when rows are rendered.

    function gatherPayload() {
        var rows = pagesBody ? pagesBody.querySelectorAll('.perm-grid-row[data-page-key]') : [];
        var out = [];
        rows.forEach(function(r){
            var key = r.getAttribute('data-page-key');
            var access = r.querySelector('input.perm-access');
            var edit = r.querySelector('input.perm-edit');
            out.push({
                page_key: key,
                can_access: access ? !!access.checked : false,
                can_edit: edit ? (!!edit.checked && !edit.disabled) : false
            });
        });
        return out;
    }

    if (saveBtn) saveBtn.addEventListener('click', function(){
        var userId = userIdInput.value;
        if (!userId) return;
        if (statusEl) statusEl.textContent = 'Saving...';
        saveBtn.disabled = true;

        var fd = new FormData();
        fd.append('user_id', userId);
        fd.append('permissions', JSON.stringify(gatherPayload()));

        var url = '../api/save_user_permissions.php';
        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(json){
                if (!json || !json.success) {
                    throw new Error((json && (json.error || json.message)) || 'Save failed');
                }
                if (statusEl) statusEl.textContent = 'Saved successfully';
                setTimeout(function(){ closeModal(); }, 500);
            })
            .catch(function(err){
                if (statusEl) statusEl.textContent = err.message || 'Save failed';
            })
            .finally(function(){
                saveBtn.disabled = false;
            });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (modal) modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) closeModal(); });
})();
</script>

<script src="../assets/js/mobile-menu.js"></script>
<script src="../assets/js/logout-confirm.js"></script>
</body>
</html>