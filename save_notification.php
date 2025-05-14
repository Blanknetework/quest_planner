<?php
session_start();

// Handle POST requests to add notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title']) && isset($_POST['message'])) {
    $notification = [
        'title' => $_POST['title'],
        'message' => $_POST['message'],
        'id' => uniqid(),
        'time' => time()
    ];
    
    // Initialize notifications array if it doesn't exist
    if (!isset($_SESSION['notifications'])) {
        $_SESSION['notifications'] = [];
    }
    
    // Add notification to session
    array_unshift($_SESSION['notifications'], $notification);
    
    // Limit to 10 notifications
    if (count($_SESSION['notifications']) > 10) {
        array_pop($_SESSION['notifications']);
    }
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Handle GET requests to retrieve notifications
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'get') {
        // Return all notifications
        echo json_encode([
            'success' => true,
            'notifications' => $_SESSION['notifications'] ?? []
        ]);
    } else if ($_GET['action'] === 'clear') {
        // Clear all notifications
        $_SESSION['notifications'] = [];
        echo json_encode(['success' => true]);
    }
    exit;
}

// Invalid request
header('HTTP/1.1 400 Bad Request');
echo json_encode(['success' => false, 'message' => 'Invalid request']); 