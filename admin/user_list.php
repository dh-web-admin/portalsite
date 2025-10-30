<?php
require_once __DIR__ . '/../session_init.php';
require_once '../config/config.php';

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
if ($row['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}
$stmt->close();

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
                                <th>Actions</th>
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
                                    <div class="role-edit-wrap" style="display:none;">
                                        <select class="edit-val edit-role">
                                        <?php
                                        $roles = ['admin','projectmanager','estimator','accounting','superintendent','foreman','mechanic','operator','laborer'];
                                        foreach ($roles as $r) {
                                            $sel = ($r === $u['role']) ? 'selected' : '';
                                            echo "<option value=\"".htmlspecialchars($r)."\" $sel>".htmlspecialchars($r)."</option>";
                                        }
                                        ?>
                                        </select>
                                        <button type="button" class="role-caret" title="Open roles" aria-label="Open roles">▾</button>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-actions">
                                        <form method="POST" action="remove_user.php" onsubmit="return confirm('Delete user <?php echo htmlspecialchars($u['email']); ?>?')" style="display:inline">
                                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($u['email']); ?>">
                                            <button type="submit" class="nav-btn-remove" style="padding:6px 10px;">Remove</button>
                                        </form>
                                        <button class="nav-btn btn-edit" style="padding:6px 10px; min-width:60px;">Edit Role</button>
                                        <button class="nav-btn btn-reset-pass" style="padding:6px 10px; width:auto; color:black;">Reset Password</button>
                                        <button class="nav-btn btn-save" style="padding:6px 10px; display:none;">Apply</button>
                                        <button class="nav-btn btn-cancel" style="padding:6px 10px; display:none;">Cancel</button>
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

        // Inline edit handlers
        function showEdit(row) {
            var viewRole = row.querySelector('.view-role'); if (viewRole) viewRole.style.display = 'none';
            var editRole = row.querySelector('.edit-role'); if (editRole) editRole.style.display = 'inline-block';
            row.querySelector('.btn-edit').style.display='none';
            row.querySelector('.btn-save').style.display='inline-block';
            row.querySelector('.btn-cancel').style.display='inline-block';
        }

        function hideEdit(row) {
            var viewRole = row.querySelector('.view-role'); if (viewRole) viewRole.style.display = '';
            var editRole = row.querySelector('.edit-role'); if (editRole) editRole.style.display = 'none';
            var save = row.querySelector('.btn-save'); if (save) save.style.display='none';
            var cancel = row.querySelector('.btn-cancel'); if (cancel) cancel.style.display='none';
            var edit = row.querySelector('.btn-edit'); if (edit) edit.style.display='inline-block';
        }

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

        var openPopup = null; // current popup row element
        table.querySelectorAll('tbody tr').forEach(function(row){
            var btnEdit = row.querySelector('.btn-edit');
            var btnSave = row.querySelector('.btn-save');
            var btnCancel = row.querySelector('.btn-cancel');
            var btnResetPass = row.querySelector('.btn-reset-pass');

            // Create popup HTML fragment for role editing
            var popupTemplate = function(currentRoleHtml){
                var tpl = document.createElement('tr');
                tpl.className = 'role-popup-row';
                // colspan should cover all data columns including hidden ID
                tpl.innerHTML = '<td colspan="5" style="padding:12px 10px;"><div style="display:flex; align-items:center; gap:12px;">' +
                    '<select class="popup-role-select">' + (function(){
                        var opts = '';
                        var roles = ['admin','projectmanager','estimator','accounting','superintendent','foreman','mechanic','operator','laborer'];
                        roles.forEach(function(r){
                            opts += '<option value="'+r+'">'+r+'</option>';
                        });
                        return opts;
                    })() +
                    '</select>' +
                    '<div style="margin-left:12px; display:flex; gap:8px;">' +
                    '<button class="nav-btn popup-apply">Apply</button>' +
                    '<button class="nav-btn popup-cancel">Cancel</button>' +
                    '</div></div></td>';
                return tpl;
            };

            // Create password reset popup
            var passwordPopupTemplate = function(){
                var tpl = document.createElement('tr');
                tpl.className = 'password-popup-row';
                tpl.innerHTML = '<td colspan="5" style="padding:12px 10px; background:#f8f9fa;">' +
                    '<div style="display:flex; flex-direction:column; gap:10px; max-width:400px;">' +
                    '<strong>Reset Password</strong>' +
                    '<div style="position:relative;">' +
                    '<input type="password" class="popup-new-password" placeholder="New password" style="padding:8px; padding-right:70px; border:1px solid #ddd; border-radius:4px; width:100%; box-sizing:border-box;">' +
                    '<button type="button" class="toggle-new-password" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:4px 8px; font-size:12px; font-weight:600; color:#667eea;">Show</button>' +
                    '</div>' +
                    '<small style="color:#666; font-size:12px; margin-top:-5px;">At least 8 chars, 1 number, 1 uppercase, 1 special character</small>' +
                    '<div style="position:relative;">' +
                    '<input type="password" class="popup-confirm-password" placeholder="Confirm password" style="padding:8px; padding-right:70px; border:1px solid #ddd; border-radius:4px; width:100%; box-sizing:border-box;">' +
                    '<button type="button" class="toggle-confirm-password" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:4px 8px; font-size:12px; font-weight:600; color:#667eea;">Show</button>' +
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
                                var roleView = row.querySelector('.view-role'); if (roleView) roleView.textContent = newRole;
                                popup.remove(); openPopup = null; toasts('Role updated');
                            } else { toasts(json.error || 'Update failed', true); }
                        }).catch(function(e){ console.error(e); toasts('Update failed', true); });
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

            if (btnCancel) btnCancel.addEventListener('click', function(){
                // reset role select to original
                var roleSel = row.querySelector('.edit-role');
                var roleView = row.querySelector('.view-role');
                if (roleSel && roleView) roleSel.value = roleView.textContent.trim();
                hideEdit(row);
            });

            if (btnPass) btnPass.addEventListener('click', function(){
                var passIn = row.querySelector('.edit-password');
                if (passIn) passIn.style.display = (passIn.style.display === 'none' || passIn.style.display === '') ? 'inline-block' : 'none';
            });

            if (btnSave) btnSave.addEventListener('click', function(){
                var id = row.getAttribute('data-user-id');
                var role = row.querySelector('.edit-role').value;

                var form = new FormData();
                form.append('id', id);
                form.append('role', role);

                fetch('../api/update_user.php', { method: 'POST', body: form, credentials: 'same-origin' })
                    .then(function(res){ return res.json(); })
                    .then(function(json){
                        if (json.success) {
                            // update UI role
                            var roleView = row.querySelector('.view-role');
                            if (roleView) roleView.textContent = role;
                            hideEdit(row);
                            toasts('Role updated');
                        } else {
                            toasts(json.error || 'Save failed', true);
                        }
                    }).catch(function(err){
                        toasts('Save failed', true);
                        console.error(err);
                    });
            });
        });
    })();
    </script>
</body>
</html>
