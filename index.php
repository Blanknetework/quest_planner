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
            // Process form data here (add quest, update status, etc.)
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
                <div class="flex items-start gap-4">
                    <div class="profile-box"></div>
                    <div class="flex flex-col gap-1">
                        <div class="username-banner px-4 py-1">Nuevowalang3rd</div>
                        <div class="level-banner px-4 py-1">LvL 9</div>
                        <div class="coin-display">
                            <div class="coin-icon">C</div>
                            <span class="font-bold text-sm">20 coins</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <div class="flex">
                <!-- Left Section - Menu and Controls -->
                <div class="w-1/4 pr-4">
                    <div class="menu-button mb-4">ADD QUEST</div>
                    
                    <!-- Difficulty Section - with label on top of border -->
                    <div class="category-container mb-4">
                        <div class="category-header">DIFFICULTY</div>
                        <div class="category-body pt-3">
                            <button class="category-option">EASY</button>
                            <button class="category-option">MEDIUM</button>
                            <button class="category-option">HARD</button>
                        </div>
                    </div>
                    
                    <!-- Timer Section - with label on top of border -->
                    <div class="category-container mb-4">
                        <div class="category-header">TIMER</div>
                        <div class="category-body pt-3">
                            <div class="time-display">00:00:00</div>
                        </div>
                    </div>
                    
                    <!-- Date Section - with label on top of border -->
                    <div class="category-container mb-4">
                        <div class="category-header">DATE</div>
                        <div class="category-body pt-3">
                            <div class="date-display">01/21/25</div>
                        </div>
                    </div>
                </div>

                

                <!-- Right Section - Quest Areas -->
                <div class="w-3/4">
                    <div class="quest-areas">
                        <!-- Unfinished Quests -->
                        <div class="quest-container">
                            <div class="quest-header">UNFINISHED QUEST</div>
                            <div class="quest-status">EDIT</div>
                            <div class="quest-items-container">
                                <?php foreach ($quests['unfinished'] as $quest): ?>
                                <div class="quest-item">
                                    <div class="flex">
                                        <span class="difficulty-badge"><?php echo $quest['difficulty']; ?></span>
                                        <span class="time-badge"><?php echo $quest['time']; ?></span>
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
                                <div class="quest-item">
                                    <div class="flex justify-between">
                                        <div>
                                            <span class="difficulty-badge"><?php echo $quest['difficulty']; ?></span>
                                            <span class="time-badge"><?php echo $quest['time']; ?></span>
                                        </div>
                                        <div class="action-buttons">
                                            <button class="action-btn action-btn-delete">✕</button>
                                            <button class="action-btn action-btn-complete">✓</button>
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

    <script src="assets/js/script.js"></script>
</body>
</html> 