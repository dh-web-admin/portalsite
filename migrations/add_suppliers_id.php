<?php
require_once __DIR__ . '/../config/config.php';

echo "Adding ID column to suppliers table...\n";

try {
  // Add id column as primary key with auto increment
  $sql = "ALTER TABLE suppliers ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST";
  
  if ($conn->query($sql) === TRUE) {
    echo "Success! ID column added to suppliers table.\n";
  } else {
    echo "Error: " . $conn->error . "\n";
  }
  
  $conn->close();
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
?>
