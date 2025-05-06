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
            margin-bottom: 60px; /* Add space for the footer */
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
    <!-- Back Button -->
    <a href="../index.php" class="absolute top-5 left-12 md:left-20 w-16 h-14 gradient-button border-[5px] border-brown-dark rounded-md flex items-center justify-center cursor-pointer">
        <img src="../assets/images/arrow-left.png" alt="Back" class="w-6 h-6">
    </a>
    
    <!-- Guild Title -->
    <div class="absolute top-5 left-32 md:left-[200px] w-64 md:w-72 h-14 gradient-orange border-[8px] border-brown-dark rounded-lg flex items-center justify-center">
        <h1 class="text-white text-sm uppercase">GUILD</h1>
    </div>
    
    <div class="container mx-auto px-4 pt-24 main-content relative flex flex-col items-center justify-end" style="min-height: 80vh;">
        <!-- Shopkeeper and Welcome Text Over Footer -->
        <div class="absolute left-1/4 -translate-x-3/4 bottom-[102px] flex flex-col items-center ">
            <img src="../assets/images/shopkeeper.png" alt="Shopkeeper" class="w-[450px] h-auto">
        </div>
     
    
    <!-- Footer with Rectangle.png -->
    <div class="footer-container">
        <img src="../assets/images/Rectangle.png" alt="Footer Background">
    </div>  
</body>
</html>
