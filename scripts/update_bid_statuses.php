<?php
// CLI script: update bids from 'bidding' to 'pending' when bid_date has passed
require_once __DIR__ . '/../config/config.php';

try {
    $sql = "UPDATE bids SET status = 'pending' WHERE status = 'bidding' AND bid_date IS NOT NULL AND DATE(bid_date) < CURDATE()";
    $res = $conn->query($sql);
    if ($res === false) {
        fwrite(STDERR, "Update failed: " . $conn->error . "\n");
        exit(2);
    }
    $affected = $conn->affected_rows;
    fwrite(STDOUT, "Updated statuses: $affected\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
