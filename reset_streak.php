<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: landing.php');
    exit;
}

require_once 'config/database.php';

// Check if streak columns exist and create them if they don't
try {
    $columnCheckStmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'streak_claimed_today'");
    $columnCheckStmt->execute();
    $streakColumnExists = $columnCheckStmt->rowCount() > 0;
    
    if (!$streakColumnExists) {
        // Add streak columns to users table
        $pdo->exec("ALTER TABLE users ADD COLUMN last_login_date DATE DEFAULT NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN streak_count INT DEFAULT 0");
        $pdo->exec("ALTER TABLE users ADD COLUMN streak_claimed_today TINYINT DEFAULT 0");
        echo "<p>Streak columns created successfully.</p>";
    }
} catch (PDOException $e) {
    echo "Error setting up streak columns: " . $e->getMessage();
    exit;
}

// Determine action
$action = $_GET['action'] ?? 'reset';

try {
    switch ($action) {
        case 'reset':
            // Reset streak completely
            $stmt = $pdo->prepare("UPDATE users SET last_login_date = NULL, streak_count = 0, streak_claimed_today = 0 WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $message = "Streak completely reset. <a href='index.php'>Go back</a>";
            break;
            
        case 'unclaim':
            // Just set streak_claimed_today to 0, keeping the streak count
            $stmt = $pdo->prepare("UPDATE users SET streak_claimed_today = 0 WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $message = "Streak set to unclaimed for today. <a href='index.php'>Go back</a>";
            break;
            
        case 'set':
            // Set streak to specific day
            $day = isset($_GET['day']) ? (int)$_GET['day'] : 1;
            $stmt = $pdo->prepare("UPDATE users SET streak_count = ?, streak_claimed_today = 0 WHERE id = ?");
            $stmt->execute([$day, $_SESSION['user_id']]);
            $message = "Streak set to day $day (unclaimed). <a href='index.php'>Go back</a>";
            break;
            
        case 'yesterday':
            // Set last login to yesterday
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $stmt = $pdo->prepare("UPDATE users SET last_login_date = ?, streak_claimed_today = 0 WHERE id = ?");
            $stmt->execute([$yesterday, $_SESSION['user_id']]);
            $message = "Last login set to yesterday. <a href='index.php'>Go back</a>";
            break;
            
        case 'status':
            // Just show current streak status
            $stmt = $pdo->prepare("SELECT last_login_date, streak_count, streak_claimed_today FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $streakInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $message = "Current streak status:<br>";
            $message .= "Last login date: " . ($streakInfo['last_login_date'] ?? 'NULL') . "<br>";
            $message .= "Streak count: " . ($streakInfo['streak_count'] ?? '0') . "<br>";
            $message .= "Claimed today: " . ($streakInfo['streak_claimed_today'] ? 'Yes' : 'No') . "<br>";
            $message .= "<a href='index.php'>Go back</a>";
            break;
            
        default:
            $message = "Unknown action. <a href='index.php'>Go back</a>";
    }
    
    echo "<h1>Streak Testing Tool</h1>";
    echo "<p>$message</p>";
    
    echo "<h2>Testing Options</h2>";
    echo "<ul>";
    echo "<li><a href='reset_streak.php?action=reset'>Reset streak completely</a></li>";
    echo "<li><a href='reset_streak.php?action=unclaim'>Set streak to unclaimed for today</a></li>";
    echo "<li><a href='reset_streak.php?action=set&day=1'>Set streak to day 1</a></li>";
    echo "<li><a href='reset_streak.php?action=set&day=2'>Set streak to day 2</a></li>";
    echo "<li><a href='reset_streak.php?action=set&day=6'>Set streak to day 6</a></li>";
    echo "<li><a href='reset_streak.php?action=set&day=7'>Set streak to day 7</a></li>";
    echo "<li><a href='reset_streak.php?action=yesterday'>Set last login to yesterday</a></li>";
    echo "<li><a href='reset_streak.php?action=status'>Show current streak status</a></li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 