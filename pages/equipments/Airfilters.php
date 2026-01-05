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
if ($role === 'developer' && isset($_GET['preview_role'])) {
    $role = $_GET['preview_role'];
}
$roleStmt->close();

if (!can_access($role, 'equipments')) {
    header('Location: /pages/dashboard/');
    exit();
}

$previewParam = isset($_GET['preview_role']) ? '?preview_role=' . urlencode($_GET['preview_role']) : '';

$equipments = [];
$equipErr = null;
try {
    $eq = $conn->query("SELECT equipment_id, COALESCE(dhss_equipment_number, '') AS number, COALESCE(type, '') AS type, COALESCE(current_hours,0) AS current_hours FROM equipments ORDER BY equipment_id ASC");
    if ($eq) {
        while ($row = $eq->fetch_assoc()) {
            $equipments[] = $row;
        }
        $eq->free();
    } else {
        $equipErr = $conn->error;
    }
} catch (Throwable $th) {
    $equipErr = $th->getMessage();
}

function ensure_filter_life_column($conn) {
    static $ensured = false;
    if ($ensured) {
        return;
    }
    try {
        $check = $conn->query("SHOW COLUMNS FROM filter_info LIKE 'filter_life'");
        $hasColumn = $check && $check->num_rows > 0;
        if ($check) {
            $check->close();
        }
        if (!$hasColumn) {
            $conn->query("ALTER TABLE filter_info ADD COLUMN filter_life DECIMAL(10,1) NULL AFTER hours");
        }
        $ensured = true;
    } catch (Throwable $e) {
        error_log('[airfilters] Unable to ensure filter_life column: ' . $e->getMessage());
    }
}

ensure_filter_life_column($conn);

$filtersByEquip = [];
$filterNames = [];
try {
    $sql = "SELECT filter_id, equipment_id, filter_name, filter_date, hours, filter_life, part_number, make FROM filter_info ORDER BY equipment_id ASC, filter_name ASC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $eid = (int)($row['equipment_id'] ?? 0);
            if (!isset($filtersByEquip[$eid])) {
                $filtersByEquip[$eid] = [];
            }
            $filtersByEquip[$eid][] = [
                'filter_id' => (int)($row['filter_id'] ?? 0),
                'equipment_id' => $eid,
                'filter_name' => $row['filter_name'] ?? '',
                'filter_date' => $row['filter_date'] ?? null,
                'hours' => $row['hours'],
                'filter_life' => $row['filter_life'],
                'part_number' => $row['part_number'] ?? '',
                'make' => $row['make'] ?? ''
            ];
            if (!empty($row['filter_name'])) {
                $filterNames[] = $row['filter_name'];
            }
        }
        $res->free();
    }
} catch (Throwable $th) {
    error_log('[airfilters] Unable to fetch filters: ' . $th->getMessage());
}

