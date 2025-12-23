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
$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$actualRole = $user ? $user['role'] : 'laborer';

// Check if developer is previewing as another role
if ($actualRole === 'developer' && isset($_GET['preview_role'])) {
    $role = $_GET['preview_role'];
} else {
    $role = $actualRole;
}

$stmt->close();

// Preserve preview mode in URLs
$previewParam = '';
if (isset($_GET['preview_role'])) {
    $previewParam = '?preview_role=' . urlencode($_GET['preview_role']);
}

// Handle save changes POST request (move this block to the top to avoid headers already sent)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_changes']) && isset($_POST['equipment_id'])) {
    $fields = [
        'dhcst_equipment_number', 'dhss_equipment_number', 'type', 'make', 'model', 'engine', 'engine_serial_number',
        'vehicle_year', 'vin', 'transmission', 'trans_serial_number', 'location', 'operating_condition', 'oil_status'
    ];
    $updates = [];
    $params = [];
    $types = '';
    foreach ($fields as $field) {
        $updates[] = "$field = ?";
        $params[] = $_POST[$field] ?? '';
        $types .= 's';
    }
    $params[] = $_POST['equipment_id'];
    $types .= 'i';
    $sql = 'UPDATE equipments SET ' . implode(', ', $updates) . ' WHERE equipment_id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    // Redirect to view mode after save
    $redirectUrl = 'equipment.php?id=' . $_POST['equipment_id'];
    // Preserve preview_role from POST (if submitted via form) or GET (if still in URL)
    $previewRole = $_POST['preview_role'] ?? $_GET['preview_role'] ?? null;
    if ($previewRole) {
        $redirectUrl .= '&preview_role=' . urlencode($previewRole);
    }
    header('Location: ' . $redirectUrl);
    exit();
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_equipment']) && isset($_POST['equipment_id'])) {
    $deleteId = (int)$_POST['equipment_id'];
    if ($deleteId > 0) {
        $stmt = $conn->prepare('DELETE FROM equipments WHERE equipment_id = ?');
        $stmt->bind_param('i', $deleteId);
        $stmt->execute();
        $redirectUrl = 'index.php';
        // Preserve preview_role from POST (if submitted via form) or GET (if still in URL)
        $previewRole = $_POST['preview_role'] ?? $_GET['preview_role'] ?? null;
        if ($previewRole) {
            $redirectUrl .= '?preview_role=' . urlencode($previewRole);
        }
        header('Location: ' . $redirectUrl);
        exit();
    }
}

// Get equipment ID from query string
$equipmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($equipmentId <= 0) {
    echo "<h2>Invalid equipment ID.</h2>";
    exit();
}

// Fetch equipment details
$stmt = $conn->prepare('SELECT * FROM equipments WHERE equipment_id = ? LIMIT 1');
$stmt->bind_param('i', $equipmentId);
$stmt->execute();
$res = $stmt->get_result();
$equipment = $res ? $res->fetch_assoc() : null;

if (!$equipment) {
    echo "<h2>Equipment not found.</h2>";
    exit();
}

// Check if in edit mode
$editMode = isset($_GET['edit']) && $_GET['edit'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Equipment Details - <?php echo htmlspecialchars($equipment['dhcst_equipment_number'] ?? 'N/A'); ?></title>
    <link rel="stylesheet" href="../../assets/css/base.css" />
    <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
    <link rel="stylesheet" href="../../assets/css/dashboard.css" />
    <style>
/* ================================
   Equipment Details – Clean Layout
   ================================ */

/* ---------- TABLE WRAPPER ---------- */


/* ---------- MAIN DETAILS TABLE ---------- */

.equipment-details-table {
    table-layout: fixed;
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95em;
    text-align: left;
}

/* ---------- LABEL (GRAY) CELLS ---------- */

.equipment-label-cell {
    width: 220px;
    min-width: 220px;
    max-width: 220px;

    background: #f3f6f9;
    color: #1f2937;
    font-weight: 700;

    padding: 6px 12px;
    text-align: left;
    vertical-align: middle;
    white-space: nowrap;

    border-right: 1px solid #e2e8f0;
}

/* ---------- VALUE (BLUE) CELLS ---------- */

.equipment-value-cell {
    background: #eef7ff;
    color: #0b5ed7;
    font-weight: 600;

    padding: 6px 16px;
    text-align: left;
    vertical-align: middle;

    width: auto;
}

/* Right-side value column divider */
.equipment-value-cell--border {
    background: #e6f2ff;
    border-left: 2px solid #d0dae3;
}

/* Empty value handling */
.equipment-value-cell:empty::before {
    content: "—";
    color: #9ca3af;
    font-weight: 400;
}

/* ---------- BACK BUTTON ---------- */

/* Back navigation placement */


/* Back button styling */
.equipment-back-btn-wrapper--top-left {
    margin-bottom: 18px;
}

.equipment-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    background: #2563eb;
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    transition: background 0.2s, transform 0.1s;
    border: none;
    cursor: pointer;
}

.equipment-back-btn:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}

.equipment-back-btn:active {
    transform: translateY(0);
}

.equipment-tab {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;

    background: transparent;
    font-size: 12px;
    font-weight: 800;
    color: #64748b;

    cursor: pointer;
    white-space: nowrap;
}

.equipment-tab:hover {
    background: #e2e8f0;
}

.equipment-tab.active {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* ---------- HISTORY CARD ---------- */

.equipment-card {
    background: #ffffff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 10px;
    padding: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    width: 100%;
    max-width: none;
    min-width: 0;
    box-sizing: border-box;
}

.equipment-card-title {
    font-size: 13px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 10px;
    text-transform: uppercase;
}

/* ---------- HISTORY TABLE ---------- */

.equipment-history-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    min-width: 0;
    box-sizing: border-box;
}

.equipment-history-table th {
    padding: 10px;
    text-align: left;
    background: #f8fafc;
    border-bottom: 2px solid #e5e7eb;

    font-size: 11px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
}

.equipment-history-table td {
    padding: 10px;
    border-bottom: 1px solid #eef2f7;
}

/* ---------- RESPONSIVE ---------- */

@media (max-width: 900px) {
    .equipment-detail-page {
        padding: 20px;
    }

    .equipment-details-table {
        font-size: 13px;
    }

    .equipment-label-cell {
        width: 180px;
        min-width: 180px;
        max-width: 180px;
    }
}

/* Remove extra top padding and margin */
.equipment-details-table-wrapper,
.equipment-section-separator,
.equipment-back-btn-wrapper--top-left {
  margin-left: 0 !important;
}

