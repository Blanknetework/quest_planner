<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
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
    <div class="game-container">
    <?php
        // Initialize quest data with difficulty levels
    $quests = [
        'unfinished' => [
                ['id' => 1, 'name' => 'Fry Egg', 'time' => '10m', 'difficulty' => 'Easy']
        ],
        'inProgress' => [
                ['id' => 2, 'name' => 'Flower arrangement', 'time' => '1h', 'difficulty' => 'Hard'],
                ['id' => 3, 'name' => 'Wash Dishes', 'time' => '30m', 'difficulty' => 'Easy']
        ]
    ];
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Process quest data
            if (isset($_POST['action'])) {
                $action = $_POST['action'];
                
                // Add a new quest
                if ($action === 'add_quest' && isset($_POST['quest_name']) && isset($_POST['difficulty']) && isset($_POST['time'])) {
                    $newQuest = [
                        'id' => time(), // Simple ID generation
                        'name' => htmlspecialchars($_POST['quest_name']),
                        'difficulty' => htmlspecialchars($_POST['difficulty']),
                        'time' => htmlspecialchars($_POST['time'])
                    ];
                    
                    $quests['unfinished'][] = $newQuest;
                }
                
                // Move quest to in-progress
                else if ($action === 'start_quest' && isset($_POST['quest_id'])) {
                    $questId = (int)$_POST['quest_id'];
                    foreach ($quests['unfinished'] as $key => $quest) {
                        if ($quest['id'] === $questId) {
                            $quests['inProgress'][] = $quest;
                            unset($quests['unfinished'][$key]);
                            break;
                        }
                    }
                }
                
                // Complete a quest
                else if ($action === 'complete_quest' && isset($_POST['quest_id'])) {
                    $questId = (int)$_POST['quest_id'];
                    foreach ($quests['inProgress'] as $key => $quest) {
                        if ($quest['id'] === $questId) {
                            unset($quests['inProgress'][$key]);
                            break;
                        }
                    }
                }
                
                // Delete a quest
                else if ($action === 'delete_quest' && isset($_POST['quest_id'])) {
                    $questId = (int)$_POST['quest_id'];
                    foreach ($quests['inProgress'] as $key => $quest) {
                        if ($quest['id'] === $questId) {
                            unset($quests['inProgress'][$key]);
                            break;
                        }
                    }
                    foreach ($quests['unfinished'] as $key => $quest) {
                        if ($quest['id'] === $questId) {
                            unset($quests['unfinished'][$key]);
                            break;
                        }
                    }
                }
            }
        }
        ?>

        <div class="container mx-auto px-4 py-4">
            <!-- Title Banner -->
            <div class="title-banner">
                <div class="title-logo"></div>
                <div class="title-text">QUEST PLANNER</div>
            </div>
            
        <!-- Header Section -->
            <header class="mb-6">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                    <div class="user-profile-section">
                        <div class="profile-box"></div>
                        <div class="user-stats">
                            <div class="username-banner px-4 py-1">Nuevowalang3rd</div>
                            <div class="level-banner px-4 py-1">LvL 9</div>
                            <div class="xp-banner px-4 py-1">XP 650/1000</div>
                            <div class="coin-display">
                                <div class="coin-icon">C</div>
                                <span class="font-bold text-sm">20 coins</span>
                </div>
                        </div>
                    </div>

                    <!-- Icon menu buttons -->
                    <div class="header-icons">
                        <div class="icon-container">
                            <img src="../assets/images/notif.png" alt="Notifications" class="header-icon">
                            <span class="icon-label">NOTIFS</span>
                </div>
                        <div class="icon-container">
                            <img src="../assets/images/guild.png" alt="Guild" class="header-icon">
                            <span class="icon-label">GUILD</span>
            </div>
                        <div class="icon-container">
                            <img src="../assets/images/setting.png" alt="Settings" class="header-icon">
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
                        <div class="quest-container">
                            <div class="quest-header">ONGOING QUESTS</div>
                            <div class="quest-items-container">
                                <?php foreach ($quests['inProgress'] as $quest): ?>
                                <div class="quest-item" data-id="<?php echo $quest['id']; ?>">
                                    <div class="quest-label">Quest:</div>
                                    <div class="quest-title"><?php echo $quest['name']; ?></div>
                                    <div class="quest-info">
                                        <div class="quest-difficulty-tag">Difficulty:</div>
                                        <div class="difficulty-value"><?php echo $quest['difficulty']; ?></div>
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
                
                    <!-- Date Section - with label on top of border -->
                    <div class="category-container mb-4">
                        <div class="category-header">DATE</div>
                        <div class="category-body pt-3">
                            <div class="date-display" id="currentDate">01/21/25</div>
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
</div>

    <!-- Add Quest Modal -->
    <div id="addQuestModal" class="quest-modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h3>ADD NEW QUEST</h3>
                <button id="closeModal" class="close-button">✕</button>
            </div>
            <form id="questForm">
                <div class="quest-form-container">
                    <!-- Quest Name Section -->
                    <div class="quest-input-section">
                        <div class="quest-label">QUEST NAME</div>
                        <div class="quest-input-box">
                            <input type="text" id="quest_name" name="quest_name" placeholder="Gotta wash dishes" required>
                        </div>
                    </div>

                    <!-- Choose/Input Quest Section -->
                    <div class="quest-input-section">
                        <div class="quest-label">Choose/Input Quest</div>
                        <div class="quest-input-box">
                            <input type="text" id="quest_input" name="quest_input" placeholder="Wash Dishes" required>
                        </div>
                    </div>

                    <!-- Quest Difficulty Section -->
                    <div class="quest-input-section">
                        <div class="quest-label">Quest Difficulty</div>
                        <div class="quest-input-box difficulty-select">
                            <div class="selected-difficulty">Easy</div>
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
                                <input type="text" id="quest_date" name="quest_date" placeholder="04/03/25" required>
                            </div>
                        </div>

                        <div class="quest-time-section">
                            <div class="quest-label">Set Quest Time</div>
                            <div class="quest-input-box">
                                <input type="text" id="quest_time" name="quest_time" placeholder="10:30 PM" required>
                            </div>
                        </div>

                        <div class="quest-timer-section">
                            <div class="quest-label">Set Quest Timer</div>
                            <div class="quest-input-box">
                                <input type="text" id="quest_timer" name="quest_timer" placeholder="01:00:00" required>
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
</script>

</body>
</html> 
