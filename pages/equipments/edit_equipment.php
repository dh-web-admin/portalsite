<?php
require_once __DIR__ . '/../../session_init.php';
require_once __DIR__ . '/../../config/config.php';

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

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'dhcst_equipment_number', 'dhss_equipment_number', 'type', 'make', 'model', 'engine', 'engine_serial_number',
        'year', 'vin', 'transmission', 'trans_serial_number', 'location', 'operating_condition', 'oil_status', 'additional_info'
    ];
    $updates = [];
    $params = [];
    $types = '';
    foreach ($fields as $field) {
        $updates[] = "$field = ?";
        $params[] = $_POST[$field] ?? '';
        $types .= 's';
    }
    $params[] = $equipmentId;
    $types .= 'i';
    $sql = 'UPDATE equipments SET ' . implode(', ', $updates) . ' WHERE equipment_id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    echo '<div style="color:green;margin-bottom:16px;">Equipment updated successfully.</div>';
    // Refresh data
    $stmt = $conn->prepare('SELECT * FROM equipments WHERE equipment_id = ? LIMIT 1');
    $stmt->bind_param('i', $equipmentId);
    $stmt->execute();
    $res = $stmt->get_result();
    $equipment = $res ? $res->fetch_assoc() : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Edit Equipment</title>
    <link rel="stylesheet" href="../../assets/css/base.css" />
    <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
    <link rel="stylesheet" href="../../assets/css/dashboard.css" />
    <style>
        .edit-form { max-width: 700px; margin: 32px auto; background: #fff; padding: 24px 32px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .edit-form label { font-weight: 700; margin-top: 12px; display: block; }
        .edit-form input { width: 100%; padding: 8px; margin-top: 4px; margin-bottom: 12px; border-radius: 6px; border: 1px solid #d1d5db; }
        .edit-form button { padding: 10px 24px; border-radius: 6px; background: #2563eb; color: #fff; font-weight: 700; border: none; cursor: pointer; margin-top: 10px; }
        .edit-form button:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="edit-form">
        <h2>Edit Equipment</h2>
        <form method="POST">
            <label>DHCST Equipment number</label>
            <input type="text" name="dhcst_equipment_number" value="<?php echo htmlspecialchars($equipment['dhcst_equipment_number'] ?? ''); ?>" />
            <label>DHSS Equipment number</label>
            <input type="text" name="dhss_equipment_number" value="<?php echo htmlspecialchars($equipment['dhss_equipment_number'] ?? ''); ?>" />
            <label>Type</label>
            <input type="text" name="type" value="<?php echo htmlspecialchars($equipment['type'] ?? ''); ?>" />
            <label>Make</label>
            <input type="text" name="make" value="<?php echo htmlspecialchars($equipment['make'] ?? ''); ?>" />
            <label>Model</label>
            <input type="text" name="model" value="<?php echo htmlspecialchars($equipment['model'] ?? ''); ?>" />
            <label>Engine</label>
            <input type="text" name="engine" value="<?php echo htmlspecialchars($equipment['engine'] ?? ''); ?>" />
            <label>Engine Serial Number</label>
            <input type="text" name="engine_serial_number" value="<?php echo htmlspecialchars($equipment['engine_serial_number'] ?? ''); ?>" />
            <label>Year</label>
            <input type="text" name="year" value="<?php echo htmlspecialchars($equipment['year'] ?? ''); ?>" />
            <label>Vin</label>
            <input type="text" name="vin" value="<?php echo htmlspecialchars($equipment['vin'] ?? ''); ?>" />
            <label>Transmission</label>
            <input type="text" name="transmission" value="<?php echo htmlspecialchars($equipment['transmission'] ?? ''); ?>" />
            <label>Trans Serial Number</label>
            <input type="text" name="trans_serial_number" value="<?php echo htmlspecialchars($equipment['trans_serial_number'] ?? ''); ?>" />
            <label>Location</label>
            <input type="text" name="location" value="<?php echo htmlspecialchars($equipment['location'] ?? ''); ?>" />

            <label>Engine Operating Condition</label>
            <select name="operating_condition">
                <option value="green" <?php if (($equipment['operating_condition'] ?? '') === 'green') echo 'selected'; ?>>Green</option>
                <option value="yellow" <?php if (($equipment['operating_condition'] ?? '') === 'yellow') echo 'selected'; ?>>Yellow</option>
                <option value="red" <?php if (($equipment['operating_condition'] ?? '') === 'red') echo 'selected'; ?>>Red</option>
            </select>

            <label>Oil Status</label>
            <select name="oil_status">
                <option value="green" <?php if (($equipment['oil_status'] ?? '') === 'green') echo 'selected'; ?>>Green</option>
                <option value="yellow" <?php if (($equipment['oil_status'] ?? '') === 'yellow') echo 'selected'; ?>>Yellow</option>
                <option value="red" <?php if (($equipment['oil_status'] ?? '') === 'red') echo 'selected'; ?>>Red</option>
            </select>
            <label>Additional Info</label>
            <textarea name="additional_info" rows="4" style="width:100%;padding:8px;margin-top:4px;margin-bottom:12px;border-radius:6px;border:1px solid #d1d5db;resize:vertical;">
                <?php echo htmlspecialchars($equipment['additional_info'] ?? ''); ?>
            </textarea>
            <button type="submit">Save Changes</button>
            <a href="equipment.php?id=<?php echo $equipmentId; ?>" style="margin-left:16px; color:#2563eb; text-decoration:underline;">Cancel</a>
        </form>
    </div>
</body>
</html>
