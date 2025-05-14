<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Include database connection
require_once 'config/database.php';

try {
    // Check if the system_settings table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($stmt->rowCount() == 0) {
        // Create the system_settings table if it doesn't exist
        $pdo->exec("CREATE TABLE system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_name VARCHAR(50) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Insert initial shop refresh timer (12 hours from now)
        $twelveHoursFromNow = time() + (12 * 3600);
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_name, setting_value) VALUES ('shop_refresh_time', ?)");
        $stmt->execute([$twelveHoursFromNow]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'next_refresh_time' => $twelveHoursFromNow]);
        exit;
    }
    
    // Get the shop refresh time from the database
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'shop_refresh_time'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result) {
        $nextRefreshTime = (int)$result['setting_value'];
        $currentTime = time();
        
        // If the refresh time has passed, set a new one
        if ($currentTime > $nextRefreshTime) {
            // Calculate how many 12-hour cycles have passed
            $secondsPassed = $currentTime - $nextRefreshTime;
            $cyclesPassed = floor($secondsPassed / (12 * 3600)) + 1;
            
            // Set the new refresh time to the next 12-hour mark
            $nextRefreshTime = $nextRefreshTime + ($cyclesPassed * 12 * 3600);
            
            // Update the database
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_name = 'shop_refresh_time'");
            $stmt->execute([$nextRefreshTime]);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'next_refresh_time' => $nextRefreshTime]);
    } else {
        // If no record exists, create one
        $twelveHoursFromNow = time() + (12 * 3600);
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_name, setting_value) VALUES ('shop_refresh_time', ?)");
        $stmt->execute([$twelveHoursFromNow]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'next_refresh_time' => $twelveHoursFromNow]);
    }
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 