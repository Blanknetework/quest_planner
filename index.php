<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: landing.php');
    exit;
}


require_once 'config/database.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Check for streak login
$showStreakModal = false;
$streakDay = 0;
$streakReward = 0;
$streakTitle = '';

try {
    // Check if streak columns exist
    $columnCheckStmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'streak_claimed_today'");
    $columnCheckStmt->execute();
    $streakColumnExists = $columnCheckStmt->rowCount() > 0;
    
    if (!$streakColumnExists) {
        // Add streak columns to users table
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN last_login_date DATE DEFAULT NULL");
            $pdo->exec("ALTER TABLE users ADD COLUMN streak_count INT DEFAULT 0");
            $pdo->exec("ALTER TABLE users ADD COLUMN streak_claimed_today TINYINT DEFAULT 0");
            
            // Set default values
            $streakInfo = [
                'last_login_date' => null,
                'streak_count' => 0,
                'streak_claimed_today' => 0
            ];
        } catch (PDOException $e) {
            // Log error
            error_log("Error adding streak columns: " . $e->getMessage());
        }
    } else {
        // Get user's last login date and streak info
        $stmt = $pdo->prepare("SELECT last_login_date, streak_count, streak_claimed_today FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $streakInfo = $stmt->fetch();
        
        if (!$streakInfo) {
            $streakInfo = [
                'last_login_date' => null,
                'streak_count' => 0,
                'streak_claimed_today' => 0
            ];
        }
    }
    
    // Get current date in server's timezone
    $today = date('Y-m-d');
    $lastLoginDate = $streakInfo['last_login_date'];
    $streakCount = (int)$streakInfo['streak_count'];
    $claimedToday = (int)$streakInfo['streak_claimed_today'];
    
    // Reset claimed status if it's a new day
    if ($lastLoginDate !== $today && $claimedToday == 1) {
        $stmt = $pdo->prepare("UPDATE users SET streak_claimed_today = 0 WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $claimedToday = 0;
    }
    
    // Always show streak modal if not claimed today
    if (!$claimedToday) {
        $showStreakModal = true;
        
        // Check if this is a consecutive day from last claim
        if ($lastLoginDate === null) {
            // First time login
            $streakDay = 1;
            $streakReward = 10;
        } else {
            // Check if it's the next day from last login
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            
            if ($lastLoginDate === $yesterday) {
                // Consecutive day, increment streak
                $streakDay = $streakCount + 1;
            } else if ($lastLoginDate === $today) {
                // Same day login, continue streak
                $streakDay = $streakCount + 1;
            } else {
                // Streak broken, reset to day 1
                $streakDay = 1;
            }
        }
        
        // Determine reward based on streak day
        switch ($streakDay) {
            case 1: $streakReward = 10; break;
            case 2: $streakReward = 25; break;
            case 3: $streakReward = 30; break;
            case 4: $streakReward = 40; break;
            case 5: $streakReward = 50; break;
            case 6: $streakReward = 60; break;
            case 7: 
                $streakReward = 70; 
                $streakTitle = "7-Day Warrior";
                break;
            default:
                // For streaks beyond 7 days
                $streakReward = 70 + (($streakDay - 7) * 10);
                break;
        }
    }
    
} catch (PDOException $e) {
    // Handle error silently
    error_log("Database error with streak: " . $e->getMessage());
}

// Process form submissions first, before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Claim streak reward
    if ($action === 'claim_streak' && isset($_POST['streak_day']) && isset($_POST['streak_reward'])) {
        $streakDay = (int)$_POST['streak_day'];
        $streakReward = (int)$_POST['streak_reward'];
        $streakTitle = $_POST['streak_title'] ?? '';
        
        try {
            // Update user's streak info and coins
            // Only update streak_count if it's a new streak day
            $stmt = $pdo->prepare("UPDATE users SET last_login_date = CURDATE(), streak_count = ?, streak_claimed_today = 1, coins = coins + ? WHERE id = ?");
            $stmt->execute([$streakDay, $streakReward, $_SESSION['user_id']]);
            
            // If there's a title to award
            if (!empty($streakTitle)) {
                // Check if titles table exists
                $stmt = $pdo->query("SHOW TABLES LIKE 'titles'");
                if ($stmt->rowCount() === 0) {
                    // Create titles table
                    $pdo->exec("CREATE TABLE titles (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        title VARCHAR(255) NOT NULL,
                        is_equipped TINYINT(1) DEFAULT 0,
                        earned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX (user_id)
                    )");
                }
                
                // Check if user already has this title
                $stmt = $pdo->prepare("SELECT id FROM titles WHERE user_id = ? AND title = ?");
                $stmt->execute([$_SESSION['user_id'], $streakTitle]);
                
                if ($stmt->rowCount() === 0) {
                    // Add title to user's collection
                    $stmt = $pdo->prepare("INSERT INTO titles (user_id, title) VALUES (?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $streakTitle]);
                    
                    // Create notification for new title
                    $notificationTitle = 'New Title Earned!';
                    $notificationMessage = "You've earned the \"" . $streakTitle . "\" title for maintaining a 7-day streak!";
                    
                    // Create notification object
                    $notification = [
                        'title' => $notificationTitle,
                        'message' => $notificationMessage,
                        'id' => uniqid(),
                        'time' => time()
                    ];
                    
                    // Initialize notifications array if it doesn't exist
                    if (!isset($_SESSION['notifications'])) {
                        $_SESSION['notifications'] = [];
                    }
                    
                    // Add notification to session
                    array_unshift($_SESSION['notifications'], $notification);
                }
            }
            
            // Create notification for streak reward
            $notificationTitle = 'Streak Reward Claimed!';
            $notificationMessage = "You've claimed your Day " . $streakDay . " streak reward: " . $streakReward . " coins!";
            
            // Create notification object
            $notification = [
                'title' => $notificationTitle,
                'message' => $notificationMessage,
                'id' => uniqid(),
                'time' => time()
            ];
            
            // Initialize notifications array if it doesn't exist
            if (!isset($_SESSION['notifications'])) {
                $_SESSION['notifications'] = [];
            }
            
            // Add notification to session
            array_unshift($_SESSION['notifications'], $notification);
            
            // If this is an AJAX request, return JSON response
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'streakDay' => $streakDay,
                    'streakReward' => $streakReward,
                    'streakTitle' => $streakTitle,
                    'message' => 'Streak reward claimed successfully!'
                ]);
                exit;
            }
            
            // Redirect to prevent form resubmission
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            // Handle error
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Database error']);
                exit;
            }
        }
    }
    
    // Add a new quest
    if ($action === 'add_quest' && isset($_POST['quest_name']) && isset($_POST['difficulty']) && isset($_POST['time'])) {
        $newQuest = [
            'id' => time(), // Simple ID generation
            'name' => htmlspecialchars($_POST['quest_name']),
            'difficulty' => htmlspecialchars($_POST['difficulty']),
            'time' => htmlspecialchars($_POST['time']),
            'is_recommendation' => isset($_POST['is_recommendation']) ? (int)$_POST['is_recommendation'] : 0
        ];
        
        // Insert quest into database
        try {
            $stmt = $pdo->prepare("INSERT INTO quests (user_id, name, difficulty, time_estimate, status, is_recommendation) VALUES (?, ?, ?, ?, 'unfinished', ?)");
            $stmt->execute([$_SESSION['user_id'], $newQuest['name'], $newQuest['difficulty'], $newQuest['time'], $newQuest['is_recommendation']]);
            $newQuest['id'] = $pdo->lastInsertId();
        } catch (PDOException $e) {
            // Check if is_recommendation column exists
            if (strpos($e->getMessage(), "Unknown column 'is_recommendation'") !== false) {
                // Add the column
                $pdo->exec("ALTER TABLE quests ADD COLUMN is_recommendation TINYINT(1) DEFAULT 0");
                
                // Try again with the new column
                $stmt = $pdo->prepare("INSERT INTO quests (user_id, name, difficulty, time_estimate, status, is_recommendation) VALUES (?, ?, ?, ?, 'unfinished', ?)");
                $stmt->execute([$_SESSION['user_id'], $newQuest['name'], $newQuest['difficulty'], $newQuest['time'], $newQuest['is_recommendation']]);
                $newQuest['id'] = $pdo->lastInsertId();
            }
            // Handle error or create quests table if it doesn't exist
            else if (strpos($e->getMessage(), "doesn't exist") !== false) {
                $pdo->exec("CREATE TABLE quests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    difficulty VARCHAR(50) NOT NULL,
                    time_estimate VARCHAR(50) NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    is_recommendation TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (user_id, status)
                )");
                
                // Try again after creating the table
                $stmt = $pdo->prepare("INSERT INTO quests (user_id, name, difficulty, time_estimate, status, is_recommendation) VALUES (?, ?, ?, ?, 'unfinished', ?)");
                $stmt->execute([$_SESSION['user_id'], $newQuest['name'], $newQuest['difficulty'], $newQuest['time'], $newQuest['is_recommendation']]);
                $newQuest['id'] = $pdo->lastInsertId();
            }
        }
    }
    
    // Move quest to in-progress
    else if ($action === 'start_quest' && isset($_POST['quest_id'])) {
        $questId = (int)$_POST['quest_id'];
        $timerValue = $_POST['timer_value'] ?? '00:00:00';
        
        try {
            // First, clear timer_value from all other in-progress quests
            $stmt = $pdo->prepare("UPDATE quests SET timer_value = NULL WHERE user_id = ? AND status = 'in_progress' AND id != ?");
            $stmt->execute([$_SESSION['user_id'], $questId]);
            
            // Then update the current quest
            $stmt = $pdo->prepare("UPDATE quests SET status = 'in_progress', timer_value = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$timerValue, $questId, $_SESSION['user_id']]);
        } catch (PDOException $e) {
            // Handle error
        }
    }
    
    // Complete a questlo
    else if ($action === 'complete_quest' && isset($_POST['quest_id'])) {
        $questId = (int)$_POST['quest_id'];
        
        // Get quest info for XP award
        $stmt = $pdo->prepare("SELECT difficulty, name, is_recommendation FROM quests WHERE id = ? AND user_id = ?");
        $stmt->execute([$questId, $_SESSION['user_id']]);
        $quest = $stmt->fetch();
        
        if ($quest) {
            // Award XP based on difficulty
            $xpGain = 0;
            
            // Base XP values - handle both lowercase and uppercase difficulty values
            $difficulty = strtolower($quest['difficulty']);
            if ($difficulty === 'easy') {
                $xpGain = 5;
            } else if ($difficulty === 'medium') {
                $xpGain = 10;
            } else if ($difficulty === 'hard') {
                $xpGain = 15;
            }
            
            // Check if this was a recommendation
            $isRecommendation = isset($quest['is_recommendation']) ? (int)$quest['is_recommendation'] : 0;
            if ($isRecommendation) {
                // Recommendations get half the XP
                $xpGain = ceil($xpGain / 2);
            }
            
            // Get current user stats
            $stmt = $pdo->prepare("SELECT level, xp, coins FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            $currentXP = $user['xp'] + $xpGain;
            $level = $user['level'];
            $coins = $user['coins'] ?? 20;
            
            // Calculate coin reward based on difficulty
            $coinReward = 0;
            if ($difficulty === 'easy') {
                $coinReward = 1;
            } else if ($difficulty === 'medium') {
                $coinReward = 2;
            } else if ($difficulty === 'hard') {
                $coinReward = 3;
            }

            // Add coins to user's balance
            $coins += $coinReward;
            
            // Calculate XP needed for next level
            $baseXP = 20;
            $nextLevelXP = $baseXP;
            for ($i = 1; $i < $level; $i++) {
                $nextLevelXP = ceil($nextLevelXP * 1.5);
            }
            
            // Check if user should level up
            $leveledUp = false;
            if ($currentXP >= $nextLevelXP) {
                // Level up
                $level++;
                $leveledUp = true;
                // Recalculate next level XP for next time
                $nextLevelXP = ceil($nextLevelXP * 1.5);
            }
            
            // Update user XP and coins in the database
            $stmt = $pdo->prepare("UPDATE users SET xp = ?, level = ?, coins = ? WHERE id = ?");
            $stmt->execute([$currentXP, $level, $coins, $_SESSION['user_id']]);
            
            // Update quest status in database and clear timer value
            $stmt = $pdo->prepare("UPDATE quests SET status = 'completed', timer_value = NULL WHERE id = ? AND user_id = ?");
            $stmt->execute([$questId, $_SESSION['user_id']]);

            // Create notification for completed quest
            $notificationTitle = 'Quest Completed!';
            $recommendationText = isset($quest['is_recommendation']) && $quest['is_recommendation'] ? " (recommendation)" : " (custom)";
            $notificationMessage = "You've completed the quest: " . $quest['name'] . $recommendationText . " and earned " . $xpGain . " XP and " . $coinReward . " coins!";
            
            // Create notification object
            $notification = [
                'title' => $notificationTitle,
                'message' => $notificationMessage,
                'id' => uniqid(), // Add a unique ID
                'time' => time()
            ];
            
            // Initialize notifications array if it doesn't exist
            if (!isset($_SESSION['notifications'])) {
                $_SESSION['notifications'] = [];
            }
            
            // Add notification to session (for both AJAX and non-AJAX)
            array_unshift($_SESSION['notifications'], $notification);
            
            // If this is an AJAX request, return JSON response
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'xpGain' => $xpGain,
                    'coinReward' => $coinReward,
                    'currentXP' => $currentXP,
                    'level' => $level,
                    'nextLevelXP' => $nextLevelXP,
                    'levelUp' => $leveledUp,
                    'questName' => $quest['name'],
                    'notificationTitle' => $notificationTitle,
                    'notificationMessage' => $notificationMessage
                ]);
                exit;
            }
        } else {
            // Quest not found
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Quest not found']);
                exit;
            }
        }
    }
    
    // Delete a quest
    else if ($action === 'delete_quest' && isset($_POST['quest_id'])) {
        $questId = (int)$_POST['quest_id'];
        
        // Delete quest from database
        try {
            $stmt = $pdo->prepare("DELETE FROM quests WHERE id = ? AND user_id = ?");
            $stmt->execute([$questId, $_SESSION['user_id']]);
        } catch (PDOException $e) {
            // Handle error
        }
    }
    
    // Redirect after processing to prevent form resubmission
    if (!$isAjax) {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        http_response_code(200);
        exit;
    }
}

