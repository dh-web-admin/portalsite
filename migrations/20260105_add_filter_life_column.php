<?php
/**
 * Migration: add filter_life column to filter_info
 * Usage: php migrations/20260105_add_filter_life_column.php
 */

require_once __DIR__ . '/../config/config.php';

function columnExists(mysqli $conn, string $table, string $column): bool {
    $tableSafe = '`' . str_replace('`', '``', $table) . '`';
    $columnSafe = $conn->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM {$tableSafe} LIKE '{$columnSafe}'";
    $result = $conn->query($sql);
    if ($result === false) {
        throw new RuntimeException('Unable to inspect columns: ' . $conn->error);
    }
    $has = $result->num_rows > 0;
    $result->close();
    return $has;
}

try {
    if (!columnExists($conn, 'filter_info', 'filter_life')) {
        $alter = 'ALTER TABLE `filter_info` ADD COLUMN `filter_life` DECIMAL(10,1) NULL AFTER `hours`';
        if (!$conn->query($alter)) {
            throw new RuntimeException('Failed to add filter_life column: ' . $conn->error);
        }
        echo "Added filter_life column to filter_info.\n";
    } else {
        echo "filter_life column already exists. Nothing to do.\n";
    }

    echo "Migration completed successfully.\n";
} catch (Throwable $e) {
    $message = 'Migration failed: ' . $e->getMessage() . "\n";
    if (PHP_SAPI === 'cli' && defined('STDERR')) {
        fwrite(STDERR, $message);
    } else {
        echo nl2br($message);
    }
    exit(1);
}

exit(0);
