<?php
require_once __DIR__ . '/../config/config.php';

echo "Adding pin_color column to suppliers table...\n";

try {
  $sql = "ALTER TABLE suppliers ADD COLUMN pin_color VARCHAR(20) NULL AFTER longitude";
  if ($conn->query($sql) === TRUE) {
    echo "✓ pin_color column added\n";
  } else {
    echo "Error adding pin_color: " . $conn->error . "\n";
  }
  $conn->close();
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
?>
