<?php
require_once __DIR__ . '/../../session_init.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

require_once '../../config/config.php';

$email = $_SESSION['email'];
$name  = $_SESSION['name'];

// Fetch user details. Guard against older schemas that lack first_name/last_name.
$first_name = '';
$last_name = '';
$role = 'laborer';

// Detect if first_name/last_name columns exist
$hasFirstName = false;
$hasLastName = false;
$col = $conn->query("SHOW COLUMNS FROM users LIKE 'first_name'");
if ($col && $col->num_rows > 0) $hasFirstName = true;
$col = $conn->query("SHOW COLUMNS FROM users LIKE 'last_name'");
if ($col && $col->num_rows > 0) $hasLastName = true;

// Build select list depending on available columns
$select = 'SELECT role, email';
if ($hasFirstName) $select .= ', first_name';
if ($hasLastName) $select .= ', last_name';
$select .= ' FROM users WHERE email = ? LIMIT 1';

$stmt = $conn->prepare($select);
$stmt->bind_param('s', $email);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
if ($user) {
    if ($hasFirstName) $first_name = $user['first_name'] ?? '';
    if ($hasLastName) $last_name  = $user['last_name'] ?? '';
    $role       = $user['role'] ?? $role;
    $email      = $user['email'] ?? $email;
}
$stmt->close();

// If schema lacks first/last name, try to split session name into parts
if (!$hasFirstName && !$hasLastName) {
    $parts = array_values(array_filter(explode(' ', trim($name))));
    $first_name = $parts[0] ?? '';
    $last_name = isset($parts[1]) ? implode(' ', array_slice($parts,1)) : '';
}

