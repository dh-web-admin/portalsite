<?php
require_once __DIR__ . '/../config/config.php';

$sql = "CREATE TABLE IF NOT EXISTS user_page_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    page_key VARCHAR(64) NOT NULL,
    can_access TINYINT(1) NOT NULL DEFAULT 1,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_page (user_id, page_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "user_page_permissions table ready\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
