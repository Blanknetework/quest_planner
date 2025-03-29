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
                    
                    <!-- Icon menu buttons moved to header -->
                    <div class="icon-menu-container" style="display: flex;">
                        <div class="icon-button notify-icon" style="margin-right: 10px;">
                            <div class="icon-label">NOTIFS</div>
                        </div>
                        <div class="icon-button guild-icon" style="margin-right: 10px;">
                            <div class="icon-label">GUILD</div>
                        </div>
                        <a href="/auth/login.php" class="icon-button settings-icon">
                            <div class="icon-label">SETTINGS</div>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <div class="main-content">
                <!-- Left Section - Menu and Controls -->
                <div class="menu-section">
                    <button class="menu-button mb-4" id="addQuestBtn">ADD QUEST</button>
                    
                    <!-- Difficulty Section - with label on top of border -->
                    <div class="category-container mb-4">
                        <div class="category-header">DIFFICULTY</div>
                        <div class="category-body pt-3">
                            <button class="category-option difficulty-btn" data-difficulty="Easy">EASY</button>
                            <button class="category-option difficulty-btn" data-difficulty="Medium">MEDIUM</button>
                            <button class="category-option difficulty-btn" data-difficulty="Hard">HARD</button>
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
            <form id="addQuestForm" method="POST">
                <input type="hidden" name="action" value="add_quest">
                <div class="form-group">
                    <label for="quest_name">Quest Name:</label>
                    <input type="text" id="quest_name" name="quest_name" required>
                </div>
                <div class="form-group">
                    <label for="difficulty">Difficulty:</label>
                    <select id="difficulty" name="difficulty" required>
                        <option value="Easy">Easy</option>
                        <option value="Medium">Medium</option>
                        <option value="Hard">Hard</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="time">Estimated Time:</label>
                    <input type="text" id="time" name="time" placeholder="e.g. 10m, 1h, 30m" required>
                </div>
                <button type="submit" class="submit-btn">Add Quest</button>
            </form>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>
