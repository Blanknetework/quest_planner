<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Include database connection
require_once 'config/database.php';

// Get the JSON data from the request
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

// Check if required data is present
if (!isset($data['item_name']) || !isset($data['item_image']) || !isset($data['item_cost'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required item data']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if user has enough coins
    $stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    $userCoins = $user['coins'] ?? 0;
    $itemCost = (int)$data['item_cost'];
    
    // Check if the user already owns this item
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_inventory WHERE user_id = ? AND item_image = ?");
    $stmt->execute([$_SESSION['user_id'], $data['item_image']]);
    $result = $stmt->fetch();
    
    if ($result && $result['count'] > 0) {
        throw new Exception('You already own this item!');
    }
    
    if ($userCoins < $itemCost) {
        throw new Exception('Not enough coins to purchase this item');
    }
    
    // Deduct coins from user
    $newCoins = $userCoins - $itemCost;
    $stmt = $pdo->prepare("UPDATE users SET coins = ? WHERE id = ?");
    $stmt->execute([$newCoins, $_SESSION['user_id']]);
    
    // Generate a unique item ID as an integer
    $itemId = mt_rand(1000, 999999);
    
    // Determine item type based on name or image (this is a simple example)
    $itemType = 'accessory'; // Default type
    $itemName = $data['item_name'];
    
    if (stripos($itemName, 'hat') !== false || stripos($itemName, 'cap') !== false) {
        $itemType = 'hat';
    } elseif (stripos($itemName, 'eyes') !== false || stripos($itemName, 'glasses') !== false) {
        $itemType = 'eyes';
    } elseif (stripos($itemName, 'mouth') !== false || stripos($itemName, 'smile') !== false) {
        $itemType = 'mouth';
    }
    
    // Add item to user's inventory
    $stmt = $pdo->prepare("INSERT INTO user_inventory (user_id, item_id, item_name, item_type, item_image, equipped) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        $itemId,
        $data['item_name'],
        $itemType,
        $data['item_image'],
        0 // Not equipped by default
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Item purchased successfully',
        'new_balance' => $newCoins
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 