<?php
require_once __DIR__ . '/../session_init.php';
require_once '../config/config.php';
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

$sql = "SELECT id, name, email, role FROM users ORDER BY id DESC";
$result = $conn->query($sql);
$users = [];
if ($result) {
    while ($r = $result->fetch_assoc()) $users[] = $r;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User List</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/admin-layout.css">
    <link rel="stylesheet" href="../assets/css/user-list.css">
</head>
<body class="admin-page">
    <div class="admin-container">
    <?php include __DIR__ . '/../partials/portalheader.php'; ?>
    <?php // Dev preview mode removed ?>

        <div class="admin-layout">
            <?php include __DIR__ . '/../partials/sidebar.php'; ?>

            <main class="content-area">
                <div class="main-content">
                    <h1>User List</h1>
                    <div class="search-box">
                        <input type="search" id="userSearch" placeholder="Search by name or email" style="padding:8px; width:100%; max-width:360px">
                    </div>

                    <table class="user-table" id="userTable">
                        <thead>
                            <tr>
                                <th class="col-id">ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr data-user-id="<?php echo htmlspecialchars($u['id']); ?>">
                                <td class="col-id"><?php echo htmlspecialchars($u['id']); ?></td>
                                <td class="col-name"><span class="view-name"><?php echo htmlspecialchars($u['name']); ?></span></td>
                                <td class="col-email"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td class="col-role">
                                    <span class="view-role"><?php echo htmlspecialchars($u['role']); ?></span>
                                </td>
                                <td>
                                    <div class="user-actions">
                                        <button class="nav-btn btn-edit-all" data-user-id="<?php echo htmlspecialchars($u['id']); ?>" data-user-email="<?php echo htmlspecialchars($u['email']); ?>" data-user-name="<?php echo htmlspecialchars($u['name']); ?>" data-user-role="<?php echo htmlspecialchars($u['role']); ?>" style="padding:8px 12px; background:#667eea; color:#fff; border:none; border-radius:6px;">Edit</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

                <!-- Unified Edit Modal -->
                <div id="userEditModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:2500; align-items:center; justify-content:center;">
                    <div style="background:#fff; width:720px; max-width:95%; border-radius:10px; padding:20px; box-shadow:0 8px 30px rgba(0,0,0,0.25); position:relative;">
                        <button id="modalCloseBtn" style="position:absolute; right:12px; top:12px; background:none;border:none;font-size:18px;cursor:pointer;">✕</button>
                        <h2 id="modalTitle">Edit User</h2>
                        <div style="display:flex; gap:18px;">
                            <div style="flex:1;">
                                <div style="margin-bottom:10px;"><strong>Name</strong><div id="modalName">&nbsp;</div></div>
                                <div style="margin-bottom:10px;">
                                    <strong>Email</strong>
                                    <div id="modalEmail">&nbsp;</div>
                                    <div id="modalEmailActions" style="margin-top:6px;">
                                        <input id="modalNewEmailInput" type="email" placeholder="Enter email" style="display:none; padding:8px; border-radius:6px; border:1px solid #ddd; width:70%; margin-right:8px;">
                                        <button id="modalConfirmAddEmail" class="nav-btn" style="display:none; padding:8px 12px; border-radius:6px; background:#059669; color:#fff; border:none; font-weight:700;">Add</button>
                                        <button id="modalAddEmailBtn" class="nav-btn" style="display:none; padding:8px 12px; border-radius:6px; background:#3b82f6; color:#fff; border:none; font-weight:700;">Add Email</button>
                                    </div>
                                </div>
                                <div style="margin-bottom:10px;"><strong>Role</strong>
                                    <select id="modalRole" style="width:100%; padding:8px; border-radius:6px; border:1px solid #ddd;">
                                    <?php
                                        $roles = ['admin','projectmanager','estimator','accounting','superintendent','foreman','mechanic','operator','laborer','developer','data_entry'];
                                        foreach ($roles as $r) {
                                            echo "<option value=\"$r\">$r</option>";
                                        }
                                    ?>
                                    </select>
                                </div>
                            </div>
                            <div style="flex:1;">
                                <div style="margin-bottom:10px;">
                                    <button id="modalShowResetBtn" type="button" class="nav-btn" style="background:#e5e7eb;color:#111;padding:8px 12px;border-radius:6px;font-weight:700;">Reset Password</button>
                                    <div id="modalPasswordSection" style="display:none;margin-top:10px;">
                                        <input id="modalNewPassword" type="password" placeholder="New password" style="width:100%; padding:8px; border-radius:6px; border:1px solid #ddd; margin-top:6px;">
                                        <input id="modalConfirmPassword" type="password" placeholder="Confirm password" style="width:100%; padding:8px; border-radius:6px; border:1px solid #ddd; margin-top:8px;">
                                        <div style="margin-top:8px; display:flex; gap:8px;"><button id="modalGenPassword" class="nav-btn" type="button" style="padding:8px 10px; border-radius:6px; border:1px solid #d1d5db; background:#fff; cursor:pointer;">Generate</button><button id="modalCopyPassword" class="nav-btn" type="button" style="padding:8px 10px; border-radius:6px; border:1px solid #d1d5db; background:#fff; cursor:pointer;">Copy</button></div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                        <div class="modal-actions" style="margin-top:18px; display:flex; gap:12px; justify-content:flex-end;">
                            <button id="modalSaveBtn" class="modal-save-btn" style="min-width:160px;">Save Changes</button>
                            <button id="modalRemoveBtn" class="nav-btn" style="background:#f44336; color:#fff; border:none;">Remove User</button>
                        </div>

                        <div id="modalStatus" style="margin-top:10px;color:#10b981;font-weight:600;display:none"></div>
                    </div>
                </div>

    <script>
    (function(){
        var usersToggle = document.getElementById('usersToggle');
        var usersGroup = document.getElementById('usersGroup');
        if (usersToggle && usersGroup) usersToggle.addEventListener('click', function(){ usersGroup.classList.toggle('open'); });

        var search = document.getElementById('userSearch');
        var table = document.getElementById('userTable');
        if (search) {
            search.addEventListener('input', function(){
                var q = search.value.toLowerCase();
                var rows = table.querySelectorAll('tbody tr');
                rows.forEach(function(r){
                    // Skip popup rows or any rows without the expected cells
                    var nameCell = r.querySelector('td.col-name');
                    var emailCell = r.querySelector('td.col-email');
                    if (!nameCell || !emailCell) return; // leave as-is
                    var name = nameCell.textContent.toLowerCase();
                    var email = emailCell.textContent.toLowerCase();
                    r.style.display = (name.indexOf(q) !== -1 || email.indexOf(q) !== -1) ? '' : 'none';
                });
            });
        }

        // Toast notification helper
        function toasts(message, isError) {
            var t = document.createElement('div');
            t.textContent = message;
            t.style.position = 'fixed';
            t.style.right = '12px';
            t.style.bottom = '12px';
            t.style.padding = '8px 12px';
            t.style.background = isError ? '#f44336' : '#4caf50';
            t.style.color = '#fff';
            t.style.borderRadius = '4px';
            document.body.appendChild(t);
            setTimeout(function(){ t.remove(); }, 3000);
        }

        // Unified Edit modal handlers
        var editModal = document.getElementById('userEditModal');
        var modalCloseBtn = document.getElementById('modalCloseBtn');
        var modalName = document.getElementById('modalName');
        var modalEmail = document.getElementById('modalEmail');
        var modalAddEmailBtn = document.getElementById('modalAddEmailBtn');
        var modalNewEmailInput = document.getElementById('modalNewEmailInput');
        var modalConfirmAddEmail = document.getElementById('modalConfirmAddEmail');
        var modalRole = document.getElementById('modalRole');
        var modalNewPassword = document.getElementById('modalNewPassword');
        var modalConfirmPassword = document.getElementById('modalConfirmPassword');
        var modalGenPassword = document.getElementById('modalGenPassword');
        var modalCopyPassword = document.getElementById('modalCopyPassword');
        var modalShowResetBtn = document.getElementById('modalShowResetBtn');
        var modalPasswordSection = document.getElementById('modalPasswordSection');
        var modalSaveBtn = document.getElementById('modalSaveBtn');
        var modalRemoveBtn = document.getElementById('modalRemoveBtn');
        var modalStatus = document.getElementById('modalStatus');

        function openEditModal(opts) {
            modalName.textContent = opts.name || '';
            var emailVal = opts.email || '';
            modalEmail.textContent = emailVal || '';
            // If no email present, show Add Email button and hide password reset
            if (!emailVal) {
                if (modalAddEmailBtn) modalAddEmailBtn.style.display = 'inline-block';
                if (modalShowResetBtn) modalShowResetBtn.style.display = 'none';
                if (modalPasswordSection) modalPasswordSection.style.display = 'none';
            } else {
                if (modalAddEmailBtn) modalAddEmailBtn.style.display = 'none';
                if (modalShowResetBtn) modalShowResetBtn.style.display = '';
            }
            modalRole.value = opts.role || '';
            modalNewPassword.value = '';
            modalConfirmPassword.value = '';
            modalStatus.style.display = 'none';
            editModal.style.display = 'flex';
            editModal.dataset.userId = opts.id || '';
        }

        function closeEditModal() { editModal.style.display = 'none'; }

        document.addEventListener('click', function(e){
            var btn = e.target.closest('.btn-edit-all');
            if (btn) {
                var id = btn.getAttribute('data-user-id');
                var email = btn.getAttribute('data-user-email');
                var name = btn.getAttribute('data-user-name');
                var role = btn.getAttribute('data-user-role');
                openEditModal({ id: id, email: email, name: name, role: role });
            }
        });

        if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeEditModal);

        // Show/hide the password inputs when Reset Password is clicked
        if (modalAddEmailBtn) modalAddEmailBtn.addEventListener('click', function(){
            // reveal the inline email input and confirm button
            modalNewEmailInput.style.display = 'inline-block';
            modalConfirmAddEmail.style.display = 'inline-block';
            modalAddEmailBtn.style.display = 'none';
            try{ modalNewEmailInput.focus(); }catch(e){}
        });

        if (modalConfirmAddEmail) modalConfirmAddEmail.addEventListener('click', function(){
            var newEmail = (modalNewEmailInput.value || '').trim();
            if (!newEmail || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(newEmail)) { toasts('Enter a valid email', true); return; }
            var form = new FormData();
            form.append('id', editModal.dataset.userId || '');
            form.append('email', newEmail);
            // ensure the API receives a valid role (update_user.php requires role validation)
            try { form.append('role', (modalRole && modalRole.value) ? modalRole.value : ''); } catch(e) {}
            fetch('../api/update_user.php', { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(json){ if (json.success) { modalEmail.textContent = newEmail; modalNewEmailInput.style.display = 'none'; modalConfirmAddEmail.style.display = 'none'; if (modalShowResetBtn) modalShowResetBtn.style.display = ''; toasts('Email added'); } else { toasts(json.error || 'Failed to add email', true); } }).catch(function(e){ console.error(e); toasts('Request failed', true); });
        });

        if (modalShowResetBtn) {
            modalShowResetBtn.addEventListener('click', function(){
                if (!modalPasswordSection) return;
                var visible = modalPasswordSection.style.display !== 'none';
                modalPasswordSection.style.display = visible ? 'none' : 'block';
                modalShowResetBtn.textContent = visible ? 'Reset Password' : 'Cancel Reset';
                if (!visible && modalNewPassword) try{ modalNewPassword.focus(); } catch(e){}
            });
        }

        // Password generator
        function generatePassword(len){ len = len||12; var upper='ABCDEFGHIJKLMNOPQRSTUVWXYZ', lower='abcdefghijklmnopqrstuvwxyz', nums='0123456789', specials='!@#$%^&*()'; var all = upper+lower+nums+specials; var pwd=''; pwd += upper[Math.floor(Math.random()*upper.length)]; pwd += nums[Math.floor(Math.random()*nums.length)]; pwd += specials[Math.floor(Math.random()*specials.length)]; for (var i=pwd.length;i<len;i++) pwd += all[Math.floor(Math.random()*all.length)]; return pwd.split('').sort(function(){return 0.5-Math.random();}).join(''); }
        if (modalGenPassword) modalGenPassword.addEventListener('click', function(){ var p = generatePassword(12); modalNewPassword.value = p; modalConfirmPassword.value = p; });
        if (modalCopyPassword) modalCopyPassword.addEventListener('click', function(){ var v = modalNewPassword.value||''; if (!v) { toasts('No password to copy', true); return; } navigator.clipboard && navigator.clipboard.writeText ? navigator.clipboard.writeText(v).then(function(){ toasts('Copied') }) : (function(){ var ta=document.createElement('textarea'); ta.value=v; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); toasts('Copied'); })(); });

        // Save changes (role +/- password)
        if (modalSaveBtn) modalSaveBtn.addEventListener('click', function(){
            var id = editModal.dataset.userId; if (!id) return;
            var roleVal = modalRole.value;
            var newPass = modalNewPassword.value || '';
            var confirm = modalConfirmPassword.value || '';
            if (newPass || confirm) {
                if (newPass.length < 8) { toasts('Password must be at least 8 characters', true); return; }
                if (!/[0-9]/.test(newPass) || !/[A-Z]/.test(newPass) || !/[!@#$%^&*()_+\-=[\]{};:'"\\|,.<>\/\?]/.test(newPass)) { toasts('Password must include number, uppercase, and special char', true); return; }
                if (newPass !== confirm) { toasts('Passwords do not match', true); return; }
            }
            var form = new FormData(); form.append('id', id); form.append('role', roleVal); if (newPass) form.append('password', newPass);
            fetch('../api/update_user.php', { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(json){ if (json.success) { modalStatus.style.display='block'; modalStatus.style.color='#10b981'; modalStatus.textContent = 'Saved'; toasts('User updated'); setTimeout(function(){ window.location.reload(); }, 700); } else { modalStatus.style.display='block'; modalStatus.style.color='#f44336'; modalStatus.textContent = json.error || 'Update failed'; } }).catch(function(e){ console.error(e); modalStatus.style.display='block'; modalStatus.style.color='#f44336'; modalStatus.textContent = 'Update failed'; });
        });

        // Remove user
        if (modalRemoveBtn) modalRemoveBtn.addEventListener('click', function(){
            if (!confirm('Delete this user?')) return;
            var email = modalEmail.textContent || '';
            var id = editModal.dataset.userId || '';
            var form = new FormData();
            if (id) form.append('id', id); else form.append('email', email);
            fetch('../api/remove_user.php', { method: 'POST', body: form, credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(json){
                if (json && json.success) {
                    toasts('User removed');
                    closeEditModal();
                    setTimeout(function(){ window.location.reload(); }, 700);
                } else {
                    toasts(json && json.error ? json.error : 'Remove failed', true);
                    console.log('Remove response:', json);
                }
            }).catch(function(e){ console.error(e); toasts('Remove failed', true); });
        });

        var openPopup = null; // current popup row element
        table.querySelectorAll('tbody tr').forEach(function(row){
            var btnEdit = row.querySelector('.btn-edit');
            var btnResetPass = row.querySelector('.btn-reset-pass');

            // Create popup HTML fragment for role editing
            var popupTemplate = function(currentRoleHtml){
                var tpl = document.createElement('tr');
                tpl.className = 'role-popup-row';
                // colspan should cover all data columns including hidden ID
                tpl.innerHTML = '<td colspan="5" style="padding:12px 10px;"><div style="display:flex; align-items:center; gap:12px;">' +
                    '<select class="popup-role-select">' + (function(){
                        var opts = '';
                        var roles = ['admin','projectmanager','estimator','accounting','superintendent','foreman','mechanic','operator','laborer','developer','data_entry'];
                        roles.forEach(function(r){
                            opts += '<option value="'+r+'">'+r+'</option>';
                        });
                        return opts;
                    })() +
                    '</select>' +
                    '<div style="margin-left:12px; display:flex; gap:8px;">' +
                    '<button class="nav-btn popup-apply" style="background:#4caf50; color:white; border:none; padding:8px 16px; border-radius:6px; font-weight:600; cursor:pointer; transition:background 0.2s;">Apply</button>' +
                    '<button class="nav-btn popup-cancel" style="background:#f44336; color:white; border:none; padding:8px 16px; border-radius:6px; font-weight:600; cursor:pointer; transition:background 0.2s;">Cancel</button>' +
                    '</div></div></td>';
                return tpl;
            };

            // Create password reset popup
            var passwordPopupTemplate = function(){
                var tpl = document.createElement('tr');
                tpl.className = 'password-popup-row';
                tpl.innerHTML = '<td colspan="5" style="padding:12px 10px; background:#f8f9fa;">' +
                                        '<div style="display:flex; flex-direction:column; gap:10px; max-width:520px;">' +
                                        '<strong>Reset Password</strong>' +
                                        '<div style="display:flex; gap:8px; align-items:center;">' +
                                        '<div style="flex:1; position:relative;">' +
                                        '<input type="password" class="popup-new-password" placeholder="New password" style="padding:8px; padding-right:100px; border:1px solid #ddd; border-radius:4px; width:100%; box-sizing:border-box;">' +
                                        '<button type="button" class="toggle-new-password" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:4px 8px; font-size:12px; font-weight:600; color:#667eea;">Show</button>' +
                                        '</div>' +
                                        '<div style="display:flex; gap:8px;">' +
                                            '<button type="button" class="btn-generate-password" style="padding:8px 10px; border-radius:6px; border:1px solid #d1d5db; background:#fff; cursor:pointer;">Generate</button>' +
                                            '<button type="button" class="btn-copy-password" style="padding:8px 10px; border-radius:6px; border:1px solid #d1d5db; background:#fff; cursor:pointer;">Copy</button>' +
                                        '</div>' +
                                        '</div>' +
                                        '<small style="color:#666; font-size:12px; margin-top:-5px;">At least 8 chars, 1 number, 1 uppercase, 1 special character</small>' +
                                        '<div style="display:flex; gap:8px; align-items:center;">' +
                                        '<div style="flex:1; position:relative;">' +
                                        '<input type="password" class="popup-confirm-password" placeholder="Confirm password" style="padding:8px; padding-right:100px; border:1px solid #ddd; border-radius:4px; width:100%; box-sizing:border-box;">' +
                                        '<button type="button" class="toggle-confirm-password" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:4px 8px; font-size:12px; font-weight:600; color:#667eea;">Show</button>' +
                                        '</div>' +
                                        '<div style="width:120px; text-align:right; color:#6b7280; font-size:12px;">&nbsp;</div>' +
                                        '</div>' +
                                        '<div style="display:flex; gap:12px; margin-top:5px;">' +
                                        '<button class="popup-save-password" style="flex:1; padding:10px; background:#4caf50; color:white; border:none; border-radius:6px; font-weight:600; cursor:pointer; transition:background 0.2s;">Save</button>' +
                                        '<button class="popup-cancel-password" style="flex:1; padding:10px; background:#f44336; color:white; border:none; border-radius:6px; font-weight:600; cursor:pointer; transition:background 0.2s;">Cancel</button>' +
                                        '</div></div></td>';
                return tpl;
            };

            if (btnEdit) btnEdit.addEventListener('click', function(){
                // close any existing popup
                if (openPopup) { openPopup.remove(); openPopup = null; }
                var currentRole = row.querySelector('.view-role').textContent.trim();
                var popup = popupTemplate();
                // set select to current role
                var sel = popup.querySelector('.popup-role-select'); sel.value = currentRole;

                // insert after row and focus select
                row.parentNode.insertBefore(popup, row.nextSibling);
                openPopup = popup;
                sel.focus && sel.focus();

                // wire save/cancel
                var saveBtn = popup.querySelector('.popup-apply');
                var cancelBtn = popup.querySelector('.popup-cancel');

                cancelBtn.addEventListener('click', function(){ popup.remove(); openPopup = null; });

                saveBtn.addEventListener('click', function(){
                    var newRole = sel.value;
                    var id = row.getAttribute('data-user-id');
                    var form = new FormData(); form.append('id', id); form.append('role', newRole);
                    fetch('../api/update_user.php', { method: 'POST', body: form, credentials: 'same-origin' })
                        .then(function(res){ return res.json(); })
                        .then(function(json){
                            if (json.success) {
                                var roleView = row.querySelector('.view-role');
                                var savedRole = (json.role && typeof json.role === 'string') ? json.role : newRole;
                                if (roleView) roleView.textContent = savedRole;
                                popup.remove(); openPopup = null; toasts('Role updated');
                                // Refresh the page to show updated data
                                setTimeout(function(){ window.location.reload(); }, 900);
                            } else { 
                                toasts(json.error || 'Update failed', true); 
                            }
                        }).catch(function(e){ 
                            console.error('Fetch error:', e); 
                            toasts('Update failed', true); 
                        });
                });
            });

            // Reset Password button handler
            if (btnResetPass) btnResetPass.addEventListener('click', function(){
                // close any existing popup
                if (openPopup) { openPopup.remove(); openPopup = null; }
                
                var popup = passwordPopupTemplate();
                row.parentNode.insertBefore(popup, row.nextSibling);
                openPopup = popup;
                
                var newPassInput = popup.querySelector('.popup-new-password');
                var confirmPassInput = popup.querySelector('.popup-confirm-password');
                var genBtn = popup.querySelector('.btn-generate-password');
                var copyBtn = popup.querySelector('.btn-copy-password');
                var saveBtn = popup.querySelector('.popup-save-password');
                var cancelBtn = popup.querySelector('.popup-cancel-password');
                var toggleNewBtn = popup.querySelector('.toggle-new-password');
                var toggleConfirmBtn = popup.querySelector('.toggle-confirm-password');
                
                newPassInput.focus && newPassInput.focus();
                
                // Toggle new password visibility
                toggleNewBtn.addEventListener('click', function(){
                    if (newPassInput.type === 'password') {
                        newPassInput.type = 'text';
                        toggleNewBtn.textContent = 'Hide';
                    } else {
                        newPassInput.type = 'password';
                        toggleNewBtn.textContent = 'Show';
                    }
                });
                
                // Toggle confirm password visibility
                toggleConfirmBtn.addEventListener('click', function(){
                    if (confirmPassInput.type === 'password') {
                        confirmPassInput.type = 'text';
                        toggleConfirmBtn.textContent = 'Hide';
                    } else {
                        confirmPassInput.type = 'password';
                        toggleConfirmBtn.textContent = 'Show';
                    }
                });

                // Password generator helper
                function generatePassword(length) {
                    length = length || 12;
                    var upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    var lower = 'abcdefghijklmnopqrstuvwxyz';
                    var numbers = '0123456789';
                    // Use a safe subset of special characters
                    var specials = '!@#$%^&*()';
                    // Ensure at least one of each required class
                    var all = upper + lower + numbers + specials;
                    var pwd = '';
                    // pick one required from each
                    pwd += upper.charAt(Math.floor(Math.random()*upper.length));
                    pwd += numbers.charAt(Math.floor(Math.random()*numbers.length));
                    pwd += specials.charAt(Math.floor(Math.random()*specials.length));
                    // fill the rest
                    for (var i = 3; i < length; i++) pwd += all.charAt(Math.floor(Math.random()*all.length));
                    // shuffle
                    pwd = pwd.split('').sort(function(){return 0.5 - Math.random();}).join('');
                    return pwd;
                }

                // Wire generate button
                if (genBtn) genBtn.addEventListener('click', function(){
                    var p = generatePassword(12);
                    newPassInput.value = p;
                    confirmPassInput.value = p;
                    // Show the generated password in plaintext by default
                    try { newPassInput.type = 'text'; confirmPassInput.type = 'text'; if (toggleNewBtn) toggleNewBtn.textContent = 'Hide'; if (toggleConfirmBtn) toggleConfirmBtn.textContent = 'Hide'; } catch(e) { /* ignore */ }
                });

                // Wire copy button (inline confirmation)
                if (copyBtn) copyBtn.addEventListener('click', function(){
                    var val = newPassInput.value || '';
                    if (!val) { toasts('No generated password to copy', true); return; }
                    var doCopy = function(text){
                        if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(text);
                        return new Promise(function(resolve, reject){ try { var ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); resolve(); } catch(e){ reject(e); } });
                    };
                    doCopy(val).then(function(){
                        // Inline confirmation element
                        var conf = document.createElement('span');
                        conf.className = 'copy-confirm';
                        conf.textContent = 'Copied!';
                        conf.style.marginLeft = '8px';
                        conf.style.color = '#10b981';
                        conf.style.fontSize = '13px';
                        conf.style.fontWeight = '600';
                        copyBtn.parentNode.appendChild(conf);
                        setTimeout(function(){ try{ conf.remove(); } catch(e){} }, 1600);
                    }).catch(function(){
                        toasts('Copy failed', true);
                    });
                });

                // Auto-generate on open (also show it)
                if (genBtn) { genBtn.click(); }
                
                cancelBtn.addEventListener('click', function(){ 
                    popup.remove(); 
                    openPopup = null; 
                });
                
                saveBtn.addEventListener('click', function(){
                    var newPass = newPassInput.value;
                    var confirmPass = confirmPassInput.value;
                    
                    if (!newPass || !confirmPass) {
                        toasts('Please fill in both password fields', true);
                        return;
                    }
                    
                    // Password validation: at least 8 chars, 1 number, 1 uppercase, 1 special char
                    if (newPass.length < 8) {
                        toasts('Password must be at least 8 characters', true);
                        return;
                    }
                    if (!/[0-9]/.test(newPass)) {
                        toasts('Password must contain at least one number', true);
                        return;
                    }
                    if (!/[A-Z]/.test(newPass)) {
                        toasts('Password must contain at least one uppercase letter', true);
                        return;
                    }
                    if (!/[!@#$%^&*()_+\-=[\]{};:'"\\|,.<>\/\?]/.test(newPass)) {
                        toasts('Password must contain at least one special character', true);
                        return;
                    }
                    
                    if (newPass !== confirmPass) {
                        toasts('Passwords do not match', true);
                        return;
                    }
                    
                    var id = row.getAttribute('data-user-id');
                    var form = new FormData();
                    form.append('id', id);
                    form.append('new_password', newPass);
                    
                    fetch('../api/update_user_password.php', { method: 'POST', body: form, credentials: 'same-origin' })
                        .then(function(res){ return res.json(); })
                        .then(function(json){
                            if (json.success) {
                                popup.remove(); 
                                openPopup = null; 
                                toasts('Password reset successfully');
                            } else { 
                                toasts(json.error || 'Password reset failed', true); 
                            }
                        }).catch(function(e){ 
                            console.error(e); 
                            toasts('Password reset failed', true); 
                        });
                });
            });

        });
    })();
    </script>
    
    <!-- Disable unsaved guard on this page to prevent errors -->
    <script>
    if (window.UnsavedGuard) {
        window.UnsavedGuard.forceAllowNextNavigation();
        // Override to make it a no-op
        window.UnsavedGuard = {
            init: function(){},
            hasChanges: function(){ return false; },
            markClean: function(){},
            forceAllowNextNavigation: function(){},
            registerElement: function(){},
            syncSnapshot: function(){}
        };
    }
    </script>
</body>
</html>
