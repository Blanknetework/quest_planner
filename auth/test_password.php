<?php
session_start();
require_once '../config/database.php';

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Only allow access to this page for users who are logged in
if (!isset($_SESSION['user_id'])) {
    echo "You must be logged in to access this page.";
    exit;
}

// Get current user
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, username, email, password FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

echo "<h1>Password Test for User: " . htmlspecialchars($user['username']) . "</h1>";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Test current password
    if ($action === 'test_current') {
        $password = $_POST['password'] ?? '';
        
        echo "<div style='background: #f0f0f0; padding: 15px; margin: 10px 0;'>";
        echo "<h3>Testing Current Password:</h3>";
        
        // Show important data for debugging
        echo "<p>User ID: " . htmlspecialchars($user['id']) . "</p>";
        echo "<p>Username: " . htmlspecialchars($user['username']) . "</p>";
        echo "<p>Email: " . htmlspecialchars($user['email']) . "</p>";
        echo "<p>Hashed Password in DB: " . htmlspecialchars($user['password']) . "</p>";
        echo "<p>Password Hash Algorithm: " . password_get_info($user['password'])['algoName'] . "</p>";
        
        // Test the password
        $result = password_verify($password, $user['password']);
        echo "<p>Entered Password: " . htmlspecialchars($password) . "</p>";
        echo "<p>Password Verification Result: " . ($result ? "SUCCESS" : "FAILURE") . "</p>";
        echo "</div>";
    }
    
    // Change password
    else if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        
        echo "<div style='background: #f0f0f0; padding: 15px; margin: 10px 0;'>";
        echo "<h3>Password Change Test:</h3>";
        
        // Verify current password
        if (password_verify($currentPassword, $user['password'])) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Show information about the new hash
            echo "<p>Current Password Verification: SUCCESS</p>";
            echo "<p>Old Hash: " . htmlspecialchars($user['password']) . "</p>";
            echo "<p>New Hash: " . htmlspecialchars($hashedPassword) . "</p>";
            
            // Update the database
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashedPassword, $user['id']])) {
                echo "<p style='color: green; font-weight: bold;'>Password updated successfully!</p>";
                
                // Verify the new password would work
                $verifyNew = password_verify($newPassword, $hashedPassword);
                echo "<p>New Password Verification: " . ($verifyNew ? "SUCCESS" : "FAILURE") . "</p>";
                
                // Reload the user data to confirm the update
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $updatedUser = $stmt->fetch();
                echo "<p>Updated Hash in DB: " . htmlspecialchars($updatedUser['password']) . "</p>";
                
                // Verify again with the hash from the DB
                $verifyUpdated = password_verify($newPassword, $updatedUser['password']);
                echo "<p>Updated Password Verification: " . ($verifyUpdated ? "SUCCESS" : "FAILURE") . "</p>";
            } else {
                echo "<p style='color: red; font-weight: bold;'>Error updating password!</p>";
                echo "<p>Error: " . implode(", ", $stmt->errorInfo()) . "</p>";
            }
        } else {
            echo "<p style='color: red; font-weight: bold;'>Current password verification failed!</p>";
        }
        echo "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
        }
        form {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f0f0f0;
            border-radius: 5px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="warning">
            <strong>Warning:</strong> This is a diagnostic tool. Use only for debugging purposes.
        </div>
        
        <h2>Test Current Password</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="test_current">
            <label for="password">Enter your current password:</label>
            <input type="password" name="password" id="password" required>
            <button type="submit">Test Password</button>
        </form>
        
        <h2>Change Password (Direct Update)</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="change_password">
            <label for="current_password">Current Password:</label>
            <input type="password" name="current_password" id="current_password" required>
            <label for="new_password">New Password:</label>
            <input type="password" name="new_password" id="new_password" required>
            <button type="submit">Update Password</button>
        </form>
        
        <p><a href="../pages/settings.php">Back to Settings</a></p>
    </div>
</body>
</html> 