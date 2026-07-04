<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

startSession();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get all in_progress scans for this user
try {
    $stmt = $pdo->prepare("
        SELECT id, target_url, status, progress, current_engine 
        FROM scans 
        WHERE user_id = :user_id AND status = 'in_progress'
        ORDER BY scan_date DESC
    ");
    $stmt->execute(['user_id' => $user_id]);
    $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'scans' => $scans
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