// Fetch legacy profile_image column if it exists
$profileImage = null;
$colRes = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
if ($colRes && $colRes->num_rows > 0) {
    $stmt = $conn->prepare('SELECT profile_image FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $profileImage = $row ? $row['profile_image'] : null;
    $stmt->close();
}

// Fetch user_details
$userDetails = [];
$stmt = $conn->prepare('SELECT u.id AS user_id, ud.profile_picture, ud.street_address, ud.city, ud.state, ud.phone FROM users u LEFT JOIN user_details ud ON ud.user_id = u.id WHERE u.email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
if ($row) {
    $userDetails = $row;
    if (!empty($row['profile_picture'])) $profileImage = $row['profile_picture'];
}
$stmt->close();

$displayName = trim(($first_name ? $first_name : $name) . ' ' . ($last_name ? $last_name : ''));
// Build initials (up to 2 chars from name parts)
$nameParts = array_values(array_filter(explode(' ', trim($displayName))));
$initials  = '';
foreach (array_slice($nameParts, 0, 2) as $part) $initials .= strtoupper(mb_substr($part, 0, 1));
if (!$initials) $initials = '?';

$hasPhoto = !empty($profileImage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#4f46e5">
    <title>Account Details</title>
    <link rel="stylesheet" href="../../assets/css/base.css">
    <link rel="stylesheet" href="../../assets/css/admin-layout.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="/PortalSite/pages/account_settings/style.css?v=1">
</head>
<body class="admin-page">
<div class="admin-container">
    <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
    <div class="admin-layout">
        <?php include __DIR__ . '/../../partials/sidebar.php'; ?>

        <main class="content-area">
            <div class="main-content ac-page">

                <!-- Page title removed per request -->

                <!-- ── Main card ── -->
                <div class="ac-card">

                    <!-- LEFT: Avatar panel -->
                    <div class="ac-avatar-col">

                        <input type="file" id="accPhotoInput" name="profile_picture" accept="image/*" style="display:none" aria-label="Choose profile photo">

                        <div class="left-avatar-box" id="accAvatarWrap">
                            <?php if ($hasPhoto): ?>
                                <div class="left-avatar-frame">
                                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="<?php echo htmlspecialchars($name); ?>'s photo" id="accAvatarImg" class="left-avatar-img">
                                </div>
                            <?php else: ?>
                                <div class="left-avatar-frame left-avatar-empty" id="accAvatarInitials"><?php echo htmlspecialchars($initials); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="left-upload-wrap">
                            <button type="button" id="leftUploadBtn" class="left-upload-btn" onclick="accTriggerPicker()">Upload Photo</button>
                        </div>

                        <div class="ac-identity">
                            <p class="ac-identity-name"><?php echo htmlspecialchars($displayName ?: $name); ?></p>
                            <span class="ac-role-badge"><?php echo htmlspecialchars(ucfirst($role)); ?></span>
                        </div>

                        <div class="left-change-pw" style="margin-top:18px;width:100%">
                            <button type="button" class="left-change-toggle" id="leftChangeToggle" aria-expanded="false" aria-controls="leftChangePanel">Change Password</button>
                            <div id="leftChangePanel" class="left-change-pw-content" hidden>
                                <?php if (isset($_SESSION['password_success'])): ?>
                                    <div class="ac-status ac-status--success"><?php echo htmlspecialchars($_SESSION['password_success']); unset($_SESSION['password_success']); ?></div>
                                <?php endif; ?>
                                <?php if (isset($_SESSION['password_error'])): ?>
                                    <div class="ac-status ac-status--error"><?php echo htmlspecialchars($_SESSION['password_error']); unset($_SESSION['password_error']); ?></div>
                                <?php endif; ?>
                                <form action="../../api/change_own_password.php" method="POST" class="left-pw-form">
                                    <div class="left-pw-field">
                                        <label for="current_password">Old Password</label>
                                        <div class="pw-input-wrap">
                                            <input type="password" id="current_password" name="current_password" required autocomplete="current-password" class="left-pw-input">
                                            <button type="button" class="pw-toggle-btn" onclick="accTogglePw('current_password', this)" aria-label="Toggle password visibility">Show</button>
                                        </div>
                                    </div>
                                    <div class="left-pw-field">
                                        <label for="new_password">New Password</label>
                                        <div class="pw-input-wrap">
                                            <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password" class="left-pw-input">
                                            <button type="button" class="pw-toggle-btn" onclick="accTogglePw('new_password', this)" aria-label="Toggle password visibility">Show</button>
                                        </div>
                                    </div>
                                    <div class="left-pw-field">
                                        <label for="confirm_password">Confirm New Password</label>
                                        <div class="pw-input-wrap">
                                            <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password" class="left-pw-input">
                                            <button type="button" class="pw-toggle-btn" onclick="accTogglePw('confirm_password', this)" aria-label="Toggle password visibility">Show</button>
                                        </div>
                                    </div>
                                    <button type="submit" class="left-change-btn">Change Password</button>
                                </form>
                            </div>
                        
                            <div class="left-save-wrap" style="margin-top:18px;display:flex;justify-content:center;width:100%">
                                <button type="button" id="accSaveBtn" class="ac-btn-primary" onclick="accSubmit(event)">Save Details</button>
                            </div>

                        </div>

                    </div><!-- /.ac-avatar-col -->

                    <div class="ac-divider" aria-hidden="true"></div>

                    <!-- RIGHT: Details form -->
                    <div class="ac-form-col">
                        <div class="ac-section-head">
                            <h2>Personal Details</h2>
                        </div>

                        <div class="ac-status" id="accStatus" role="alert" aria-live="polite"></div>

                        <form id="accDetailsForm" onsubmit="return accSubmit(event)" novalidate>
                            <div class="ac-fields">

                                <div class="ac-field">
                                    <label for="first_name">First Name</label>
                                    <input type="text" name="first_name" id="first_name" placeholder="First name" value="<?php echo htmlspecialchars($first_name ?? ''); ?>" readonly>
                                </div>

                                <div class="ac-field">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" name="last_name" id="last_name" placeholder="Last name" value="<?php echo htmlspecialchars($last_name ?? ''); ?>" readonly>
                                </div>

                                <!-- Role is shown on the left as a badge; removed from the form -->

                            </div><!-- /.personal-fields -->

                            <div style="height:18px"></div>
                            <div class="ac-section-head">
                                <h2>Contact Details</h2>
                            </div>

                            <div class="ac-fields">

                                <div class="ac-field ac-field--full">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" readonly>
                                </div>

                                <div class="ac-field ac-field--full">
                                    <label for="street_address">Street Address</label>
                                    <input type="text" name="street_address" id="street_address"
                                           placeholder="123 Main St" autocomplete="street-address"
                                           value="<?php echo htmlspecialchars($userDetails['street_address'] ?? ''); ?>">
                                </div>

                                <div class="ac-field">
                                    <label for="city">City</label>
                                    <input type="text" name="city" id="city"
                                           placeholder="City" autocomplete="address-level2"
                                           value="<?php echo htmlspecialchars($userDetails['city'] ?? ''); ?>">
                                </div>

                                <div class="ac-field">
                                    <label for="state">State</label>
                                    <input type="text" name="state" id="state"
                                           placeholder="State" autocomplete="address-level1"
                                           value="<?php echo htmlspecialchars($userDetails['state'] ?? ''); ?>">
                                </div>

                                <div class="ac-field ac-field--full">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" name="phone" id="phone"
                                           placeholder="(555) 000-0000" autocomplete="tel"
                                           value="<?php echo htmlspecialchars($userDetails['phone'] ?? ''); ?>">
                                </div>

                            </div><!-- /.contact-fields -->

                            <!-- Save button moved to left column under Change Password -->
                        </form>

                    </div><!-- /.acc-right -->
                </div><!-- /.acc-card -->

                

            </div><!-- /.main-content.acc-wrap -->
        </main>
    </div>
</div>

<script src="../../assets/js/mobile-menu.js"></script>
<script>
(function () {
    'use strict';

    const photoInput = document.getElementById('accPhotoInput');
    const avatarWrap = document.getElementById('accAvatarWrap');
    const uploadBtn = document.getElementById('leftUploadBtn');
    const statusEl   = document.getElementById('accStatus');
    const saveBtn    = document.getElementById('accSaveBtn');

    let hasPhoto = <?php echo $hasPhoto ? 'true' : 'false'; ?>;

    /* Trigger file picker */
    window.accTriggerPicker = function () { photoInput.click(); };

    /* Preview on file select + swap buttons */
    photoInput.addEventListener('change', function () {
        const file = photoInput.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (ev) {
            // Remove initials placeholder if present
            const initials = document.getElementById('accAvatarInitials');
            if (initials) initials.remove();

            // Create / update avatar <img>
            let img = document.getElementById('accAvatarImg');
            if (!img) {
                img = document.createElement('img');
                img.id        = 'accAvatarImg';
                img.className = 'left-avatar-img';
                img.alt       = 'Profile photo';
                avatarWrap.insertBefore(img, avatarWrap.firstChild);
            }
            img.src = ev.target.result;

            // overlay removed: no hover overlay element will be appended

            // Swap Upload → Change Photo button label
            if (!hasPhoto && uploadBtn) {
                uploadBtn.textContent = 'Change Photo';
                hasPhoto = true;
            }
        };
        reader.readAsDataURL(file);
    });

    /* Inline status banner */
    function showStatus(type, msg) {
        // type: 'success' or 'error'
        statusEl.classList.remove('ac-status--success', 'ac-status--error', 'ac-status--visible');
        if (type === 'success') statusEl.classList.add('ac-status--success');
        if (type === 'error') statusEl.classList.add('ac-status--error');
        statusEl.classList.add('ac-status--visible');
        statusEl.textContent = (type === 'success' ? '✓ ' : '✗ ') + msg;
        clearTimeout(statusEl._timer);
        statusEl._timer = setTimeout(() => {
            statusEl.classList.remove('ac-status--visible', 'ac-status--success', 'ac-status--error');
            statusEl.textContent = '';
        }, 4200);
    }

    /* Submit details form */
    window.accSubmit = function (e) {
        e.preventDefault();
        saveBtn.disabled = true;
        // show spinner inside button
        const spinner = saveBtn.querySelector('.ac-spinner');
        if (spinner) { spinner.style.opacity = 1; }

        const fd = new FormData(document.getElementById('accDetailsForm'));
        if (photoInput.files && photoInput.files[0]) fd.set('profile_picture', photoInput.files[0]);

        fetch('../../api/save_user_details.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.text())
            .then(text => {
                let data = null;
                try {
                    data = JSON.parse(text);
                } catch (err) {
                    console.error('save_user_details: invalid JSON response', text);
                    showStatus('error', 'Network error. Please try again.');
                    return;
                }

                if (data && data.success) {
                    if (data.url) {
                        const img = document.getElementById('accAvatarImg');
                        if (img) img.src = data.url;
                    }
                    showStatus('success', 'Details saved successfully.');
                } else {
                    console.error('save_user_details error payload', data);
                    showStatus('error', 'Could not save. Please try again.');
                }
            })
            .catch((err) => {
                console.error('save_user_details network/fetch error', err);
                showStatus('error', 'Network error. Please try again.');
            })
            .finally(() => {
                saveBtn.disabled = false;
                if (spinner) { spinner.style.opacity = 0; }
            });

        return false;
    };

    /* Password toggle */
    window.accTogglePw = function (id, btn) {
        const el = document.getElementById(id);
        const hide = el.type === 'password';
        el.type = hide ? 'text' : 'password';
        btn.textContent = hide ? 'Hide' : 'Show';
    };

    /* Change-password panel toggle */
    const changeToggle = document.getElementById('leftChangeToggle');
    const changePanel  = document.getElementById('leftChangePanel');
    if (changeToggle && changePanel) {
        changeToggle.addEventListener('click', function () {
            const expanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            if (expanded) {
                // collapse with smooth animation
                changePanel.classList.remove('open');
                setTimeout(() => { changePanel.hidden = true; }, 260);
            } else {
                // expand
                changePanel.hidden = false;
                // allow layout then animate
                requestAnimationFrame(() => changePanel.classList.add('open'));
                // focus first input for accessibility
                const first = changePanel.querySelector('input');
                if (first) setTimeout(() => first.focus(), 260);
            }
        });
    }

    // Intercept change-password form and submit via AJAX with client-side validation
    (function(){
        const pwForm = document.querySelector('.left-pw-form');
        if (!pwForm) return;

        function clientValidate(current, nw, confirm) {
            if (!current || !nw || !confirm) return 'All fields are required';
            if (nw !== confirm) return 'New passwords do not match';
            if (nw.length < 8) return 'Password must be at least 8 characters';
            if (!/[0-9]/.test(nw)) return 'Password must contain at least one number';
            if (!/[A-Z]/.test(nw)) return 'Password must contain at least one uppercase letter';
            if (!/[!@#$%^&*()_+\-=[\]{};:\'"\\|,.<>\/\?]/.test(nw)) return 'Password must contain at least one special character';
            return null;
        }

        pwForm.addEventListener('submit', function(ev){
            ev.preventDefault();
            const cur = pwForm.current_password.value.trim();
            const nw = pwForm.new_password.value;
            const conf = pwForm.confirm_password.value;
            const vErr = clientValidate(cur,nw,conf);
            if (vErr) { showStatus('error', vErr); return; }

            const fd = new FormData(pwForm);
            fetch('../../api/change_own_password.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.text())
                .then(text => {
                    let data = null;
                    try {
                        data = JSON.parse(text);
                    } catch (err) {
                        console.error('change_password: invalid JSON response', text);
                        showStatus('error', 'Network error while changing password');
                        return;
                    }
                    if (data && data.success) {
                        showStatus('success', data.message || 'Password updated');
                        setTimeout(() => { window.location.href = '/PortalSite/auth/logout.php'; }, 2200);
                    } else {
                        showStatus('error', data.error || 'Could not change password');
                    }
                })
                .catch(err => {
                    console.error('change_password ajax error', err);
                    showStatus('error', 'Network error while changing password');
                });
        });
    })();

    /* Keyboard on avatar wrap */
    avatarWrap.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); window.accTriggerPicker(); }
    });

    /* Read-only fields: prevent typing/pasting and show popup */
    function showReadonlyPopup(el, msg) {
        const popup = document.createElement('div');
        popup.className = 'readonly-popup';
        popup.textContent = msg || 'This field is not editable';
        document.body.appendChild(popup);

        const rect = el.getBoundingClientRect();
        const x = rect.left + rect.width / 2 + window.scrollX;
        const y = rect.top + window.scrollY;
        popup.style.left = x + 'px';
        popup.style.top = y + 'px';

        // trigger show with small delay to allow placement
        requestAnimationFrame(() => popup.classList.add('show'));

        clearTimeout(popup._timer);
        popup._timer = setTimeout(() => {
            popup.classList.remove('show');
            setTimeout(() => {
                if (popup && popup.parentNode) popup.parentNode.removeChild(popup);
            }, 200);
        }, 1600);
    }

    ['first_name', 'last_name', 'email'].forEach(function (id) {
        const f = document.getElementById(id);
        if (!f) return;
        f.addEventListener('keydown', function (ev) {
            // allow Tab / Shift+Tab / navigation keys
            const allowKeys = ['Tab','ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home','End'];
            if (allowKeys.indexOf(ev.key) !== -1) return;
            ev.preventDefault();
            showReadonlyPopup(f, 'This field cannot be edited here');
        });
        f.addEventListener('paste', function (ev) { ev.preventDefault(); showReadonlyPopup(f, 'This field cannot be edited here'); });
    });

    // Server-side password messages
    <?php if (isset($_SESSION['password_success'])): ?>
    (function(){
        try { showStatus('success', <?php echo json_encode($_SESSION['password_success']); ?>); } catch(e){}
        <?php if (isset($_SESSION['password_logout']) && $_SESSION['password_logout']): ?>
            setTimeout(function(){ window.location.href = '/PortalSite/auth/logout.php'; }, 2500);
            <?php unset($_SESSION['password_logout']); ?>
        <?php endif; ?>
        <?php unset($_SESSION['password_success']); ?>
    })();
    <?php elseif (isset($_SESSION['password_error'])): ?>
    (function(){ try { showStatus('error', <?php echo json_encode($_SESSION['password_error']); ?>); } catch(e){}; <?php unset($_SESSION['password_error']); ?> })();
    <?php endif; ?>
})();
</script>
</body>
</html>