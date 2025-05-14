<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Include database connection
require_once 'config/database.php';

// Get data from POST request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['next_refresh_time'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing next_refresh_time parameter']);
    exit;
}

$nextRefreshTime = (int)$data['next_refresh_time'];

try {
    // First check if the system_settings table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($stmt->rowCount() == 0) {
        // Create the system_settings table if it doesn't exist
        $pdo->exec("CREATE TABLE system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_name VARCHAR(50) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    }
    
    // Update the shop refresh time in the database
    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_name, setting_value) 
                          VALUES ('shop_refresh_time', ?) 
                          ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$nextRefreshTime, $nextRefreshTime]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'next_refresh_time' => $nextRefreshTime]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 