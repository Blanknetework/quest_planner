<?php
// Include database connection
require_once './config/database.php';

try {
    // Check if inventory table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'inventory'");
    if ($stmt->rowCount() == 0) {
        // Create inventory table if it doesn't exist
        $sql = "CREATE TABLE inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            item_image VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $pdo->exec($sql);
        echo "Inventory table created successfully!";
    } else {
        echo "Inventory table already exists.";
    }
} catch (PDOException $e) {
    echo "Error creating inventory table: " . $e->getMessage();
}
?> 