.equipment-details-table-wrapper {
  max-width: none;
  width: 100%;
  margin-left: 0;
}

.equipment-section-separator {
  border: none;
  border-top: 2px solid #e5e7eb;
  margin: 56px 0 40px 0;
  width: 100%;
  margin-left: 0;
}

/* Equipment history card and table full width */
.equipment-card {
  width: 100%;
  max-width: 100%;
  min-width: 0;
  box-sizing: border-box;
  margin-left: 0;
  margin-right: 0;
}
.equipment-history-table {
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

/* Remove extra padding/margin from the card wrapper if present */
.equipment-future-section {
  padding-left: 0;
  padding-right: 0;
  margin-left: 0;
  margin-right: 0;
  width: 100%;
  max-width: none;
}

/* Remove border radius and box-shadow for flush look if needed */
.equipment-card {
  border-radius: 0;
  box-shadow: none;
}

/* Remove left margin from the card if present */
.equipment-card,
.equipment-future-section {
  margin-left: 0 !important;
}

/* Add to <style> block */
.equipment-actions-box {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-left: 32px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 16px 16px 16px 16px;
  min-width: 160px;
  box-shadow: 0 2px 8px rgba(2,6,23,0.07);
  align-items: stretch;
}
.equipment-action-btn {
  padding: 7px 0;
  border-radius: 5px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  border: 1px solid #d1d5db;
  background: #f4f6fa;
  color: #334155;
  transition: all 0.2s;
  margin-bottom: 0;
  box-shadow: none;
  letter-spacing: 0.01em;
}
.equipment-action-btn:hover {
  background: #e9eef5;
  color: #1e293b;
}
.equipment-action-btn--primary {
  background: #e0e7ef;
  border-color: #cbd5e1;
  color: #2563eb;
}
.equipment-action-btn--primary:hover {
  background: #cbd5e1;
  color: #1d4ed8;
}
.equipment-editable-cell {
  width: 100%;
  padding: 8px 12px;
  font-size: 15px;
  border-radius: 6px;
  border: 1px solid #d1d5db;
  background: #eef7ff;
  color: #0b5ed7;
  box-sizing: border-box;
  margin: 0;
  outline: none;
  transition: border 0.2s, background 0.2s;
}
.equipment-editable-cell:focus {
  background: #e0e7ef;
  border-color: #2563eb;
}
.equipment-value-cell {
  vertical-align: middle;
}
/* ================================
   FINAL SPACING FIXES
   ================================ */

/* Remove extra top gap from admin layout */
.content-area {
  padding-top: 0 !important;
}

/* Pull equipment page closer to header */
.equipment-detail-page {
  padding-top: 0 !important;
}

/* Align back button fully left */
.equipment-back-btn-wrapper--top-left {
  margin-left: 0 !important;
  padding-left: 0 !important;
  display: flex;
  justify-content: flex-start;
}

/* Ensure page content aligns left consistently */
.equipment-details-table-wrapper,
.equipment-future-section,
.equipment-card {
  margin-left: 0 !important;
}
/* ================================
   GLOBAL PAGE SPACING (REDUCED)
   ================================ */

/* Main content wrapper */
.content-area {
  padding: 10px 14px !important; /* was 20px 28px */
}

/* Limit width for readability */
.equipment-detail-page {
  max-width: 1400px;
  margin: 0 auto;
}

/* Add subtle breathing room around tables */
.equipment-details-table-wrapper,
.equipment-history-table-wrapper {
  margin-top: 6px;        /* was 12px */
  padding: 6px 8px;      /* was 12px 16px */
  background: #ffffff;
  border-radius: 8px;    /* slightly reduced for tighter look */
}

.equipment-tabs-container {
  width: 800px; /* Adjust as needed */
  margin: 0 auto 16px auto;
  max-width: 100%;
}

/* Edit button styling */
.equipment-history-row {
    position: relative;
}
.equipment-history-edit-cell {
    padding: 10px !important;
    width: auto;
    min-width: 120px;
    text-align: left;
    white-space: nowrap;
}
.equipment-history-edit-btn {
    opacity: 0;
    background: #2563eb;
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 14px;
    cursor: pointer;
    transition: opacity 0.2s, background 0.2s;
    vertical-align: middle;
    margin-right: 6px;
}
.equipment-history-row:hover .equipment-history-edit-btn {
    opacity: 1;
}
.equipment-history-edit-btn:hover {
    background: #1d4ed8;
}
.equipment-edited-copy-badge {
    display: inline-block;
    background: #dbeafe;
    color: #1e40af;
    font-size: 0.85em;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
    white-space: nowrap;
    margin-right: 6px;
    border: 1px solid #93c5fd;
    vertical-align: middle;
}
.equipment-copy-toggle {
    cursor: pointer;
    transition: background 0.2s;
}
.equipment-copy-toggle:hover {
    background: #bfdbfe;
}
.equipment-history-original-hidden {
    display: none;
}
.equipment-history-copy-row {
    background: #f8fafc;
}

/* Modal styling */
.equipment-modal {
    position: fixed;
    z-index: 1000;
    left: 0; top: 0; right: 0; bottom: 0;
    background: rgba(30,41,59,0.18);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s;
    padding: 20px;
}
.equipment-modal__dialog {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 32px #0002;
    min-width: 520px;
    max-width: 640px;
    width: 100%;
    max-height: calc(100vh - 40px);
    padding: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.equipment-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f1f5f9;
    padding: 12px 18px;
    border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
}
.equipment-modal__title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #222;
    margin: 0;
}
.equipment-icon-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #64748b;
    cursor: pointer;
    padding: 0 2px;
    margin-left: 8px;
    border-radius: 4px;
    transition: background 0.15s;
}
.equipment-icon-btn:hover {
    background: #e5e7eb;
}
.equipment-form {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
}
.equipment-form__grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px 20px;
    padding: 18px 20px 0 20px;
}
.equipment-form__field label {
    font-weight: 600;
    color: #222;
    margin-bottom: 5px;
    display: block;
    font-size: 0.85rem;
}
.equipment-form__field input,
.equipment-form__field select,
.equipment-form__field textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.95rem;
    background: #f8fafc;
    color: #222;
    margin-top: 2px;
    box-sizing: border-box;
    transition: border 0.15s;
}
.equipment-form__field input:focus,
.equipment-form__field select:focus,
.equipment-form__field textarea:focus {
    border: 1.5px solid #2563eb;
    outline: none;
    background: #fff;
}
.equipment-form__field input[readonly],
.equipment-form__field textarea[readonly] {
    background: #f1f5f9 !important;
    color: #64748b !important;
    cursor: not-allowed;
}
.equipment-form__field select[disabled] {
    background: #f1f5f9 !important;
    color: #64748b !important;
    cursor: not-allowed;
}
.equipment-form__actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 20px;
    background: #f8fafc;
    border-top: 1px solid #e5e7eb;
    flex-shrink: 0;
}
.equipment-btn {
    padding: 8px 22px;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 600;
    border: none;
    background: #2563eb;
    color: #fff;
    cursor: pointer;
    transition: background 0.15s;
}
.equipment-btn--secondary {
    background: #e5e7eb;
    color: #222;
}
.equipment-btn:hover {
    background: #1d4ed8;
}
.equipment-btn--secondary:hover {
    background: #cbd5e1;
}
.equipment-btn[style*="background: #dc2626"]:hover {
    background: #b91c1c !important;
}
.equipment-form__error {
    color: #dc2626;
    font-weight: 600;
    margin-top: 8px;
    text-align: center;
}