// Get user data from database
$stmt = $pdo->prepare("SELECT username, level, xp, coins FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Default values if no data found
$username = $user['username'] ?? 'Adventurer';
$level = $user['level'] ?? 1;
$currentXP = $user['xp'] ?? 0;
$coins = $user['coins'] ?? 20;

// Calculate XP needed for next level (starting at 20 for level 1, then multiplying by 1.5 each level)
$baseXP = 20;
$nextLevelXP = $baseXP;
for ($i = 1; $i < $level; $i++) {
    $nextLevelXP = ceil($nextLevelXP * 1.5);
}

// Initialize quest data with difficulty levels
$quests = [
    'unfinished' => [],
    'inProgress' => []
];

// Check if there's an active timer
$activeTimerQuest = null;
$activeTimer = "00:00:00";
try {
    // Get in-progress quests with timer
    $stmt = $pdo->prepare("SELECT * FROM quests WHERE user_id = ? AND status = 'in_progress' AND timer_value IS NOT NULL ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $activeTimerQuest = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($activeTimerQuest) {
        $activeTimer = $activeTimerQuest['timer_value'];
    }
} catch (PDOException $e) {
    // If table doesn't have timer_value column, add it
    if (strpos($e->getMessage(), "Unknown column 'timer_value'") !== false) {
        $pdo->exec("ALTER TABLE quests ADD COLUMN timer_value VARCHAR(10) DEFAULT NULL");
    }
}

// Load quests from database
try {
    // Get unfinished quests
    $stmt = $pdo->prepare("SELECT * FROM quests WHERE user_id = ? AND status = 'unfinished' ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $quests['unfinished'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get in-progress quests
    $stmt = $pdo->prepare("SELECT * FROM quests WHERE user_id = ? AND status = 'in_progress' ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $quests['inProgress'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Map time_estimate to time for all quests
    foreach ($quests['unfinished'] as &$quest) {
        $quest['time'] = $quest['time_estimate'] ?? "00:00:00";
    }
    unset($quest); // Break the reference
    
    foreach ($quests['inProgress'] as &$quest) {
        $quest['time'] = $quest['time_estimate'] ?? "00:00:00";
    }
    unset($quest); // Break the reference
} catch (PDOException $e) {
    // Handle error silently
    error_log("Database error: " . $e->getMessage());
}

// Fetch avatar fields for the current user
$stmt = $pdo->prepare("SELECT skin_color, hairstyle, eye_shape, mouth_shape, gender, boy_style FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get equipped title if any
$equippedTitle = "New Adventurer"; // Default title
try {
    $stmt = $pdo->prepare("SELECT title FROM titles WHERE user_id = ? AND is_equipped = 1 LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $titleData = $stmt->fetch();
    
    if ($titleData) {
        $equippedTitle = $titleData['title'];
    }
} catch (PDOException $e) {
    // Title table might not exist yet, will be created when needed
}

// Determine which skin to use
$skin = ($user['gender'] === 'Male') ? ($user['boy_style'] ?? 'boy') : ($user['skin_color'] ?? 'base1');
$hairstyle = $user['hairstyle'] ?? 'hair';
$eye_shape = $user['eye_shape'] ?? 'eye';
$mouth_shape = $user['mouth_shape'] ?? 'lip';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quest Planner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<style>
        
        .game-container {
            background-image: url('../assets/images/dashboard.jpg');
            background-size: cover;
            background-attachment: fixed;
            min-height: 100vh;
            overflow-x: hidden;
            padding: 20px;
        }

        .title-banner {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-bottom: 20px;    
            padding: 10px;
        }

        /* Datalist styling for Chrome */
        input::-webkit-calendar-picker-indicator {
            opacity: 1;
            color: white;
            background-color: #FFAA4B;
            border-radius: 3px;
            cursor: pointer;
        }

        /* Make sure dropdown options are visible */
        option {
            background-color: #4D2422;
            color: white;
            padding: 8px;
            border-bottom: 1px solid #FFAA4B;
        }

        option:hover {
            background-color: #75341A;
        }

        /* Firefox datalist styling */
        @-moz-document url-prefix() {
            input[list]::-moz-list-bullet {
                color: #FFAA4B;
            }
        }

        .title-box {
            width: 56px;
            height: 50px;
            background-color: #4D2422;
            border-radius: 10px;
            flex-shrink: 0;
            margin-right: 15px;
        }

        .title-image {
            max-width: 250px;
            height: auto;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
        }

        select {
            width: 100%;
            padding: 8px 10px;
            border: 3px solid #5C3D2E;
            background: white;
            font-family: 'KongText', monospace, system-ui;
            font-size: 12px;
            color: #5C3D2E;
            appearance: none;
        }
        
        /* Ongoing Quests Styling */
        .ongoing-quests-container {
            margin-bottom: 20px;
            margin-top: 30px;
            width: 279px;
        }

        .ongoing-quest-header {
            background: linear-gradient(90deg, #FFAA4B, #FF824E);
            border: 7px solid #8A4B22;
            border-radius: 8px;
            border-bottom: none;
            text-align: center;
            padding: 6px 0;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
            margin-bottom: -5px; 
            width: 90%;
            height: 82px;
            font-size: 24px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .ongoing-quest-items-container {
            border: 7px solid #FF9926;
            background-color: #75341A;
            border-radius: 13px;
            padding: 16px;
            min-height: 150px;
        }

        /* Timer Section Styling */
        .timer-container {
            margin-bottom: 20px;
            margin-top: 30px;
            width: 279px;
        }

        .timer-header {
            background: linear-gradient(90deg, #FFAA4B, #FF824E);
            border: 7px solid #8A4B22;
            border-radius: 8px;
            border-bottom: none;
            text-align: center;
            padding: 6px 0;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
            margin-bottom: -5px; 
            width: 90%;
            font-size: 24px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .timer-box {
            border: 7px solid #FF9926;
            background-color: #75341A;
            border-radius: 13px;
            padding: 16px;
        }

        .timer-display {
            font-size: 25px;
            font-weight: bold;
            color: white;
            text-align: center;
            padding: 5px;
            margin-bottom: 15px;
        }

        .timer-controls-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .timer-buttons {
            background-color: #FFAA4B;
            border: 5px solid #4D2422;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            padding: 8px 12px;
            width: 70%;
        }

        .timer-btn {
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            cursor: pointer;
            background-color: transparent;
            border: none;
        }

        .timer-btn img {
            width: 27px;
            height: 27px;
        }

        .timer-stop-btn {
            width: 27px;
            height: 27px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .timer-stop-btn img {
            max-width: none !important;
        }

        .timer-complete-btn {
            width: 46px;
            height: 46px;
            background-color: #FFAA4B;
            border: 5px solid #4D2422;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            cursor: pointer;
            color: #75341A;
            font-size: 20px;
        }
        
        /* Notifications Modal Styling */
        .notifications-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 50;
            visibility: hidden;
            opacity: 0;
            transition: visibility 0s, opacity 0.3s;
        }
        
        .notifications-modal.show {
            visibility: visible;
            opacity: 1;
        }
        
        .notifications-content {
            width: 90%;
            max-width: 800px;
            background-color: #5C2E1B;
            border: 7px solid #FF9926;
            border-radius: 13px;
            padding: 30px;
            color: white;
            max-height: 100vh;
            overflow-y: auto;
        }
        
        .notifications-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .notification-icon-box {
            width: 95px;
            height: 70px;
            background: linear-gradient(#FFAA4B, #FF824E);
            border: 7px solid #8A4B22;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            cursor: pointer;
        }
        
        .notification-icon-box img {
            width: 30px;
            height: 30px;
        }
        
        .notifications-header h2 {
            font-size: 36px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: white;
            margin-bottom: 10px;
            margin-left: 15px;
        }
        
        .notification-item {
            background-color: #8B4513;
            border: 3px solid #FF9926;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .notification-title {
            font-weight: bold;
            text-transform: uppercase;
            color: #FFFFFF;
            margin-bottom: 5px;
            font-size: 18px;
        }
        
        .notification-message {
            color: #FC8C1F;
            font-size: 14px;
        }

        .notification-delete {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 24px;
            height: 24px;
            background-color: #FF4B4B;
            border: 2px solid #4D2422;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }

        .notification-delete:hover {
            background-color: #FF6B6B;
        }

    </style>

    
    <div class="game-container">
        <div class="container mx-auto px-4 py-4">
            <!-- Title Banner -->
            <div class="title-banner">
                <img src="../assets/images/Quest-Planner.png" alt="QUEST PLANNER" class="title-image">
            </div>
            
        <!-- Header Section -->
            <header class="mb-6">
                <div style="display: flex; align-items: flex-start; margin-bottom: 10px;">
                    <div class="user-profile-section" style="flex: 0 0 auto;">
                        <div class="profile-box" style="position: relative; width: 150px; height: 150px;">
                            <img src="assets/images/character/<?php echo htmlspecialchars($skin); ?>.png" style="position:absolute;left:0;top:0;width:100%;height:100%;z-index:1;" alt="Skin">
                            <img src="assets/images/character/<?php echo htmlspecialchars($eye_shape); ?>.png" style="position:absolute;left:0;top:0;width:100%;height:100%;z-index:2;" alt="Eyes">
                            <img src="assets/images/character/<?php echo htmlspecialchars($mouth_shape); ?>.png" style="position:absolute;left:0;top:0;width:100%;height:100%;z-index:3;" alt="Mouth">
                            <img src="assets/images/character/<?php echo htmlspecialchars($hairstyle); ?>.png" style="position:absolute;left:0;top:0;width:100%;height:100%;z-index:4;" alt="Hair">
                        </div>
                        <div class="user-stats">
                            <div class="username-banner px-4 py-1 w-[281px] h-[36px]"><?php echo htmlspecialchars($username); ?></div>
                            <div class="level-banner px-4 py-1 w-[231px] h-[33px]">LvL <?php echo htmlspecialchars($level); ?></div>
                            <div class="xp-banner px-4 py-1 w-[231px] h-[33px] relative bg-[#5C2F22]  overflow-hidden">
                                <div class="absolute top-0 left-0 h-full bg-gradient-to-r from-[#EA6242] to-[#EE8F50]" style="width: <?php echo ($currentXP / $nextLevelXP) * 100; ?>%;"></div>
                                <div class="relative z-10 text-white font-bold">XP <?php echo $currentXP; ?>/<?php echo $nextLevelXP; ?></div>
                            </div>
                            <div class="username-banner px-4 py-1 w-[231px] h-[33px] text-[12px]"><?php echo htmlspecialchars($equippedTitle); ?></div>
                            <div class="coin-display">
                                <div class="coin-icon">C</div>
                                <span class="font-bold text-sm"><?php echo $coins; ?> coins</span>
                        </div>
                    </div>
                    <div class="header-icons" style="margin-left: 370px; display: flex;">
                        <div class="icon-container">
                            <img src="../assets/images/inventory.png" alt="Inventory" class="header-icon" onclick="location.href='pages/inventory.php'" style="cursor: pointer;">
                            <span class="icon-label">INVENTORY</span>
                        </div>
                        <div class="icon-container">
                            <img src="../assets/images/4.svg" alt="Notifications" class="header-icon" id="notifications-btn">
                            <span class="icon-label">NOTIFS</span>
                        </div>
                        <div class="icon-container">
                            <img src="../assets/images/5.svg" alt="Guild" class="header-icon" onclick="location.href='pages/guild.php'" style="cursor: pointer;">
                            <span class="icon-label">GUILD</span>
                        </div>
                        <div class="icon-container">
                        <img src="../assets/images/3.svg" alt="Settings" class="header-icon" onclick="location.href='pages/settings.php'" style="cursor: pointer;">
                        <span class="icon-label">SETTINGS</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
            <div class="main-content">
                <!-- Left Section - Menu and Controls -->
                <div class="menu-section">
                    <button class="menu-button mb-8" id="addQuestBtn">ADD QUEST</button>

                    <!-- Ongoing Quest Section -->
                    <div class="ongoing-quests-container">
                        <div class="ongoing-quest-header">
                            ONGOING<br>QUESTS
                        </div>
                        <!-- Main Ongoing Quest Box -->
                        <div class="ongoing-quest-items-container">
                            <?php if (empty($quests['inProgress'])): ?>
                                <p class="text-center text-white text-sm">No quests in progress.</p>
                            <?php else: ?>
                                <?php foreach ($quests['inProgress'] as $quest): ?>
                                <div class="ongoing-quest-item p-3 text-white">
                                    <div class="flex justify-between">
                                        <span class="text-[13px]  font-bold uppercase">QUEST:</span>
                                        <span></span> <!-- Spacer -->
                                    </div>
                                    <div class="text-center text-lg font-bold uppercase my-2">
                                        <?php echo htmlspecialchars($quest['name']); ?>
                                    </div>
                                    <div class="flex justify-between items-end">
                                        <span class="text-[13px] font-bold uppercase">DIFFICULTY:</span>
                                        <span class="text-[13px] font-bold uppercase"><?php echo htmlspecialchars($quest['difficulty']); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Timer Section -->
                    <div class="timer-container">
                        <div class="timer-header">
                            TIMER
                        </div>
                        <div class="timer-box">
                            <div class="timer-display" id="timer"><?php echo $activeTimer; ?></div>
                            <div class="timer-controls-container">
                                <div class="timer-buttons">
                                    <button class="timer-btn" id="timer-play"><img src="../assets/images/triangle.png" alt="Play"></button>
                                    <button class="timer-btn" id="timer-pause"><img src="../assets/images/pause.png" alt="Pause"></button>
                                    <button class="timer-stop-btn" id="timer-stop"><img src="../assets/images/stop.png" alt="Stop"></button>
                                </div>
                                <button class="timer-complete-btn" id="timer-complete">✓</button>
                            </div>
                        </div>
                    </div>
                                     
                    

                </div>

                <!-- Right Section - Quest Areas -->
                <div class="quests-section grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-10">
                    <!-- Unfinished Quests -->
                    <div class="relative pt-6">
                        <!-- Header positioned above -->
                        <div class="absolute top-0 left-1/2 transform -translate-x-1/2 z-5">
                        <span class="inline-block px-20 py-1 bg-gradient-to-r from-[#FFAA4B] to-[#FF824E] border-4 border-[#8A4B22] rounded-md text-white font-bold uppercase text-sm whitespace-nowrap">
                                Unfinished Quest
                            </span>
                        </div>
                        <!-- Main Quest Box -->
                        <div class="quest-container bg-[#75341A] border-[7px] border-[#D08C1F]  overflow-visible relative min-h-[200px]">
                            <span class="quest-status absolute top-2 right-2  text-[#FFFFFF] text-[13px] font-bold px-2 py-1 rounded">EDIT</span>
                            <div class="quest-items-container p-4 space-y-3">
                                <?php foreach ($quests['unfinished'] as $quest): ?>
                                <div class="quest-item bg-[#84503B]  p-3 mt-4 flex items-center justify-between shadow-[inset_0_0_0_2px_#84503B,inset_0_0_0_4px_#EBA977]" data-id="<?php echo $quest['id']; ?>">
                                    <div class="flex-grow flex flex-col">
                                        <div class="flex items-center space-x-1 mb-1"> <!-- Badges Row -->
                                            <span class="difficulty-badge  text-white text-xs px-2 py-0.5 rounded"><?php echo strtoupper($quest['difficulty']); ?></span>
                                            <span class="time-badge  text-white text-xs px-2 py-0.5 rounded"><?php echo $quest['time']; ?></span>
                                        </div>
                                        <div class="flex items-center space-x-2"> <!-- Icons + Text Row -->
                                            <div class="icon-placeholder w-5 h-5 bg-[#D4AF88] border-2 border-[#4D2422] rounded-full flex items-center justify-center text-[#4D2422] font-bold text-xs">✓</div>
                                            <div class="icon-placeholder w-5 h-5 bg-[#D4AF88] border-2 border-[#4D2422] rounded-full flex items-center justify-center text-[#4D2422] font-bold text-xs">▶</div>
                                            <span class="text-sm font-bold text-[#FFFFFF] uppercase"><?php echo $quest['name']; ?></span>
                                        </div>
                                    </div>
                                    <div class="action-buttons flex space-x-1">
                                        <button class="action-btn action-btn-delete delete-quest w-6 h-6 flex items-center justify-center bg-[#FF4B4B] text-white rounded border border-[#4D2422]" data-id="<?php echo $quest['id']; ?>">✕</button>
                                        <button class="action-btn action-btn-start start-quest w-6 h-6 flex items-center justify-center bg-[#4BFF4B] text-white rounded border border-[#4D2422]" data-id="<?php echo $quest['id']; ?>" data-timer="<?php echo $quest['time']; ?>">▶</button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- In Progress Quests -->
                    <div class="relative pt-6">
                         <!-- Header positioned above -->
                        <div class="absolute top-0 left-1/2 transform -translate-x-1/2 z-5">
                             <span class="inline-block px-20 py-1 bg-gradient-to-r from-[#FFAA4B] to-[#FF824E] border-4 border-[#8A4B22] rounded-md text-white font-bold uppercase text-sm whitespace-nowrap">
                                In Progress Quest
                            </span>
                        </div>
                         <!-- Main Quest Box -->
                        <div class="quest-container bg-[#75341A] border-[7px] border-[#D08C1F] rounded-lg overflow-visible relative min-h-[200px]">
                            <span class="quest-status absolute top-2 right-2  text-[#FFFFFF] text-[13px] font-bold px-2 py-1 rounded">DONE</span>
                            <div class="quest-items-container p-4 space-y-3">
                                <?php foreach ($quests['inProgress'] as $quest): ?>
                                <div class="quest-item bg-[#A67B5B] rounded-lg p-3 mt-4 flex items-center justify-between shadow-[inset_0_0_0_2px_#84503B,inset_0_0_0_4px_#EBA977]" data-id="<?php echo $quest['id']; ?>">
                                     <div class="flex-grow flex flex-col">
                                        <div class="flex items-center space-x-1 mb-1"> <!-- Badges Row -->
                                            <span class="difficulty-badge bg-[#4D2422] text-white text-xs px-2 py-0.5 rounded"><?php echo strtoupper($quest['difficulty']); ?></span>
                                            <span class="time-badge bg-[#4D2422] text-white text-xs px-2 py-0.5 rounded"><?php echo $quest['time']; ?></span>
                                        </div>
                                        <div class="flex items-center space-x-2"> <!-- Icons + Text Row -->
                                            <div class="icon-placeholder w-5 h-5 bg-[#D4AF88] border-2 border-[#4D2422] rounded-full flex items-center justify-center text-[#4D2422] font-bold text-xs">✓</div>
                                            <div class="icon-placeholder w-5 h-5 bg-[#D4AF88] border-2 border-[#4D2422] rounded-full flex items-center justify-center text-[#4D2422] font-bold text-xs">▶</div> 
                                            <span class="text-sm font-bold text-[#4D2422] uppercase"><?php echo $quest['name']; ?></span>
                                        </div>
                                    </div>
                                    <div class="action-buttons flex space-x-1">
                                        <button class="action-btn action-btn-delete delete-quest w-6 h-6 flex items-center justify-center bg-[#FF4B4B] text-white rounded border border-[#4D2422]" data-id="<?php echo $quest['id']; ?>">✕</button>
                                        <button class="action-btn action-btn-complete complete-quest w-6 h-6 flex items-center justify-center bg-[#FF9C4B] text-white rounded border border-[#4D2422]" data-id="<?php echo $quest['id']; ?>">✓</button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Quest Modal -->
    <div id="addQuestModal" class="quest-modal hidden">
        <div class="modal-content bg-[#75341A] border-4 border-[#FFAA4B] rounded-lg p-8">
            <div class="modal-header flex justify-between items-center mb-6">
                <button id="closeModal" class="close-button text-white hover:text-[#FFAA4B]">✕</button>
            </div>
            <form id="questForm" method="POST" action="index.php">
                <input type="hidden" name="action" value="add_quest">
                <input type="hidden" id="is_recommendation" name="is_recommendation" value="0">
                <div class="quest-form-container space-y-6">
                    <!-- Quest Name Section -->
                    <div class="relative quest-input-section">
                        <label for="quest_name" class="absolute left-1/2 transform -translate-x-1/2 top-2 text-[#FFFFFF] text-xs uppercase tracking-wider">QUEST NAME</label>
                        <input type="text" id="quest_name" name="quest_name" required
                            class="w-full pt-7 pb-2 px-4 bg-[#4D2422] border-2 border-[#FFAA4B] rounded text-white text-center">
                    </div>

                    <!-- Choose/Input Quest Section -->
                    <div class="quest-input-section">
                        <div class="text-[#FFD700] mb-2 uppercase text-sm text-center">Choose/Input quest</div>
                        <div class="relative">
                            <input type="text" id="quest_input" name="quest_input" required
                                class="w-full px-4 py-3 bg-[#4D2422] border-2 border-[#FFAA4B] rounded text-white text-center"
                                placeholder="Click to see suggestions or type your own" autocomplete="off">
                            <div id="custom-dropdown" class="absolute left-0 right-0 top-full mt-1 bg-[#4D2422] border-2 border-[#FFAA4B] rounded max-h-48 overflow-y-auto z-10 hidden">
                                <!-- Suggestions will be populated here -->
                            </div>
                        </div>
                    </div>
                                    
                    <!-- Quest Difficulty Section -->
                    <div class="quest-input-section">
                        <div class="text-[#FFD700] mb-2 uppercase text-sm text-center">Quest difficulty</div>
                        <select name="difficulty" id="difficulty" required
                            class="w-full px-4 py-3 bg-[#4D2422] border-2 border-[#FFAA4B] rounded text-white appearance-none text-center">
                            <option value="easy">Easy</option>
                            <option value="medium">Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>

                    <!-- Quest Date/Time Section -->
                    <div class="grid grid-cols-3 gap-4">
                        <div class="quest-date-section">
                            <div class="text-[#FFD700] mb-2 uppercase text-sm text-center">Set Quest Date</div>
                            <input type="text" id="quest_date" name="quest_date" value="04/03/25"
                                class="w-full px-4 py-3 bg-[#4D2422] border-2 border-[#FFAA4B] rounded text-white text-center placeholder-gray-400">
                        </div>

                        <div class="quest-time-section">
                            <div class="text-[#FFD700] mb-2 uppercase text-sm text-center">Set Quest Time</div>
                            <input type="text" id="quest_time" name="quest_time" value="10:30 PM"
                                class="w-full px-4 py-3 bg-[#4D2422] border-2 border-[#FFAA4B] rounded text-white text-center placeholder-gray-400">
                        </div>

                        <div class="quest-timer-section">
                            <div class="text-[#FFD700] mb-2 uppercase text-sm text-center">Set Quest Timer</div>
                            <input type="text" id="time" name="time" value="00:00:00" required
                                class="w-full px-4 py-3 bg-[#4D2422] border-2 border-[#FFAA4B] rounded text-white text-center placeholder-gray-400">
                            <div id="timer-error" class="text-red-500 text-sm mt-1 text-center hidden">Please set a timer greater than 00:00:00</div>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3 bg-[#FFAA4B] text-white font-bold uppercase rounded border-2 border-[#4D2422] hover:bg-[#FF9C4B] transition-colors mt-6">
                        ADD QUEST
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Notifications Modal -->
    <div id="notificationsModal" class="notifications-modal">
        <div class="notifications-content">
            <div class="notifications-header">
                <div class="notification-icon-box" id="notifications-back-btn" style="cursor: pointer;">
                    <img src="../assets/images/arrow-left.png" alt="Back">
                </div>
                <h2>Notifications</h2>
            </div>
            
            <div id="notifications-container">
                <?php if (isset($_SESSION['notifications']) && !empty($_SESSION['notifications'])): ?>
                    <?php foreach ($_SESSION['notifications'] as $notification): ?>
                        <div class="notification-item" data-id="<?php echo isset($notification['id']) ? htmlspecialchars($notification['id']) : ''; ?>">
                            <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                            <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                            <div class="notification-delete">✕</div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-white py-10">No notifications yet</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Timer Finished Modal -->
    <div id="timerFinishedModal" class="notifications-modal">
        <div class="notifications-content" style="max-width: 500px; text-align: center;">
            <h2 style="font-size: 28px; margin-bottom: 20px; text-transform: uppercase; color: white;">Timer Finished!</h2>
            <p style="color: #FC8C1F; font-size: 18px; margin-bottom: 25px;">You've run out of time. What would you like to do?</p>
            <div style="display: flex; justify-content: space-around;">
                <button id="restartTimerBtn" style="background-color: #FFAA4B; border: 5px solid #4D2422; color: white; padding: 10px 20px; font-weight: bold; border-radius: 8px; cursor: pointer;">RESTART TIMER</button>
                <button id="finishQuestBtn" style="background-color: #4BFF4B; border: 5px solid #4D2422; color: white; padding: 10px 20px; font-weight: bold; border-radius: 8px; cursor: pointer;">FINISH QUEST</button>
                <button id="closeTimerModalBtn" style="background-color: #75341A; border: 5px solid #4D2422; color: white; padding: 10px 20px; font-weight: bold; border-radius: 8px; cursor: pointer;">CLOSE</button>
            </div>
        </div>
    </div>

    <!-- Quest Completed Modal -->
    <div id="questCompletedModal" class="notifications-modal">
        <div class="notifications-content" style="max-width: 500px; text-align: center;">
            <h2 style="font-size: 28px; margin-bottom: 20px; text-transform: uppercase; color: white;">Congratulations!</h2>
            <p style="color: #FC8C1F; font-size: 18px; margin-bottom: 10px;">You've completed your quest:</p>
            <div id="completedQuestName" style="color: white; font-size: 24px; margin: 15px 0; font-weight: bold;">Quest Name</div>
            <div id="xpEarnedText" style="color: white; font-size: 22px; margin: 10px 0; padding: 10px; background-color: #8B4513; border: 3px solid #FF9926; border-radius: 5px;">
                +<span id="xpAmount">0</span> XP EARNED!
            </div>
            <div id="coinsEarnedText" style="color: white; font-size: 22px; margin: 10px 0 20px; padding: 10px; background-color: #8B4513; border: 3px solid #FF9926; border-radius: 5px;">
                +<span id="coinAmount">0</span> COINS EARNED!
            </div>
            <button id="closeCompletedModalBtn" style="background-color: #FFAA4B; border: 5px solid #4D2422; color: white; padding: 10px 20px; font-weight: bold; border-radius: 8px; cursor: pointer; margin-top: 10px;">CONTINUE</button>
        </div>
    </div>

    <!-- Streak Modal -->
    <div id="streakModal" class="notifications-modal <?php echo $showStreakModal ? 'show' : ''; ?>">
        <div class="notifications-content" style="max-width: 500px; text-align: center;">
            <h2 style="font-size: 28px; margin-bottom: 10px; text-transform: uppercase; color: white;">Daily Streak!</h2>
            <div style="display: flex; justify-content: center; margin: 20px 0;">
                <?php for ($i = 1; $i <= 7; $i++): ?>
                    <div style="position: relative; width: 40px; height: 40px; margin: 0 5px;">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background-color: <?php echo $i <= $streakDay ? '#FFAA4B' : '#75341A'; ?>; border: 3px solid #4D2422; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                            <?php echo $i; ?>
                        </div>
                        <?php if ($i === $streakDay): ?>
                            <div style="position: absolute; top: -10px; right: -10px; width: 20px; height: 20px; background-color: #FF4B4B; border: 2px solid #4D2422; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;">!</div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
            <p style="color: #FC8C1F; font-size: 18px; margin-bottom: 10px;">Day <?php echo $streakDay; ?> Streak Reward:</p>
            <div style="color: white; font-size: 24px; margin: 15px 0; font-weight: bold; padding: 10px; background-color: #8B4513; border: 3px solid #FF9926; border-radius: 5px;">
                +<?php echo $streakReward; ?> COINS
            </div>
            <?php if (!empty($streakTitle)): ?>
                <div style="color: white; font-size: 20px; margin: 15px 0; font-weight: bold; padding: 10px; background-color: #8B4513; border: 3px solid #FF9926; border-radius: 5px;">
                    NEW TITLE: "<?php echo $streakTitle; ?>"
                </div>
            <?php endif; ?>
            <form id="claimStreakForm" method="POST" action="index.php">
                <input type="hidden" name="action" value="claim_streak">
                <input type="hidden" name="streak_day" value="<?php echo $streakDay; ?>">
                <input type="hidden" name="streak_reward" value="<?php echo $streakReward; ?>">
                <?php if (!empty($streakTitle)): ?>
                    <input type="hidden" name="streak_title" value="<?php echo $streakTitle; ?>">
                <?php endif; ?>
                <button type="submit" style="background-color: #FFAA4B; border: 5px solid #4D2422; color: white; padding: 10px 30px; font-weight: bold; border-radius: 8px; cursor: pointer; margin-top: 20px; font-size: 18px;">CLAIM REWARD</button>
            </form>
        </div>
    </div>

<script>
        // Notifications Modal
        document.getElementById('notifications-btn').addEventListener('click', function() {
            document.getElementById('notificationsModal').classList.add('show');
            
            // Add event listeners to delete buttons
            setupNotificationDeleteButtons();
        });
        
        // Setup notification delete buttons
        function setupNotificationDeleteButtons() {
            document.querySelectorAll('.notification-delete').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent modal closing
                    
                    // Get notification item and ID
                    const notificationItem = this.closest('.notification-item');
                    const notificationId = notificationItem.getAttribute('data-id');
                    
                    // Send delete request to server
                    deleteNotification(notificationId, notificationItem);
                });
            });
        }
        
        // Delete a single notification
        function deleteNotification(notificationId, element) {
            fetch('delete_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + encodeURIComponent(notificationId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove notification from DOM
                    element.remove();
                    
                    // Check if there are any notifications left
                    const container = document.getElementById('notifications-container');
                    if (container.children.length === 0) {
                        container.innerHTML = '<div class="text-center text-white py-10">No notifications yet</div>';
                    }
                }
            })
            .catch(error => console.error('Error deleting notification:', error));
        }
        
        // Close the notifications modal when clicking the back button
        document.getElementById('notifications-back-btn').addEventListener('click', function() {
            document.getElementById('notificationsModal').classList.remove('show');
        });
        
        // Close the notifications modal when clicking outside of it
        document.getElementById('notificationsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });

        // Streak Modal Functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Handle streak claim via AJAX
            const streakForm = document.getElementById('claimStreakForm');
            if (streakForm) {
                streakForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Get form data
                    const formData = new FormData(streakForm);
                    
                    // Send AJAX request
                    fetch('index.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Hide streak modal
                            document.getElementById('streakModal').classList.remove('show');
                            
                            // Update coins display
                            const coinDisplay = document.querySelector('.coin-display span');
                            if (coinDisplay) {
                                const currentCoins = parseInt(coinDisplay.textContent);
                                coinDisplay.textContent = (currentCoins + data.streakReward) + ' coins';
                            }
                            
                            // If there's a title, update the title display
                            if (data.streakTitle) {
                                const titleDisplay = document.querySelector('.username-banner');
                                if (titleDisplay) {
                                    titleDisplay.textContent = data.streakTitle;
                                }
                            }
                        } else {
                            console.error('Error claiming streak reward:', data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                });
            }
            
            // Allow closing streak modal by clicking outside
            const streakModal = document.getElementById('streakModal');
            if (streakModal) {
                streakModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.remove('show');
                    }
                });
            }
        });

        // Inline debugging script
        document.getElementById('addQuestBtn').addEventListener('click', function() {
            console.log('Add Quest button clicked (inline)');
            document.getElementById('addQuestModal').classList.remove('hidden');
            
            // Ensure chore suggestions are loaded when modal opens
            updateChoreSuggestions();
        });

            // Handle close button click
            document.getElementById('closeModal').addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('addQuestModal').classList.add('hidden');
            });

            // Close modal when clicking outside
            document.getElementById('addQuestModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                }
            });

                    // Prevent clicks inside modal content from closing the modal
                document.querySelector('.modal-content').addEventListener('click', function(e) {
                    e.stopPropagation();
                });

        // Add form validation for timer
        document.getElementById('questForm').addEventListener('submit', function(e) {
            const timerValue = document.getElementById('time').value;
            if (timerValue === '00:00:00') {
                e.preventDefault();
                document.getElementById('timer-error').classList.remove('hidden');
                
                // Make error disappear after 3 seconds
                setTimeout(function() {
                    document.getElementById('timer-error').classList.add('hidden');
                }, 3000);
                
                return false;
            }
            document.getElementById('timer-error').classList.add('hidden');
            return true;
        });

        // Also validate timer on input change
        document.getElementById('time').addEventListener('input', function() {
            if (this.value !== '00:00:00') {
                document.getElementById('timer-error').classList.add('hidden');
            }
        });

        // Timer functionality
        let timerInterval = null;
        let timerSeconds = parseTimeString('<?php echo $activeTimer; ?>');
        let activeQuestId = <?php echo $activeTimerQuest ? $activeTimerQuest['id'] : 'null'; ?>;

        function parseTimeString(timeStr) {
            const [h, m, s] = timeStr.split(':').map(Number);
            return h * 3600 + m * 60 + s;
        }

        function formatTime(seconds) {
            const h = String(Math.floor(seconds / 3600)).padStart(2, '0');
            const m = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
            const s = String(seconds % 60).padStart(2, '0');
            return `${h}:${m}:${s}`;
        }

        function updateTimerDisplay() {
            document.getElementById('timer').textContent = formatTime(timerSeconds);
        }

        function startCountdown() {
            if (timerInterval) clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                if (timerSeconds > 0) {
                    timerSeconds--;
                    updateTimerDisplay();
                    
                    // Update timer in database every 10 seconds to avoid too many requests
                    if (activeQuestId && timerSeconds % 10 === 0) {
                        updateTimerInDatabase(activeQuestId, formatTime(timerSeconds));
                    }
                } else {
                    clearInterval(timerInterval);
                    // Show timer finished modal
                    document.getElementById('timerFinishedModal').classList.add('show');
                    
                    // Update timer in database to show it's finished
                    if (activeQuestId) {
                        updateTimerInDatabase(activeQuestId, "00:00:00");
                    }
                }
            }, 1000);
        }
        
        function updateTimerInDatabase(questId, timerValue) {
            const formData = new FormData();
            formData.append('action', 'update_timer');
            formData.append('quest_id', questId);
            formData.append('timer_value', timerValue);
            
            fetch('update_timer.php', {
                method: 'POST',
                body: formData
            })
            .catch(error => {
                console.error('Error updating timer:', error);
            });
        }

        // Automatically start countdown if there's an active timer
        if (activeQuestId && timerSeconds > 0) {
            startCountdown();
        }

        // Function to add a notification
        function addNotification(title, message) {
            const container = document.getElementById('notifications-container');
            const emptyNotice = container.querySelector('.text-center');
            
            // Remove the "No notifications yet" message if it exists
            if (emptyNotice) {
                emptyNotice.remove();
            }
            
            // Create notification item
            const notificationItem = document.createElement('div');
            notificationItem.className = 'notification-item';
            
            // Create title
            const notificationTitle = document.createElement('div');
            notificationTitle.className = 'notification-title';
            notificationTitle.textContent = title;
            
            // Create message
            const notificationMessage = document.createElement('div');
            notificationMessage.className = 'notification-message';
            notificationMessage.textContent = message;
            
            // Add elements to notification item
            notificationItem.appendChild(notificationTitle);
            notificationItem.appendChild(notificationMessage);
            
            // Add notification to container
            container.prepend(notificationItem); 
            
            // Also send to server to save in session
            fetch('save_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'title=' + encodeURIComponent(title) + '&message=' + encodeURIComponent(message)
            });
        }

        // Setup action buttons for quests
        document.querySelectorAll('.start-quest, .complete-quest, .delete-quest').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const questId = this.getAttribute('data-id');
                let action = '';
                
                if (this.classList.contains('start-quest')) {
                    action = 'start_quest';
                } else if (this.classList.contains('complete-quest')) {
                    action = 'complete_quest';
                } else if (this.classList.contains('delete-quest')) {
                    if (!confirm('Are you sure you want to delete this quest?')) {
                        return;
                    }
                    action = 'delete_quest';
                }
                
                // Create form data
                const formData = new FormData();
                formData.append('action', action);
                formData.append('quest_id', questId);
                
                // Use fetch instead of form submission
                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
        });

        // START QUEST HANDLER (no reload, updates timer and DOM)
        document.querySelectorAll('.start-quest').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();

                // 1. Set and start the timer
                const timerValue = this.getAttribute('data-timer');
                timerSeconds = parseTimeString(timerValue);
                updateTimerDisplay();
                
                // Stop any existing timer before starting a new one
                if (timerInterval) clearInterval(timerInterval);
                startCountdown();

                // 2. Move quest to in-progress via AJAX (no reload!)
                const questId = this.getAttribute('data-id');
                const formData = new FormData();
                formData.append('action', 'start_quest');
                formData.append('quest_id', questId);
                formData.append('timer_value', formatTime(timerSeconds));

                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        // Set active quest ID for timer updates
                        activeQuestId = questId;
                        
                        // Move quest card in the DOM (do NOT touch timer controls)
                        const questItem = this.closest('.quest-item');
                        questItem.parentNode.removeChild(questItem);

                        // Find the in-progress quest container
                        const inProgressContainer = document.querySelector(
                            '.quests-section .quest-container.bg-[#75341A] ~ .quest-container .quest-items-container'
                        );
                        if (inProgressContainer) {
                            // Update quest card style
                            questItem.classList.remove('bg-[#84503B]');
                            questItem.classList.add('bg-[#A67B5B]', 'rounded-lg');
                            // Update action buttons
                            const actionButtons = questItem.querySelector('.action-buttons');
                            if (actionButtons) {
                                // Remove the start button
                                const startBtn = actionButtons.querySelector('.start-quest');
                                if (startBtn) startBtn.remove();
                                // Add the complete button if not present
                                if (!actionButtons.querySelector('.complete-quest')) {
                                    const completeBtn = document.createElement('button');
                                    completeBtn.className = 'action-btn action-btn-complete complete-quest w-6 h-6 flex items-center justify-center bg-[#FF9C4B] text-white rounded border border-[#4D2422]';
                                    completeBtn.setAttribute('data-id', questId);
                                    completeBtn.textContent = '✓';
                                    actionButtons.appendChild(completeBtn);

                                    // Add event for complete button
                                    completeBtn.addEventListener('click', function() {
                                        if (timerInterval) clearInterval(timerInterval);
                                        // Optionally, remove from DOM or update UI
                                    });
                                }
                            }
                            inProgressContainer.appendChild(questItem);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
        });

        // COMPLETE QUEST HANDLER (AJAX, can update DOM or reload)
        document.querySelectorAll('.complete-quest').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const questId = this.getAttribute('data-id');
                
                // Stop timer if it's running
                if (timerInterval) clearInterval(timerInterval);
                
                // Complete the quest using our new function
                completeQuest(questId, true); // Show notification
            });
        });

        // DELETE QUEST HANDLER (AJAX, can update DOM or reload if you want)
        document.querySelectorAll('.delete-quest').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to delete this quest?')) {
                    return;
                }
                const questId = this.getAttribute('data-id');
                const formData = new FormData();
                formData.append('action', 'delete_quest');
                formData.append('quest_id', questId);
                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        // Optionally remove from DOM or update UI
                        const questItem = this.closest('.quest-item');
                        if (questItem) questItem.remove();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
        });

        // Play button: resume countdown
        document.getElementById('timer-play').addEventListener('click', function() {
            startCountdown();
        });

        // Pause button: pause countdown
        document.getElementById('timer-pause').addEventListener('click', function() {
            if (timerInterval) clearInterval(timerInterval);
        });

        // Stop button: stop and reset timer to 00:00:00
        document.getElementById('timer-stop').addEventListener('click', function() {
            if (timerInterval) clearInterval(timerInterval);
            timerSeconds = 0;
            updateTimerDisplay();
            
            // Clear the timer in the database if there's an active quest
            if (activeQuestId) {
                updateTimerInDatabase(activeQuestId, "00:00:00");
                
                // Show the timer finished modal with options
                document.getElementById('timerFinishedModal').classList.add('show');
            }
        });

        // Complete button: complete the quest and show congratulations
        document.getElementById('timer-complete').addEventListener('click', function() {
            if (timerInterval) clearInterval(timerInterval);
            
            // If there's an active quest, mark it as complete
            if (activeQuestId) {
                completeQuest(activeQuestId, true); // Show notification
            }
        });

        // Function to complete a quest and show the completed modal
        function completeQuest(questId, showNotification = true) {
            const formData = new FormData();
            formData.append('action', 'complete_quest');
            formData.append('quest_id', questId);
            
            fetch('index.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                // Check if response is JSON
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.includes("application/json")) {
                    return response.json();
                } else {
                    // If not JSON, reload the page
                    window.location.reload();
                    return null;
                }
            })
            .then(data => {
                if (data && data.success) {
                    // Update quest name and XP amount in modal
                    document.getElementById('completedQuestName').textContent = data.questName || 'Quest';
                    document.getElementById('xpAmount').textContent = data.xpGain;
                    document.getElementById('coinAmount').textContent = data.coinReward;
                    
                    // Show completion modal
                    document.getElementById('questCompletedModal').classList.add('show');
                    
                    // Reset active quest ID
                    activeQuestId = null;
                    
                    // Optionally remove quest from DOM
                    const questItem = document.querySelector(`.quest-item[data-id="${questId}"]`);
                    if (questItem) questItem.remove();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Fallback - reload the page if there's an error
                window.location.reload();
            });
        }

        // Finish quest button in Timer Finished modal
        document.getElementById('finishQuestBtn').addEventListener('click', function() {
            if (!activeQuestId) {
                document.getElementById('timerFinishedModal').classList.remove('show');
                return;
            }
            
            // Hide timer finished modal
            document.getElementById('timerFinishedModal').classList.remove('show');
            
            // Complete the quest
            completeQuest(activeQuestId, true); // Show notification
        });

        // Close timer modal
        document.getElementById('closeTimerModalBtn').addEventListener('click', function() {
            document.getElementById('timerFinishedModal').classList.remove('show');
        });
        
        // Close timer modal when clicking outside
        document.getElementById('timerFinishedModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });

        // Close completed modal
        document.getElementById('closeCompletedModalBtn').addEventListener('click', function() {
            // Don't clear notifications anymore
            // clearAllNotifications();
            
            // Close the modal
            document.getElementById('questCompletedModal').classList.remove('show');
            
            // Reload the page to refresh the UI
            window.location.reload();
        });
        
        // Close completed modal when clicking outside
        document.getElementById('questCompletedModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });

        // Restart timer button
        document.getElementById('restartTimerBtn').addEventListener('click', function() {
            if (!activeQuestId) {
                document.getElementById('timerFinishedModal').classList.remove('show');
                return;
            }
            
            // Get the quest's original time from the database
            fetch('get_quest_time.php?quest_id=' + activeQuestId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('timerFinishedModal').classList.remove('show');
                    
                    // Reset timer with original quest time
                    timerSeconds = parseTimeString(data.time_estimate);
                    updateTimerDisplay();
                    startCountdown();
                    
                    // Update timer in database
                    updateTimerInDatabase(activeQuestId, data.time_estimate);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('timerFinishedModal').classList.remove('show');
            });
        });

        // Replace with direct embedding of chore data
        let choresData = {
            "easy": [
                "Clean the light switches and doorknobs",
                "Clean the lint trap",
                "Clean the mirrors and glass",
                "Wipe the baseboards",
                "Wipe the cabinet doors",
                "Dust the surfaces",
                "Dust the dressers and nightstands",
                "Water the indoor plants",
                "Fluff the pillows",
                "Organize the digital files",
                "Organize the remote controls",
                "Replace the towels and bath mats",
                "Restock the toilet paper and soap",
                "Sort the laundry by color",
                "Put away the clean clothes",
                "Refill the pet food and water bowls",
                "Test the smoke detectors",
                "Tighten the loose screws",
                "Empty the trash bins",
                "Pick up the toys"
            ],
            "medium": [
                "Clean the countertops",
                "Clean the windowsills",
                "Clean the cabinet exteriors",
                "Clean the sinks and faucets",
                "Clean the pet dishes",
                "Clean the bathroom counters",
                "Clean the pet bedding",
                "Wipe the appliances",
                "Dust the shelves and electronics",
                "Organize the closet",
                "Organize the pantry",
                "Organize the workspace",
                "Organize the tools",
                "Make the bed",
                "Fold the laundry",
                "Wash the pet bedding",
                "Groom the pet",
                "Trim the pet nails",
                "Replace the batteries in remotes",
                "Replace the light bulbs"
            ],
            "hard": [
                "Clean the floors",
                "Clean the windows",
                "Clean the refrigerator interior",
                "Clean the microwave",
                "Clean the oven and stovetop",
                "Clean the bathtub and shower",
                "Clean the ceiling fans",
                "Clean the garage",
                "Organize the garage",
                "Clean the car interior",
                "Vacuum the floors",
                "Vacuum the couches and chairs",
                "Mop the hard floors",
                "Scrub the sinks and faucets",
                "Scrub the bathtub and shower",
                "Check the plumbing for leaks",
                "Check the car fluids and tire pressure",
                "Power wash the exterior walls",
                "Mow the lawn",
                "Trim the hedges and bushes",
                "Rotate the mattress",
                "Change the bed linens",
                "Pull the weeds",
                "Rake the leaves",
                "Sweep the porch or patio"
            ]
        };
        
        // Call updateChoreSuggestions immediately to populate the datalist
        updateChoreSuggestions();

        // Update chore suggestions for custom dropdown
        function updateChoreSuggestions() {
            const difficultySelect = document.getElementById('difficulty');
            const selectedDifficulty = difficultySelect.value.toLowerCase();
            const dropdown = document.getElementById('custom-dropdown');
            const inputField = document.getElementById('quest_input');
            
            console.log("Selected difficulty:", selectedDifficulty);
            console.log("Available chores for this difficulty:", choresData[selectedDifficulty]);
            
            // Clear existing options
            dropdown.innerHTML = '';
            
            // Add new options based on selected difficulty
            if (choresData[selectedDifficulty]) {
                choresData[selectedDifficulty].forEach(chore => {
                    const option = document.createElement('div');
                    option.className = 'p-2 hover:bg-[#75341A] cursor-pointer text-white text-center';
                    option.textContent = chore;
                    option.onclick = function() {
                        document.getElementById('quest_input').value = chore;
                        dropdown.classList.add('hidden');
                        
                        // Always update the quest name when a chore is selected
                        const questNameInput = document.getElementById('quest_name');
                        questNameInput.value = chore;
                        
                        // Mark this as a recommendation
                        document.getElementById('is_recommendation').value = "1";
                        
                        // Set the difficulty based on which array contains this chore
                        for (const [difficulty, chores] of Object.entries(choresData)) {
                            if (chores.includes(chore)) {
                                document.getElementById('difficulty').value = difficulty;
                                break;
                            }
                        }
                    };
                    dropdown.appendChild(option);
                });
            } else {
                console.error("No chores found for difficulty:", selectedDifficulty);
            }
        }

        // Update suggestions when difficulty changes
        document.getElementById('difficulty').addEventListener('change', updateChoreSuggestions);

        // Update suggestions when quest input is clicked
        document.getElementById('quest_input').addEventListener('click', function() {
            updateChoreSuggestions();
            document.getElementById('custom-dropdown').classList.remove('hidden');
        });

        // Show dropdown when input is focused
        document.getElementById('quest_input').addEventListener('focus', function() {
            updateChoreSuggestions();
            document.getElementById('custom-dropdown').classList.remove('hidden');
        });

        // Hide dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.id !== 'quest_input' && !e.target.closest('#custom-dropdown')) {
                document.getElementById('custom-dropdown').classList.add('hidden');
            }
        });

        // Filter suggestions as user types
        document.getElementById('quest_input').addEventListener('input', function() {
            const inputValue = this.value.toLowerCase();
            const difficultySelect = document.getElementById('difficulty');
            const selectedDifficulty = difficultySelect.value.toLowerCase();
            const dropdown = document.getElementById('custom-dropdown');
            
            // Reset recommendation flag when user types
            document.getElementById('is_recommendation').value = "0";
            
            // Show dropdown
            dropdown.classList.remove('hidden');
            
            // Clear existing options
            dropdown.innerHTML = '';
            
            // Filter chores based on input
            if (choresData[selectedDifficulty]) {
                const filteredChores = choresData[selectedDifficulty].filter(
                    chore => chore.toLowerCase().includes(inputValue)
                );
                
                filteredChores.forEach(chore => {
                    const option = document.createElement('div');
                    option.className = 'p-2 hover:bg-[#75341A] cursor-pointer text-white text-center';
                    option.textContent = chore;
                    option.onclick = function() {
                        document.getElementById('quest_input').value = chore;
                        dropdown.classList.add('hidden');
                        
                        // Always update the quest name when a chore is selected
                        const questNameInput = document.getElementById('quest_name');
                        questNameInput.value = chore;
                        
                        // Mark this as a recommendation
                        document.getElementById('is_recommendation').value = "1";
                        
                        // Set the difficulty based on which array contains this chore
                        for (const [difficulty, chores] of Object.entries(choresData)) {
                            if (chores.includes(chore)) {
                                document.getElementById('difficulty').value = difficulty;
                                break;
                            }
                        }
                    };
                    dropdown.appendChild(option);
                });
            }
            
            // Also update the quest name as user types
            const questNameInput = document.getElementById('quest_name');
            questNameInput.value = this.value;
        });
</script>

</body>
</html> 