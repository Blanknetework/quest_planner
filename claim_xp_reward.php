<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Function to log errors
function logError($message) {
    $logFile = './logs/claim_errors.log';
    $dir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    // Log the error with timestamp
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

try {
    // Include database connection
    require_once './config/database.php';
    
    // Get the request data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['item_name']) || !isset($data['item_image']) || !isset($data['required_level'])) {
        logError("Missing required parameters: " . json_encode($data));
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    $itemName = $data['item_name'];
    $itemImage = $data['item_image'];
    $requiredLevel = (int)$data['required_level'];
    
    // Check if the user has the required level
    $stmt = $pdo->prepare("SELECT level FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        logError("User not found: User ID = " . $_SESSION['user_id']);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $userLevel = (int)$user['level'];
    
    if ($userLevel < $requiredLevel) {
        logError("Level requirement not met: User level = $userLevel, Required level = $requiredLevel");
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'You need to reach level ' . $requiredLevel . ' to claim this item'
        ]);
        exit;
    }
    
    // Check if the user already has this item
    $stmt = $pdo->prepare("SELECT id FROM user_inventory WHERE user_id = ? AND item_image = ?");
    $stmt->execute([$_SESSION['user_id'], $itemImage]);
    $existingItem = $stmt->fetch();
    
    if ($existingItem) {
        logError("Item already claimed: User ID = " . $_SESSION['user_id'] . ", Item = $itemName");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'You already have this item']);
        exit;
    }
    
    // Add the item to the user's inventory
    $stmt = $pdo->prepare("INSERT INTO user_inventory (user_id, item_name, item_image) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $itemName, $itemImage]);
    
    // Log successful claim
    logError("Item successfully claimed: User ID = " . $_SESSION['user_id'] . ", Item = $itemName");
    
    // Return success response with the user's current level
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Successfully claimed ' . $itemName . '!',
        'user_level' => $userLevel
    ]);
    
} catch (PDOException $e) {
    logError("Database error: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    logError("General error: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your claim']);
    exit;
} 