/* Add red border for red status */
.equipment-details-table-wrapper--red {
    border: 3px solid #e53935 !important;
    box-shadow: 0 0 0 2px #ffb3b3;
    border-radius: 10px;
}
    </style>
</head>
<body class="admin-page">
    <div class="admin-container">
        <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
        <div class="admin-layout">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
            <main class="content-area">
                <div class="equipment-detail-page">
                    <div class="equipment-back-btn-wrapper equipment-back-btn-wrapper--top-left">
                        <a href="index.php<?php echo $previewParam; ?>" class="equipment-back-btn">
                            <span>←</span>
                            <span>Back to Equipments</span>
                        </a>
                    </div>
                    <?php if ($editMode) { ?>
<form method="POST" style="margin-bottom:0;">
<?php if (isset($_GET['preview_role'])): ?>
<input type="hidden" name="preview_role" value="<?php echo htmlspecialchars($_GET['preview_role']); ?>" />
<?php endif; ?>
<?php } ?>
<div style="display: flex; justify-content: flex-start; align-items: center; margin-bottom: 18px; gap: 12px;">
    <?php if (!$editMode) { ?>
        <a href="equipment.php?id=<?php echo $equipmentId; ?>&edit=1<?php echo isset($_GET['preview_role']) ? '&preview_role=' . urlencode($_GET['preview_role']) : ''; ?>" class="equipment-action-btn equipment-action-btn--primary" style="min-width: 80px; padding: 8px 20px; font-size: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.07);">Edit</a>
    <?php } else { ?>
        <button class="equipment-action-btn equipment-action-btn--primary" type="submit" name="save_changes" style="min-width: 80px; padding: 8px 20px; font-size: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.07);">Save Changes</button>
        <a href="equipment.php?id=<?php echo $equipmentId; ?><?php echo isset($_GET['preview_role']) ? '&preview_role=' . urlencode($_GET['preview_role']) : ''; ?>" class="equipment-action-btn" style="background:#f3f6f9;color:#2563eb;border-color:#d1d5db;min-width:80px;padding:8px 20px;font-size:15px;box-shadow:0 1px 3px rgba(0,0,0,0.07);text-decoration:none;">Cancel</a>
        <input type="hidden" name="equipment_id" value="<?php echo $equipmentId; ?>" />
        <button type="submit" name="delete_equipment" class="equipment-action-btn" style="background:#ef4444;color:#fff;border-color:#ef4444;min-width:80px;padding:8px 20px;font-size:15px;box-shadow:0 1px 3px rgba(0,0,0,0.07);" onclick="return confirm('Are you sure you want to delete this equipment?');">Delete</button>
    <?php } ?>
