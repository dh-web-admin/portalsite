<?php
require_once __DIR__ . '/config/config.php';

echo "Checking suppliers table structure...\n\n";

$result = $conn->query('DESCRIBE suppliers');

echo "Current structure:\n";
echo str_pad("Field", 25) . str_pad("Type", 20) . str_pad("Key", 10) . "Extra\n";
echo str_repeat("-", 70) . "\n";

while($row = $result->fetch_assoc()) {
    echo str_pad($row['Field'], 25) . 
         str_pad($row['Type'], 20) . 
         str_pad($row['Key'], 10) . 
         $row['Extra'] . "\n";
}

echo "\nChecking if 'id' column exists and if it's a primary key...\n";

$result = $conn->query("SHOW COLUMNS FROM suppliers WHERE Field = 'id'");
$idColumn = $result->fetch_assoc();

if (!$idColumn) {
    echo "⚠️  No 'id' column found!\n";
} else {
    echo "✓ 'id' column exists\n";
    echo "  Type: " . $idColumn['Type'] . "\n";
    echo "  Key: " . ($idColumn['Key'] ?: 'none') . "\n";
    echo "  Extra: " . ($idColumn['Extra'] ?: 'none') . "\n";
}
