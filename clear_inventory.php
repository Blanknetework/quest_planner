<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Include database connection
require_once 'config/database.php';

try {
    // Delete all inventory items for the current user
    $stmt = $pdo->prepare("DELETE FROM user_inventory WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    $rowCount = $stmt->rowCount();
    
    // Redirect back to inventory page
    header('Location: pages/inventory.php?cleared=' . $rowCount);
    exit;
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?> 