</div>
<?php
$isRedStatus = ($equipment['operating_condition'] ?? '') === 'red' || ($equipment['oil_status'] ?? '') === 'red';
?>
<div class="equipment-details-table-wrapper<?php if ($isRedStatus) echo ' equipment-details-table-wrapper--red'; ?>">
    <div class="equipment-details-row" style="display: flex; align-items: flex-start;">
      <div class="equipment-details-table-wrapper" style="flex: 1;">
        <table class="equipment-details-table">
            <tbody>
                <tr>
                    <th class="equipment-label-cell">DHCST Equipment number</th>
                    <td class="equipment-value-cell">
                        <?php if ($editMode) { ?>
                            <input type="text" name="dhcst_equipment_number" value="<?php echo htmlspecialchars($equipment['dhcst_equipment_number'] ?? ''); ?>" class="equipment-editable-cell" />
                        <?php } else { ?>
                            <?php echo htmlspecialchars($equipment['dhcst_equipment_number'] ?? ''); ?>
                        <?php } ?>
                    </td>
                    <th class="equipment-label-cell">Make</th>
                    <td class="equipment-value-cell equipment-value-cell--border">
                        <?php if ($editMode) { ?>
                            <input type="text" name="make" value="<?php echo htmlspecialchars($equipment['make'] ?? ''); ?>" class="equipment-editable-cell" />
                        <?php } else { ?>
                            <?php echo htmlspecialchars($equipment['make'] ?? ''); ?>
                        <?php } ?>
                    </td>
                </tr>
                <tr>
                    <th class="equipment-label-cell">DHSS Equipment number</th>
                    <td class="equipment-value-cell">
                        <?php if ($editMode) { ?>
                            <input type="text" name="dhss_equipment_number" value="<?php echo htmlspecialchars($equipment['dhss_equipment_number'] ?? ''); ?>" class="equipment-editable-cell" />
                        <?php } else { ?>
                            <?php echo htmlspecialchars($equipment['dhss_equipment_number'] ?? ''); ?>
                        <?php } ?>
                    </td>
                    <th class="equipment-label-cell">Model</th>
                    <td class="equipment-value-cell equipment-value-cell--border">
                        <?php if ($editMode) { ?>
                            <input type="text" name="model" value="<?php echo htmlspecialchars($equipment['model'] ?? ''); ?>" class="equipment-editable-cell" />
                        <?php } else { ?>
                            <?php echo htmlspecialchars($equipment['model'] ?? ''); ?>
                        <?php } ?>
                    </td>
                </tr>
                <tr>
                    <th class="equipment-label-cell">Type</th>
                    <td class="equipment-value-cell">
                        <?php if ($editMode) { ?>
                            <input type="text" name="type" value="<?php echo htmlspecialchars($equipment['type'] ?? ''); ?>" class="equipment-editable-cell" />
                        <?php } else { ?>
                            <?php echo htmlspecialchars($equipment['type'] ?? ''); ?>
                        <?php } ?>
                    </td>
                    <th class="equipment-label-cell">Engine</th>
                    <td class="equipment-value-cell equipment-value-cell--border">
                        <?php if ($editMode) { ?>
                            <input type="text" name="engine" value="<?php echo htmlspecialchars($equipment['engine'] ?? ''); ?>" class="equipment-editable-cell" />
                        <?php } else { ?>
                            <?php echo htmlspecialchars($equipment['engine'] ?? ''); ?>
                        <?php } ?>
                    </td>
                </tr>
                <tr>
                    <th class="equipment-label-cell">Year</th>
                    <td class="equipment-value-cell">
                        <?php if ($editMode) { ?>
                            <input type="text" name="vehicle_year" value="<?php echo htmlspecialchars($equipment['vehicle_year'] ?? ''); ?>" class="equipment-editable-cell" />
                        <?php } else { ?>
                            <?php echo htmlspecialchars($equipment['vehicle_year'] ?? ''); ?>
                        <?php } ?>
                    </td>
                    <th class="equipment-label-cell">Engine Serial Number</th>
                    <td class="equipment-value-cell equipment-value-cell--border">
                        <?php if ($editMode) { ?>
                            <input type="text" name="engine_serial_number" value="<?php echo htmlspecialchars($equipment['engine_serial_number'] ?? ''); ?>" class="equipment-editable-cell" />
                        <?php } else { ?>
                            <?php echo htmlspecialchars($equipment['engine_serial_number'] ?? ''); ?>
                        <?php } ?>
                    </td>
                </tr>
                <tr>
                    <th class="equipment-label-cell">Vin</th>
                    <td class="equipment-value-cell">
                        <?php if ($editMode) { ?>
                            <input type="text" name="vin" value="<?php echo htmlspecialchars($equipment['vin'] ?? ''); ?>" class="equipment-editable-cell" />
                        <?php } else { ?>
                            <?php echo htmlspecialchars($equipment['vin'] ?? ''); ?>
                        <?php } ?>
                    </td>
                    <th class="equipment-label-cell">Transmission</th>
                    <td class="equipment-value-cell equipment-value-cell--border">
                        <?php if ($editMode) { ?>
                            <input type="text" name="transmission" value="<?php echo htmlspecialchars($equipment['transmission'] ?? ''); ?>" class="equipment-editable-cell" />
                        <?php } else { ?>
                            <?php echo htmlspecialchars($equipment['transmission'] ?? ''); ?>
                        <?php } ?>
                    </td>
                </tr>
                <tr>
                    <th class="equipment-label-cell">Location</th>
                    <td class="equipment-value-cell">
                        <?php if ($editMode) { ?>
                            <input type="text" name="location" value="<?php echo htmlspecialchars($equipment['location'] ?? ''); ?>" class="equipment-editable-cell" />
                        <?php } else { ?>
                            <?php echo htmlspecialchars($equipment['location'] ?? ''); ?>
                        <?php } ?>
                    </td>
                    <th class="equipment-label-cell">Trans Serial Number</th>
                    <td class="equipment-value-cell equipment-value-cell--border">
                        <?php if ($editMode) { ?>
                            <input type="text" name="trans_serial_number" value="<?php echo htmlspecialchars($equipment['trans_serial_number'] ?? ''); ?>" class="equipment-editable-cell" />
                        <?php } else { ?>
                            <?php echo htmlspecialchars($equipment['trans_serial_number'] ?? ''); ?>
                        <?php } ?>
                    </td>
                </tr>
                <tr>
                    <th class="equipment-label-cell">Engine Operating Condition</th>
                    <td class="equipment-value-cell">
                        <?php if ($editMode) { ?>
                            <select name="operating_condition" class="equipment-editable-cell">
                                <option value="green" <?php if (($equipment['operating_condition'] ?? '') === 'green') echo 'selected'; ?>>Green</option>
                                <option value="yellow" <?php if (($equipment['operating_condition'] ?? '') === 'yellow') echo 'selected'; ?>>Yellow</option>
                                <option value="red" <?php if (($equipment['operating_condition'] ?? '') === 'red') echo 'selected'; ?>>Red</option>
                            </select>
                        <?php } else {
                            $val = trim((string)($equipment['operating_condition'] ?? ''));
                            $svgMap = [
                                'green' => 'greenengine.svg',
                                'yellow' => 'yellowengine.svg',
                                'red' => 'redengine.svg'
                            ];
                            if ($val !== '' && isset($svgMap[$val])) {
                                echo '<img src="images/' . htmlspecialchars($svgMap[$val]) . '" alt="' . htmlspecialchars($val) . ' engine" style="height:28px;vertical-align:middle;" />';
                            } else {
                                echo '<span class="equipment-pill equipment-pill--neutral">—</span>';
                            }
                        } ?>
                    </td>
                    <th class="equipment-label-cell">Oil Status</th>
                    <td class="equipment-value-cell equipment-value-cell--border">
                        <?php if ($editMode) { ?>
                            <select name="oil_status" class="equipment-editable-cell">
                                <option value="green" <?php if (($equipment['oil_status'] ?? '') === 'green') echo 'selected'; ?>>Green</option>
                                <option value="yellow" <?php if (($equipment['oil_status'] ?? '') === 'yellow') echo 'selected'; ?>>Yellow</option>
                                <option value="red" <?php if (($equipment['oil_status'] ?? '') === 'red') echo 'selected'; ?>>Red</option>
                            </select>
                        <?php } else {
                            $val = trim((string)($equipment['oil_status'] ?? ''));
                            $oilSvgMap = [
                                'green' => 'greenoil.svg',
                                'yellow' => 'yellowoil.svg',
                                'red' => 'redoil.svg'
                            ];
                            if ($val !== '' && isset($oilSvgMap[$val])) {
                                echo '<img src="images/' . htmlspecialchars($oilSvgMap[$val]) . '" alt="' . htmlspecialchars($val) . ' oil" style="height:28px;vertical-align:middle;" />';
                            } else {
                                echo '<span class="equipment-pill equipment-pill--neutral">—</span>';
                            }
                        } ?>
                    </td>
                </tr>
            </tbody>
        </table>
      </div>
    </div>
