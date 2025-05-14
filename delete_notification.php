<?php
session_start();

// Check if ID was provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $notificationId = $_POST['id'];
    $notificationFound = false;
    
    // Initialize notifications array if it doesn't exist
    if (!isset($_SESSION['notifications'])) {
        $_SESSION['notifications'] = [];
    }
    
    // Find and remove the notification
    foreach ($_SESSION['notifications'] as $key => $notification) {
        if (isset($notification['id']) && $notification['id'] === $notificationId) {
            unset($_SESSION['notifications'][$key]);
            $notificationFound = true;
            break;
        }
    }
    
    // Reindex the array after deletion
    $_SESSION['notifications'] = array_values($_SESSION['notifications']);
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $notificationFound,
        'message' => $notificationFound ? 'Notification deleted' : 'Notification not found'
    ]);
    exit;
}

// Invalid request
header('HTTP/1.1 400 Bad Request');
echo json_encode(['success' => false, 'message' => 'Invalid request']); 