<?php
require_once __DIR__ . '/../config/config.php';
$filters = [
    'AIR FILTER 1',
    'AIR FILTER 2',
    'OIL FILTER 1',
    'OIL FILTER 2',
    'HYDRAULIC FILTER',
    'FUEL FILTER 1',
    'FUEL FILTER 2',
    'COOLANT FILTER',
    'WATER SEPARATOR',
    'CANISTER FILTER',
    'WATER FILTER 1',
    'WATER FILTER 2'
];
foreach ($filters as $filter) {
    $stmt = $conn->prepare('INSERT IGNORE INTO filter_names (filter_name) VALUES (?)');
    $stmt->bind_param('s', $filter);
    if ($stmt->execute()) {
        echo "<p style='color:green;'>Inserted: $filter</p>\n";
    } else {
        echo "<p style='color:red;'>Error inserting $filter: " . htmlspecialchars($stmt->error) . "</p>\n";
    }
    $stmt->close();
}
$conn->close();
?>