</div>
<input type="hidden" name="equipment_id" value="<?php echo $equipmentId; ?>" />
</form>

                    <hr class="equipment-section-separator" />
                    <!-- Future Sections Placeholder -->
                    <div class="equipment-future-section">
                        <div class="equipment-tabs-container">
      <div class="equipment-tabs">
        <button class="equipment-tab">Filters</button>
        <button class="equipment-tab">Tires</button>
        <button class="equipment-tab">Oil</button>
        <button class="equipment-tab">Manuals</button>
        <button class="equipment-tab">Warranty</button>
        <button class="equipment-tab">Parts</button>
        <button class="equipment-tab">Dimensions</button>
        <button class="equipment-tab">Photos</button>
      </div>
    </div>

                        <div style="display: flex; justify-content: flex-start; align-items: center; margin-bottom: 8px;">
                            <button id="new-issue-btn" style="padding: 8px 18px; border-radius: 6px; background: #2563eb; color: #fff; font-weight: 700; font-size: 15px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.07); cursor: pointer; transition: background 0.2s;">New Issue</button>
                        </div>
                        
                        <!-- New Issue Modal -->
                        <div id="newIssueModal" class="equipment-modal" style="display:none;" aria-hidden="true">
                            <div class="equipment-modal__dialog" role="dialog" aria-modal="true" aria-label="Create new issue">
                                <div class="equipment-modal__header">
                                    <h3 class="equipment-modal__title">Create New Issue</h3>
                                    <button id="closeNewIssueModal" class="equipment-icon-btn" type="button" aria-label="Close">×</button>
                                </div>
                                <form id="newIssueForm" class="equipment-form" autocomplete="off">
                                    <input type="hidden" id="issue_equipment_id" name="equipment_id" value="<?php echo (int)$equipmentId; ?>" />
                                    <div class="equipment-form__grid" style="grid-template-columns: 1fr 1fr;">
                                        <div class="equipment-form__field">
                                            <label for="issue_date_reported">Date Reported</label>
                                            <input id="issue_date_reported" name="date_reported" type="datetime-local" required />
                                        </div>
                                        <div class="equipment-form__field">
                                            <label for="issue_reported_by">Reported by</label>
                                            <input id="issue_reported_by" name="reported_by" type="text" list="reportedByList" autocomplete="off" required />
                                            <datalist id="reportedByList">
                                                <!-- JS will populate with user names -->
                                            </datalist>
                                        </div>
                                        <div class="equipment-form__field" style="grid-column: span 2;">
                                            <label for="issue_reported_issues">Reported Issues</label>
                                            <textarea id="issue_reported_issues" name="reported_issues" rows="3" style="width:100%;resize:vertical;" required></textarea>
                                        </div>
                                        <div class="equipment-form__field">
                                            <label for="issue_equipment_location">Equipment Location</label>
                                            <input id="issue_equipment_location" name="equipment_location" type="text" required />
                                        </div>
                                        <div class="equipment-form__field">
                                            <label for="issue_operating_condition">Operating Condition</label>
                                            <select id="issue_operating_condition" name="operating_condition" required>
                                                <option value="">Select...</option>
                                                <option value="green">Green</option>
                                                <option value="yellow">Yellow</option>
                                                <option value="red">Red</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="equipment-form__actions" style="margin-top:18px;">
                                        <button id="cancelNewIssue" class="equipment-btn equipment-btn--secondary" type="button">Cancel</button>
                                        <button id="saveNewIssue" class="equipment-btn" type="submit">Save</button>
                                    </div>
                                    <div id="newIssueError" class="equipment-form__error" role="alert" style="display:none;"></div>
                                </form>
                            </div>
                        </div>

                        <!-- Edit Issue Modal -->
                        <div id="editIssueModal" class="equipment-modal" style="display:none;" aria-hidden="true">
                            <div class="equipment-modal__dialog" role="dialog" aria-modal="true" aria-label="Edit issue">
                                <div class="equipment-modal__header">
                                    <h3 class="equipment-modal__title">Edit Issue</h3>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <button id="editTopFieldsBtn" class="equipment-btn equipment-btn--secondary" type="button" style="padding: 6px 16px; font-size: 0.9rem;">Edit</button>
                                        <button id="closeEditIssueModal" class="equipment-icon-btn" type="button" aria-label="Close">×</button>
                                    </div>
                                </div>
                                <form id="editIssueForm" class="equipment-form" autocomplete="off">
                                    <input type="hidden" id="edit_equipment_id" name="equipment_id" value="<?php echo (int)$equipmentId; ?>" />
                                    <input type="hidden" id="edit_issue_id" name="issue_id" value="" />
                                    <input type="hidden" id="edit_original_date_reported" name="original_date_reported" value="" />
                                    <div class="equipment-form__grid" style="grid-template-columns: 1fr 1fr;">
                                        <div class="equipment-form__field">
                                            <label for="edit_date_reported">Date Reported</label>
                                            <input id="edit_date_reported" name="date_reported" type="datetime-local" required readonly />
                                        </div>
                                        <div class="equipment-form__field">
                                            <label for="edit_reported_by">Reported by</label>
                                            <input id="edit_reported_by" name="reported_by" type="text" list="reportedByList" autocomplete="off" required readonly />
                                        </div>
                                        <div class="equipment-form__field" style="grid-column: span 2;">
                                            <label for="edit_reported_issues">Reported Issues</label>
                                            <textarea id="edit_reported_issues" name="reported_issues" rows="3" style="width:100%;resize:vertical;" required readonly></textarea>
                                        </div>
                                        <div class="equipment-form__field" style="grid-column: span 2;">
                                            <label for="edit_equipment_location">Equipment Location</label>
                                            <input id="edit_equipment_location" name="equipment_location" type="text" required readonly />
                                        </div>
                                        <div style="grid-column: span 2; width:100%;"><hr style="border:0;border-top:3px solid #334155;margin:12px 0 12px 0;"></div>
                                        <div class="equipment-form__field">
                                            <label for="edit_operating_condition">Operating Condition</label>
                                            <select id="edit_operating_condition" name="operating_condition" required>
                                                <option value="">Select...</option>
                                                <option value="green">Green</option>
                                                <option value="yellow">Yellow</option>
                                                <option value="red">Red</option>
                                            </select>
                                        </div>
                                        <div class="equipment-form__field">
                                            <label for="edit_date_repaired">Date Repaired</label>
                                            <input id="edit_date_repaired" name="date_repaired" type="datetime-local" />
                                        </div>
                                        <div class="equipment-form__field" style="grid-column: span 2;">
                                            <label for="edit_mechanic_diagnosis">Mechanic Diagnosis</label>
                                            <textarea id="edit_mechanic_diagnosis" name="mechanic_diagnosis" rows="2" style="width:100%;resize:vertical;"></textarea>
                                        </div>
                                        <div class="equipment-form__field">
                                            <label for="edit_repair_mechanic">Repair Mechanic</label>
                                            <input id="edit_repair_mechanic" name="repair_mechanic" type="text" />
                                        </div>
                                        <div class="equipment-form__field">
                                            <label for="edit_parts_fixed">Parts Fixed</label>
                                            <input id="edit_parts_fixed" name="parts_fixed" type="text" />
                                        </div>
                                        <div class="equipment-form__field">
                                            <label for="edit_pictures">Pictures</label>
                                            <input id="edit_pictures" name="pictures" type="text" />
                                        </div>
                                    </div>
                                    <div class="equipment-form__actions" style="margin-top:18px; justify-content: space-between;">
                                        <button id="deleteEditIssue" class="equipment-btn" type="button" style="background: #dc2626; color: #fff;">Delete Issue</button>
                                        <div style="display: flex; gap: 16px;">
                                            <button id="cancelEditIssue" class="equipment-btn equipment-btn--secondary" type="button">Cancel</button>
                                            <button id="saveEditIssue" class="equipment-btn" type="submit">Update Issue</button>
                                        </div>
                                    </div>
                                    <div id="editIssueError" class="equipment-form__error" role="alert" style="display:none;"></div>
                                </form>
                            </div>
                        </div>

                        <div class="equipment-card">
                            <div class="equipment-card-header">
                                <h2 class="equipment-card-title">Equipment History</h2>
                            </div>
                            <div style="overflow-x: auto;">
                                <table class="equipment-history-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 70px;">Issue #</th>
                                            <th>Date Reported</th>
                                            <th>Reported Issues</th>
                                            <th>Reported by</th>
                                            <th>Equipment Location</th>
                                            <th>Operating Condition</th>
                                            <th>Mechanic Diagnosis</th>
                                            <th>Date Repaired</th>
                                            <th>Repair Mechanic</th>
                                            <th>Parts fixed</th>
                                            <th>pictures</th>
                                        </tr>
                                    </thead>
                                    <tbody>
