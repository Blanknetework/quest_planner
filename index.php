<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// Include database connection
require_once 'config/database.php';

// Process form submissions first, before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Add a new quest
    if ($action === 'add_quest' && isset($_POST['quest_name']) && isset($_POST['difficulty']) && isset($_POST['time'])) {
        $newQuest = [
            'id' => time(), // Simple ID generation
            'name' => htmlspecialchars($_POST['quest_name']),
            'difficulty' => htmlspecialchars($_POST['difficulty']),
            'time' => htmlspecialchars($_POST['time'])
        ];
        
        // Insert quest into database
        try {
            $stmt = $pdo->prepare("INSERT INTO quests (user_id, name, difficulty, time_estimate, status) VALUES (?, ?, ?, ?, 'unfinished')");
            $stmt->execute([$_SESSION['user_id'], $newQuest['name'], $newQuest['difficulty'], $newQuest['time']]);
            $newQuest['id'] = $pdo->lastInsertId();
        } catch (PDOException $e) {
            // Handle error or create quests table if it doesn't exist
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                $pdo->exec("CREATE TABLE quests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    difficulty VARCHAR(50) NOT NULL,
                    time_estimate VARCHAR(50) NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (user_id, status)
                )");
                
                // Try again after creating the table
                $stmt = $pdo->prepare("INSERT INTO quests (user_id, name, difficulty, time_estimate, status) VALUES (?, ?, ?, ?, 'unfinished')");
                $stmt->execute([$_SESSION['user_id'], $newQuest['name'], $newQuest['difficulty'], $newQuest['time']]);
                $newQuest['id'] = $pdo->lastInsertId();
            }
        }
    }
    
    // Move quest to in-progress
    else if ($action === 'start_quest' && isset($_POST['quest_id'])) {
        $questId = (int)$_POST['quest_id'];
        try {
            $stmt = $pdo->prepare("UPDATE quests SET status = 'in_progress' WHERE id = ? AND user_id = ?");
            $stmt->execute([$questId, $_SESSION['user_id']]);
        } catch (PDOException $e) {
            // Handle error
        }
    }
    
    // Complete a quest
    else if ($action === 'complete_quest' && isset($_POST['quest_id'])) {
        $questId = (int)$_POST['quest_id'];
        
        // Get quest info for XP award
        $stmt = $pdo->prepare("SELECT difficulty FROM quests WHERE id = ? AND user_id = ?");
        $stmt->execute([$questId, $_SESSION['user_id']]);
        $quest = $stmt->fetch();
        
        if ($quest) {
            // Award XP based on difficulty
            $xpGain = 0;
            if ($quest['difficulty'] === 'Easy') {
                $xpGain = 5;
            } else if ($quest['difficulty'] === 'Medium') {
                $xpGain = 10;
            } else if ($quest['difficulty'] === 'Hard') {
                $xpGain = 15;
            }
            
            // Get current user stats
            $stmt = $pdo->prepare("SELECT level, xp FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            $currentXP = $user['xp'] + $xpGain;
            $level = $user['level'];
            
            // Calculate XP needed for next level
            $baseXP = 20;
            $nextLevelXP = $baseXP;
            for ($i = 1; $i < $level; $i++) {
                $nextLevelXP = ceil($nextLevelXP * 1.5);
            }
            
            // Check if user should level up
            if ($currentXP >= $nextLevelXP) {
                // Level up
                $level++;
                // Recalculate next level XP for next time
                $nextLevelXP = ceil($nextLevelXP * 1.5);
            }
            
            // Update user XP in the database
            $stmt = $pdo->prepare("UPDATE users SET xp = ?, level = ? WHERE id = ?");
            $stmt->execute([$currentXP, $level, $_SESSION['user_id']]);
            
            // Update quest status in database
            $stmt = $pdo->prepare("UPDATE quests SET status = 'completed' WHERE id = ? AND user_id = ?");
            $stmt->execute([$questId, $_SESSION['user_id']]);

            // Add notification for completed quest
            $notification = [
                'title' => 'Quest Completed!',
                'message' => "You've completed the quest: " . $quest['name'] . " and earned " . $xpGain . " XP!"
            ];
            
            // Initialize notifications array if it doesn't exist
            if (!isset($_SESSION['notifications'])) {
                $_SESSION['notifications'] = [];
            }
            
            // Add notification to session
            array_unshift($_SESSION['notifications'], $notification);
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
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get user data from database
$stmt = $pdo->prepare("SELECT username, level, xp FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Default values if no data found
$username = $user['username'] ?? 'Adventurer';
$level = $user['level'] ?? 1;
$currentXP = $user['xp'] ?? 0;

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

// Load quests from database
try {
    // Get unfinished quests
    $stmt = $pdo->prepare("SELECT * FROM quests WHERE user_id = ? AND status = 'unfinished'");
    $stmt->execute([$_SESSION['user_id']]);
    $quests['unfinished'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get in-progress quests
    $stmt = $pdo->prepare("SELECT * FROM quests WHERE user_id = ? AND status = 'in_progress'");
    $stmt->execute([$_SESSION['user_id']]);
    $quests['inProgress'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Map time_estimate to time for all quests
    foreach ($quests['unfinished'] as &$quest) {
        if (isset($quest['time_estimate'])) {
            $quest['time'] = $quest['time_estimate'];
        } else {
            $quest['time'] = "00:00:00"; // Default value
        }
    }
    
    foreach ($quests['inProgress'] as &$quest) {
        if (isset($quest['time_estimate'])) {
            $quest['time'] = $quest['time_estimate'];
        } else {
            $quest['time'] = "00:00:00"; // Default value
        }
    }
} catch (PDOException $e) {
   
}
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

    </style>

    
    <div class="game-container">
        <div class="container mx-auto px-4 py-4">
            <!-- Title Banner -->
            <div class="title-banner">
                <div class="title-box"></div>
                <img src="../assets/images/Quest-Planner.png" alt="QUEST PLANNER" class="title-image">
            </div>
            
        <!-- Header Section -->
            <header class="mb-6">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                    <div class="user-profile-section">
                        <div class="profile-box"></div>
                        <div class="user-stats">
                            <div class="username-banner px-4 py-1 w-[281px] h-[36px]"><?php echo htmlspecialchars($username); ?></div>
                            <div class="level-banner px-4 py-1 w-[231px] h-[33px]">LvL <?php echo htmlspecialchars($level); ?></div>
                            <div class="xp-banner px-4 py-1 w-[231px] h-[33px] relative bg-[#5C2F22]  overflow-hidden">
                                <div class="absolute top-0 left-0 h-full bg-gradient-to-r from-[#EA6242] to-[#EE8F50]" style="width: <?php echo ($currentXP / $nextLevelXP) * 100; ?>%;"></div>
                                <div class="relative z-10 text-white font-bold">XP <?php echo $currentXP; ?>/<?php echo $nextLevelXP; ?></div>
                            </div>
                            <div class="coin-display">
                                <div class="coin-icon">C</div>
                                <span class="font-bold text-sm">20 coins</span>
                </div>
                        </div>
                    </div>

                    <!-- Icon menu buttons -->
                    <div class="header-icons">
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
                            <div class="timer-display" id="timer">00:00:00</div>
                            <div class="timer-controls-container">
                                <div class="timer-buttons">
                                    <button class="timer-btn"><img src="../assets/images/triangle.png" alt="Play"></button>
                                    <button class="timer-btn"><img src="../assets/images/pause.png" alt="Pause"></button>
                                    <button class="timer-stop-btn"><img src="../assets/images/stop.png" alt="Stop"></button>
                                </div>
                                <button class="timer-complete-btn">✓</button>
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
                                        <button class="action-btn action-btn-start start-quest w-6 h-6 flex items-center justify-center bg-[#4BFF4B] text-white rounded border border-[#4D2422]" data-id="<?php echo $quest['id']; ?>">▶</button>
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
                        <input type="text" id="quest_input" name="quest_input" required
                            class="w-full px-4 py-3 bg-[#4D2422] border-2 border-[#FFAA4B] rounded text-white text-center">
                    </div>

                    <!-- Quest Difficulty Section -->
                    <div class="quest-input-section">
                        <div class="text-[#FFD700] mb-2 uppercase text-sm text-center">Quest difficulty</div>
                        <select name="difficulty" id="difficulty" required
                            class="w-full px-4 py-3 bg-[#4D2422] border-2 border-[#FFAA4B] rounded text-white appearance-none text-center">
                            <option value="Easy">Easy</option>
                            <option value="Medium">Medium</option>
                            <option value="Hard">Hard</option>
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
                        <div class="notification-item">
                            <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                            <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-white py-10">No notifications yet</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<script>
        // Notifications Modal
        document.getElementById('notifications-btn').addEventListener('click', function() {
            document.getElementById('notificationsModal').classList.add('show');
        });
        
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
            container.prepend(notificationItem); // Add at the top
            
            // Also send to server to save in session
            fetch('save_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'title=' + encodeURIComponent(title) + '&message=' + encodeURIComponent(message)
            });
        }

        // Inline debugging script
        document.getElementById('addQuestBtn').addEventListener('click', function() {
            console.log('Add Quest button clicked (inline)');
            document.getElementById('addQuestModal').classList.remove('hidden');
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
</script>

</body>
</html> 
