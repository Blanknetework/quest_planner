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
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        @font-face {
            font-family: 'KongText';
            src: url('../assets/fonts/kongtext/kongtext.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        
        :root {
            --pixel-font: 'KongText', 'Courier New', monospace, system-ui;
        }
        
        .game-container {
            background-image: url('../assets/images/bg.svg');
            background-size: cover;
            background-attachment: fixed;
            min-height: 100vh;
            overflow-x: hidden;
            padding: 20px;
        }

        .auth-form {
            background-color: var(--quest-box-tan);
            border: 4px solid var(--border-brown);
            border-radius: 0; /* Square corners for pixel look */
            padding: 20px;
            max-width: 400px;
            margin: 0 auto;
            margin-top: 50px;
        }

        .auth-header {
            background-color: var(--button-orange);
            color: #FFFFFF;
            font-weight: bold;
            text-align: center;
            padding: 10px 0;
            margin: -20px -20px 20px -20px;
            border-bottom: 4px solid var(--border-brown);
            text-shadow: 1px 1px 0 rgba(0, 0, 0, 0.5);
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .auth-input {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 3px solid var(--border-brown);
            border-radius: 0; /* Square corners for pixel look */
            background-color: #FFF;
            font-family: var(--pixel-font);
            font-size: 12px;
            color: var(--dark-text);
        }

        .auth-input[type="password"] {
            font-family: Arial, sans-serif !important; /* Our font KongText dont support special characters so wala tayong choice, kung hindi gumamit ng standard font */
            letter-spacing: 2px;
            color: #000000;
        }

        .auth-button {
            background-color: var(--button-orange);
            color: white;
            border: 3px solid var(--border-brown);
            border-radius: 0; /* Square corners for pixel look */
            padding: 8px 15px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            font-family: var(--pixel-font);
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .auth-button:hover {
            opacity: 0.9;
        }

        .auth-link {
            color: var(--dark-text);
            text-decoration: none;
            font-size: 12px;
            display: block;
            margin-top: 15px;
            text-align: center;
        }

        .auth-link:hover {
            text-decoration: underline;
        }

        .social-login {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }

        .social-button {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 12px;
            border: 3px solid var(--border-brown);
            border-radius: 0;
            font-family: var(--pixel-font);
            font-size: 12px;
            cursor: pointer;
            text-transform: uppercase;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .google-btn {
            background-color: #DB4437;
            color: white;
        }

        .facebook-btn {
            background-color: #4267B2;
            color: white;
        }

        .social-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 0 var(--border-brown);
        }

        .social-button:active {
            transform: translateY(0);
            box-shadow: none;
        }

        .social-button svg {
            width: 20px;
            height: 20px;
            margin-right: 8px;
        }

        .auth-divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            color: var(--dark-text);
            font-size: 12px;
        }

        .divider-line {
            flex-grow: 1;
            height: 2px;
            background-color: var(--border-brown);
        }

        .divider-text {
            padding: 0 10px;
        }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="container mx-auto px-4 py-4">
            <!-- Title Banner -->
            <div class="title-banner">
                <div class="title-logo"></div>
                <div class="title-text">QUEST PLANNER</div>
            </div>
            
            <!-- Login Form -->
            <div class="auth-form">
                <div class="auth-header">LOGIN</div>
                <form method="POST" action="login.php">
                    <input type="email" name="email" class="auth-input" placeholder="EMAIL" required>
                    <input type="password" name="password" class="auth-input" placeholder="PASSWORD" required>
                    <button type="submit" class="auth-button">LOGIN</button>
                </form>

                <a href="forgot_password.php" class="auth-link">FORGOT PASSWORD?</a>
                <a href="register.php" class="auth-link">NEW ADVENTURER? REGISTER</a>

                <div class="auth-divider">
                    <div class="divider-line"></div>
                    <div class="divider-text">OR</div>
                    <div class="divider-line"></div>
                </div>

                <div class="social-login">
                    <a href="google-auth.php" class="social-button google-btn">
                        <svg class="w-5 h-5 mr-2 inline-block" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z"/>
                        </svg>
                        CONTINUE WITH GOOGLE
                    </a>
                    <a href="facebook-auth.php" class="social-button facebook-btn">
                        <svg class="w-5 h-5 mr-2 inline-block" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M12 2.04C6.5 2.04 2 6.53 2 12.06C2 17.06 5.66 21.21 10.44 21.96V14.96H7.9V12.06H10.44V9.85C10.44 7.34 11.93 5.96 14.22 5.96C15.31 5.96 16.45 6.15 16.45 6.15V8.62H15.19C13.95 8.62 13.56 9.39 13.56 10.18V12.06H16.34L15.89 14.96H13.56V21.96A10 10 0 0 0 22 12.06C22 6.53 17.5 2.04 12 2.04Z"/>
                        </svg>
                        CONTINUE WITH FACEBOOK
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>