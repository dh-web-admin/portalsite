<?php
require_once __DIR__ . '/../config/config.php';

echo "Adding latitude and longitude columns to suppliers table...\n";

try {
  // Add latitude column
  $sql1 = "ALTER TABLE suppliers ADD COLUMN latitude DECIMAL(10, 8) NULL AFTER state";
  if ($conn->query($sql1) === TRUE) {
    echo "✓ latitude column added\n";
  } else {
    echo "Error adding latitude: " . $conn->error . "\n";
  }
  
  // Add longitude column
  $sql2 = "ALTER TABLE suppliers ADD COLUMN longitude DECIMAL(11, 8) NULL AFTER latitude";
  if ($conn->query($sql2) === TRUE) {
    echo "✓ longitude column added\n";
  } else {
    echo "Error adding longitude: " . $conn->error . "\n";
  }
  
  echo "\nSuccess! Coordinate columns added to suppliers table.\n";
  echo "You can now run the geocode script to populate coordinates for existing suppliers.\n";
  
  $conn->close();
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
?>