$filterNames = array_values(array_unique($filterNames));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Air Filters</title>
    <link rel="stylesheet" href="../../assets/css/base.css">
    <link rel="stylesheet" href="../../assets/css/admin-layout.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .oil-status-panel { padding:12px; background:#fff; border:1px solid #e6eef6; border-radius:8px; box-shadow:0 6px 18px rgba(2,6,23,0.04); margin-top:22px; box-sizing:border-box; }
        #filtersPanel { margin-bottom:90px; }
        .panel-wrapper { max-width:1200px; margin-left:auto; margin-right:auto; position:relative; }
        .panel-wrapper.wide { max-width:1600px; }
        .equipment-back-btn-wrapper--top-left { margin-top:18px; margin-bottom:18px; }
        .equipment-back-btn { display:inline-flex; align-items:center; gap:8px; padding:10px 18px; background:#2563eb; color:#fff; text-decoration:none; border-radius:8px; font-weight:600; font-size:14px; border:none; cursor:pointer; transition:background 0.2s ease, transform 0.1s ease; }
        .equipment-back-btn:hover { background:#1d4ed8; }
        .equipment-back-btn:active { transform:scale(0.98); }
        .oil-page-heading { text-align:center; margin:8px 0 8px; }
        .oil-page-heading h1 { margin:0; font-size:26px; letter-spacing:3px; font-weight:800; color:#0f172a; }
        .oil-page-heading .subtitle { margin-top:6px; color:#6b7280; font-size:14px; }
        #filtersContainer { margin-top:10px; padding:0 6px; }
        #filtersTable { width:100%; border-collapse:collapse; }
        #filtersTable th, #filtersTable td { padding:10px 12px; text-align:left; white-space:normal; word-wrap:break-word; word-break:break-word; }
        #filtersTable thead th { border-bottom:1px solid #e2e8f0; font-size:13px; color:#475569; text-transform:uppercase; letter-spacing:0.05em; }
        #filtersTable tbody tr { border-bottom:1px solid #edf2f7; }
        .hours-bubble { display:inline-block; background:#ffffff; padding:10px 16px; border-radius:999px; border:1px solid #e6eef6; font-weight:700; color:#0f172a; font-size:15px; box-shadow:0 8px 22px rgba(2,6,23,0.05); }
        .selected-info { text-align:center; margin-bottom:10px; }
        .selected-info.outside-info { position:absolute; right:40px; top:-40px; display:flex; align-items:center; justify-content:flex-end; }
        .selected-info.outside-info .hours-bubble { padding:8px 14px; font-size:14px; }
        @media (max-width:1200px) { .selected-info.outside-info { display:none; } }
        #equipmentRibbon { transition:all .18s ease; }
        .equipment-chip { padding:10px 14px; border-radius:999px; border:1px solid rgba(226,232,240,0.9); background:#f8fafc; cursor:pointer; font-size:14px; box-shadow:0 6px 18px rgba(2,6,23,0.05); color:#0f172a; transition:all .15s ease; }
        .equipment-chip:hover { transform:translateY(-2px); box-shadow:0 10px 26px rgba(2,6,23,0.08); }
        .equipment-chip.is-selected { background:#2563eb; color:#fff; border-color:#1e40af; transform:translateY(-6px); box-shadow:0 14px 34px rgba(37,99,235,0.22); }
        .parts-action-btn { background:#f3f4f6; color:#0f172a; border:1px solid #e6eef6; padding:6px 10px; border-radius:6px; font-size:13px; cursor:pointer; transition:background .12s ease, transform .12s ease; }
        .parts-action-btn:hover { background:#e6eef6; transform:translateY(-2px); }
        .filters-reset-btn { background:#111827; color:#fff; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; box-shadow:0 6px 18px rgba(2,6,23,0.06); transition:transform .12s ease; }
        .filters-reset-btn:hover { transform:translateY(-2px); }
        .parts-delete-btn { background:#ef4444; color:#fff; border:none; padding:8px 12px; border-radius:8px; cursor:pointer; }
        .parts-delete-btn:hover { transform:translateY(-2px); }
        #showAddFilterBtn { background:#111827; color:#fff; border:none; padding:8px 14px; border-radius:8px; box-shadow:0 6px 18px rgba(2,6,23,0.06); cursor:pointer; }
        .filter-name-cell { display:flex; align-items:center; justify-content:space-between; gap:12px; }
        #filterAlerts .filter-alert { padding:10px 14px; border-radius:8px; margin-bottom:8px; font-weight:600; }
        #filterAlerts .filter-alert.warn { background:linear-gradient(90deg,#fff7ed,#fffaf0); color:#92400e; border:1px solid #fcd34d; }
        #filterAlerts .filter-alert.urgent { background:linear-gradient(90deg,#fff1f2,#fff5f6); color:#7f1d1d; border:1px solid #f87171; }
    </style>
</head>
<body class="admin-page">
    <div class="admin-container">
        <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
        <div class="admin-layout">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
            <main class="content-area">
                <div class="main-content">
                    <?php $previewParam = isset($_GET['preview_role']) ? '?preview_role=' . urlencode($_GET['preview_role']) : ''; ?>
                    <div class="panel-wrapper">
                        <div class="equipment-back-btn-wrapper equipment-back-btn-wrapper--top-left" style="text-align:left;">
                            <a id="backBtn" href="index.php<?php echo $previewParam; ?>" class="equipment-back-btn"><span>←</span><span>Back to Equipments</span></a>
                        </div>
                    </div>
                    <div class="oil-page-heading" aria-hidden="true">
                        <h1 id="equipmentHeading"></h1>
                        <div class="subtitle" id="equipmentSubtitle">Air Filter Reference Sheet</div>
                    </div>
                    <div class="panel-wrapper">
                        <div class="oil-status-panel" id="filtersPanel">
                            <div id="filterAlerts" style="margin-bottom:10px;"></div>
                            <div style="display:flex;justify-content:flex-end;align-items:center;margin-bottom:8px;">
                                <button id="showAddFilterBtn" type="button">+ Add Filter</button>
                            </div>
                            <div id="filtersContainer">
                                <table id="filtersTable">
                                    <thead>
                                        <tr>
                                            <th>Filter</th>
                                            <th>Make</th>
                                            <th>Part Number</th>
                                            <th>Current Hours</th>
                                            <th>Filter Life</th>
                                            <th>Condition</th>
                                            <th style="width:110px;">Reset</th>
                                        </tr>
                                    </thead>
                                    <tbody id="filtersTbody">
                                        <tr><td colspan="7" style="color:#64748b">Select an equipment below to view filters.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div id="selectedInfo" class="selected-info outside-info" aria-live="polite"></div>
                        <div id="addFilterModal" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(2,6,23,0.45);z-index:1200;">
                            <div style="background:#fff;padding:18px;border-radius:10px;min-width:520px;max-width:95%;box-shadow:0 16px 48px rgba(2,6,23,0.3);">
                                <h3 style="margin:0 0 8px 0;">Add Filter</h3>
                                <div style="display:flex;flex-direction:column;gap:8px;">
                                    <input list="existingFiltersList" id="filterNameInput" name="filter_name" placeholder="Filter name" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;">
                                    <datalist id="existingFiltersList"></datalist>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <input id="makeInput" name="make" placeholder="Make" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:160px;">
                                        <input id="partNumberInput" name="part_number" placeholder="Part Number" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:160px;">
                                        <input id="filterLifeInput" name="filter_life" placeholder="Filter Life (hours)" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:160px;">
                                    </div>
                                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:6px;">
                                        <button id="submitAddFilter" type="button" class="btn" style="padding:8px 12px;border-radius:8px;background:#2563eb;color:#fff;border:none;">Save</button>
                                        <button id="cancelAddFilter" type="button" class="btn btn-ghost" style="padding:8px 12px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="editFilterModal" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(2,6,23,0.45);z-index:1200;">
                            <div style="background:#fff;padding:18px;border-radius:10px;min-width:520px;max-width:95%;box-shadow:0 16px 48px rgba(2,6,23,0.3);">
                                <h3 style="margin:0 0 8px 0;">Edit Filter</h3>
                                <input type="hidden" id="editFilterId">
                                <div style="display:flex;flex-direction:column;gap:8px;">
                                    <input id="editFilterName" placeholder="Filter name" list="existingFiltersList" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;">
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <input id="editMake" placeholder="Make" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:160px;">
                                        <input id="editPartNumber" placeholder="Part Number" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:160px;">
                                        <input id="editFilterLife" placeholder="Filter Life (hours)" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:160px;">
                                    </div>
                                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:6px;">
                                        <button id="submitEditFilter" type="button" class="btn" style="padding:8px 12px;border-radius:8px;background:#2563eb;color:#fff;border:none;">Save</button>
                                        <button id="deleteFilterBtn" type="button" class="parts-delete-btn" style="background:#ef4444;color:#fff;border:none;padding:8px 12px;border-radius:8px;">Delete</button>
                                        <button id="cancelEditFilter" type="button" class="btn btn-ghost" style="padding:8px 12px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="equipmentRibbon" style="position:fixed;left:50%;transform:translateX(-50%);bottom:18px;z-index:999;background:rgba(255,255,255,0.96);padding:8px 12px;border-radius:999px;box-shadow:0 6px 20px rgba(2,6,23,0.08);display:flex;gap:8px;align-items:center;max-width:95%;overflow:auto;"></div>
                </div>
            </main>
        </div>
    </div>
    <script>
        var INITIAL_EQUIPMENTS = <?php echo json_encode($equipments ?: []); ?>;
        var INITIAL_FILTERS = <?php echo json_encode($filtersByEquip ?: new stdClass()); ?>;
        var EXISTING_FILTER_NAMES = <?php echo json_encode($filterNames ?: []); ?>;
        var CURRENT_EQUIPMENT_ID = null;

        function formatCell(value) {
            return value === null || value === undefined || value === '' ? '—' : String(value);
        }

        var API_BASE = (function(){
            try {
                var path = location.pathname || '/';
                var idx = path.indexOf('/pages/');
                if (idx !== -1) { return location.origin + path.slice(0, idx) + '/'; }
                return location.origin + '/';
            } catch (e) { return location.origin + '/'; }
        })();

        function getEquipmentById(id) {
            return INITIAL_EQUIPMENTS.find(function(eq){ return Number(eq.equipment_id) === Number(id); }) || null;
        }

        function computeFilterMetrics(filter, equipment) {
            var eqHours = equipment ? parseFloat(equipment.current_hours) || 0 : 0;
            var lastReset = parseFloat(filter.hours) || 0;
            var life = parseFloat(filter.filter_life) || 0;
            var hoursSince = eqHours - lastReset;
            if (isNaN(hoursSince) || hoursSince < 0) { hoursSince = 0; }
            var condition = null;
            if (life > 0) {
                var usedPct = (hoursSince / life) * 100;
                condition = Math.max(0, Math.min(100, Math.round(100 - usedPct)));
            }
            return { hoursSince: hoursSince, life: life, condition: condition };
        }

        function renderFiltersFor(equipmentId) {
            var tbody = document.getElementById('filtersTbody');
            if (!tbody) return;
            tbody.innerHTML = '';
            var filters = INITIAL_FILTERS && INITIAL_FILTERS[equipmentId] ? INITIAL_FILTERS[equipmentId] : [];
            CURRENT_EQUIPMENT_ID = equipmentId;
            var equipment = getEquipmentById(equipmentId);
            var currentHours = equipment ? formatCell(equipment.current_hours) : '—';
            var warnAlerts = [];
            var urgentAlerts = [];
            if (!filters.length) {
                tbody.innerHTML = '<tr><td colspan="7" style="color:#64748b">No filters for this equipment yet.</td></tr>';
                updateHeading(equipment, equipmentId);
                setSelectedInfo(currentHours);
                updateFilterAlerts([], []);
                return;
            }
            filters.forEach(function(filter){
                var metrics = computeFilterMetrics(filter, equipment);
                var conditionText = metrics.condition === null ? '—' : metrics.condition + '%';
                if (metrics.condition !== null) {
                    if (metrics.condition <= 0) {
                        urgentAlerts.push(filter.filter_name || 'Filter');
                    } else if (metrics.condition < 20) {
                        warnAlerts.push(filter.filter_name || 'Filter');
                    }
                }
                var tr = document.createElement('tr');
                tr.innerHTML = '<td><div class="filter-name-cell"><span>' + formatCell(filter.filter_name) + '</span><button type="button" class="parts-action-btn" onclick="openEditFilterModal(' + (filter.filter_id || 0) + ',' + equipmentId + ')">Edit</button></div></td>' +
                    '<td>' + formatCell(filter.make) + '</td>' +
                    '<td>' + formatCell(filter.part_number) + '</td>' +
                    '<td>' + (metrics.hoursSince ? metrics.hoursSince.toFixed(1) : '0.0') + '</td>' +
                    '<td>' + (metrics.life ? metrics.life : '—') + '</td>' +
                    '<td>' + conditionText + '</td>' +
                    '<td><button type="button" class="filters-reset-btn" onclick="resetFilter(' + (filter.filter_id || 0) + ',' + equipmentId + ')">Reset</button></td>';
                tbody.appendChild(tr);
            });
            updateHeading(equipment, equipmentId);
            setSelectedInfo(currentHours);
            updateFilterAlerts(warnAlerts, urgentAlerts);
        }

        function setSelectedInfo(hours) {
            var info = document.getElementById('selectedInfo');
            if (!info) return;
            info.innerHTML = '<div class="hours-bubble">Current equipment hours: ' + formatCell(hours) + '</div>';
        }

        function updateFilterAlerts(warnList, urgentList) {
            var container = document.getElementById('filterAlerts');
            if (!container) return;
            container.innerHTML = '';
            if (urgentList && urgentList.length) {
                urgentList.forEach(function(name){
                    var alert = document.createElement('div');
                    alert.className = 'filter-alert urgent';
                    alert.textContent = 'Change ' + name + ' now.';
                    container.appendChild(alert);
                });
            } else if (warnList && warnList.length) {
                warnList.forEach(function(name){
                    var alert = document.createElement('div');
                    alert.className = 'filter-alert warn';
                    alert.textContent = 'Change ' + name + ' soon.';
                    container.appendChild(alert);
                });
            }
        }

        function buildRibbon() {
            var ribbon = document.getElementById('equipmentRibbon');
            if (!ribbon) return;
            ribbon.innerHTML = '';
            if (!INITIAL_EQUIPMENTS || !INITIAL_EQUIPMENTS.length) {
                ribbon.innerHTML = '<span style="color:#94a3b8;">No equipment found.</span>';
                return;
            }
            INITIAL_EQUIPMENTS.forEach(function(eq){
                var chip = document.createElement('button');
                chip.className = 'equipment-chip';
                chip.type = 'button';
                chip.dataset.eid = eq.equipment_id;
                chip.textContent = eq.number && eq.number !== '' ? eq.number : ('#' + eq.equipment_id);
                chip.addEventListener('click', function(){
                    var prev = document.querySelector('.equipment-chip.is-selected');
                    if (prev) { prev.classList.remove('is-selected'); }
                    chip.classList.add('is-selected');
                    renderFiltersFor(eq.equipment_id);
                });
                ribbon.appendChild(chip);
            });
            var first = ribbon.querySelector('.equipment-chip');
            var params = new URLSearchParams(location.search || '');
            var requested = params.get('id') || params.get('equipment_id');
            var initialChip = requested ? ribbon.querySelector('.equipment-chip[data-eid="' + requested + '"]') : null;
            if (initialChip) {
                initialChip.click();
            } else if (first) {
                first.click();
            }
        }

        function updateHeading(equip, equipmentId) {
            var heading = document.getElementById('equipmentHeading');
            var subtitle = document.getElementById('equipmentSubtitle');
            if (!heading) return;
            var label = '';
            if (equip) {
                label = (equip.number && equip.number !== '') ? equip.number : ('#' + equip.equipment_id);
                if (equip.type) { label += ' | ' + equip.type; }
            } else if (equipmentId) {
                label = '#' + equipmentId;
            }
            heading.textContent = label;
            if (subtitle) { subtitle.textContent = 'Air Filter Reference Sheet'; }
        }

        function renderExistingFiltersDatalist() {
            var dl = document.getElementById('existingFiltersList');
            if (!dl) return;
            dl.innerHTML = '';
            (EXISTING_FILTER_NAMES || []).forEach(function(name){
                var opt = document.createElement('option');
                opt.value = name;
                dl.appendChild(opt);
            });
        }

        function openAddFilterModal() {
            if (!CURRENT_EQUIPMENT_ID) {
                alert('Select an equipment before adding filters.');
                return;
            }
            renderExistingFiltersDatalist();
            var modal = document.getElementById('addFilterModal');
            if (modal) {
                modal.style.display = 'flex';
                setTimeout(function(){ document.getElementById('filterNameInput').focus(); }, 50);
            }
        }

        function closeAddFilterModal() {
            var modal = document.getElementById('addFilterModal');
            if (modal) { modal.style.display = 'none'; }
            ['filterNameInput','makeInput','partNumberInput','filterLifeInput'].forEach(function(id){ var el = document.getElementById(id); if (el) el.value = ''; });
        }

        function closeEditFilterModal() {
            var modal = document.getElementById('editFilterModal');
            if (modal) { modal.style.display = 'none'; }
        }

        function submitAddFilter() {
            if (!CURRENT_EQUIPMENT_ID) {
                alert('Select an equipment before adding filters.');
                return;
            }
            var btn = document.getElementById('submitAddFilter');
            if (!btn) return;
            btn.disabled = true;
            var orig = btn.textContent;
            btn.textContent = 'Saving...';
            var nameVal = document.getElementById('filterNameInput').value.trim();
            if (!nameVal) {
                alert('Filter name is required.');
                btn.disabled = false;
                btn.textContent = orig;
                return;
            }
            var data = new FormData();
            data.append('equipment_id', CURRENT_EQUIPMENT_ID);
            data.append('filter_name', nameVal);
            data.append('make', document.getElementById('makeInput').value);
            data.append('part_number', document.getElementById('partNumberInput').value);
            data.append('filter_life', document.getElementById('filterLifeInput').value);
            fetch(API_BASE + 'api/add_filter_info.php', { method:'POST', body:data, credentials:'same-origin' })
                .then(function(resp){ return resp.text().then(function(text){ try { return JSON.parse(text); } catch(e){ throw { type:'parse', text:text }; } }); })
                .then(function(json){
                    if (!json || !json.success) { throw new Error((json && json.error) ? json.error : 'Unable to save'); }
                    if (json.row) {
                        INITIAL_FILTERS[CURRENT_EQUIPMENT_ID] = INITIAL_FILTERS[CURRENT_EQUIPMENT_ID] || [];
                        INITIAL_FILTERS[CURRENT_EQUIPMENT_ID].push(json.row);
                        if (json.row.filter_name && EXISTING_FILTER_NAMES.indexOf(json.row.filter_name) === -1) {
                            EXISTING_FILTER_NAMES.push(json.row.filter_name);
                        }
                    }
                    renderFiltersFor(CURRENT_EQUIPMENT_ID);
                    closeAddFilterModal();
                })
                .catch(function(err){
                    console.error('Add filter error', err);
                    if (err && err.type === 'parse') {
                        alert('Error adding filter: invalid server response.');
                        console.error('Raw response:', err.text);
                    } else {
                        alert('Error adding filter: ' + (err && err.message ? err.message : 'Unknown error'));
                    }
                })
                .finally(function(){ btn.disabled = false; btn.textContent = orig; });
        }

        function openEditFilterModal(filterId, equipmentId) {
            var filters = INITIAL_FILTERS && INITIAL_FILTERS[equipmentId] ? INITIAL_FILTERS[equipmentId] : [];
            var filter = filters.find(function(item){ return Number(item.filter_id) === Number(filterId); });
            if (!filter) { alert('Filter not found'); return; }
            CURRENT_EQUIPMENT_ID = equipmentId;
            renderExistingFiltersDatalist();
            document.getElementById('editFilterId').value = filter.filter_id;
            document.getElementById('editFilterName').value = filter.filter_name || '';
            document.getElementById('editMake').value = filter.make || '';
            document.getElementById('editPartNumber').value = filter.part_number || '';
            document.getElementById('editFilterLife').value = filter.filter_life || '';
            var modal = document.getElementById('editFilterModal');
            if (modal) { modal.style.display = 'flex'; }
        }

        function submitEditFilter() {
            var id = document.getElementById('editFilterId').value;
            if (!id) { alert('Invalid filter id'); return; }
            var nameVal = document.getElementById('editFilterName').value.trim();
            if (!nameVal) { alert('Filter name is required.'); return; }
            var data = new FormData();
            data.append('filter_id', id);
            data.append('filter_name', nameVal);
            data.append('make', document.getElementById('editMake').value);
            data.append('part_number', document.getElementById('editPartNumber').value);
            data.append('filter_life', document.getElementById('editFilterLife').value);
            fetch(API_BASE + 'api/update_filter_info.php', { method:'POST', body:data, credentials:'same-origin' })
                .then(function(resp){ return resp.text().then(function(text){ try { return JSON.parse(text); } catch(e){ throw { type:'parse', text:text }; } }); })
                .then(function(json){
                    if (!json || !json.success) { throw new Error((json && json.error) ? json.error : 'Update failed'); }
                    if (json.row) {
                        for (var key in INITIAL_FILTERS) {
                            if (!INITIAL_FILTERS.hasOwnProperty(key)) continue;
                            INITIAL_FILTERS[key] = INITIAL_FILTERS[key].map(function(item){ return Number(item.filter_id) === Number(json.row.filter_id) ? json.row : item; });
                        }
                    }
                    closeEditFilterModal();
                    if (CURRENT_EQUIPMENT_ID) { renderFiltersFor(CURRENT_EQUIPMENT_ID); }
                })
                .catch(function(err){
                    console.error('Update filter error', err);
                    if (err && err.type === 'parse') {
                        alert('Error updating filter: invalid server response.');
                        console.error('Raw response:', err.text);
                    } else {
                        alert('Error updating filter: ' + (err && err.message ? err.message : 'Unknown error'));
                    }
                });
        }

        function submitDeleteFilter() {
            var id = document.getElementById('editFilterId').value;
            if (!id) { alert('Invalid filter id'); return; }
            if (!confirm('Delete this filter? This action cannot be undone.')) return;
            var data = new FormData();
            data.append('id', id);
            fetch(API_BASE + 'api/delete_filter_info.php', { method:'POST', body:data, credentials:'same-origin' })
                .then(function(resp){ return resp.text().then(function(text){ try { return JSON.parse(text); } catch(e){ throw { type:'parse', text:text }; } }); })
                .then(function(json){
                    if (!json || !json.success) { throw new Error((json && json.error) ? json.error : 'Delete failed'); }
                    for (var key in INITIAL_FILTERS) {
                        if (!INITIAL_FILTERS.hasOwnProperty(key)) continue;
                        INITIAL_FILTERS[key] = INITIAL_FILTERS[key].filter(function(item){ return Number(item.filter_id) !== Number(id); });
                    }
                    closeEditFilterModal();
                    if (CURRENT_EQUIPMENT_ID) { renderFiltersFor(CURRENT_EQUIPMENT_ID); }
                })
                .catch(function(err){
                    console.error('Delete filter error', err);
                    if (err && err.type === 'parse') {
                        alert('Error deleting filter: invalid server response.');
                        console.error('Raw response:', err.text);
                    } else {
                        alert('Error deleting filter: ' + (err && err.message ? err.message : 'Unknown error'));
                    }
                });
        }

        function resetFilter(filterId, equipmentId) {
            if (!filterId) return;
            if (!confirm('Reset this filter to the equipment\'s current hours?')) return;
            var data = new FormData();
            data.append('id', filterId);
            fetch(API_BASE + 'api/reset_filter_hours.php', { method:'POST', body:data, credentials:'same-origin' })
                .then(function(resp){ return resp.text().then(function(text){ try { return JSON.parse(text); } catch(e){ throw { type:'parse', text:text }; } }); })
                .then(function(json){
                    if (!json || !json.success) { throw new Error((json && json.error) ? json.error : 'Reset failed'); }
                    if (json.row) {
                        for (var key in INITIAL_FILTERS) {
                            if (!INITIAL_FILTERS.hasOwnProperty(key)) continue;
                            INITIAL_FILTERS[key] = INITIAL_FILTERS[key].map(function(item){ return Number(item.filter_id) === Number(json.row.filter_id) ? json.row : item; });
                        }
                    }
                    if (equipmentId) { renderFiltersFor(equipmentId); }
                })
                .catch(function(err){
                    console.error('Reset filter error', err);
                    if (err && err.type === 'parse') {
                        alert('Error resetting filter: invalid server response.');
                        console.error('Raw response:', err.text);
                    } else {
                        alert('Error resetting filter: ' + (err && err.message ? err.message : 'Unknown error'));
                    }
                });
        }

        document.addEventListener('DOMContentLoaded', function(){
            buildRibbon();
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
            var addBtn = document.getElementById('showAddFilterBtn');
            if (addBtn) { addBtn.addEventListener('click', openAddFilterModal); }
            var cancelAdd = document.getElementById('cancelAddFilter');
            if (cancelAdd) { cancelAdd.addEventListener('click', function(){ closeAddFilterModal(); }); }
            var submitAdd = document.getElementById('submitAddFilter');
            if (submitAdd) { submitAdd.addEventListener('click', submitAddFilter); }
            var cancelEdit = document.getElementById('cancelEditFilter');
            if (cancelEdit) { cancelEdit.addEventListener('click', closeEditFilterModal); }
            var submitEdit = document.getElementById('submitEditFilter');
            if (submitEdit) { submitEdit.addEventListener('click', submitEditFilter); }
            var deleteBtn = document.getElementById('deleteFilterBtn');
            if (deleteBtn) { deleteBtn.addEventListener('click', submitDeleteFilter); }
            var addModal = document.getElementById('addFilterModal');
            if (addModal) {
                addModal.addEventListener('click', function(evt){ if (evt.target === addModal) { closeAddFilterModal(); } });
            }
            var editModal = document.getElementById('editFilterModal');
            if (editModal) {
                editModal.addEventListener('click', function(evt){ if (evt.target === editModal) { closeEditFilterModal(); } });
            }
            document.addEventListener('keydown', function(evt){
                if (evt.key === 'Escape') {
                    closeAddFilterModal();
                    closeEditFilterModal();
                }
            });
            renderExistingFiltersDatalist();
        });
    </script>
    <script src="../../assets/js/mobile-menu.js"></script>
    <script src="../../assets/js/logout-confirm.js"></script>
</body>
</html>