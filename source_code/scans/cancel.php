<?php
/**
 * ============================================
 * Cancel & Delete Scan (By Process ID)
 * ============================================
 */
require_once '../includes/functions.php';
require_once '../includes/db.php';

startSession();
requireLogin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$scan_id = $data['scan_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$scan_id) {
    echo json_encode(['success' => false, 'error' => 'No scan ID provided.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT pid FROM scans WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => $scan_id, 'user_id' => $user_id]);
    $scan = $stmt->fetch();

    if ($scan) {
        
        if (!empty($scan['pid'])) {
            $pid = (int)$scan['pid'];
            
            exec("pkill -P $pid");
            
            exec("kill -9 $pid");
        }

        // 3. حذف الفحص من قاعدة البيانات
        $delStmt = $pdo->prepare("DELETE FROM scans WHERE id = :id AND user_id = :user_id");
        $delStmt->execute(['id' => $scan_id, 'user_id' => $user_id]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Scan not found or already deleted.']);
    }

} catch (PDOException $e) {
    error_log("Cancel Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error while cancelling.']);
}
?>