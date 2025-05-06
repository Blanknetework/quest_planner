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

// Calculate XP needed for next level
$baseXP = 20;
$nextLevelXP = $baseXP;
for ($i = 1; $i < $level; $i++) {
    $nextLevelXP = ceil($nextLevelXP * 1.5);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guild - Quest Planner</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        }
        
        .menu-button {
            background: linear-gradient(90deg, #FFAA4B, #FF824E);
            border: 5px solid #8A4B22;
            border-radius: 10px;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            padding: 12px 30px;
            width: 80%;
            margin: 10px auto;
            display: block;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.2s;
        }
        
        .menu-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .menu-button-shop {
            background: #FCA016;
        }
        
        .menu-button-achievements {
            background: #1FCE76;
        }
        
        .menu-button-xp {
            background: #1FADCE;
        }
        
        .speech-bubble {
            background-color: #75341A;
            border: 5px solid #FF9926;
            border-radius: 15px;
            padding: 15px;
            position: relative;
            color: white;
            margin-bottom: 30px;
        }
        
        .speech-bubble:after {
            content: '';
            position: absolute;
            bottom: -25px;
            left: 50px;
            border-width: 20px 15px 0;
            border-style: solid;
            border-color: #FF9926 transparent;
        }
        
        .speech-bubble:before {
            content: '';
            position: absolute;
            bottom: -18px;
            left: 52px;
            border-width: 18px 13px 0;
            border-style: solid;
            border-color: #75341A transparent;
            z-index: 1;
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Back Button -->
        <a href="../index.php" class="absolute top-5 left-5 w-14 h-12 bg-gradient-to-r from-orange-light to-orange-dark border-[5px] border-brown-dark rounded-md flex items-center justify-center cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        
        <!-- Main Content -->
        <div class="flex flex-col items-center mt-10">
            <!-- Speech Bubble -->
            <div class="speech-bubble max-w-lg text-center mb-8">
                <p class="text-xl">Welcome to the Guild, <?php echo htmlspecialchars($username); ?>! I'm the shopkeeper. What can I help you with today?</p>
            </div>
            
            <!-- Shopkeeper Image -->
            <div class="mb-8">
                <img src="../assets/images/shopkeeper.png" alt="Shopkeeper" class="max-w-xs mx-auto">
            </div>
            
            <!-- Menu Buttons -->
            <div class="w-full max-w-md space-y-4 mt-4">
                <a href="#" class="menu-button menu-button-shop">
                    SHOP
                </a>
                
                <a href="#" class="menu-button menu-button-achievements">
                    ACHIEVEMENTS
                </a>
                
                <a href="#" class="menu-button menu-button-xp">
                    XP PROGRESS
                </a>
            </div>
            
            <!-- User Info -->
            <div class="mt-8 text-center text-white">
                <p class="text-xl mb-2"><?php echo htmlspecialchars($username); ?></p>
                <p class="text-lg">Level: <?php echo htmlspecialchars($level); ?></p>
                <div class="w-64 h-4 bg-gray-700 rounded-full mt-2 overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-[#EA6242] to-[#EE8F50]" style="width: <?php echo ($currentXP / $nextLevelXP) * 100; ?>%"></div>
                </div>
                <p class="text-sm mt-1">XP: <?php echo $currentXP; ?>/<?php echo $nextLevelXP; ?></p>
            </div>
        </div>
    </div>
</body>
</html>
