<?php
require_once __DIR__ . '/config/config.php';

$result = $conn->query('SELECT id, name, city, state, latitude, longitude FROM suppliers WHERE id = 15 LIMIT 1');
$row = $result->fetch_assoc();

if ($row) {
    echo "ID 15: {$row['name']} - {$row['city']}, {$row['state']}\n";
    if ($row['latitude'] && $row['longitude']) {
        echo "  Coordinates: ({$row['latitude']}, {$row['longitude']})\n";
        echo "  ✓ HAS COORDINATES\n";
    } else {
        echo "  ✗ NO COORDINATES\n";
    }
}