<?php
$historyStmt = $conn->prepare('SELECT id, date_reported, reported_issues, reported_by, equipment_location, operating_condition, mechanic_diagnosis, date_repaired, repair_mechanic, parts_fixed, pictures, is_edited_copy, original_issue_id FROM equipment_history WHERE equipment_id = ? ORDER BY id DESC');
$historyStmt->bind_param('i', $equipmentId);
$historyStmt->execute();
$historyRes = $historyStmt->get_result();

// Build arrays to track the chain of edits
$allRows = [];
$rowsById = [];
$hasNewerVersion = []; // Track which rows have newer versions (edited copies)

if ($historyRes && $historyRes->num_rows > 0) {
    while ($row = $historyRes->fetch_assoc()) {
        $allRows[] = $row;
        $rowsById[$row['id']] = $row;
        // If this is an edited copy, mark its parent as having a newer version
        if (!empty($row['is_edited_copy']) && !empty($row['original_issue_id'])) {
            $hasNewerVersion[$row['original_issue_id']] = true;
        }
    }
}

if (count($allRows) > 0) {
    foreach ($allRows as $row) {
        $rowData = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
        $hasNewer = isset($hasNewerVersion[$row['id']]);
        
        $rowClass = 'equipment-history-row';
        if (!empty($row['is_edited_copy'])) {
            $rowClass .= ' equipment-history-copy-row';
        }
        
        // Hide rows only if they have a newer edited copy version
        // Show all standalone issues (non-edited copies) and the latest version of each edited chain
        if ($hasNewer) {
            // This row has a newer edited copy, so hide it by default
            $rowClass .= ' equipment-history-original-hidden';
        }
        
        echo '<tr class="' . $rowClass . '" data-row="' . $rowData . '"';
        if (!empty($row['is_edited_copy']) && !empty($row['original_issue_id'])) {
            echo ' data-original-id="' . htmlspecialchars($row['original_issue_id']) . '"';
        }
        if ($hasNewer) {
            echo ' data-issue-id="' . htmlspecialchars($row['id']) . '"';
        }
        echo '>';
        // Issue number column, with edit button and edited copy marker if needed
        echo '<td class="equipment-history-edit-cell">';
        // Show edit button for all visible rows (not hidden ones)
        if (!$hasNewer) {
            echo '<button class="equipment-history-edit-btn" aria-label="Edit">✎</button> ';
            // Only show "edited" badge if this is an edited copy (and it's the latest in its chain)
            if (!empty($row['is_edited_copy'])) {
                echo '<span class="equipment-edited-copy-badge equipment-copy-toggle" title="Click to show previous version">edited</span>';
            } else {
                echo htmlspecialchars($row['id']);
            }
        } else {
            // Hidden previous versions just show their issue number (no edit button or "edited" badge)
            echo htmlspecialchars($row['id']);
        }
        echo '</td>';
        echo '<td>' . htmlspecialchars($row['date_reported'] ?? '') . '</td>';
        echo '<td>' . nl2br(htmlspecialchars($row['reported_issues'] ?? '')) . '</td>';
        echo '<td>' . htmlspecialchars($row['reported_by'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['equipment_location'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['operating_condition'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['mechanic_diagnosis'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['date_repaired'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['repair_mechanic'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['parts_fixed'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['pictures'] ?? '') . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="11" style="text-align: center; padding: 24px; color: #94a3b8;">No history records yet. Add equipment issues and repairs to track maintenance history.</td></tr>';
}
$historyStmt->close();
?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

<script>
// Modal open/close logic
const newIssueBtn = document.getElementById('new-issue-btn');
const newIssueModal = document.getElementById('newIssueModal');
const closeNewIssueModal = document.getElementById('closeNewIssueModal');
const cancelNewIssue = document.getElementById('cancelNewIssue');
const issueDateInput = document.getElementById('issue_date_reported');

// Edit modal elements
const editIssueModal = document.getElementById('editIssueModal');
const closeEditIssueModal = document.getElementById('closeEditIssueModal');
const cancelEditIssue = document.getElementById('cancelEditIssue');
const editIssueForm = document.getElementById('editIssueForm');
const saveEditIssueBtn = document.getElementById('saveEditIssue');
const editIssueError = document.getElementById('editIssueError');
const editTopFieldsBtn = document.getElementById('editTopFieldsBtn');

function openNewIssueModal() {
    if (newIssueModal) {
        newIssueModal.style.display = 'flex';
        newIssueModal.setAttribute('aria-hidden', 'false');
        if (issueDateInput) {
            const now = new Date();
            const pad = n => n.toString().padStart(2, '0');
            const local = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
            issueDateInput.value = local;
        }
        setTimeout(() => {
            const first = document.getElementById('issue_reported_issues');
            if (first) first.focus();
        }, 100);
    }
}

function closeNewIssueModalFn() {
    if (newIssueModal) {
        newIssueModal.style.display = 'none';
        newIssueModal.setAttribute('aria-hidden', 'true');
        document.getElementById('newIssueForm').reset();
        document.getElementById('newIssueError').style.display = 'none';
    }
}

function openEditIssueModal(rowData) {
    if (editIssueModal && rowData) {
        // Set issue ID
        document.getElementById('edit_issue_id').value = rowData.id || '';
        
        // Set field values
        document.getElementById('edit_date_reported').value = formatDateTimeLocal(rowData.date_reported);
        document.getElementById('edit_original_date_reported').value = rowData.date_reported;
        document.getElementById('edit_reported_by').value = rowData.reported_by || '';
        document.getElementById('edit_reported_issues').value = rowData.reported_issues || '';
        document.getElementById('edit_equipment_location').value = rowData.equipment_location || '';
        document.getElementById('edit_operating_condition').value = rowData.operating_condition || '';
        document.getElementById('edit_mechanic_diagnosis').value = rowData.mechanic_diagnosis || '';
        document.getElementById('edit_date_repaired').value = formatDateTimeLocal(rowData.date_repaired);
        document.getElementById('edit_repair_mechanic').value = rowData.repair_mechanic || '';
        document.getElementById('edit_parts_fixed').value = rowData.parts_fixed || '';
        document.getElementById('edit_pictures').value = rowData.pictures || '';
        
        // Ensure top fields are disabled/readonly
        document.getElementById('edit_date_reported').readOnly = true;
        document.getElementById('edit_reported_by').readOnly = true;
        document.getElementById('edit_reported_issues').readOnly = true;
        document.getElementById('edit_equipment_location').readOnly = true;
        
        // Reset Edit button text
        const editBtn = document.getElementById('editTopFieldsBtn');
        if (editBtn) editBtn.textContent = 'Edit';
        
        // Reset save button text
        const saveBtn = document.getElementById('saveEditIssue');
        if (saveBtn) saveBtn.textContent = 'Update Issue';
        
        editIssueModal.style.display = 'flex';
        editIssueModal.setAttribute('aria-hidden', 'false');
    }
}

function closeEditIssueModalFn() {
    if (editIssueModal) {
        editIssueModal.style.display = 'none';
        editIssueModal.setAttribute('aria-hidden', 'true');
        editIssueForm.reset();
        editIssueError.style.display = 'none';
    }
}

// Handle Edit button for top fields
if (editTopFieldsBtn) {
    editTopFieldsBtn.addEventListener('click', function() {
        const dateReported = document.getElementById('edit_date_reported');
        const reportedBy = document.getElementById('edit_reported_by');
        const reportedIssues = document.getElementById('edit_reported_issues');
        const equipmentLocation = document.getElementById('edit_equipment_location');
        
        if (dateReported.readOnly) {
            // Enable editing
            dateReported.readOnly = false;
            reportedBy.readOnly = false;
            reportedIssues.readOnly = false;
            equipmentLocation.readOnly = false;
            editTopFieldsBtn.textContent = 'Cancel Edit';
            
            // Update button text to indicate it will create a copy
            if (saveEditIssueBtn) {
                saveEditIssueBtn.textContent = 'Save edited copy';
            }
        } else {
            // Disable editing and restore original values
            dateReported.readOnly = true;
            reportedBy.readOnly = true;
            reportedIssues.readOnly = true;
            equipmentLocation.readOnly = true;
            editTopFieldsBtn.textContent = 'Edit';
            
            // Restore original values from the form data
            const originalDate = document.getElementById('edit_original_date_reported').value;
            if (originalDate) {
                dateReported.value = formatDateTimeLocal(originalDate);
            }
            // Note: We'd need to store original values if we want to restore them fully
            // For now, just disable the fields
            
            // Reset button text
            if (saveEditIssueBtn) {
                saveEditIssueBtn.textContent = 'Update Issue';
            }
        }
    });
}

function formatDateTimeLocal(dateStr) {
    if (!dateStr) return '';
    try {
        const date = new Date(dateStr);
        const pad = n => n.toString().padStart(2, '0');
        return date.getFullYear() + '-' + 
               pad(date.getMonth()+1) + '-' + 
               pad(date.getDate()) + 'T' + 
               pad(date.getHours()) + ':' + 
               pad(date.getMinutes());
    } catch (e) {
        return '';
    }
}

if (newIssueBtn) newIssueBtn.addEventListener('click', openNewIssueModal);
if (closeNewIssueModal) closeNewIssueModal.addEventListener('click', closeNewIssueModalFn);
if (cancelNewIssue) cancelNewIssue.addEventListener('click', closeNewIssueModalFn);
if (newIssueModal) newIssueModal.addEventListener('click', function(e){ if (e.target === newIssueModal) closeNewIssueModalFn(); });

if (closeEditIssueModal) closeEditIssueModal.addEventListener('click', closeEditIssueModalFn);
if (cancelEditIssue) cancelEditIssue.addEventListener('click', closeEditIssueModalFn);
if (editIssueModal) editIssueModal.addEventListener('click', function(e){ if (e.target === editIssueModal) closeEditIssueModalFn(); });

// Handle delete issue button
const deleteEditIssueBtn = document.getElementById('deleteEditIssue');
if (deleteEditIssueBtn) {
    deleteEditIssueBtn.addEventListener('click', function() {
        const issueId = document.getElementById('edit_issue_id').value;
        if (!issueId) {
            alert('Invalid issue ID.');
            return;
        }
        
        // Show confirmation dialog
        if (confirm('Are you sure you want to delete this issue? This action cannot be undone.')) {
            // Disable the button and show loading state
            deleteEditIssueBtn.disabled = true;
            deleteEditIssueBtn.textContent = 'Deleting...';
            
            // Send delete request
            const fd = new FormData();
            fd.append('issue_id', issueId);
            
            fetch('../../api/delete_equipment_issue.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        alert(res.message || 'Failed to delete issue.');
                        deleteEditIssueBtn.disabled = false;
                        deleteEditIssueBtn.textContent = 'Delete Issue';
                        return;
                    }
                    // Close modal and reload page
                    closeEditIssueModalFn();
                    window.location.href = window.location.pathname + window.location.search;
                })
                .catch(() => {
                    alert('Network error while deleting issue.');
                    deleteEditIssueBtn.disabled = false;
                    deleteEditIssueBtn.textContent = 'Delete Issue';
                });
        }
    });
}

document.querySelectorAll('.equipment-history-edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const row = this.closest('.equipment-history-row');
        if (row) {
            const rowDataStr = row.getAttribute('data-row');
            if (rowDataStr) {
                try {
                    const rowData = JSON.parse(rowDataStr);
                    openEditIssueModal(rowData);
                } catch (e) {
                    console.error('Error parsing row data:', e);
                }
            }
        }
    });
});

// Handle clicking on edited copy badge to show/hide all previous versions
document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('equipment-copy-toggle')) {
        e.preventDefault();
        e.stopPropagation();
        const currentRow = e.target.closest('.equipment-history-row');
        if (currentRow) {
            const originalId = currentRow.getAttribute('data-original-id');
            if (originalId) {
                // Build the chain of all previous versions by following original_issue_id
                const allPreviousRows = [];
                let currentId = originalId;
                const processedIds = new Set();
                
                while (currentId && !processedIds.has(currentId)) {
                    processedIds.add(currentId);
                    const row = document.querySelector('.equipment-history-row[data-issue-id="' + currentId + '"]');
                    if (row) {
                        allPreviousRows.push(row);
                        // Check if this row also has a previous version
                        const rowOriginalId = row.getAttribute('data-original-id');
                        if (rowOriginalId) {
                            currentId = rowOriginalId;
                        } else {
                            break; // Reached the original
                        }
                    } else {
                        break;
                    }
                }
                
                if (allPreviousRows.length > 0) {
                    // Check if any previous rows are currently visible
                    const anyVisible = allPreviousRows.some(row => !row.classList.contains('equipment-history-original-hidden'));
                    
                    if (anyVisible) {
                        // Hide all previous versions
                        allPreviousRows.forEach(row => {
                            row.classList.add('equipment-history-original-hidden');
                        });
                    } else {
                        // Show all previous versions in order
                        let insertAfter = currentRow;
                        allPreviousRows.forEach(row => {
                            row.classList.remove('equipment-history-original-hidden');
                            insertAfter.insertAdjacentElement('afterend', row);
                            insertAfter = row;
                        });
                    }
                }
            }
        }
    }
});

