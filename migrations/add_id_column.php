<?php
require_once __DIR__ . '/../config/config.php';

echo "Adding id column as primary key to suppliers table...\n\n";

try {
  // Check if id column already exists
  $result = $conn->query("SHOW COLUMNS FROM suppliers WHERE Field = 'id'");
  
  if ($result->num_rows > 0) {
    echo "⚠️  'id' column already exists!\n";
    $row = $result->fetch_assoc();
    echo "   Type: " . $row['Type'] . "\n";
    echo "   Key: " . ($row['Key'] ?: 'none') . "\n";
    echo "   Extra: " . ($row['Extra'] ?: 'none') . "\n\n";
    
    if ($row['Key'] === 'PRI') {
      echo "✓ 'id' is already set as PRIMARY KEY. No action needed.\n";
      exit;
    } else {
      echo "Setting 'id' as PRIMARY KEY...\n";
      $sql = "ALTER TABLE suppliers MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY";
    }
  } else {
    echo "Adding new 'id' column as PRIMARY KEY...\n";
    $sql = "ALTER TABLE suppliers ADD COLUMN id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST";
  }
  
  if ($conn->query($sql) === TRUE) {
    echo "✓ Success! 'id' column is now the PRIMARY KEY with AUTO_INCREMENT\n";
  } else {
    echo "✗ Error: " . $conn->error . "\n";
  }
  
  $conn->close();
} catch (Exception $e) {
  echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
