<?php
require_once __DIR__ . '/../../session_init.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../partials/permissions.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

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

$canEditEquipments = can_edit_page('equipments');

$equipmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($equipmentId <= 0) {
    die('Invalid equipment ID.');
}

// Fetch equipment basics
$eqStmt = $conn->prepare('SELECT equipment_id, dhss_equipment_number, dhcst_equipment_number, type, vehicle_year, make, model, current_hours FROM equipments WHERE equipment_id = ? LIMIT 1');
$eqStmt->bind_param('i', $equipmentId);
$eqStmt->execute();
$eqRes = $eqStmt->get_result();
$equipment = $eqRes ? $eqRes->fetch_assoc() : null;
$eqStmt->close();

if (!$equipment) {
    die('Equipment not found.');
}

// Fetch tire info (single row per equipment expected)
$tireStmt = $conn->prepare('SELECT tire_id, steer_tire_make, steer_tire_model, steer_tire_size, drive_tire_make, drive_tire_model, drive_tire_size FROM tire_info WHERE equipment_id = ? LIMIT 1');
$tireStmt->bind_param('i', $equipmentId);
$tireStmt->execute();
$tireRes = $tireStmt->get_result();
$tire = $tireRes ? $tireRes->fetch_assoc() : null;
$tireStmt->close();

function display_cell($value) {
    $trimmed = trim((string)($value ?? ''));
    return $trimmed === '' ? '—' : htmlspecialchars($trimmed);
}

$equipmentLabel = '';
if (!empty($equipment['dhss_equipment_number'])) {
    $equipmentLabel = $equipment['dhss_equipment_number'];
} elseif (!empty($equipment['dhcst_equipment_number'])) {
    $equipmentLabel = $equipment['dhcst_equipment_number'];
} else {
    $equipmentLabel = '#' . $equipmentId;
}
if (!empty($equipment['type'])) {
    $equipmentLabel .= ' | ' . $equipment['type'];
}