document.addEventListener('keydown', function(e){ 
    if (e.key === 'Escape') {
        if (newIssueModal && newIssueModal.style.display === 'flex') closeNewIssueModalFn();
        if (editIssueModal && editIssueModal.style.display === 'flex') closeEditIssueModalFn();
    }
});

// AJAX submit for new issue form
const newIssueForm = document.getElementById('newIssueForm');
const saveNewIssueBtn = document.getElementById('saveNewIssue');
const newIssueError = document.getElementById('newIssueError');
if (newIssueForm) {
    newIssueForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (saveNewIssueBtn) { saveNewIssueBtn.disabled = true; saveNewIssueBtn.textContent = 'Saving...'; }
        if (newIssueError) { newIssueError.style.display = 'none'; newIssueError.textContent = ''; }
        const fd = new FormData(newIssueForm);
        fetch('../../api/add_equipment_issue.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    if (newIssueError) { newIssueError.textContent = res.message || 'Failed to save issue.'; newIssueError.style.display = 'block'; }
                    return;
                }
                closeNewIssueModalFn();
                window.location.href = window.location.pathname + window.location.search;
            })
            .catch(() => {
                if (newIssueError) { newIssueError.textContent = 'Network error while saving.'; newIssueError.style.display = 'block'; }
            })
            .finally(() => {
                if (saveNewIssueBtn) { saveNewIssueBtn.disabled = false; saveNewIssueBtn.textContent = 'Save'; }
            });
    });
}

