<?php
require_once __DIR__ . '/config/config.php';

$result = $conn->query('SELECT COUNT(*) as count FROM suppliers WHERE latitude IS NULL OR longitude IS NULL');
$row = $result->fetch_assoc();
echo 'Suppliers missing coordinates: ' . $row['count'] . PHP_EOL;

if ($row['count'] > 0) {
    echo "\nFirst 5 suppliers missing coordinates:\n";
    $result = $conn->query('SELECT id, name, address, city, state FROM suppliers WHERE latitude IS NULL OR longitude IS NULL LIMIT 5');
    while ($supplier = $result->fetch_assoc()) {
        echo "  - ID {$supplier['id']}: {$supplier['name']} ({$supplier['city']}, {$supplier['state']})\n";
    }
}
