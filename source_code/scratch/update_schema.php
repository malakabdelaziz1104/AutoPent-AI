<?php
require_once '../includes/db.php';
try {
    $pdo->exec("ALTER TABLE scans MODIFY COLUMN current_engine VARCHAR(255) DEFAULT NULL");
    echo "Column current_engine modified successfully to VARCHAR(255).";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
