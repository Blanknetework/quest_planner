<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require '../vendor/autoload.php';
require_once '../config/database.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $token = bin2hex(random_bytes(32));
    
    try {
        // First check if the email exists and is verified
        $check_stmt = $pdo->prepare("SELECT id, email_verified FROM users WHERE email = ?");
        $check_stmt->execute([$email]);
        $user = $check_stmt->fetch();
        
        if ($user) {
            // Check if email is verified
            if ($user['email_verified'] != 1) {
                $error = "This account has not been verified. Please verify your email first.";
            } else {
                // Update the user with reset token
    $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?");
    $stmt->execute([$token, $email]);
    
    if ($stmt->rowCount() > 0) {
        $mail = new PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USERNAME'];
            $mail->Password = $_ENV['SMTP_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $_ENV['SMTP_PORT'];
            
            $mail->setFrom($_ENV['SMTP_USERNAME'], 'Quest Planner');
            $mail->addAddress($email);
                        
                        $reset_link = $_ENV['APP_URL'] . "/auth/reset_password.php?token=" . $token;
            
            $mail->isHTML(true);
                        $mail->Subject = 'Password Reset Request - Quest Planner';
                        $mail->Body = "
                            <div style='font-family: \"Courier New\", monospace; max-width: 600px; margin: 0 auto; background-color: #F5E6D3; border: 4px solid #2F1810; padding: 20px;'>
                                <div style='background-color: #FF824E; color: white; text-align: center; padding: 15px; border: 4px solid #2F1810; margin: -20px -20px 20px -20px;'>
                                    <h1 style='margin: 0; font-size: 24px; letter-spacing: 2px;'>QUEST PLANNER</h1>
                                </div>
                                
                                <div style='background-color: white; border: 4px solid #2F1810; padding: 20px; margin-bottom: 20px;'>
                                    <h2 style='color: #FF824E; font-size: 20px; margin-top: 0;'>Password Reset Request</h2>
                                    <p style='color: #2F1810; line-height: 1.5;'>You requested to reset your password. Click the button below to proceed:</p>
                                    
                                    <div style='text-align: center; margin: 30px 0;'>
                                        <a href='{$reset_link}' 
                                           style='background-color: #FF824E; 
                                                  color: white; 
                                                  padding: 12px 24px; 
                                                  text-decoration: none; 
                                                  border: 3px solid #2F1810;
                                                  font-weight: bold;
                                                  display: inline-block;
                                                  text-transform: uppercase;
                                                  letter-spacing: 1px;'>
                                            🗝️ Reset Password 🗝️
                                        </a>
                                    </div>
                                    
                                    <div style='border-top: 2px solid #2F1810; margin-top: 20px; padding-top: 20px; font-size: 14px;'>
                                        <p style='color: #2F1810;'>If you didn't request this password reset, please ignore this email.</p>
                                        <p style='color: #2F1810;'>This link will expire in 1 hour.</p>
                                    </div>
                                </div>
                            </div>
                        ";
                        
                        $mail->AltBody = "
                            Password Reset Request - Quest Planner
                            ====================================
                            
                            You requested to reset your password. Click the link below to proceed:
                            
                            {$reset_link}
                            
                            If you didn't request this password reset, please ignore this email.
                            This link will expire in 1 hour.
                        ";
            
            $mail->send();
            $success = "Password reset link has been sent to your email";
        } catch (Exception $e) {
                        error_log("Failed to send password reset email: " . $e->getMessage());
                        $error = "Could not send reset email. Please try again later.";
                    }
                }
            }
        } else {
            $error = "No account found with this email address.";
        }
    } catch(PDOException $e) {
        error_log("Password reset error: " . $e->getMessage());
        $error = "An error occurred. Please try again later.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Quest Planner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        @font-face {
            font-family: 'KongText';
            src: url('assets/fonts/kongtext/kongtext.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        
        :root {
            --pixel-font: 'KongText', 'Courier New', monospace, system-ui;
        }
        
        .game-container {
            background-image: url('../assets/images/bggg.jpg');
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

        .title-banner {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-bottom: 20px;
            padding: 10px;
        }

        .title-box {
            width: 56px;
            height: 50px;
            background-color: #4D2422;
            border-radius: 10px;
            flex-shrink: 0;
            margin-right: 15px;
        }

        .title-image {
            max-width: 250px;
            height: auto;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
        }

        .auth-link:hover {
            text-decoration: underline;
        }
        
        .auth-description {
            color: var(--dark-text);
            font-size: 12px;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .message-box {
            background-color: var(--medium-brown);
            border: 3px solid var(--border-brown);
            border-radius: 0;
            padding: 10px;
            margin-bottom: 15px;
            color: var(--dark-text);
            font-size: 12px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="container mx-auto px-4 py-4">
            <!-- Title Banner -->               
             <div class="title-banner">
                <div class="title-box"></div>
                <img src="../assets/images/Quest-Planner.png" alt="QUEST PLANNER" class="title-image">
            </div>
            
            <!-- Forgot Password Form -->
            <div class="auth-form">
                <div class="auth-header">FORGOT PASSWORD</div>
                
                <p class="auth-description">ENTER YOUR EMAIL ADDRESS AND WE'LL SEND YOU A LINK TO RESET YOUR PASSWORD.</p>
                
                <?php if($error): ?>
                    <div class="bg-red-100 border-2 border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="bg-green-100 border-2 border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($success); ?>
                        <p class="mt-4 text-center">
                            <a href="login.php" 
                               class="inline-block bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600 transition-colors">
                                Go to Login
                            </a>
                        </p>
                    </div>
                <?php else: ?>
                <form method="POST" action="forgot_password.php">
                    <input type="email" name="email" class="auth-input" placeholder="EMAIL" required>
                    <button type="submit" class="auth-button">SEND RESET LINK</button>
                </form>
                <?php endif; ?>

                <a href="login.php" class="auth-link">BACK TO LOGIN</a>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                successMessage.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>