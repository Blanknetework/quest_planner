<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        if ($user['email_verified'] == 1) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['level'] = $user['level'] ?? 1;
            header("Location: ../index.php");
            exit();
        } else {
            $error = "Please verify your email address before logging in.";
        }
    } else {
        $error = "Invalid email or password";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Quest Planner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        @font-face {
            font-family: 'KongText';
            src: url('../assets/fonts/kongtext/kongtext.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        
        body {
            background-image: url('../assets/images/bggg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            font-family: 'KongText', monospace;
            overflow: hidden;
        }
        
        .auth-input {
            background-color: #FFEAE4;
            border: 5px solid #FDB21C;
            border-radius: 13px;
            padding: 8px 12px;
            width: 100%;
            font-family: 'KongText', monospace;
        }
        
        .auth-input[type="password"] {
            font-family: Arial, sans-serif !important;
            letter-spacing: 2px;
        }
        
        .title-gradient {
            background: linear-gradient(90deg, #FFAA4B, #FF824E);
        }
        
        .auth-button {
            background: linear-gradient(90deg, #FFAA4B, #FF824E);
            border: 5px solid #8A4B22;
            border-radius: 13px;
            color: white;
            font-family: 'KongText', monospace;
            padding: 10px 20px;
            transition: all 0.2s;
        }
        
        .auth-button:hover {
            transform: translateY(-2px);
        }
        
        .social-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: transform 0.2s;
        }
        
        .social-btn:hover {
            transform: scale(1.1);
        }
        
        .particle {
            position: absolute;
            background-color: rgba(255, 255, 255, 0.6);
            border-radius: 50%;
            pointer-events: none;
            animation: float-up 15s linear infinite;
        }
        
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            pointer-events: none;
            z-index: 0;
        }
        
        @keyframes float-up {
            0% {
                transform: translateY(0) translateX(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) translateX(var(--tx));
                opacity: 0;
            }
        }
    </style>
</head>
<body class="flex flex-col items-center justify-center m-0 h-screen">
    <div class="particles" id="particles"></div>
    
    <!-- Title Box -->
    <div class="title-gradient border-[7px] border-[#8A4B22] w-[350px] md:w-[450px] md:h-[76px] py-2 px-4 text-center mb-[-25px] z-10 relative flex items-center justify-center">
        <h1 class="text-white text-[16px] font-bold">LOG IN TO YOUR ACCOUNT</h1>
    </div>
    
    <!-- Main Box -->
    <div class="bg-[#75341A] border-[5px] border-[#FF9926] rounded-[13px] w-[320px] md:w-[621px] md:l-[528px] p-6 pt-10 z-0 ">
        <?php if(isset($error)): ?>
            <div class="bg-red-500 text-white p-2 mb-4 rounded text-center text-xs">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php" class="flex flex-col items-center">
            <div class="w-full mb-4">
                <label for="email" class="text-white block mb-2 text-sm">Email</label>
                <input type="email" name="email" id="email" class="auth-input" required>
            </div>
            
            <div class="w-full mb-4">
                <label for="password" class="text-white block mb-2 text-sm">Password</label>
                <input type="password" name="password" id="password" class="auth-input" required>
            </div>
            
            <div class="w-full flex justify-start mb-6">
                <a href="forgot_password.php" class="text-[#FFEAE4] text-xs hover:underline">Forgot Password?</a>
            </div>
            
            <button type="submit" class="auth-button w-[80%] py-2 mb-3">LOGIN</button>
            
            <a href="register.php" class="text-[#FFEAE4] text-xs hover:underline mb-4">New Adventurer? Register</a>
            
            <div class="flex items-center w-full mb-5">
                <div class="h-[2px] bg-[#FF9926] flex-grow"></div>
                <div class="text-[#FFEAE4] px-2 text-xs">OR</div>
                <div class="h-[2px] bg-[#FF9926] flex-grow"></div>
            </div>
            
            <div class="flex justify-center gap-6">
                <a href="google-auth.php" class="social-btn bg-[#DB4437]">
                    <i class="fab fa-google text-lg"></i>
                </a>
                <a href="facebook-auth.php" class="social-btn bg-[#3B5998]">
                    <i class="fab fa-facebook-f text-lg"></i>
                </a>
            </div>
        </form>
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