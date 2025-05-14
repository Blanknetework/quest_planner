<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

require_once 'config/database.php';

// Check if quest_id is provided
if (!isset($_GET['quest_id'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Quest ID is required']));
}

$questId = (int)$_GET['quest_id'];

try {
    // Get the quest's time estimate
    $stmt = $pdo->prepare("SELECT time_estimate FROM quests WHERE id = ? AND user_id = ?");
    $stmt->execute([$questId, $_SESSION['user_id']]);
    $quest = $stmt->fetch();
    
    if ($quest) {
        echo json_encode([
            'success' => true, 
            'time_estimate' => $quest['time_estimate']
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Quest not found']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 