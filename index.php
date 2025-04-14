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
            width: 279px;
        }
        
        .ongoing-quests-container .ongoing-quest-container {
            background-color: #FFA346;
            border: 4px solid #5C3D2E;
            border-radius: 8px;
            padding: 0px;
            width: 100%;
            height: 133px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 4px 0 rgba(0, 0, 0, 0.2);
        }
        
        .ongoing-quests-container .ongoing-quest-header {
            background-color: #FFA346;
            color: white;
            text-align: center;
            font-weight: bold;
            padding: 8px 0;
            font-size: 16px;
            text-transform: uppercase;
            text-shadow: 1px 1px 0 #000;
            letter-spacing: 1px;
            font-family: 'KongText', monospace, system-ui;
        }
        
        .ongoing-quests-container .ongoing-quest-items {
            background-color: #F2D2A9; /* Lighter beige/tan color */
            border-top: 4px solid #5C3D2E;
            padding: 10px;
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            position: relative;
        }
        
        /* Custom scrollbar for the quest items */
        .ongoing-quests-container .ongoing-quest-items::-webkit-scrollbar {
            width: 8px;
        }
        
        .ongoing-quests-container .ongoing-quest-items::-webkit-scrollbar-track {
            background: #F2D2A9;
        }
        
        .ongoing-quests-container .ongoing-quest-items::-webkit-scrollbar-thumb {
            background-color: #5C3D2E;
            border-radius: 4px;
        }
        
        .ongoing-quests-container .ongoing-quest-item {
            text-align: left;
            padding: 5px;
            margin-bottom: 0;
        }
        
        .ongoing-quests-container .ongoing-quest-label {
            font-family: 'KongText', monospace, system-ui;
            font-size: 10px;
            color: #5C3D2E;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        
        .ongoing-quests-container .ongoing-quest-title {
            font-family: 'KongText', monospace, system-ui;
            font-size: 14px;
            color: #5C3D2E;
            letter-spacing: 1px;
            margin-bottom: 5px;
            word-spacing: 3px;
            text-transform: uppercase;
        }
        
        .ongoing-quests-container .ongoing-quest-info {
            display: flex;
            align-items: center;
            font-family: 'KongText', monospace, system-ui;
            font-size: 10px;
            color: #5C3D2E;
        }
        
        .ongoing-quests-container .ongoing-difficulty-tag {
            margin-right: 5px;
            text-transform: uppercase;
        }
        
        .ongoing-quests-container .ongoing-difficulty-value {
            color: #5C3D2E;
            text-transform: uppercase;
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
                            <div class="username-banner px-4 py-1"><?php echo htmlspecialchars($username); ?></div>
                            <div class="level-banner px-4 py-1">LvL <?php echo htmlspecialchars($level); ?></div>
                            <div class="xp-banner px-4 py-1">XP <?php echo $currentXP; ?>/<?php echo $nextLevelXP; ?></div>
                            <div class="coin-display">
                                <div class="coin-icon">C</div>
                                <span class="font-bold text-sm">20 coins</span>
                </div>
                        </div>
                    </div>

                    <!-- Icon menu buttons -->
                    <div class="header-icons">
                        <div class="icon-container">
                            <img src="../assets/images/4.svg" alt="Notifications" class="header-icon">
                            <span class="icon-label">NOTIFS</span>
                </div>
                        <div class="icon-container">
                            <img src="../assets/images/5.svg" alt="Guild" class="header-icon">
                            <span class="icon-label">GUILD</span>
            </div>
                        <div class="icon-container">
                            <img src="../assets/images/3.svg" alt="Settings" class="header-icon">
                            <span class="icon-label">SETTINGS</span>
                </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
            <div class="main-content">
                <!-- Left Section - Menu and Controls -->
                <div class="menu-section">
                    <button class="menu-button mb-4" id="addQuestBtn">ADD QUEST</button>
                    
                    <!-- Difficulty Section - restyled to match the image -->
                    <div class="ongoing-quests-container">
                        <div class="ongoing-quest-container">
                            <div class="ongoing-quest-header">ONGOING QUESTS</div>
                            <div class="ongoing-quest-items">
                                <?php foreach ($quests['inProgress'] as $quest): ?>
                                <div class="ongoing-quest-item">
                                    <div class="ongoing-quest-title"><?php echo strtoupper($quest['name']); ?></div>
                                    <div class="ongoing-quest-info">
                                        <div class="ongoing-difficulty-value"><?php echo $quest['difficulty']; ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                
                    <!-- Timer Section - with label on top of border -->
                    <div class="category-container mb-4">
                        <div class="category-header">TIMER</div>
                        <div class="category-body pt-3">
                            <div class="time-display" id="timer">00:00:00</div>
                        </div>
                    </div>
                
                </div>

                <!-- Right Section - Quest Areas -->
                <div class="quests-section">
                    
                    
                          
                    <div class="quest-areas">
                        <!-- Unfinished Quests -->
                        <div class="quest-container">
                            <div class="quest-header">UNFINISHED QUEST</div>
                            <div class="quest-status">EDIT</div>
                            <div class="quest-items-container">
                    <?php foreach ($quests['unfinished'] as $quest): ?>
                                <div class="quest-item" data-id="<?php echo $quest['id']; ?>">
                                    <div class="flex justify-between">
                                        <div>
                                            <span class="difficulty-badge"><?php echo $quest['difficulty']; ?></span>
                                            <span class="time-badge"><?php echo $quest['time']; ?></span>
                                        </div>
                                        <div class="action-buttons">
                                            <button class="action-btn action-btn-delete delete-quest" data-id="<?php echo $quest['id']; ?>">✕</button>
                                            <button class="action-btn action-btn-complete start-quest" data-id="<?php echo $quest['id']; ?>">▶</button>
                                        </div>
                                    </div>
                                    <div class="quest-content">
                                        <div class="checkbox-circle"></div>
                                        <span class="text-sm font-bold"><?php echo $quest['name']; ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    <!-- In Progress Quests -->
                    <div class="quest-container">
                        <div class="quest-header">IN PROGRESS QUEST</div>
                        <div class="quest-status">DONE</div>
                        <div class="quest-items-container">
                            <?php foreach ($quests['inProgress'] as $quest): ?>
                            <div class="quest-item" data-id="<?php echo $quest['id']; ?>">
                                <div class="flex justify-between">
                                    <div>
                                        <span class="difficulty-badge"><?php echo $quest['difficulty']; ?></span>
                                        <span class="time-badge"><?php echo $quest['time']; ?></span>
                </div>
                                    <div class="action-buttons">
                                        <button class="action-btn action-btn-delete delete-quest" data-id="<?php echo $quest['id']; ?>">✕</button>
                                        <button class="action-btn action-btn-complete complete-quest" data-id="<?php echo $quest['id']; ?>">✓</button>
            </div>
        </div>
                                <div class="quest-content">
                                    <div class="checkbox-circle"></div>
                                    <span class="text-sm font-bold"><?php echo $quest['name']; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Quest Modal -->
    <div id="addQuestModal" class="quest-modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h3>ADD NEW QUEST</h3>
                <button id="closeModal" class="close-button">✕</button>
            </div>
            <form id="questForm" method="POST" action="index.php">
                <input type="hidden" name="action" value="add_quest">
                <div class="quest-form-container">
                    <!-- Quest Name Section -->
                    <div class="quest-input-section">
                        <div class="quest-label">QUEST NAME</div>
                        <div class="quest-input-box">
                            <input type="text" id="quest_name" name="quest_name" required>
                        </div>
                    </div>

                    <!-- Choose/Input Quest Section -->
                    <div class="quest-input-section">
                        <div class="quest-label">Choose/Input Quest</div>
                        <div class="quest-input-box">
                            <input type="text" id="quest_input" name="quest_input" >
                        </div>
                    </div>

                    <!-- Quest Difficulty Section -->
                    <div class="quest-input-section">
                        <div class="quest-label">Quest Difficulty</div>
                        <div class="quest-input-box difficulty-select">
                            <select name="difficulty" id="difficulty" required>
                                <option value="Easy">Easy</option>
                                <option value="Medium">Medium</option>
                                <option value="Hard">Hard</option>
                            </select>
                        </div>
                        <div class="difficulty-tooltip">
                            After adding quest, choose quest difficulty.
                            <br>NOTE: There are preset quests for each difficulty. Specific quest are rewarded with fixed XP
                        </div>
                    </div>

                    <!-- Quest Date/Time Section -->
                    <div class="quest-datetime-container">
                        <div class="quest-date-section">
                            <div class="quest-label">Set Quest Date</div>
                            <div class="quest-input-box">
                                <input type="text" id="quest_date" name="quest_date" placeholder="00/00/00">
                            </div>
                        </div>

                        <div class="quest-time-section">
                            <div class="quest-label">Set Quest Time</div>
                            <div class="quest-input-box">
                                <input type="text" id="quest_time" name="quest_time" placeholder="00:00 PM">
                            </div>
                        </div>

                        <div class="quest-timer-section">
                            <div class="quest-label">Set Quest Timer</div>
                            <div class="quest-input-box">
                                <input type="text" id="time" name="time" placeholder="00:00:00" required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">ADD QUEST</button>
                </div>
            </form>
        </div>
    </div>

<script>
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
