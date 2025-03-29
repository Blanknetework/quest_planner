<?php
// Start the session and include required files before any output
session_start();
require_once '../vendor/autoload.php';
require_once '../config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Initialize variables
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        // Generate verification token
        $verification_token = bin2hex(random_bytes(32));
        
        // Insert user into database
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, verification_token) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $password, $verification_token]);
        
        // Configure PHPMailer
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $_ENV['SMTP_PORT'];
        
        $mail->setFrom($_ENV['SMTP_USERNAME'], 'Quest Planner');
        $mail->addAddress($email, $username);
        
        $verification_link = $_ENV['APP_URL'] . "/auth/verify.php?token=" . $verification_token;
        
        $mail->isHTML(true);
        $mail->Subject = 'Verify your Quest Planner account';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #f97316;'>Welcome to Quest Planner!</h2>
                <p>Hi {$username},</p>
                <p>Please click the button below to verify your email address:</p>
                <p style='text-align: center;'>
                    <a href='{$verification_link}' 
                       style='background-color: #f97316; 
                              color: white; 
                              padding: 10px 20px; 
                              text-decoration: none; 
                              border-radius: 5px; 
                              display: inline-block;'>
                        Verify Email Address
                    </a>
                </p>
                <p>If the button doesn't work, copy and paste this link into your browser:</p>
                <p>{$verification_link}</p>
                <p>If you did not create an account, no further action is required.</p>
            </div>
        ";
        $mail->AltBody = "Welcome to Quest Planner!\n\nPlease click the link below to verify your email address:\n{$verification_link}";
        
        if($mail->send()) {
            $_SESSION['registration_success'] = true;
            header("Location: verification-sent.php");
            exit();
        }
    } catch(PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "Email already exists";
        } else {
            $error = "Registration failed";
        }
    } catch(Exception $e) {
        $error = "Could not send verification email. Please try again later.";
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Quest Planner</title>
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
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .error-message {
            color: var(--delete-red);
            font-size: 12px;
            margin-top: 5px;
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
            
            <!-- Registration Form -->
            <div class="auth-form">
                <div class="auth-header">CREATE YOUR ACCOUNT</div>
                <form method="POST" action="register.php">
                    <div class="form-group">
                        <input type="text" name="username" class="auth-input" placeholder="USERNAME" required>
                    </div>
                    <div class="form-group">
                        <input type="email" name="email" class="auth-input" placeholder="EMAIL" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" class="auth-input" placeholder="PASSWORD" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="confirm_password" class="auth-input" placeholder="CONFIRM PASSWORD" required>
                    </div>
                    <button type="submit" class="auth-button">REGISTER</button>
                </form>

                <a href="login.php" class="auth-link">ALREADY HAVE AN ACCOUNT? LOGIN</a>

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