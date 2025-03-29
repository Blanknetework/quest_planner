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
            background-image: url('assets/images/bg.svg');
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
            justify-content: space-between;
            margin-top: 20px;
        }

        .social-button {
            width: 48%;
            padding: 8px 0;
            text-align: center;
            border: 3px solid var(--border-brown);
            border-radius: 0; /* Square corners for pixel look */
            font-family: var(--pixel-font);
            font-size: 12px;
            cursor: pointer;
            text-transform: uppercase;
        }

        .google-btn {
            background-color: #DB4437;
            color: white;
        }

        .facebook-btn {
            background-color: #4267B2;
            color: white;
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
                    <a href="#" class="social-button google-btn">GOOGLE</a>
                    <a href="#" class="social-button facebook-btn">FACEBOOK</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>