<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Include database connection
require_once '../config/database.php';

// Get user data from database
$stmt = $pdo->prepare("SELECT username, level, xp, coins FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Default values if no data found
$username = $user['username'] ?? 'Adventurer';
$level = $user['level'] ?? 1;
$currentXP = $user['xp'] ?? 0;
$coins = $user['coins'] ?? 20;

// Calculate XP needed for next level
$baseXP = 20;
$nextLevelXP = $baseXP;
for ($i = 1; $i < $level; $i++) {
    $nextLevelXP = ceil($nextLevelXP * 1.5);
}

// Check which XP rewards the user has already claimed
$claimedItems = [];
try {
    $stmt = $pdo->prepare("SELECT item_image FROM user_inventory WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    while ($item = $stmt->fetch()) {
        $claimedItems[] = $item['item_image'];
    }
} catch (PDOException $e) {
    // Silently handle error
}

// Convert claimed items to JSON for JavaScript
$claimedItemsJson = json_encode($claimedItems);

// Check if we have a stored refresh timer in the database
try {
    // First check if the system_settings table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($stmt->rowCount() == 0) {
        // Create the system_settings table if it doesn't exist
        $pdo->exec("CREATE TABLE system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_name VARCHAR(50) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Insert initial shop refresh timer (12 hours from now)
        $twelveHoursFromNow = time() + (12 * 3600);
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_name, setting_value) VALUES ('shop_refresh_time', ?)");
        $stmt->execute([$twelveHoursFromNow]);
    }
    
    // Get the shop refresh time from the database
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'shop_refresh_time'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result) {
        $nextRefreshTime = (int)$result['setting_value'];
        $currentTime = time();
        
        // If the refresh time has passed, set a new one
        if ($currentTime > $nextRefreshTime) {
            // Calculate how many 12-hour cycles have passed
            $secondsPassed = $currentTime - $nextRefreshTime;
            $cyclesPassed = floor($secondsPassed / (12 * 3600)) + 1;
            
            // Set the new refresh time to the next 12-hour mark
            $nextRefreshTime = $nextRefreshTime + ($cyclesPassed * 12 * 3600);
            
            // Update the database
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_name = 'shop_refresh_time'");
            $stmt->execute([$nextRefreshTime]);
        }
        
        // Calculate remaining time until next refresh
        $secondsRemaining = $nextRefreshTime - $currentTime;
        $hours = floor($secondsRemaining / 3600);
        $minutes = floor(($secondsRemaining % 3600) / 60);
        $seconds = $secondsRemaining % 60;
        $refreshTime = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
    } else {
        // If no record exists, create one
        $twelveHoursFromNow = time() + (12 * 3600);
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_name, setting_value) VALUES ('shop_refresh_time', ?)");
        $stmt->execute([$twelveHoursFromNow]);
        
        // Set default refresh time to 12:00:00
        $refreshTime = "12:00:00";
    }
} catch (PDOException $e) {
    // If there's an error, default to 12:00:00
    $refreshTime = "12:00:00";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guild - Quest Planner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        @font-face {
            font-family: 'KongText';
            src: url('../assets/fonts/kongtext/kongtext.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        
        body {
            font-family: 'KongText', monospace;
            background-image: url('../assets/images/shop-bg.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .menu-button {
            border: 8px solid #8A4B22;
            border-radius: 12px;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            padding: 25px 30px;
            width: 360px;
            max-width: 100%;
            height: 80px;
            margin: 12px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 28px;
            transition: all 0.2s;
            text-align: center;
            letter-spacing: 2px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        
        .menu-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.4);
        }
        
        .menu-button-shop {
            background: linear-gradient(to bottom, #FB8020, #FEBB1C, #874325);
        }
        
        .menu-button-achievements {
            background: linear-gradient(to bottom, #2FDD63, #0D824F, #04EE81);
        }
        
        .menu-button-xp {
            background: linear-gradient(to bottom, #2FCEDD, #1A87B3, #04C9EE);
        }
        
        .gradient-orange {
            background: linear-gradient(to bottom, #FC8C1F, #FDB21C, #DDB21F);
        }
        
        .gradient-button {
            background: linear-gradient(90deg, #FFAA4B, #FF824E);
        }
        
        /* XP Reward Item Styles */
        .xp-reward-item {
            box-shadow: 0 0 15px rgba(255, 156, 89, 0.6);
            transition: all 0.3s ease;
        }
        
        .xp-reward-item:hover {
            box-shadow: 0 0 20px rgba(255, 156, 89, 0.9);
            transform: translateY(-2px);
        }
        
        .level-label {
            box-shadow: 0 0 10px rgba(47, 206, 221, 0.7);
        }
        
        .footer-container {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            z-index: 1;
        }
        
        .footer-container img {
            width: 100%;
            height: auto;
        }
        
        .main-content {
            flex: 1;
            margin-bottom: 60px; 
        }
        

    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brown: {
                            DEFAULT: '#75341A',
                            dark: '#8A4B22'
                        },
                        orange: {
                            DEFAULT: '#FF9926',
                            light: '#FFAA4B',
                            dark: '#FF824E'
                        }
                    }
                }
            }
        };
    </script>
</head>
<body>
    <!-- Main Back Button (will change functionality based on view) -->
    <a id="back-button" href="../index.php" class="absolute top-5 left-12 md:left-20 w-16 h-14 gradient-button border-[5px] border-brown-dark rounded-md flex items-center justify-center cursor-pointer z-40">
        <img src="../assets/images/arrow-left.png" alt="Back" class="w-6 h-6">
    </a>  
    
    <!-- Guild Title -->
    <div id="guild-title" class="absolute top-5 left-32 md:left-[200px] w-64 md:w-72 h-14 gradient-orange border-[8px] border-brown-dark rounded-lg flex items-center justify-center">
        <h1 class="text-white text-sm uppercase">GUILD</h1>
    </div>
    
    <!-- Shop Title (hidden by default) -->
    <div id="shop-title" class="absolute top-5 left-32 md:left-[200px] w-64 md:w-72 h-14 gradient-orange border-[8px] border-brown-dark rounded-lg flex items-center justify-center" style="display: none;">
        <h1 class="text-white text-sm uppercase">SHOP</h1>
    </div>
    
    <!-- Achievements Title (hidden by default) -->
    <div id="achievements-title" class="absolute top-5 left-32 md:left-[200px] w-64 md:w-72 h-14 border-[8px] border-[#198439] rounded-lg flex items-center justify-center" style="display: none; background: linear-gradient(to bottom, #2FDD63, #0D824F);">
        <h1 class="text-white text-sm uppercase">ACHIEVEMENTS</h1>
    </div>
    
    <!-- XP Progress Title (hidden by default) -->
    <div id="xp-progress-title" class="absolute top-5 left-32 md:left-[200px] w-64 md:w-72 h-14 gradient-orange border-[8px] border-brown-dark rounded-lg flex items-center justify-center" style="display: none;">
        <h1 class="text-white text-sm uppercase">XP PROGRESS</h1>
    </div>
    
    <div class="container mx-auto px-4 pt-24 main-content relative flex flex-col items-center" style="min-height: 80vh;">
        <!-- Shopkeeper with chat box when shop is not open -->
        <div id="shopkeeper-main" class="absolute left-1/4 -translate-x-3/4 bottom-[102px] flex flex-col items-center">
            <img src="../assets/images/shopkeeper.png" alt="Shopkeeper" class="w-[450px] h-auto relative z-0">
            <div class="absolute left-1/2 top-[400px] -translate-x-1/2 -translate-y-1/2 z-10 flex flex-col items-center w-full">
                <div class="border-4 border-brown rounded-xl px-10 py-4 w-[600px] h-[170px] shadow-lg flex items-center justify-center" style="background: linear-gradient(60deg, #FFAA4B 0%, #FF824E 100%);">
                    <p class="text-white font-bold text-center text-[16px] md:text-[16px] leading-normal" style="font-family: 'KongText', monospace; text-shadow: 2px 2px 4px #75341A; letter-spacing: 1px;">
                        <span id="typewriter-text"></span>
                    </p>
                </div>
            </div>
        </div>
    </div>
    

        <div id="shop-container" class="fixed inset-0 flex flex-col items-center justify-center bg-transparent z-0" style="display: none;">
            <div id="shopkeeper-main" class="absolute left-[527px] -translate-x-3/4 bottom-[162px] flex flex-col items-center">
                <img src="../assets/images/shopkeeper.png" alt="Shopkeeper" class="w-[450px] h-auto relative z-0">
                <div class="absolute left-1/2 top-[400px] -translate-x-1/2 -translate-y-1/2 z-10 flex flex-col items-center w-full">
                    <div class="border-4 border-[#5C2F22] rounded-xl px-10 py-4 w-[600px] h-[170px] shadow-lg flex items-center justify-center" style="background: linear-gradient(60deg, #FFAA4B 0%, #FF824E 100%);">
                    <p class="text-white font-bold text-center text-[16px] md:text-[16px] leading-normal" style="font-family: 'KongText', monospace; text-shadow: 2px 2px 4px #75341A; letter-spacing: 1px;">
                        <span id="typewriter-shop">Your journey continues! Browse available upgrades and see what treasures await—every item brings you closer to greatness!</span>
                    </p>
                    </div>
                </div>
            </div>


            <div class="absolute left-[770px] top-[50px] flex flex-col items-center justify-start bg-[#75341A] border-8 border-[#5C2F22] rounded-xl shadow-lg ml-16" style="width: 900px; height: 600px;">
                <div class="flex items-center justify-start w-full px-8 pt-6">
                        <div class="coin-icon">C</div>
                    <span class="text-white text-lg font-bold"><?php echo $coins; ?> coins</span>
                    </div>
                <div class="text-white text-3xl font-bold uppercase text-center mt-2 mb-6 tracking-wider" style="font-family: 'KongText', monospace;">Featured Items</div>
                   <div class="grid grid-cols-4 gap-6 px-8 w-full">
                    <div class="flex flex-col items-center bg-[#8A4B22] border-[9px] border-orange-400 rounded-lg p-4">
                        <div class="text-white text-base font-bold uppercase mb-2">ITEM</div>
                        <img src="../assets/images/10.png" alt="Cat Hat" class="w-16 h-16 mb-2">
                        <div class="flex items-center gap-2 mt-2">
                            <div class="coin-icon">C</div>
                            <span class="text-white text-base">20</span>
                            <button class="ml-2 bg-orange-500 text-white rounded px-3 py-1 text-sm font-bold uppercase buy-btn" data-item="Cat Hat" data-image="../assets/images/10.png" data-cost="20">Buy</button>
                            </div>
                        </div>
                    <div class="flex flex-col items-center bg-[#8A4B22] border-[9px] border-orange-400 rounded-lg p-4">
                        <div class="text-white text-base font-bold uppercase mb-2">ITEM</div>
                        <img src="../assets/images/11.png" alt="Headphones" class="w-16 h-16 mb-2">
                        <div class="flex items-center gap-2 mt-2">
                            <div class="coin-icon">C</div>
                            <span class="text-white text-base">20</span>
                            <button class="ml-2 bg-orange-500 text-white rounded px-3 py-1 text-sm font-bold uppercase buy-btn" data-item="Headphones" data-image="../assets/images/11.png" data-cost="20">Buy</button>
                            </div>
                        </div>
                    <div class="flex flex-col items-center bg-[#8A4B22] border-[9px] border-orange-400 rounded-lg p-4">
                        <div class="text-white text-base font-bold uppercase mb-2">ITEM</div>
                        <img src="../assets/images/21.png" alt="Hat" class="w-16 h-16 mb-2">
                        <div class="flex items-center gap-2 mt-2">
                            <div class="coin-icon">C</div>
                            <span class="text-white text-base">20</span>
                            <button class="ml-2 bg-orange-500 text-white rounded px-3 py-1 text-sm font-bold uppercase buy-btn" data-item="Hat" data-image="../assets/images/21.png" data-cost="20">Buy</button>
                            </div>
                        </div>
                    <div class="flex flex-col items-center bg-[#8A4B22] border-[9px] border-orange-400 rounded-lg p-4">
                        <div class="text-white text-base font-bold uppercase mb-2">ITEM</div>
                        <img src="../assets/images/16.png" alt="Glasses" class="w-16 h-16 mb-2">
                        <div class="flex items-center gap-2 mt-2">
                            <div class="coin-icon">C</div>
                            <span class="text-white text-base">20</span>
                            <button class="ml-2 bg-orange-500 text-white rounded px-3 py-1 text-sm font-bold uppercase buy-btn" data-item="Glasses" data-image="../assets/images/16.png" data-cost="20">Buy</button>
                            </div>
                        </div>
                        <!-- Row 2 -->
                    <div class="flex flex-col items-center bg-[#8A4B22] border-[9px] border-orange-400 rounded-lg p-4">
                        <div class="text-white text-base font-bold uppercase mb-2">ITEM</div>
                        <img src="../assets/images/12.png" alt="Mouth Shape" class="w-16 h-16 mb-2">
                        <div class="flex items-center gap-2 mt-2">
                            <div class="coin-icon">C</div>
                            <span class="text-white text-base">20</span>
                            <button class="ml-2 bg-orange-500 text-white rounded px-3 py-1 text-sm font-bold uppercase buy-btn" data-item="Mouth Shape" data-image="../assets/images/12.png" data-cost="20">Buy</button>
                            </div>
                        </div>
                    <div class="flex flex-col items-center bg-[#8A4B22] border-[9px] border-orange-400 rounded-lg p-4">
                        <div class="text-white text-base font-bold uppercase mb-2">ITEM</div>
                        <img src="../assets/images/6.png" alt="Sprout" class="w-16 h-16 mb-2">
                        <div class="flex items-center gap-2 mt-2">
                            <div class="coin-icon">C</div>
                            <span class="text-white text-base">20</span>
                            <button class="ml-2 bg-orange-500 text-white rounded px-3 py-1 text-sm font-bold uppercase buy-btn" data-item="Sprout" data-image="../assets/images/6.png" data-cost="20">Buy</button>
                            </div>
                        </div>
                    <div class="flex flex-col items-center bg-[#8A4B22] border-[9px] border-orange-400 rounded-lg p-4">
                        <div class="text-white text-base font-bold uppercase mb-2">ITEM</div>
                        <img src="../assets/images/14.png" alt="Mouth Shape" class="w-16 h-16 mb-2">
                        <div class="flex items-center gap-2 mt-2">
                            <div class="coin-icon">C</div>
                            <span class="text-white text-base">20</span>
                            <button class="ml-2 bg-orange-500 text-white rounded px-3 py-1 text-sm font-bold uppercase buy-btn" data-item="Mouth Shape" data-image="../assets/images/14.png" data-cost="20">Buy</button>
                            </div>
                        </div>
                    <div class="flex flex-col items-center bg-[#8A4B22] border-[9px] border-orange-400 rounded-lg p-4">
                        <div class="text-white text-base font-bold uppercase mb-2">ITEM</div>
                        <img src="../assets/images/22.png" alt="Cap" class="w-16 h-16 mb-2">
                        <div class="flex items-center gap-2 mt-2">
                            <div class="coin-icon">C</div>
                            <span class="text-white text-base">20</span>
                            <button class="ml-2 bg-orange-500 text-white rounded px-3 py-1 text-sm font-bold uppercase buy-btn" data-item="Cap" data-image="../assets/images/22.png" data-cost="20">Buy</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Achievements Container -->
        <div id="achievements-container" class="fixed inset-0 flex flex-col items-center justify-center bg-transparent z-0" style="display: none;">
            <div id="shopkeeper-achievements" class="absolute left-[527px] -translate-x-3/4 bottom-[162px] flex flex-col items-center">
                <img src="../assets/images/shopkeeper.png" alt="Shopkeeper" class="w-[450px] h-auto relative z-0">
                <div class="absolute left-1/2 top-[400px] -translate-x-1/2 -translate-y-1/2 z-10 flex flex-col items-center w-full">
                    <div class="border-4 border-[#5C2F22] rounded-xl px-10 py-4 w-[600px] h-[170px] shadow-lg flex items-center justify-center" style="background: linear-gradient(60deg, #FFAA4B 0%, #FF824E 100%);">
                    <p class="text-white font-bold text-center text-[16px] md:text-[16px] leading-normal" style="font-family: 'KongText', monospace; text-shadow: 2px 2px 4px #75341A; letter-spacing: 1px;">
                        <span id="typewriter-achievements">Your journey continues! Track your achievements and see what badges you've earned—every victory brings you closer to greatness!</span>
                    </p>
                    </div>
                </div>
            </div>

            <div class="absolute left-[770px] top-[50px] flex flex-col items-center justify-start bg-[#75341A] border-8 border-[#5C2F22] rounded-xl shadow-lg ml-16 relative" style="width: 900px; height: 600px; padding-bottom: 60px;">
                <!-- Left side icons -->
                <div class="absolute -left-[56px] top-15 flex flex-col space-y-3">
                    <div class="bg-[#5C2F22] border-[5px] border-[#A2573A] rounded-md p-2 cursor-pointer">
                        <img src="../assets/images/41.png" alt="Icon 1" class="w-6 h-6">
                    </div>
                    <div class="bg-[#5C2F22] border-[5px] border-[#A2573A] rounded-md p-2 cursor-pointer">
                        <img src="../assets/images/42.png" alt="Icon 2" class="w-6 h-6">
                    </div>
                </div>
                
                <div class="text-white text-5xl font-bold uppercase text-center mt-6 mb-8 tracking-wider" style="font-family: 'KongText', monospace;">ACHIEVEMENTS</div>
                <div class="w-full px-8 space-y-4">
                    <!-- Achievement Row 1 -->
                    <div class="flex bg-[#8A4B22] border-[6px] border-orange-400 rounded-lg p-4 items-center justify-between">
                        <div class="flex flex-col">
                            <div class="text-white text-xl font-bold uppercase mb-1">LAST-MINUTE HERO</div>
                            <div class="text-orange-300 text-sm">SUCCESSFULLY COMPLETE A QUEST JUST BEFORE THE DEADLINE.</div>
                        </div>
                        <div class="flex items-center bg-[#6B3213] p-2 rounded-md border-2 border-orange-400">
                            <div class="coin-icon mr-2">C</div>
                            <span class="text-white text-xl">20</span>
                        </div>
                    </div>

                    <!-- Achievement Row 2 -->
                    <div class="flex bg-[#8A4B22] border-[6px] border-orange-400 rounded-lg p-4 items-center justify-between">
                        <div class="flex flex-col">
                            <div class="text-white text-xl font-bold uppercase mb-1">NO SNOOZE</div>
                            <div class="text-orange-300 text-sm">START YOUR QUEST ON TIME FOR A WEEK.</div>
                        </div>
                        <div class="flex items-center bg-[#6B3213] p-2 rounded-md border-2 border-orange-400">
                            <div class="coin-icon mr-2">C</div>
                            <span class="text-white text-xl">15</span>
                        </div>
                    </div>

                    <!-- Achievement Row 3 -->
                    <div class="flex bg-[#8A4B22] border-[6px] border-orange-400 rounded-lg p-4 items-center justify-between">
                        <div class="flex flex-col">
                            <div class="text-white text-xl font-bold uppercase mb-1">WEEKEND WARRIOR</div>
                            <div class="text-orange-300 text-sm">WORK ON QUEST DURING A WEEKEND.</div>
                        </div>
                        <div class="flex items-center bg-[#6B3213] p-2 rounded-md border-2 border-orange-400">
                            <div class="coin-icon mr-2">XP</div>
                            <span class="text-white text-xl">20</span>
                        </div>
                    </div>

                    <!-- Achievement Row 4 with Claim Button -->
                    <div class="flex bg-[#8A4B22] border-[6px] border-orange-400 rounded-lg p-4 items-center justify-between relative">
                        <div class="flex flex-col">
                            <div class="text-white text-xl font-bold uppercase mb-1">EARLY BIRD</div>
                            <div class="text-orange-300 text-sm">FINISH A QUEST BEFORE THE DEADLINE.</div>
                        </div>
                        <div class="flex items-center bg-[#6B3213] p-2 rounded-md border-2 border-orange-400">
                            <div class="coin-icon mr-2">C</div>
                            <span class="text-white text-xl">30</span>
                        </div>
                        
                  
                    </div>
                </div>
            </div>
        </div>

        <!-- XP Progress Container -->
        <div id="xp-progress-container" class="fixed inset-0 flex flex-col items-center justify-center bg-transparent z-0" style="display: none;">
            <!-- Blue background with light rays -->
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('../assets/images/xp-bg.png');"></div>
            
            <!-- Main Content Area with Title, Level, and XP Bar -->
            <div class="absolute  left-[40px] top-[150px] w-[500px] flex flex-col items-start">        
                <!-- XP Progress Title -->
                <div class="flex items-center mb-6 ml-10 mt-4">
                    <div class="text-white font-bold" style="font-family: 'KongText', monospace; font-size: 32px; text-shadow: 2px 2px 0px rgba(0,0,0,0.5); letter-spacing: 2px; line-height: 1.2; text-transform: uppercase;">
                       XP PROGRESS
                    </div>
                </div>
                
                <!-- Level Display -->
                <div class="text-yellow-300 text-3xl font-bold mb-4 ml-10" style="font-family: 'KongText', monospace; text-shadow: 2px 2px 0px rgba(0,0,0,0.5);">
                    LVL <?php echo $level; ?>
                </div>
                
                <!-- XP Bar (orange/brown) -->
                <div class="w-[450px] h-[35px] relative mb-4 ml-10 overflow-hidden rounded-lg" style="border: 3px solid #a6673c; background-color: #703423;">
                    <div class="h-full rounded-md" style="width: <?php echo min(100, ($currentXP / $nextLevelXP) * 100); ?>%; background: linear-gradient(to bottom, #ff9c59, #e3713b);">
                    </div>
                    <div class="absolute inset-0 flex items-center justify-center text-white font-bold text-lg" style="font-family: 'KongText', monospace; text-shadow: 1px 1px 0px rgba(0,0,0,0.5);">
                        XP <?php echo $currentXP; ?>/<?php echo $nextLevelXP; ?>
                    </div>
                </div>
            </div>
            
            <!-- Shopkeeper on right side with chat bubble -->
            <div class="absolute right-[80px] top-[80px] flex flex-col items-center">
                <img src="../assets/images/shopkeeper.png" alt="Shopkeeper" class="w-[400px] h-auto">
                <div class="absolute top-[80px] right-[350px] bg-orange-400 border-4 border-[#5C2F22] rounded-xl p-4 w-[250px]" style="background: linear-gradient(60deg, #FFAA4B 0%, #FF824E 100%);">
                    <p class="text-white text-sm font-bold">
                        <span id="typewriter-xp">Your journey continues! Track your XP progress and see how close you are to leveling up—every quest brings you closer to greatness!</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Side Buttons -->
    <div id="guild-buttons" class="fixed right-0 top-32 w-full flex flex-col items-center gap-4 z-20 px-4 md:right-[24px] md:items-end md:w-auto">
        <button id="shop-button" onclick="showShopView()" class="w-full h-16 md:w-[400px] md:h-[70px] lg:w-[540px] lg:h-[100px] text-2xl md:text-3xl lg:text-4xl font-bold uppercase text-white shadow-lg rounded-lg border-[6px]" style="background: linear-gradient(180deg, #FB8020 0%, #FEBB1C 50%, #874325 100%); border-color: #FB8020; font-family: 'KongText', monospace; text-shadow: 2px 2px 0 #75341A, 0 2px 4px #75341A;">SHOP</button>
        <button id="achievements-button" onclick="showAchievementsView()" class="w-full h-16 md:w-[400px] md:h-[70px] lg:w-[540px] lg:h-[100px] text-2xl md:text-3xl lg:text-4xl font-bold uppercase text-white shadow-lg rounded-lg border-[6px]" style="background: linear-gradient(to bottom, #2FDD63, #0D824F); border-color: #198439; font-family: 'KongText', monospace; text-shadow: 2px 2px 0 #0D824F, 0 2px 4px #0D824F;">ACHIEVEMENTS</button>
        <button id="xp-progress-button" onclick="showXPProgressView()" class="w-full h-16 md:w-[400px] md:h-[70px] lg:w-[540px] lg:h-[100px] text-2xl md:text-3xl lg:text-4xl font-bold uppercase text-white shadow-lg rounded-lg border-[6px]" style="background: linear-gradient(180deg, #2FCEDD 0%, #1A87B3 50%, #04C9EE 100%); border-color: #1F65A6; font-family: 'KongText', monospace; text-shadow: 2px 2px 0 #1A1A8A, 0 2px 4px #1A1A8A;">XP PROGRESS</button>
    </div>
    
    <!-- Footer with Rectangle.png -->
    <div class="footer-container">
        <img src="../assets/images/Rectangle.png" alt="Footer Background">
        <div id="refresh-footer" class="absolute inset-y-0 left-[250px] flex flex-col items-center justify-center" style="display: none;">
            <div class="text-white font-bold mb-2 text-xl">NEXT REFRESH</div>
            <div class="bg-[#FFAA4B] text-[#4D2422] font-bold px-6 py-2 border-2 border-[#4D2422] w-[300px] h-[50px] text-center rounded-md flex items-center justify-center">
                <span id="refresh-timer" class="text-2xl"><?php echo $refreshTime; ?></span>
            </div>
        </div>
        <div id="avatar-edit-btn" class="absolute top-[80px] left-[750px] right-0 flex justify-center" style="display: none;">
            <button class="bg-[#FFAA4B] text-[#4D2422] font-bold px-4 py-1 border-2 border-[#4D2422] w-[300px] h-[50px] text-sm rounded flex items-center justify-center" onclick="window.location.href='inventory.php'">
                EDIT AVATAR
            </button>
        </div>
        <!-- Claim Button in footer -->
        <div id="claim-btn" class="absolute top-[80px] left-[750px] right-0 flex justify-center" style="display: none;">
            <button class="bg-[#FFAA4B] text-[#4D2422] font-bold px-8 py-3 border-2 border-[#4D2422] rounded text-xl uppercase">
                CLAIM
            </button>
        </div>
        
        <!-- XP Progress Items in footer -->
        <div id="xp-footer-items" class="absolute inset-x-0 bottom-10 flex items-center" style="display: none; background-color: #4D2422; padding: 20px 0; height: 260px;">
            <div class="flex items-center ml-20">
                <!-- Level 5 item -->
                <div id="level5-container" class="flex flex-col items-center mr-12">
                    <div class="relative w-[230px] h-[230px] border-[8px] border-orange-500 rounded-lg bg-[#753419] flex items-center justify-center xp-reward-item">
                        <!-- Level label -->
                        <div class="absolute -top-5 left-1/2 transform -translate-x-1/2 bg-[#2FCEDD] text-white px-4 py-1 rounded-md font-bold z-10 border-2 border-[#1A87B3] level-label">
                            LEVEL 5
                        </div>
                        <img src="../assets/images/43.png" alt="Blue Scroll" class="w-full h-full object-cover">
                    </div>
                    <div class="mt-3 w-[140px] h-[40px] bg-orange-500 text-white rounded-md flex items-center justify-center font-bold text-xl cursor-pointer xp-claim-btn" 
                        data-item-name="Blue Scroll" 
                        data-item-image="../assets/images/43.png" 
                        data-required-level="5" 
                        style="font-family: 'KongText', monospace;">
                        CLAIM
                    </div>
                </div>
                
                <!-- Row of other items -->
                <div class="flex space-x-6">
                    <!-- Purple Scroll - Level 10 -->
                    <div id="level10-container" class="flex flex-col items-center">
                        <div class="relative w-[180px] h-[180px] border-[6px] border-orange-500 rounded-lg bg-[#753419] flex items-center justify-center xp-reward-item">
                            <!-- Level label -->
                            <div class="absolute -top-5 left-1/2 transform -translate-x-1/2 bg-[#2FCEDD] text-white px-4 py-1 rounded-md font-bold z-10 border-2 border-[#1A87B3] level-label">
                                LEVEL 10
                            </div>
                        <img src="../assets/images/44.png" alt="Purple Scroll" class="w-full h-full object-cover">
                        </div>
                        <div class="mt-3 w-[120px] h-[35px] bg-orange-500 text-white rounded-md flex items-center justify-center font-bold text-lg cursor-pointer xp-claim-btn" 
                            data-item-name="Purple Scroll" 
                            data-item-image="../assets/images/44.png" 
                            data-required-level="10" 
                            style="font-family: 'KongText', monospace;">
                            CLAIM
                        </div>
                    </div>
                    
                    <!-- Blue Scroll - Level 15 -->
                    <div id="level15-container" class="flex flex-col items-center">
                        <div class="relative w-[180px] h-[180px] border-[6px] border-orange-500 rounded-lg bg-[#753419] flex items-center justify-center xp-reward-item">
                            <!-- Level label -->
                            <div class="absolute -top-5 left-1/2 transform -translate-x-1/2 bg-[#2FCEDD] text-white px-4 py-1 rounded-md font-bold z-10 border-2 border-[#1A87B3] level-label">
                                LEVEL 15
                            </div>
                        <img src="../assets/images/45.png" alt="Blue Scroll" class="w-full h-full object-cover">
                        </div>
                        <div class="mt-3 w-[120px] h-[35px] bg-orange-500 text-white rounded-md flex items-center justify-center font-bold text-lg cursor-pointer xp-claim-btn" 
                            data-item-name="Blue Scroll 2" 
                            data-item-image="../assets/images/45.png" 
                            data-required-level="15" 
                            style="font-family: 'KongText', monospace;">
                            CLAIM
                        </div>
                    </div>
                    
                    <!-- Locked badges -->
                    <div class="w-[180px] h-[180px] border-[6px] border-[#8a6b5c] rounded-lg bg-[#4d2c24] flex items-center justify-center">
                        <img src="../assets/images/ic_round-lock.png" alt="Lock" class="w-[30px] h-[30px]">
                    </div>
                    
                    <div class="w-[180px] h-[180px] border-[6px] border-[#8a6b5c] rounded-lg bg-[#4d2c24] flex items-center justify-center">
                        <img src="../assets/images/ic_round-lock.png" alt="Lock" class="w-[30px] h-[30px]">
                    </div>
                    
                    <div class="w-[180px] h-[180px] border-[6px] border-[#8a6b5c] rounded-lg bg-[#4d2c24] flex items-center justify-center">
                        <img src="../assets/images/ic_round-lock.png" alt="Lock" class="w-[30px] h-[30px]">
                    </div>
                </div>
            </div>
        </div>
    </div>  
</div>

<!-- Purchase Confirmation Modal -->
<div id="purchase-modal" class="fixed inset-0 flex items-center justify-center z-50" style="display: none; background-color: rgba(0, 0, 0, 0.7);">
    <div class="bg-[#75341A] border-8 border-[#FF9926] rounded-xl p-6 w-[400px] shadow-lg">
        <div class="text-white text-2xl font-bold uppercase text-center mb-6">Confirm Purchase</div>
        
        <div class="flex items-center justify-center mb-4">
            <img id="purchase-item-image" src="" alt="Item" class="w-24 h-24 border-4 border-[#FF9926] rounded-lg bg-[#5C2E1B]">
        </div>
        
        <div class="text-center mb-6">
            <div id="purchase-item-name" class="text-white text-xl font-bold uppercase mb-4">Item Name</div>
            <div class="text-orange-300 text-xl mb-2">Purchase this item for:</div>
            <div class="flex items-center justify-center">
                <div class="coin-icon mr-2">C</div>
                <span id="purchase-item-cost" class="text-white text-2xl">20</span>
            </div>
        </div>
        
        <div class="flex justify-center gap-4 mt-4">
            <button id="confirm-purchase" class="bg-[#FFAA4B] text-[#4D2422] font-bold px-4 py-2 border-2 border-[#4D2422] rounded uppercase hover:bg-[#FF824E] transition-colors">Confirm</button>
            <button id="cancel-purchase" class="bg-[#5C2E1B] text-white font-bold px-4 py-2 border-2 border-[#A67B5B] rounded uppercase hover:bg-[#75341A] transition-colors">Cancel</button>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div id="error-modal" class="fixed inset-0 flex items-center justify-center z-50" style="display: none; background-color: rgba(0, 0, 0, 0.7);">
    <div class="bg-[#75341A] border-8 border-[#FF4B4B] rounded-xl p-6 w-[400px] shadow-lg">
        <div class="text-white text-2xl font-bold uppercase text-center mb-6">Error</div>
        
        <div class="text-center mb-6">
            <div class="flex justify-center mb-4">
                <div class="w-16 h-16 rounded-full bg-[#FF4B4B] flex items-center justify-center">
                    <span class="text-white text-4xl font-bold">✕</span>
                </div>
            </div>
            <div id="error-message" class="text-white text-xl mb-4">An error occurred while processing your purchase.</div>
        </div>
        
        <div class="flex justify-center gap-4 mt-4" id="error-buttons">
            <button id="close-error" class="bg-[#FF4B4B] text-white font-bold px-4 py-2 border-2 border-[#4D2422] rounded uppercase hover:bg-[#FF6B6B] transition-colors">OK</button>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="success-modal" class="fixed inset-0 flex items-center justify-center z-50" style="display: none; background-color: rgba(0, 0, 0, 0.7);">
    <div class="bg-[#75341A] border-8 border-[#2FDD63] rounded-xl p-6 w-[400px] shadow-lg">
        <div class="text-white text-2xl font-bold uppercase text-center mb-6">Success</div>
        
        <div class="text-center mb-6">
            <div class="flex justify-center mb-4">
                <img src="../assets/images/success-icon.svg" alt="Success" class="w-16 h-16">
            </div>
            <div id="success-message" class="text-white text-xl mb-4">Item purchased successfully!</div>
        </div>
        
        <div class="flex justify-center gap-4 mt-4">
            <button id="close-success" class="bg-[#2FDD63] text-[#0D824F] font-bold px-4 py-2 border-2 border-[#0D824F] rounded uppercase hover:bg-[#4DFD83] transition-colors">OK</button>
            <button id="next-reward" class="bg-[#FFAA4B] text-[#4D2422] font-bold px-4 py-2 border-2 border-[#4D2422] rounded uppercase hover:bg-[#FF824E] transition-colors" style="display: none;">Next Reward</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Check and update UI based on claimed items
        setTimeout(() => {
            // Only call updateUIBasedOnClaimedItems if we're on the XP progress view
            if (window.location.search.includes('?xp-progress')) {
                updateUIBasedOnClaimedItems();
            }
        }, 500);
        
        // Countdown timer for shop refresh - runs continuously
        let refreshTimerElement = document.getElementById('refresh-timer');
        let timerInterval;
        let totalSeconds = 0; // Will be calculated from the server time
        let serverRefreshTime = 0; // Server timestamp for next refresh
        let lastSyncTime = 0; // Last time we synced with server
        
        // Initialize the timer from server
        function initializeTimer() {
            // Always load fresh time from server on initialization
            syncWithServer();
        }
        
        // Function to sync timer with server
        function syncWithServer() {
            fetch('../sync_timer.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update our timer based on server data
                        serverRefreshTime = parseInt(data.next_refresh_time);
                        lastSyncTime = Math.floor(Date.now() / 1000);
                        totalSeconds = serverRefreshTime - lastSyncTime;
                        
                        // Update display
                        updateTimerDisplay();
                        
                        // Start countdown if not already started
                        startContinuousCountdown();
                    }
                })
                .catch(error => console.error('Error syncing with server:', error));
        }
        
        // Function to update timer display if visible
        function updateTimerDisplay() {
            if (refreshTimerElement) {
                // Calculate new hours, minutes, seconds
                let h = Math.floor(totalSeconds / 3600);
                let m = Math.floor((totalSeconds % 3600) / 60);
                let s = totalSeconds % 60;
                
                // Format with leading zeros
                let formattedTime = 
                    String(h).padStart(2, '0') + ':' + 
                    String(m).padStart(2, '0') + ':' + 
                    String(s).padStart(2, '0');
                
                // Update the display
                refreshTimerElement.textContent = formattedTime;
            }
        }
        
        // Start a continuous countdown that always runs
        function startContinuousCountdown() {
            // Clear any existing interval
            if (timerInterval) {
                clearInterval(timerInterval);
            }
            
            // Start the countdown
            timerInterval = setInterval(function() {
                // Calculate based on server time
                const now = Math.floor(Date.now() / 1000);
                totalSeconds = serverRefreshTime - now;
                
                if (totalSeconds <= 0) {
                    // Reset to 12 hours and update server refresh time
                    totalSeconds = 12 * 3600; 
                    serverRefreshTime = now + totalSeconds;
                    
                    // Notify server that timer has reset
                    fetch('../reset_timer.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            next_refresh_time: serverRefreshTime
                        })
                    }).catch(error => console.error('Error resetting timer on server:', error));
                }
                
                // Update display if the timer element is visible
                if (refreshTimerElement && window.getComputedStyle(refreshTimerElement.parentElement.parentElement).display !== 'none') {
                    updateTimerDisplay();
                }
            }, 1000);
            
            // Set up periodic sync with server (every 5 minutes)
            setTimeout(function() {
                syncWithServer();
                // Repeat every 5 minutes
                setInterval(syncWithServer, 5 * 60 * 1000);
            }, 5 * 60 * 1000); // First sync after 5 minutes
        }
        
        // Purchase functionality
        const purchaseModal = document.getElementById('purchase-modal');
        const purchaseItemImage = document.getElementById('purchase-item-image');
        const purchaseItemName = document.getElementById('purchase-item-name');
        const purchaseItemCost = document.getElementById('purchase-item-cost');
        const confirmPurchaseBtn = document.getElementById('confirm-purchase');
        const cancelPurchaseBtn = document.getElementById('cancel-purchase');
        const errorModal = document.getElementById('error-modal');
        const errorMessage = document.getElementById('error-message');
        const closeErrorBtn = document.getElementById('close-error');
        const successModal = document.getElementById('success-modal');
        const successMessage = document.getElementById('success-message');
        const closeSuccessBtn = document.getElementById('close-success');
        let currentPurchaseItem = null;
        
        // Close error modal
        closeErrorBtn.addEventListener('click', function() {
            errorModal.style.display = 'none';
        });
        
        // Close success modal
        closeSuccessBtn.addEventListener('click', function() {
            successModal.style.display = 'none';
        });
        
        // Show error message
        function showError(message) {
            errorMessage.textContent = message;
            
            // Remove retry button if it exists
            const retryButton = document.getElementById('retry-error');
            if (retryButton) {
                retryButton.remove();
            }
            
            errorModal.style.display = 'flex';
        }
        
        // Show success message
        function showSuccess(message) {
            successMessage.textContent = message;
            
            // Check if there's a next available reward
            const nextRewardBtn = document.getElementById('next-reward');
            const userLevel = <?php echo $level; ?>;
            const level5Button = document.querySelector('.xp-claim-btn[data-required-level="5"]');
            const level10Button = document.querySelector('.xp-claim-btn[data-required-level="10"]');
            const level15Button = document.querySelector('.xp-claim-btn[data-required-level="15"]');
            
            // Hide the next reward button by default
            nextRewardBtn.style.display = 'none';
            
            // Determine the next available reward based on user level and claimed status
            let nextAvailableLevel = null;
            
            if (level5Button && level5Button.textContent === 'CLAIM' && userLevel >= 5) {
                nextAvailableLevel = 5;
            } else if (level10Button && level10Button.textContent === 'CLAIM' && userLevel >= 10) {
                nextAvailableLevel = 10;
            } else if (level15Button && level15Button.textContent === 'CLAIM' && userLevel >= 15) {
                nextAvailableLevel = 15;
            }
            
            // If there's a next available reward, show the button
            if (nextAvailableLevel !== null) {
                nextRewardBtn.style.display = 'block';
                
                // Set up click handler for next reward button
                nextRewardBtn.onclick = function() {
                    // Close the success modal
                    successModal.style.display = 'none';
                    
                    // Make sure XP progress view is shown
                    if (!xpProgressMode) {
                        xpProgressButton.click();
                    }
                    
                    // Highlight the next available reward
                    const nextLevelContainer = document.getElementById(`level${nextAvailableLevel}-container`);
                    if (nextLevelContainer) {
                        // If the next available reward is not already at the front, move it there
                        const level5Container = document.getElementById('level5-container');
                        if (nextLevelContainer !== level5Container) {
                            swapLevelItems(5, nextAvailableLevel);
                        }
                    }
                };
            }
            
            successModal.style.display = 'flex';
        }
        
        // Update coin display
        function updateCoinDisplay(newBalance) {
            const coinDisplay = document.querySelector('.coin-icon + span');
            if (coinDisplay) {
                coinDisplay.textContent = newBalance + ' coins';
            }
        }
        
        // Add event listeners to all buy buttons
        document.querySelectorAll('.buy-btn').forEach(button => {
            button.addEventListener('click', function() {
                const itemName = this.getAttribute('data-item');
                const itemImage = this.getAttribute('data-image');
                const itemCost = this.getAttribute('data-cost');
                
                // Set the current purchase item
                currentPurchaseItem = {
                    name: itemName,
                    image: itemImage,
                    cost: itemCost
                };
                
                // Update modal content
                purchaseItemImage.src = itemImage;
                purchaseItemName.textContent = itemName;
                purchaseItemCost.textContent = itemCost;
                
                // Show the modal
                purchaseModal.style.display = 'flex';
            });
        });
        
        // Confirm purchase button
        confirmPurchaseBtn.addEventListener('click', function() {
            if (currentPurchaseItem) {
                // Make an AJAX call to process the purchase and add to inventory
                fetch('../add_to_inventory.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        item_name: currentPurchaseItem.name,
                        item_image: currentPurchaseItem.image,
                        item_cost: currentPurchaseItem.cost
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Close the modal first
                        purchaseModal.style.display = 'none';
                        
                        // Show success message
                        showSuccess(`Successfully purchased ${currentPurchaseItem.name} for ${currentPurchaseItem.cost} coins!`);
                        
                        // Update the displayed coin amount if needed
                        if (data.new_balance !== undefined) {
                            updateCoinDisplay(data.new_balance);
                        }
                    } else {
                        // Close the purchase modal
                        purchaseModal.style.display = 'none';
                        
                        // Show error message
                        showError(data.message || 'Error processing purchase. Please try again.');
                    }
                    
                    // Reset current purchase item
                    currentPurchaseItem = null;
                })
                .catch(error => {
                    console.error('Error:', error);
                    
                    // Close the purchase modal
                    purchaseModal.style.display = 'none';
                    
                    // Show error message
                    showError('An error occurred while processing your purchase.');
                    
                    // Reset current purchase item
                    currentPurchaseItem = null;
                });
            }
        });
        
        // Cancel purchase button
        cancelPurchaseBtn.addEventListener('click', function() {
            // Close the modal
            purchaseModal.style.display = 'none';
            
            // Reset current purchase item
            currentPurchaseItem = null;
        });
        
        // Close modal when clicking outside
        purchaseModal.addEventListener('click', function(e) {
            if (e.target === purchaseModal) {
                // Close the modal
                purchaseModal.style.display = 'none';
                
                // Reset current purchase item
                currentPurchaseItem = null;
            }
        });
        
        // typewriter for guild
        const text = "Welcome to the Guild Hall! Explore the shop for upgrades, celebrate your victories in achievements, and track your XP progress as you rise through the levels!";
        const typewriter = document.getElementById('typewriter-text');
        let i = 0;
        function type() {
            if (i < text.length) {
                typewriter.textContent += text.charAt(i);
                i++;
                setTimeout(type, 30); 
            }
        }
        type();

        // Typewriter for shop
        const shopText = "Stock up on upgrades and essentials—gear up for your next quest!";
        const typewriterShop = document.getElementById('typewriter-shop');
        let j = 0;
        function typeShop() {
            if (j < shopText.length) {
                typewriterShop.textContent += shopText.charAt(j);
                j++;
                setTimeout(typeShop, 30);
            }
        }
        
        // Typewriter for achievements
        const achievementsText = "Celebrate your victories and unlock your achievements!";
        const typewriterAchievements = document.getElementById('typewriter-achievements');
        let k = 0;
        function typeAchievements() {
            if (k < achievementsText.length) {
                typewriterAchievements.textContent += achievementsText.charAt(k);
                k++;
                setTimeout(typeAchievements, 30);
            }
        }
        
        // Typewriter for XP Progress
        const xpText = "Your journey continues! Track your XP progress and see how close you are to leveling up—every quest brings you closer to greatness!";
        const typewriterXP = document.getElementById('typewriter-xp');
        let l = 0;
        function typeXP() {
            if (l < xpText.length) {
                typewriterXP.textContent += xpText.charAt(l);
                l++;
                setTimeout(typeXP, 30);
            }
        }

        // Shop functionality
        const shopButton = document.getElementById('shop-button');
        const achievementsButton = document.getElementById('achievements-button');
        const xpProgressButton = document.getElementById('xp-progress-button');
        const shopContainer = document.getElementById('shop-container');
        const achievementsContainer = document.getElementById('achievements-container');
        const xpProgressContainer = document.getElementById('xp-progress-container');
        const guildButtons = document.getElementById('guild-buttons');
        const shopkeeperMain = document.getElementById('shopkeeper-main');
        const shopkeeperShop = document.getElementById('shopkeeper-shop');
        const guildTitle = document.getElementById('guild-title');
        const shopTitle = document.getElementById('shop-title');
        const achievementsTitle = document.getElementById('achievements-title');
        const xpProgressTitle = document.getElementById('xp-progress-title');
        const backButton = document.getElementById('back-button');
        const refreshFooter = document.getElementById('refresh-footer');
        const avatarEditBtn = document.getElementById('avatar-edit-btn');
        const claimBtn = document.getElementById('claim-btn');

        // Log if any elements are missing
        console.log('Button elements found:', {
            shopButton: !!shopButton,
            achievementsButton: !!achievementsButton,
            xpProgressButton: !!xpProgressButton,
            shopContainer: !!shopContainer,
            achievementsContainer: !!achievementsContainer,
            xpProgressContainer: !!xpProgressContainer
        });

        // Original back URL (index.php)
        const originalBackUrl = backButton.getAttribute('href');

        // Mode flags
        let shopMode = false;
        let achievementsMode = false;
        let xpProgressMode = false;

        // Shop button click handler
        shopButton.addEventListener('click', function() {
            console.log('Shop button clicked');
            // Hide all containers first
            shopContainer.style.display = 'none';
            achievementsContainer.style.display = 'none';
            xpProgressContainer.style.display = 'none';
            
            // Show shop specific elements
            shopContainer.style.display = 'block';
            guildButtons.style.display = 'none';
            shopkeeperMain.style.display = 'none';
            if (shopkeeperShop) shopkeeperShop.style.display = 'flex';
            guildTitle.style.display = 'none';
            shopTitle.style.display = 'flex';
            achievementsTitle.style.display = 'none';
            xpProgressTitle.style.display = 'none';
            refreshFooter.style.display = 'flex'; // Show refresh footer in shop mode
            avatarEditBtn.style.display = 'flex'; // Show edit avatar button in shop mode
            claimBtn.style.display = 'none'; // Hide claim button in shop mode
            
            // Initialize the refresh countdown timer
            initializeTimer();
            
            // Typewriter effect for shop
            if (typewriterShop) {
                typewriterShop.textContent = '';
                j = 0;
                typeShop();
            }
            
            // Change back button functionality
            backButton.removeAttribute('href');
            backButton.style.cursor = 'pointer';
            
            // Update mode flags
            shopMode = true;
            achievementsMode = false;
            xpProgressMode = false;
            
            // Update URL
            window.history.pushState({}, '', window.location.pathname + '?shop');
        });

        // Achievements button click handler
        achievementsButton.addEventListener('click', function() {
            console.log('Achievements button clicked');
            // Hide all containers first
            shopContainer.style.display = 'none';
            achievementsContainer.style.display = 'none';
            xpProgressContainer.style.display = 'none';
            
            // Show achievements specific elements
            achievementsContainer.style.display = 'block';
            guildButtons.style.display = 'none';
            shopkeeperMain.style.display = 'none';
            guildTitle.style.display = 'none';
            shopTitle.style.display = 'none';
            achievementsTitle.style.display = 'flex';
            xpProgressTitle.style.display = 'none';
            refreshFooter.style.display = 'none'; // Don't show refresh footer in achievements
            avatarEditBtn.style.display = 'none'; // Don't show edit avatar in achievements
            claimBtn.style.display = 'flex'; // Show claim button in achievements mode
            
            // Typewriter effect for achievements
            if (typewriterAchievements) {
                typewriterAchievements.textContent = '';
                k = 0;
                typeAchievements();
            }
            
            // Change back button functionality
            backButton.removeAttribute('href');
            backButton.style.cursor = 'pointer';
            
            // Update mode flags
            shopMode = false;
            achievementsMode = true;
            xpProgressMode = false;
            
            // Update URL
            window.history.pushState({}, '', window.location.pathname + '?achievements');
        });
        
        // XP Progress button click handler
        xpProgressButton.addEventListener('click', function() {
            console.log('XP Progress button clicked');
            // Hide all containers first
            shopContainer.style.display = 'none';
            achievementsContainer.style.display = 'none';
            xpProgressContainer.style.display = 'none';
            
            // Show XP Progress specific elements
            xpProgressContainer.style.display = 'block';
            guildButtons.style.display = 'none';
            shopkeeperMain.style.display = 'none';
            guildTitle.style.display = 'none';
            shopTitle.style.display = 'none';
            achievementsTitle.style.display = 'none';
            xpProgressTitle.style.display = 'flex';
            refreshFooter.style.display = 'none'; // Don't show refresh footer in XP Progress
            avatarEditBtn.style.display = 'none'; // Don't show edit avatar in XP Progress
            claimBtn.style.display = 'none'; // Don't show claim button in XP Progress mode
            
            // Show XP Progress items in footer
            document.getElementById('xp-footer-items').style.display = 'flex';
            
            // Update UI based on claimed items with a delay to ensure DOM is ready
            setTimeout(() => {
                console.log('Calling updateUIBasedOnClaimedItems after delay');
                updateUIBasedOnClaimedItems();
            }, 300);
            
            // Typewriter effect for XP Progress
            if (typewriterXP) {
                typewriterXP.textContent = '';
                l = 0;
                typeXP();
            }
            
            // Change back button functionality
            backButton.removeAttribute('href');
            backButton.style.cursor = 'pointer';
            
            // Update mode flags
            shopMode = false;
            achievementsMode = false;
            xpProgressMode = true;
            
            // Update URL
            window.history.pushState({}, '', window.location.pathname + '?xp-progress');
        });

        // Back button click handler
        backButton.addEventListener('click', function(e) {
            if (shopMode || achievementsMode || xpProgressMode) {
                e.preventDefault();
                
                // Hide all specialty containers
                shopContainer.style.display = 'none';
                achievementsContainer.style.display = 'none';
                xpProgressContainer.style.display = 'none';
                
                // Hide XP Progress items in footer
                document.getElementById('xp-footer-items').style.display = 'none';
                
                // Restore guild view
                guildButtons.style.display = 'flex';
                shopkeeperMain.style.display = 'flex';
                if (shopkeeperShop) shopkeeperShop.style.display = 'none';
                guildTitle.style.display = 'flex';
                shopTitle.style.display = 'none';
                achievementsTitle.style.display = 'none';
                xpProgressTitle.style.display = 'none';
                refreshFooter.style.display = 'none'; // Hide refresh footer
                avatarEditBtn.style.display = 'none'; // Hide edit avatar button
                claimBtn.style.display = 'none'; // Hide claim button
                
                // Restore original back button functionality
                backButton.setAttribute('href', originalBackUrl);
                
                // Reset mode flags
                shopMode = false;
                achievementsMode = false;
                xpProgressMode = false;
                
                // Restore URL
                window.history.pushState({}, '', window.location.pathname);
            }
        });

        // Check URL parameters on page load
        if (window.location.search.includes('?shop')) {
            // Automatically show shop view
            console.log('URL has ?shop parameter, showing shop view');
            setTimeout(() => shopButton.click(), 100);
        } else if (window.location.search.includes('?achievements')) {
            // Automatically show achievements view
            console.log('URL has ?achievements parameter, showing achievements view');
            setTimeout(() => achievementsButton.click(), 100);
        } else if (window.location.search.includes('?xp-progress')) {
            // Automatically show XP progress view
            console.log('URL has ?xp-progress parameter, showing XP progress view');
            setTimeout(() => xpProgressButton.click(), 100);
        }
        
        // XP Reward Claim functionality
        document.querySelectorAll('.xp-claim-btn').forEach(button => {
            const requiredLevel = parseInt(button.getAttribute('data-required-level'));
            const userLevel = <?php echo $level; ?>; // Get user's current level from PHP
            const itemImage = button.getAttribute('data-item-image');
            const claimedItems = <?php echo $claimedItemsJson; ?>;
            
            // Check if user has already claimed this item
            if (claimedItems.includes(itemImage)) {
                // Disable button and change appearance if already claimed
                button.classList.remove('bg-orange-500');
                button.classList.add('bg-gray-500');
                button.textContent = 'CLAIMED';
                button.style.cursor = 'default';
                button.disabled = true;
            }
            // Check if user meets the level requirement
            else if (userLevel < requiredLevel) {
                // Disable button and change appearance if user doesn't meet the level requirement
                button.classList.remove('bg-orange-500');
                button.classList.add('bg-gray-400');
                button.textContent = 'LOCKED';
                button.style.cursor = 'not-allowed';
                button.disabled = true;
            }
            
            button.addEventListener('click', function() {
                if (this.disabled) return; // Don't do anything if button is disabled
                
                const itemName = this.getAttribute('data-item-name');
                const itemImage = this.getAttribute('data-item-image');
                const requiredLevel = this.getAttribute('data-required-level');
                
                // Show loading state
                const originalText = this.textContent;
                this.textContent = 'CLAIMING...';
                this.disabled = true;
                
                // Make an AJAX call to process the claim and add to inventory
                fetch('../claim_xp_reward.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        item_name: itemName,
                        item_image: itemImage,
                        required_level: requiredLevel
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        // If server returns an error status, throw an error with the status
                        throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Show success message
                        showSuccess(data.message);
                        
                        // Disable the claim button and change its appearance to show it's been claimed
                        this.classList.remove('bg-orange-500');
                        this.classList.add('bg-gray-500');
                        this.textContent = 'CLAIMED';
                        this.style.cursor = 'default';
                        this.disabled = true;
                        
                        // Update claimedItems array to include the newly claimed item
                        claimedItems.push(itemImage);
                        
                        // Get user level
                        const userLevel = <?php echo $level; ?>;
                        
                        // Determine which reward to show prominently next based on user level and claimed status
                        if (requiredLevel == 5) {
                            // If user claimed level 5, check if they can claim level 10 or 15
                            if (userLevel >= 10) {
                                const level10Button = document.querySelector('.xp-claim-btn[data-required-level="10"]');
                                const level10Claimed = level10Button && level10Button.textContent === 'CLAIMED';
                                
                                if (!level10Claimed) {
                                    // Move level 10 to the front if it's available
                                    setTimeout(() => {
                                        swapLevelItems(5, 10);
                                    }, 1500);
                                } else if (userLevel >= 15) {
                                    // If level 10 is claimed, check level 15
                                    const level15Button = document.querySelector('.xp-claim-btn[data-required-level="15"]');
                                    const level15Claimed = level15Button && level15Button.textContent === 'CLAIMED';
                                    
                                    if (!level15Claimed) {
                                        setTimeout(() => {
                                            swapLevelItems(5, 15);
                                        }, 1500);
                                    }
                                }
                            }
                        } else if (requiredLevel == 10) {
                            // If user claimed level 10, check if they can claim level 15
                            if (userLevel >= 15) {
                                const level15Button = document.querySelector('.xp-claim-btn[data-required-level="15"]');
                                const level15Claimed = level15Button && level15Button.textContent === 'CLAIMED';
                                
                                if (!level15Claimed) {
                                    setTimeout(() => {
                                        swapLevelItems(10, 15);
                                    }, 1500);
                                } else {
                                    // If level 15 is also claimed, check if level 5 is unclaimed
                                    const level5Button = document.querySelector('.xp-claim-btn[data-required-level="5"]');
                                    const level5Claimed = level5Button && level5Button.textContent === 'CLAIMED';
                                    
                                    if (!level5Claimed) {
                                        setTimeout(() => {
                                            swapLevelItems(10, 5);
                                        }, 1500);
                                    }
                                }
                            }
                        }
                        
                        // Check all other buttons to see if they should be updated
                        // Use the user's level from the server response if available
                        const currentLevel = data.user_level || userLevel;
                        updateNextClaimButtons(currentLevel);
                    } else {
                        // Reset button state
                        this.textContent = originalText;
                        this.disabled = false;
                        
                        // Show error message
                        showError(data.message || 'Error processing claim. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    
                    // Reset button state
                    this.textContent = originalText;
                    this.disabled = false;
                    
                    // Show detailed error message with retry option
                    showErrorWithRetry('An error occurred while processing your claim. This might be due to a network issue or server problem.', () => {
                        // Retry the claim when the user clicks retry
                        this.click();
                    });
                });
            });
        });

        // Function to swap the positions of two level items
        function swapLevelItems(claimedLevel, nextLevel) {
            const claimedContainer = document.getElementById(`level${claimedLevel}-container`);
            const nextContainer = document.getElementById(`level${nextLevel}-container`);
            
            // Make sure both containers exist
            if (!claimedContainer || !nextContainer) {
                console.log(`Could not find containers for levels ${claimedLevel} and ${nextLevel}`);
                return;
            }
            
            try {
                // Make sure the containers are visible (parent is displayed)
                const footerItems = document.getElementById('xp-footer-items');
                if (footerItems && window.getComputedStyle(footerItems).display === 'none') {
                    console.log('XP footer items are not visible yet, skipping swap');
                    return;
                }
                
                // Make sure the reward items exist
                const claimedItem = claimedContainer.querySelector('.xp-reward-item');
                const nextItem = nextContainer.querySelector('.xp-reward-item');
                
                if (!claimedItem || !nextItem) {
                    console.log('Could not find reward items in containers');
                    return;
                }
                
                // Swap their positions in the DOM
                const parent = claimedContainer.parentNode;
                const claimedNext = claimedContainer.nextSibling;
                
                if (claimedNext === nextContainer) {
                    parent.insertBefore(nextContainer, claimedContainer);
                } else {
                    const nextNext = nextContainer.nextSibling;
                    parent.insertBefore(nextContainer, claimedContainer);
                    parent.insertBefore(claimedContainer, nextNext);
                }
            } catch (error) {
                console.error('Error in swapLevelItems:', error);
            }
        }

        // Function to check and update UI on page load based on claimed items
        function updateUIBasedOnClaimedItems() {
            try {
                // Check if XP progress view is visible
                const xpProgressContainer = document.getElementById('xp-progress-container');
                const xpFooterItems = document.getElementById('xp-footer-items');
                
                if (!xpProgressContainer || !xpFooterItems || 
                    window.getComputedStyle(xpProgressContainer).display === 'none' || 
                    window.getComputedStyle(xpFooterItems).display === 'none') {
                    console.log('XP progress view is not active, skipping UI update');
                    return;
                }
                
                const claimedItems = <?php echo $claimedItemsJson; ?>;
                const userLevel = <?php echo $level; ?>;
                const level5Image = "../assets/images/43.png";
                const level10Image = "../assets/images/44.png";
                const level15Image = "../assets/images/45.png";
                
                // Get all buttons
                const level5Button = document.querySelector('.xp-claim-btn[data-required-level="5"]');
                const level10Button = document.querySelector('.xp-claim-btn[data-required-level="10"]');
                const level15Button = document.querySelector('.xp-claim-btn[data-required-level="15"]');
                
                if (!level5Button || !level10Button || !level15Button) {
                    console.log('Could not find all level buttons');
                    return;
                }
                
                // Check which items are claimed
                const level5Claimed = claimedItems.includes(level5Image);
                const level10Claimed = claimedItems.includes(level10Image);
                const level15Claimed = claimedItems.includes(level15Image);
                
                // Arrange rewards based on user level and claimed status
                if (level5Claimed) {
                    // If level 5 is claimed, check if user can claim level 10
                    if (userLevel >= 10 && !level10Claimed) {
                        // Move level 10 to the front
                        setTimeout(() => {
                            swapLevelItems(5, 10);
                        }, 500);
                    } else if (userLevel >= 15 && !level15Claimed) {
                        // If level 10 is claimed or unavailable, but level 15 is available, move level 15 to the front
                        setTimeout(() => {
                            swapLevelItems(5, 15);
                        }, 500);
                    }
                } else if (level10Claimed && userLevel >= 15 && !level15Claimed) {
                    // If level 10 is claimed but level 5 is not, and level 15 is available
                    setTimeout(() => {
                        swapLevelItems(10, 15);
                    }, 500);
                }
                
                // Always ensure the next available reward is prominent
                if (!level5Claimed && userLevel >= 5) {
                    // Level 5 is already in front by default
                } else if (!level10Claimed && userLevel >= 10) {
                    // Move level 10 to the front if level 5 is claimed or user is below level 5
                    if (level5Claimed || userLevel < 5) {
                        setTimeout(() => {
                            swapLevelItems(5, 10);
                        }, 500);
                    }
                } else if (!level15Claimed && userLevel >= 15) {
                    // Move level 15 to the front if lower levels are claimed or unavailable
                    if ((level5Claimed || userLevel < 5) && (level10Claimed || userLevel < 10)) {
                        setTimeout(() => {
                            swapLevelItems(5, 15);
                        }, 500);
                    }
                }
            } catch (error) {
                console.error('Error in updateUIBasedOnClaimedItems:', error);
            }
        }

        // Show error with retry option
        function showErrorWithRetry(message, retryCallback) {
            errorMessage.textContent = message;
            
            // Create retry button if it doesn't exist
            let retryButton = document.getElementById('retry-error');
            if (!retryButton) {
                retryButton = document.createElement('button');
                retryButton.id = 'retry-error';
                retryButton.className = 'bg-[#FFAA4B] text-white font-bold px-4 py-2 border-2 border-[#4D2422] rounded uppercase hover:bg-[#FF824E] transition-colors';
                retryButton.textContent = 'RETRY';
                
                // Add retry button to the error buttons container
                const buttonsContainer = document.getElementById('error-buttons');
                buttonsContainer.insertBefore(retryButton, buttonsContainer.firstChild);
            }
            
            // Set up retry callback
            retryButton.onclick = function() {
                errorModal.style.display = 'none';
                if (typeof retryCallback === 'function') {
                    retryCallback();
                }
            };
            
            errorModal.style.display = 'flex';
        }

        // Function to update next claim buttons based on level
        function updateNextClaimButtons(userLevel) {
            // Get all claim buttons
            const claimButtons = document.querySelectorAll('.xp-claim-btn');
            
            // Sort them by required level
            const sortedButtons = Array.from(claimButtons).sort((a, b) => {
                return parseInt(a.getAttribute('data-required-level')) - parseInt(b.getAttribute('data-required-level'));
            });
            
            // Find the next unclaimed button that meets the level requirement
            let nextAvailableLevel = null;
            
            for (let button of sortedButtons) {
                const requiredLevel = parseInt(button.getAttribute('data-required-level'));
                
                // Skip buttons that are already claimed (have 'CLAIMED' text)
                if (button.textContent === 'CLAIMED') {
                    continue;
                }
                
                // If user meets level requirement, enable the button
                if (userLevel >= requiredLevel && button.textContent === 'LOCKED') {
                    button.classList.remove('bg-gray-400');
                    button.classList.add('bg-orange-500');
                    button.textContent = 'CLAIM';
                    button.style.cursor = 'pointer';
                    button.disabled = false;
                    
                    // Store the next available level for UI updates
                    if (nextAvailableLevel === null || requiredLevel < nextAvailableLevel) {
                        nextAvailableLevel = requiredLevel;
                    }
                }
            }
            
            // If we found a next available level, check if we need to swap UI elements
            if (nextAvailableLevel !== null) {
                // Get the claim status of each level
                const level5Button = document.querySelector('.xp-claim-btn[data-required-level="5"]');
                const level10Button = document.querySelector('.xp-claim-btn[data-required-level="10"]');
                const level15Button = document.querySelector('.xp-claim-btn[data-required-level="15"]');
                
                const level5Claimed = level5Button && level5Button.textContent === 'CLAIMED';
                const level10Claimed = level10Button && level10Button.textContent === 'CLAIMED';
                const level15Claimed = level15Button && level15Button.textContent === 'CLAIMED';
                
                // Determine which reward should be shown prominently
                if (!level5Claimed && userLevel >= 5) {
                    // Level 5 is already in front by default
                } else if (!level10Claimed && userLevel >= 10) {
                    // Move level 10 to the front if level 5 is claimed or unavailable
                    if (level5Claimed || userLevel < 5) {
                        swapLevelItems(5, 10);
                    }
                } else if (!level15Claimed && userLevel >= 15) {
                    // Move level 15 to the front if lower levels are claimed or unavailable
                    if ((level5Claimed || userLevel < 5) && (level10Claimed || userLevel < 10)) {
                        swapLevelItems(5, 15);
                    }
                }
            }
        }
    });
</script>
</body>
</html>