$currentHours = isset($equipment['current_hours']) ? (string)$equipment['current_hours'] : '—';
$tireId = isset($tire['tire_id']) ? (int)$tire['tire_id'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tires</title>
    <link rel="stylesheet" href="../../assets/css/base.css" />
    <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
    <link rel="stylesheet" href="../../assets/css/dashboard.css" />
    <style>
        .panel-wrapper { max-width: 1200px; margin: 0 auto; position: relative; }
        .equipment-back-btn-wrapper--top-left { margin-top: 18px; margin-bottom: 18px; }
        .equipment-back-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; border: none; cursor: pointer; transition: background 0.2s ease, transform 0.1s ease; }
        .equipment-back-btn:hover { background: #1d4ed8; }
        .equipment-back-btn:active { transform: scale(0.98); }
        .oil-page-heading { text-align: center; margin: 8px 0 8px; }
        .oil-page-heading h1 { margin: 0; font-size: 26px; letter-spacing: 3px; font-weight: 800; color: #0f172a; }
        .oil-page-heading .subtitle { margin-top: 6px; color: #6b7280; font-size: 14px; }
        .selected-info { position: absolute; right: 0; top: 10px; display: flex; align-items: center; justify-content: flex-end; }
        .hours-bubble { display: inline-block; background: #ffffff; padding: 10px 16px; border-radius: 999px; border: 1px solid #e6eef6; font-weight: 700; color: #0f172a; font-size: 15px; box-shadow: 0 8px 22px rgba(2,6,23,0.05); }
        .info-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; align-items: stretch; }
        .info-card { display: flex; flex-direction: column; height: 100%; background: #fff; border: 1px solid #e6eef6; border-radius: 12px; box-shadow: 0 6px 18px rgba(2,6,23,0.04); padding: 18px; margin-bottom: 0; min-height: 100%; }
        .info-card .card-header { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
        .info-card h3 { margin: 0; font-size: 15px; text-transform: uppercase; letter-spacing: 0.05em; color: #0f172a; }
        .info-list { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: 1fr; gap: 12px; }
        .info-list li { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: #f8fafc; border: 1px solid #eef2f7; border-radius: 10px; }
        .info-label { color: #475569; font-weight: 700; font-size: 14px; }
        .info-value { color: #0f172a; font-weight: 700; font-size: 15px; }
        .edit-btn { padding: 6px 12px; font-size: 12px; font-weight: 700; border-radius: 10px; border: 1px solid #c2c7cf; background: #c2c7cf; color: #ffffff; cursor: pointer; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.15); transition: background 0.15s ease, border-color 0.15s ease, transform 0.1s ease; }
        .edit-btn:hover { background: #aeb4bd; border-color: #aeb4bd; }
        .edit-btn:active { transform: scale(0.98); }
        .tire-guide-section { margin-top: 28px; }
        .tire-guide-spacer { height: 321px; }
        .tire-guide-section { position: fixed; left: 0; right: 0; bottom: 0; padding: 12px 16px 20px 16px; background: #ffffff; z-index: 100; display: flex; justify-content: center; }
        .tire-guide-card { background: transparent; border: none; border-radius: 0; box-shadow: none; padding: 0; max-width: 1100px; width: 100%; }
        .tire-guide-card h3 { margin: 0 0 6px 0; font-size: 16px; letter-spacing: 0.04em; text-transform: uppercase; color: #0f172a; }
        .tire-guide-card p { margin: 0 0 16px 0; color: #475569; font-size: 14px; }
        .tire-annotated { position: relative; max-width: 820px; margin: 0 auto; padding: 8px 12px 0 12px; height: 254px; overflow: hidden; }
        .tire-arc { width: 100%; height: 506px; background: center top / auto 506px no-repeat url('images/tireguide.svg'); }
        /* Modal */
        .tire-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 200; padding: 16px; }
        .tire-modal.is-open { display: flex; }
        .tire-modal__dialog { background: #fff; border-radius: 14px; width: 100%; max-width: 520px; box-shadow: 0 18px 46px rgba(15,23,42,0.25); overflow: hidden; }
        .tire-modal__header { background: #4b5563; color: #fff; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; }
        .tire-modal__title { margin: 0; font-size: 18px; font-weight: 700; }
        .tire-modal__close { background: rgba(255,255,255,0.2); border: none; color: #fff; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; font-size: 18px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .tire-modal__close:hover { background: rgba(255,255,255,0.3); }
        .tire-modal__body { padding: 20px; }
        .tire-modal__section { display: none; }
        .tire-modal__section.is-active { display: grid; gap: 14px; }
        .tire-modal__field { display: flex; flex-direction: column; gap: 6px; }
        .tire-modal__field label { font-weight: 700; color: #374151; font-size: 14px; }
        .tire-modal__field input { padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
        .tire-modal__actions { display: flex; justify-content: flex-end; gap: 10px; padding: 0 20px 20px 20px; }
        .tire-btn { padding: 10px 16px; border-radius: 10px; border: 1px solid transparent; font-weight: 700; cursor: pointer; font-size: 14px; }
        .tire-btn--secondary { background: #f3f4f6; border-color: #e5e7eb; color: #374151; }
        .tire-btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        .tire-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .tire-modal__error { color: #b91c1c; font-weight: 700; padding: 0 20px 8px 20px; display: none; }
        @media (max-width: 720px) {
        }
        @media (max-width: 640px) {
            .info-list { grid-template-columns: 1fr; }
            .selected-info { position: static; margin-bottom: 12px; justify-content: center; }
        }
    </style>
</head>
<body class="admin-page">
    <div class="admin-container">
        <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
        <div class="admin-layout">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
            <main class="content-area">
                <div class="main-content">
                    <div class="panel-wrapper">
                        <div class="equipment-back-btn-wrapper equipment-back-btn-wrapper--top-left" style="text-align:left;">
                            <a id="backBtn" href="index.php" class="equipment-back-btn"><span>←</span><span>Back to Equipments</span></a>
                        </div>
                        <div class="selected-info" aria-live="polite">
                            <div class="hours-bubble">Current equipment hours: <?php echo display_cell($currentHours); ?></div>
                        </div>
                    </div>

                    <div class="oil-page-heading" aria-hidden="true">
                        <h1><?php echo htmlspecialchars($equipmentLabel); ?></h1>
                        <div class="subtitle">Tires Reference Sheet</div>
                    </div>

                    <div class="panel-wrapper" style="margin-top: 18px;">
                        <div class="info-row">
                            <div class="info-card">
                                <div class="card-header">
                                    <h3>Equipment Details</h3>
                                    <?php if (!empty($canEditEquipments)) { ?><button type="button" class="edit-btn" data-scope="equipment">Edit</button><?php } ?>
                                </div>
                                <ul class="info-list">
                                    <li><span class="info-label">Type</span><span class="info-value" id="eq-type-value"><?php echo display_cell($equipment['type'] ?? ''); ?></span></li>
                                    <li><span class="info-label">Year</span><span class="info-value" id="eq-year-value"><?php echo display_cell($equipment['vehicle_year'] ?? ''); ?></span></li>
                                    <li><span class="info-label">Make</span><span class="info-value" id="eq-make-value"><?php echo display_cell($equipment['make'] ?? ''); ?></span></li>
                                    <li><span class="info-label">Model</span><span class="info-value" id="eq-model-value"><?php echo display_cell($equipment['model'] ?? ''); ?></span></li>
                                </ul>
                            </div>

                            <div class="info-card">
                                <div class="card-header">
                                    <h3>Steer Tires</h3>
                                    <?php if (!empty($canEditEquipments)) { ?><button type="button" class="edit-btn" data-scope="steer">Edit</button><?php } ?>
                                </div>
                                <ul class="info-list">
                                    <li><span class="info-label">Make</span><span class="info-value" id="steer-make-value"><?php echo display_cell($tire['steer_tire_make'] ?? ''); ?></span></li>
                                    <li><span class="info-label">Model</span><span class="info-value" id="steer-model-value"><?php echo display_cell($tire['steer_tire_model'] ?? ''); ?></span></li>
                                    <li><span class="info-label">Size</span><span class="info-value" id="steer-size-value"><?php echo display_cell($tire['steer_tire_size'] ?? ''); ?></span></li>
                                </ul>
                            </div>

                            <div class="info-card">
                                <div class="card-header">
                                    <h3>Drive Tires</h3>
                                    <?php if (!empty($canEditEquipments)) { ?><button type="button" class="edit-btn" data-scope="drive">Edit</button><?php } ?>
                                </div>
                                <ul class="info-list">
                                    <li><span class="info-label">Make</span><span class="info-value" id="drive-make-value"><?php echo display_cell($tire['drive_tire_make'] ?? ''); ?></span></li>
                                    <li><span class="info-label">Model</span><span class="info-value" id="drive-model-value"><?php echo display_cell($tire['drive_tire_model'] ?? ''); ?></span></li>
                                    <li><span class="info-label">Size</span><span class="info-value" id="drive-size-value"><?php echo display_cell($tire['drive_tire_size'] ?? ''); ?></span></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="tire-guide-spacer"></div>
                    <div class="panel-wrapper tire-guide-section">
                        <div class="tire-guide-card">
                            <h3>Tire Size Quick Guide</h3>
                            <p>The two-digit number after the slash mark in a tire size is the aspect ratio. For example, in a size P215/65 R15 tire, the 65 means that the height is equal to 65% of the tire's width. The bigger the aspect ratio, the bigger the tire's sidewall will be.</p>
                            <div class="tire-annotated">
                                <div class="tire-arc" aria-hidden="true"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (!empty($canEditEquipments)) { ?>
                <div id="tireEditModal" class="tire-modal" aria-hidden="true">
                    <div class="tire-modal__dialog" role="dialog" aria-modal="true" aria-label="Edit equipment and tires">
                        <div class="tire-modal__header">
                            <h3 class="tire-modal__title">Edit Details</h3>
                            <button type="button" class="tire-modal__close" id="tireModalClose" aria-label="Close">×</button>
                        </div>
                        <div class="tire-modal__error" id="tireModalError"></div>
                        <form id="tireModalForm">
                            <div class="tire-modal__body">
                                <div class="tire-modal__section" data-scope="equipment">
                                    <div class="tire-modal__field">
                                        <label for="modal_type">Type</label>
                                        <input id="modal_type" name="type" type="text" />
                                    </div>
                                    <div class="tire-modal__field">
                                        <label for="modal_year">Year</label>
                                        <input id="modal_year" name="vehicle_year" type="text" />
                                    </div>
                                    <div class="tire-modal__field">
                                        <label for="modal_make">Make</label>
                                        <input id="modal_make" name="make" type="text" />
                                    </div>
                                    <div class="tire-modal__field">
                                        <label for="modal_model">Model</label>
                                        <input id="modal_model" name="model" type="text" />
                                    </div>
                                </div>

                                <div class="tire-modal__section" data-scope="steer">
                                    <div class="tire-modal__field">
                                        <label for="modal_steer_make">Steer Make</label>
                                        <input id="modal_steer_make" name="steer_tire_make" type="text" />
                                    </div>
                                    <div class="tire-modal__field">
                                        <label for="modal_steer_model">Steer Model</label>
                                        <input id="modal_steer_model" name="steer_tire_model" type="text" />
                                    </div>
                                    <div class="tire-modal__field">
                                        <label for="modal_steer_size">Steer Size</label>
                                        <input id="modal_steer_size" name="steer_tire_size" type="text" />
                                    </div>
                                </div>

                                <div class="tire-modal__section" data-scope="drive">
                                    <div class="tire-modal__field">
                                        <label for="modal_drive_make">Drive Make</label>
                                        <input id="modal_drive_make" name="drive_tire_make" type="text" />
                                    </div>
                                    <div class="tire-modal__field">
                                        <label for="modal_drive_model">Drive Model</label>
                                        <input id="modal_drive_model" name="drive_tire_model" type="text" />
                                    </div>
                                    <div class="tire-modal__field">
                                        <label for="modal_drive_size">Drive Size</label>
                                        <input id="modal_drive_size" name="drive_tire_size" type="text" />
                                    </div>
                                </div>
                            </div>
                            <div class="tire-modal__actions">
                                <button type="button" class="tire-btn tire-btn--secondary" id="tireModalCancel">Cancel</button>
                                <button type="submit" class="tire-btn tire-btn--primary" id="tireModalSave">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php } ?>
            </main>
        </div>
    </div>
    <script>
        (function(){
            var backBtn = document.getElementById('backBtn');
            if (backBtn) {
                backBtn.addEventListener('click', function(e){
                    try {
                        var ref = document.referrer || '';
                        if (ref && ref.indexOf(location.origin) === 0) {
                            e.preventDefault();
                            history.back();
                        }
                    } catch (err) {}
                });
            }

            var CAN_EDIT = <?php echo !empty($canEditEquipments) ? 'true' : 'false'; ?>;
            if (!CAN_EDIT) return;

            var modal = document.getElementById('tireEditModal');
            var btns = document.querySelectorAll('.info-card .edit-btn');
            var closeBtn = document.getElementById('tireModalClose');
            var cancelBtn = document.getElementById('tireModalCancel');
            var form = document.getElementById('tireModalForm');
            var saveBtn = document.getElementById('tireModalSave');
            var errBox = document.getElementById('tireModalError');
            var titleEl = document.querySelector('.tire-modal__title');
            var sections = document.querySelectorAll('.tire-modal__section');
            var activeScope = 'equipment';

            var equipmentId = <?php echo (int)$equipmentId; ?>;
            var tireId = <?php echo (int)$tireId; ?>;

            var inputs = {
                type: document.getElementById('modal_type'),
                year: document.getElementById('modal_year'),
                make: document.getElementById('modal_make'),
                model: document.getElementById('modal_model'),
                steer_make: document.getElementById('modal_steer_make'),
                steer_model: document.getElementById('modal_steer_model'),
                steer_size: document.getElementById('modal_steer_size'),
                drive_make: document.getElementById('modal_drive_make'),
                drive_model: document.getElementById('modal_drive_model'),
                drive_size: document.getElementById('modal_drive_size')
            };

            function fillFormFromPage(){
                inputs.type.value = document.getElementById('eq-type-value').textContent.trim();
                inputs.year.value = document.getElementById('eq-year-value').textContent.trim();
                inputs.make.value = document.getElementById('eq-make-value').textContent.trim();
                inputs.model.value = document.getElementById('eq-model-value').textContent.trim();
                inputs.steer_make.value = document.getElementById('steer-make-value').textContent.trim();
                inputs.steer_model.value = document.getElementById('steer-model-value').textContent.trim();
                inputs.steer_size.value = document.getElementById('steer-size-value').textContent.trim();
                inputs.drive_make.value = document.getElementById('drive-make-value').textContent.trim();
                inputs.drive_model.value = document.getElementById('drive-model-value').textContent.trim();
                inputs.drive_size.value = document.getElementById('drive-size-value').textContent.trim();
            }

            function setActiveScope(scope){
                activeScope = scope || 'equipment';
                sections.forEach(function(s){
                    var sScope = s.getAttribute('data-scope');
                    if (sScope === activeScope) s.classList.add('is-active');
                    else s.classList.remove('is-active');
                });
                if (titleEl) {
                    if (activeScope === 'steer') titleEl.textContent = 'Edit Steer Tires';
                    else if (activeScope === 'drive') titleEl.textContent = 'Edit Drive Tires';
                    else titleEl.textContent = 'Edit Equipment Details';
                }
            }

            function openModal(scope){
                if (!modal) return;
                fillFormFromPage();
                setActiveScope(scope);
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden','false');
                if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }
                if (activeScope === 'steer' && inputs.steer_make) inputs.steer_make.focus();
                else if (activeScope === 'drive' && inputs.drive_make) inputs.drive_make.focus();
                else if (inputs.type) inputs.type.focus();
            }

            function closeModal(){
                if (!modal) return;
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden','true');
                if (form) form.reset();
                if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }
            }

            btns.forEach(function(b){
                b.addEventListener('click', function(){
                    openModal(b.getAttribute('data-scope') || 'equipment');
                });
            });
            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
            if (modal) modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
            document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) closeModal(); });

            function setError(msg){
                if (!errBox) return;
                errBox.textContent = msg;
                errBox.style.display = msg ? 'block' : 'none';
            }

            if (form) form.addEventListener('submit', function(e){
                e.preventDefault();
                setError('');
                if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving...'; }

                var equipFd = new FormData();
                equipFd.append('equipment_id', equipmentId);
                equipFd.append('type', inputs.type.value);
                equipFd.append('vehicle_year', inputs.year.value);
                equipFd.append('make', inputs.make.value);
                equipFd.append('model', inputs.model.value);

                var tireFd = new FormData();
                tireFd.append('tire_id', tireId);
                tireFd.append('equipment_id', equipmentId);
                tireFd.append('steer_tire_make', inputs.steer_make.value);
                tireFd.append('steer_tire_model', inputs.steer_model.value);
                tireFd.append('steer_tire_size', inputs.steer_size.value);
                tireFd.append('drive_tire_make', inputs.drive_make.value);
                tireFd.append('drive_tire_model', inputs.drive_model.value);
                tireFd.append('drive_tire_size', inputs.drive_size.value);

                var requests = [];
                if (activeScope === 'equipment') {
                    requests.push(
                        fetch('../../api/update_equipment.php', { method: 'POST', body: equipFd, credentials: 'same-origin' })
                            .then(function(r){ return r.json().then(function(j){ return { ok: r.ok, json: j }; }); })
                    );
                } else {
                    // update_tire_info.php expects all tire fields; keep the non-edited side intact
                    requests.push(
                        fetch('../../api/update_tire_info.php', { method: 'POST', body: tireFd, credentials: 'same-origin' })
                            .then(function(r){ return r.json().then(function(j){ return { ok: r.ok, json: j }; }); })
                    );
                }

                Promise.all(requests).then(function(resArray){
                    var res = resArray[0];
                    if (!res.ok || !res.json || !res.json.success) {
                        throw new Error((res.json && (res.json.message || res.json.error)) ? (res.json.message || res.json.error) : 'Update failed');
                    }

                    if (res.json && typeof res.json.tire_id !== 'undefined') {
                        var newId = parseInt(res.json.tire_id, 10);
                        if (!isNaN(newId) && newId > 0) tireId = newId;
                    }

                    // Reflect updates in UI
                    if (activeScope === 'equipment') {
                        document.getElementById('eq-type-value').textContent = inputs.type.value;
                        document.getElementById('eq-year-value').textContent = inputs.year.value;
                        document.getElementById('eq-make-value').textContent = inputs.make.value;
                        document.getElementById('eq-model-value').textContent = inputs.model.value;
                    } else {
                        document.getElementById('steer-make-value').textContent = inputs.steer_make.value;
                        document.getElementById('steer-model-value').textContent = inputs.steer_model.value;
                        document.getElementById('steer-size-value').textContent = inputs.steer_size.value;
                        document.getElementById('drive-make-value').textContent = inputs.drive_make.value;
                        document.getElementById('drive-model-value').textContent = inputs.drive_model.value;
                        document.getElementById('drive-size-value').textContent = inputs.drive_size.value;
                    }
                    closeModal();
                }).catch(function(err){
                    setError(err.message || 'Update failed');
                }).finally(function(){
                    if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }
                });
            });
        })();
    </script>
</body>
</html>
