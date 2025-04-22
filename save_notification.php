<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title']) && isset($_POST['message'])) {
    // Initialize notifications array if it doesn't exist
    if (!isset($_SESSION['notifications'])) {
        $_SESSION['notifications'] = [];
    }
    
    // Add new notification to the beginning of the array
    $notification = [
        'title' => $_POST['title'],
        'message' => $_POST['message']
    ];
    
    array_unshift($_SESSION['notifications'], $notification);
    
    // Return success response
    echo json_encode(['success' => true]);
} else {
    // Return error response
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
} 