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
$actualRole = $row['role'];

// Check if developer is previewing as admin
if ($actualRole === 'developer' && isset($_GET['preview_role']) && $_GET['preview_role'] === 'admin') {
    $role = 'admin';
} elseif ($actualRole !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
} else {
    $role = 'admin';
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
    <?php include __DIR__ . '/../partials/dev_notch.php'; ?>

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
                                </td>
                                <td>
                                    <div class="user-actions">
                                        <form method="POST" action="remove_user.php" onsubmit="return confirm('Delete user <?php echo htmlspecialchars($u['email']); ?>?')" style="display:inline">
                                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($u['email']); ?>">
                                            <button type="submit" class="nav-btn-remove" style="padding:6px 10px;">Remove</button>
                                        </form>
                                        <button class="nav-btn btn-edit" style="padding:6px 10px; min-width:60px;">Edit Role</button>
                                        <button class="nav-btn btn-reset-pass" style="padding:6px 10px; width:auto; color:black;">Reset Password</button>
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
