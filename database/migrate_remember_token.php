<?php
require_once __DIR__ . '/../config/config.php';

echo "Running database migration for remember token...\n";

// Add remember_token column
$sql1 = "ALTER TABLE users ADD COLUMN remember_token VARCHAR(64) NULL DEFAULT NULL";
if ($conn->query($sql1)) {
    echo "✓ Added remember_token column\n";
} else {
    if (strpos($conn->error, 'Duplicate column name') !== false) {
        echo "✓ remember_token column already exists\n";
    } else {
        echo "✗ Error adding remember_token: " . $conn->error . "\n";
    }
}

// Add remember_token_expires column
$sql2 = "ALTER TABLE users ADD COLUMN remember_token_expires DATETIME NULL DEFAULT NULL";
if ($conn->query($sql2)) {
    echo "✓ Added remember_token_expires column\n";
} else {
    if (strpos($conn->error, 'Duplicate column name') !== false) {
        echo "✓ remember_token_expires column already exists\n";
    } else {
        echo "✗ Error adding remember_token_expires: " . $conn->error . "\n";
    }
}

// Add index
$sql3 = "ALTER TABLE users ADD INDEX idx_remember_token (remember_token)";
if ($conn->query($sql3)) {
    echo "✓ Added index on remember_token\n";
} else {
    if (strpos($conn->error, 'Duplicate key name') !== false) {
        echo "✓ Index already exists\n";
    } else {
        echo "✗ Error adding index: " . $conn->error . "\n";
    }
}

echo "\nMigration completed!\n";
$conn->close();
?>
