<?php
session_start();
require_once '../../config/database.php';

// Check if user is admin
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => 'No action specified'];
    
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        $action = $_POST['action'];
        
        switch ($action) {
            case 'edit':
                if (isset($_POST['username']) && isset($_POST['email']) && isset($_POST['status'])) {
                    $username = $_POST['username'];
                    $email = $_POST['email'];
                    $status = (int)$_POST['status'];
                    
                    try {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, email_verified = ? WHERE id = ?");
                        $result = $stmt->execute([$username, $email, $status, $user_id]);
                        
                        if ($result) {
                            $response = ['success' => true, 'message' => 'User updated successfully'];
                        } else {
                            $response = ['success' => false, 'message' => 'Failed to update user'];
                        }
                    } catch (PDOException $e) {
                        $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Missing required fields'];
                }
                break;
                
            default:
                $response = ['success' => false, 'message' => 'Invalid action'];
        }
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
}