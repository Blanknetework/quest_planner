<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_timer') {
    $questId = (int)$_POST['quest_id'];
    $timerValue = $_POST['timer_value'] ?? '00:00:00';
    
    try {
        $stmt = $pdo->prepare("UPDATE quests SET timer_value = ? WHERE id = ? AND user_id = ? AND status = 'in_progress'");
        $stmt->execute([$timerValue, $questId, $_SESSION['user_id']]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
} 