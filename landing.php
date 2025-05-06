<?php
session_start();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quest Planner - Begin Your Adventure</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        pixel: ['KongText', 'monospace']
                    },
                    colors: {
                        'quest-orange': '#FFA53B',
                        'quest-dark': '#222222',
                        'quest-btn-shadow': '#e07a1b'
                    },
                    animation: {
                        'float': 'float 3s ease-in-out infinite',
                        'float-up': 'float-up 15s linear infinite'
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-10px)' }
                        },
                        'float-up': {
                            '0%': { transform: 'translateY(0) translateX(0)', opacity: '0' },
                            '10%': { opacity: '1' },
                            '90%': { opacity: '1' },
                            '100%': { transform: 'translateY(-100vh) translateX(var(--tx))', opacity: '0' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @font-face {
            font-family: 'KongText';
            src: url('assets/fonts/kongtext/kongtext.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        
        body {
            background-image: url('assets/images/quest-bg.png');
            background-size: cover;
            background-attachment: fixed;
            min-height: 100vh;
            width: 100vw;
            overflow: hidden;
        }
        
        .particle {
            position: absolute;
            background-color: rgba(255, 255, 255, 0.6);
            border-radius: 50%;
            pointer-events: none;
        }
    </style>
</head>
<body class="w-screen h-screen flex items-center justify-center relative overflow-hidden m-0 p-0">
    <div class="absolute top-0 left-0 w-full h-full pointer-events-none z-0" id="particles"></div>
    
    <div class="flex flex-col items-center justify-center w-full h-full z-10">
        <div class="flex-1 flex items-center justify-center">
            <img src="assets/images/quest.png" alt="Quest Planner" class="w-[1100px] lg:w-[1000px] md:w-[800px] sm:w-[600px] max-w-[90vw] animate-float select-none">
        </div>
        
        <div class="flex flex-col items-center w-full absolute bottom-8 sm:bottom-16 md:bottom-24 lg:bottom-20">
            <a href="auth/login.php" 
               class="bg-quest-orange text-quest-dark border-4 border-quest-dark h-10 w-[220px] font-pixel text-xs 
                      uppercase tracking-wider cursor-pointer my-2 rounded-full flex items-center justify-center
                      transition-all duration-200 shadow-[0_4px_0_#e07a1b] hover:bg-[#ffb95e] hover:transform hover:translate-y-[-2px] hover:scale-[1.03]">
                LOG IN
            </a>
            
            <a href="auth/register.php" 
               class="bg-quest-orange text-quest-dark border-4 border-quest-dark h-10 w-[220px] font-pixel text-xs
                      uppercase tracking-wider cursor-pointer my-2 rounded-full flex items-center justify-center
                      transition-all duration-200 shadow-[0_4px_0_#e07a1b] hover:bg-[#ffb95e] hover:transform hover:translate-y-[-2px] hover:scale-[1.03]">
                SIGN UP
            </a>
        </div>
    </div>

    <script>
        // Create floating particles effect
        const particlesContainer = document.getElementById('particles');
        const particleCount = 30;
        
        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.classList.add('particle', 'animate-float-up');
            
            // Random position
            const posX = Math.random() * 100;
            const posY = Math.random() * 100;
            const size = 3 + Math.random() * 5;
            const delay = Math.random() * 5;
            const translateX = -100 + Math.random() * 200;
            
            particle.style.left = `${posX}%`;
            particle.style.bottom = `${posY}%`;
            particle.style.width = `${size}px`;
            particle.style.height = `${size}px`;
            particle.style.animationDelay = `${delay}s`;
            particle.style.setProperty('--tx', `${translateX}px`);
            
            particlesContainer.appendChild(particle);
        }
    </script>
</body>
</html> 