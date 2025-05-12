<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Include database connection
require_once '../config/database.php';

// Get user data from database
$stmt = $pdo->prepare("SELECT username, level, xp FROM users WHERE id = ?");
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

// Format time for refresh timer
$hours = 12;
$minutes = 0;
$seconds = 0;
$refreshTime = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
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
    <div id="achievements-title" class="absolute top-5 left-32 md:left-[200px] w-64 md:w-72 h-14 gradient-orange border-[8px] border-brown-dark rounded-lg flex items-center justify-center" style="display: none;">
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
                    <span class="text-white text-lg font-bold">20 coins</span>
                    </div>
                <div class="text-white text-3xl font-bold uppercase text-center mt-2 mb-6 tracking-wider" style="font-family: 'KongText', monospace;">Featured Items</div>
                   <div class="grid grid-cols-4 gap-6 px-8 w-full">
                    <div class="flex flex-col items-center bg-[#8A4B22] border-[9px] border-orange-400 rounded-lg p-4">
                        <div class="text-white text-base font-bold uppercase mb-2">ITEM</div>
                        <img src="../assets/images/10.png" alt="Pillow" class="w-16 h-16 mb-2">
                        <div class="flex items-center gap-2 mt-2">
                            <div class="coin-icon">C</div>
                            <span class="text-white text-base">20</span>
                            <button class="ml-2 bg-orange-500 text-white rounded px-3 py-1 text-sm font-bold uppercase">Buy</button>
                            </div>
                        </div>
                    <div class="flex flex-col items-center bg-[#8A4B22] border-[9px] border-orange-400 rounded-lg p-4">
                        <div class="text-white text-base font-bold uppercase mb-2">ITEM</div>
                        <img src="../assets/images/11.png" alt="Headphones" class="w-16 h-16 mb-2">
                        <div class="flex items-center gap-2 mt-2">
                            <div class="coin-icon">C</div>
                            <span class="text-white text-base">20</span>
                            <button class="ml-2 bg-orange-500 text-white rounded px-3 py-1 text-sm font-bold uppercase">Buy</button>
                            </div>
                        </div>
                    <div class="flex flex-col items-center bg-[#8A4B22] border-[9px] border-orange-400 rounded-lg p-4">
                        <div class="text-white text-base font-bold uppercase mb-2">ITEM</div>
                        <img src="../assets/images/21.png" alt="Hat" class="w-16 h-16 mb-2">
                        <div class="flex items-center gap-2 mt-2">
                            <div class="coin-icon">C</div>
                            <span class="text-white text-base">20</span>
                            <button class="ml-2 bg-orange-500 text-white rounded px-3 py-1 text-sm font-bold uppercase">Buy</button>
                            </div>
                        </div>
                    <div class="flex flex-col items-center bg-[#8A4B22] border-[9px] border-orange-400 rounded-lg p-4">
                        <div class="text-white text-base font-bold uppercase mb-2">ITEM</div>
                        <img src="../assets/images/16.png" alt="Glasses" class="w-16 h-16 mb-2">
                        <div class="flex items-center gap-2 mt-2">
                            <div class="coin-icon">C</div>
                            <span class="text-white text-base">20</span>
                            <button class="ml-2 bg-orange-500 text-white rounded px-3 py-1 text-sm font-bold uppercase">Buy</button>
                            </div>
                        </div>
                        <!-- Row 2 -->
                    <div class="flex flex-col items-center bg-[#8A4B22] border-[9px] border-orange-400 rounded-lg p-4">
                        <div class="text-white text-base font-bold uppercase mb-2">ITEM</div>
                        <img src="../assets/images/12.png" alt="Mustache" class="w-16 h-16 mb-2">
                        <div class="flex items-center gap-2 mt-2">
                            <div class="coin-icon">C</div>
                            <span class="text-white text-base">20</span>
                            <button class="ml-2 bg-orange-500 text-white rounded px-3 py-1 text-sm font-bold uppercase">Buy</button>
                            </div>
                        </div>
                    <div class="flex flex-col items-center bg-[#8A4B22] border-[9px] border-orange-400 rounded-lg p-4">
                        <div class="text-white text-base font-bold uppercase mb-2">ITEM</div>
                        <img src="../assets/images/6.png" alt="Sprout" class="w-16 h-16 mb-2">
                        <div class="flex items-center gap-2 mt-2">
                            <div class="coin-icon">C</div>
                            <span class="text-white text-base">20</span>
                            <button class="ml-2 bg-orange-500 text-white rounded px-3 py-1 text-sm font-bold uppercase">Buy</button>
                            </div>
                        </div>
                    <div class="flex flex-col items-center bg-[#8A4B22] border-[9px] border-orange-400 rounded-lg p-4">
                        <div class="text-white text-base font-bold uppercase mb-2">ITEM</div>
                        <img src="../assets/images/14.png" alt="Mask" class="w-16 h-16 mb-2">
                        <div class="flex items-center gap-2 mt-2">
                            <div class="coin-icon">C</div>
                            <span class="text-white text-base">20</span>
                            <button class="ml-2 bg-orange-500 text-white rounded px-3 py-1 text-sm font-bold uppercase">Buy</button>
                            </div>
                        </div>
                    <div class="flex flex-col items-center bg-[#8A4B22] border-[9px] border-orange-400 rounded-lg p-4">
                        <div class="text-white text-base font-bold uppercase mb-2">ITEM</div>
                        <img src="../assets/images/22.png" alt="Cap" class="w-16 h-16 mb-2">
                        <div class="flex items-center gap-2 mt-2">
                            <div class="coin-icon">C</div>
                            <span class="text-white text-base">20</span>
                            <button class="ml-2 bg-orange-500 text-white rounded px-3 py-1 text-sm font-bold uppercase">Buy</button>
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
        <button id="shop-button" class="w-full h-16 md:w-[400px] md:h-[70px] lg:w-[540px] lg:h-[100px] text-2xl md:text-3xl lg:text-4xl font-bold uppercase text-white shadow-lg rounded-lg border-[6px]" style="background: linear-gradient(180deg, #FB8020 0%, #FEBB1C 50%, #874325 100%); border-color: #FB8020; font-family: 'KongText', monospace; text-shadow: 2px 2px 0 #75341A, 0 2px 4px #75341A;">SHOP</button>
        <button id="achievements-button" class="w-full h-16 md:w-[400px] md:h-[70px] lg:w-[540px] lg:h-[100px] text-2xl md:text-3xl lg:text-4xl font-bold uppercase text-white shadow-lg rounded-lg border-[6px]" style="background: linear-gradient(180deg, #2FDD63 0%, #0D824F 50%, #04EE81 100%); border-color: #198439; font-family: 'KongText', monospace; text-shadow: 2px 2px 0 #0D824F, 0 2px 4px #0D824F;">ACHIEVEMENTS</button>
        <button id="xp-progress-button" class="w-full h-16 md:w-[400px] md:h-[70px] lg:w-[540px] lg:h-[100px] text-2xl md:text-3xl lg:text-4xl font-bold uppercase text-white shadow-lg rounded-lg border-[6px]" style="background: linear-gradient(180deg, #2FCEDD 0%, #1A87B3 50%, #04C9EE 100%); border-color: #1F65A6; font-family: 'KongText', monospace; text-shadow: 2px 2px 0 #1A1A8A, 0 2px 4px #1A1A8A;">XP PROGRESS</button>
    </div>
    
    <!-- Footer with Rectangle.png -->
    <div class="footer-container">
        <img src="../assets/images/Rectangle.png" alt="Footer Background">
        <div id="refresh-footer" class="absolute inset-y-0 left-[250px] flex flex-col items-center justify-center" style="display: none;">
            <div class="text-white font-bold mb-2 text-xl">NEXT REFRESH</div>
            <div class="bg-[#FFAA4B] text-[#4D2422] font-bold px-6 py-2 border-2 border-[#4D2422] w-[300px] h-[50px] text-center rounded-md flex items-center justify-center">
                <span class="text-2xl"><?php echo $refreshTime; ?></span>
            </div>
        </div>
        <div id="avatar-edit-btn" class="absolute top-[80px] left-[750px] right-0 flex justify-center" style="display: none;">
            <button class="bg-[#FFAA4B] text-[#4D2422] font-bold px-4 py-1 border-2 border-[#4D2422] w-[300px] h-[50px] text-sm rounded flex items-center justify-center">
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
                <div class="flex flex-col items-center mr-12">
                    <div class="w-[230px] h-[230px] border-[8px] border-orange-500 rounded-lg bg-[#753419] flex items-center justify-center">
                        <img src="../assets/images/43.png" alt="Blue Scroll" class="w-full h-full object-cover">
                    </div>
                    <div class="mt-3 w-[140px] h-[40px] bg-orange-500 text-white rounded-md flex items-center justify-center font-bold text-xl" style="font-family: 'KongText', monospace;">
                        CLAIM
                    </div>
                </div>
                
                <!-- Row of other items -->
                <div class="flex space-x-6">
                    <!-- Purple Scroll -->
                    <div class="w-[180px] h-[180px] border-[6px] border-orange-500 rounded-lg bg-[#753419] flex items-center justify-center">
                        <img src="../assets/images/44.png" alt="Purple Scroll" class="w-full h-full object-cover">
                    </div>
                    
                    <!-- Blue Scroll -->
                    <div class="w-[180px] h-[180px] border-[6px] border-orange-500 rounded-lg bg-[#753419] flex items-center justify-center">
                        <img src="../assets/images/45.png" alt="Blue Scroll" class="w-full h-full object-cover">
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
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

        // Original back URL (index.php)
        const originalBackUrl = backButton.getAttribute('href');

        // Mode flags
        let shopMode = false;
        let achievementsMode = false;
        let xpProgressMode = false;

        // Shop button click handler
        shopButton.addEventListener('click', function() {
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
    });
</script>
</body>
</html>