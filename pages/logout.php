<?php
// Start the session
session_start();

// Store the user ID before unsetting session variables
$userId = $_SESSION['user_id'] ?? null;

// Reset streak_claimed_today in the database if user is logged in
if ($userId) {
    require_once '../config/database.php';
    
    try {
        // Update the user's streak_claimed_today to 0
        $stmt = $pdo->prepare("UPDATE users SET streak_claimed_today = 0 WHERE id = ?");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        // Silently handle error - we don't want to interrupt the logout process
        error_log("Error resetting streak_claimed_today: " . $e->getMessage());
    }
}

// Unset all session variables
$_SESSION = array();

// If a session cookie is used, destroy it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to the login page
header("Location: ../auth/login.php");
exit;
?>