// AJAX submit for edit issue form
if (editIssueForm) {
    editIssueForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (saveEditIssueBtn) { saveEditIssueBtn.disabled = true; saveEditIssueBtn.textContent = 'Saving...'; }
        if (editIssueError) { editIssueError.style.display = 'none'; editIssueError.textContent = ''; }
        
        const dateReported = document.getElementById('edit_date_reported');
        const issueId = document.getElementById('edit_issue_id').value;
        const isTopFieldsEditable = !dateReported.readOnly;
        
        if (isTopFieldsEditable) {
            // Top fields are editable, so create a new copy
            const fd = new FormData(editIssueForm);
            fd.append('is_edited_copy', '1');
            fd.append('original_issue_id', issueId); // Track the original issue ID
            fetch('../../api/add_equipment_issue.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        if (editIssueError) { editIssueError.textContent = res.message || 'Failed to save edited copy.'; editIssueError.style.display = 'block'; }
                        return;
                    }
                    closeEditIssueModalFn();
                    window.location.href = window.location.pathname + window.location.search;
                })
                .catch(() => {
                    if (editIssueError) { editIssueError.textContent = 'Network error while saving.'; editIssueError.style.display = 'block'; }
                })
                .finally(() => {
                    if (saveEditIssueBtn) { saveEditIssueBtn.disabled = false; saveEditIssueBtn.textContent = 'Save edited copy'; }
                });
        } else {
            // Top fields are readonly, so update existing issue
            const updateFd = new FormData();
            updateFd.append('issue_id', issueId);
            updateFd.append('equipment_id', document.getElementById('edit_equipment_id').value || '');
            updateFd.append('operating_condition', document.getElementById('edit_operating_condition').value || '');
            updateFd.append('mechanic_diagnosis', document.getElementById('edit_mechanic_diagnosis').value || '');
            updateFd.append('date_repaired', document.getElementById('edit_date_repaired').value || '');
            updateFd.append('repair_mechanic', document.getElementById('edit_repair_mechanic').value || '');
            updateFd.append('parts_fixed', document.getElementById('edit_parts_fixed').value || '');
            updateFd.append('pictures', document.getElementById('edit_pictures').value || '');
            
            fetch('../../api/update_equipment_issue.php', { method: 'POST', body: updateFd, credentials: 'same-origin' })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        if (editIssueError) { editIssueError.textContent = res.message || 'Failed to update issue.'; editIssueError.style.display = 'block'; }
                        return;
                    }
                    closeEditIssueModalFn();
                    window.location.href = window.location.pathname + window.location.search;
                })
                .catch(() => {
                    if (editIssueError) { editIssueError.textContent = 'Network error while saving.'; editIssueError.style.display = 'block'; }
                })
                .finally(() => {
                    if (saveEditIssueBtn) { saveEditIssueBtn.disabled = false; saveEditIssueBtn.textContent = 'Update Issue'; }
                });
        }
    });
}

// Sidebar toggle handlers
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
</body>
</html>