<?php
require_once __DIR__ . '/../../session_init.php';

require_once __DIR__ . '/../../config/config.php';

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
    header('Location: equipment.php?id=' . $_POST['equipment_id']);
    exit();
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_equipment']) && isset($_POST['equipment_id'])) {
    $deleteId = (int)$_POST['equipment_id'];
    if ($deleteId > 0) {
        $stmt = $conn->prepare('DELETE FROM equipments WHERE equipment_id = ?');
        $stmt->bind_param('i', $deleteId);
        $stmt->execute();
        header('Location: index.php');
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

    padding: 8px 12px;
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

    padding: 8px 16px;
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
.equipment-back-btn {
    display: inline-flex;
    align-items: center;
    // ...existing code...
  gap: 2px;
  margin-bottom: 16px;
  background: #f1f5f9;
  padding: 4px;
  border-radius: 8px;
  overflow-x: auto;
  /* Add left alignment */
  justify-content: flex-start;
  margin-left: 0;
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
                        <a href="index.php" class="equipment-back-btn">
                            <span>←</span>
                            <span>Back to Equipments</span>
                        </a>
                    </div>
                    <?php if ($editMode) { ?>
<form method="POST" style="margin-bottom:0;">
<?php } ?>
<div style="display: flex; justify-content: flex-start; align-items: center; margin-bottom: 18px; gap: 12px;">
    <?php if (!$editMode) { ?>
        <a href="equipment.php?id=<?php echo $equipmentId; ?>&edit=1" class="equipment-action-btn equipment-action-btn--primary" style="min-width: 80px; padding: 8px 20px; font-size: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.07);">Edit</a>
    <?php } else { ?>
        <button class="equipment-action-btn equipment-action-btn--primary" type="submit" name="save_changes" style="min-width: 80px; padding: 8px 20px; font-size: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.07);">Save Changes</button>
        <a href="equipment.php?id=<?php echo $equipmentId; ?>" class="equipment-action-btn" style="background:#f3f6f9;color:#2563eb;border-color:#d1d5db;min-width:80px;padding:8px 20px;font-size:15px;box-shadow:0 1px 3px rgba(0,0,0,0.07);text-decoration:none;">Cancel</a>
        <input type="hidden" name="equipment_id" value="<?php echo $equipmentId; ?>" />
        <button type="submit" name="delete_equipment" class="equipment-action-btn" style="background:#ef4444;color:#fff;border-color:#ef4444;min-width:80px;padding:8px 20px;font-size:15px;box-shadow:0 1px 3px rgba(0,0,0,0.07);" onclick="return confirm('Are you sure you want to delete this equipment?');">Delete</button>
    <?php } ?>
</div>
<?php
$isRedStatus = ($equipment['operating_condition'] ?? '') === 'red' || ($equipment['oil_status'] ?? '') === 'red';
?>
<div class="equipment-details-table-wrapper<?php if ($isRedStatus) echo ' equipment-details-table-wrapper--red'; ?>">
        <style>
        /* Add red border for red status */
        .equipment-details-table-wrapper--red {
            border: 3px solid #e53935 !important;
            box-shadow: 0 0 0 2px #ffb3b3;
            border-radius: 10px;
        }
        </style>
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
      <!-- Action buttons removed as requested -->
    </div>
</div>
<input type="hidden" name="equipment_id" value="<?php echo $equipmentId; ?>" />
</form>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_changes']) && isset($_POST['equipment_id'])) {
    $fields = [
        'dhcst_equipment_number', 'dhss_equipment_number', 'type', 'make', 'model', 'engine', 'engine_serial_number',
        'vehicle_year', 'vin', 'transmission', 'trans_serial_number', 'location'
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
    header('Location: equipment.php?id=' . $_POST['equipment_id']);
    exit();
}
?>
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
                        <div class="equipment-card">
                            <div class="equipment-card-header">
                                <h2 class="equipment-card-title">Equipment History</h2>
                            </div>
                            <div style="overflow-x: auto;">
                                <table class="equipment-history-table">
                                    <thead>
                                        <tr>
                                            <th>Date Reported</th>
                                            <th>Reported Issues</th>
                                            <th>Mechanic Diagnosis</th>
                                            <th>Date Repaired</th>
                                            <th>Repair Mechanic</th>
                                            <th>Part</th>
                                            <th>Photo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 24px; color: #94a3b8;">
                                                No history records yet. Add equipment issues and repairs to track maintenance history.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
<script>
// Action buttons and edit/save/cancel logic removed as requested
</script>