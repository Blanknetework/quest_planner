<?php
session_start();
require_once '../vendor/autoload.php';
require_once '../config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$token = $_GET['token'] ?? '';
$error = null;
$success = null;

// Debug output
error_log("Token received: " . $token);


function generateResetToken($email) {
    global $pdo; 
    
    // Generate token
    $reset_token = bin2hex(random_bytes(32));
    $reset_token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Update user record with reset token
    $sql = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$reset_token, $reset_token_expiry, $email])) {
        return $reset_token;
    }
    return false;
}

// Verify token and handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['password'])) {
    error_log("Form submitted"); // Debug log
    
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password) || empty($confirm_password)) {
        $error = "Both password fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        try {
            // Check if token exists and not expired
            $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            error_log("User found: " . ($user ? 'yes' : 'no')); // Debug log
            
            if ($user) {
                // First update the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
                
                if ($stmt->execute([$hashed_password, $user['id']])) {
                    // Send confirmation email
                    try {
                        $mail = new PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = $_ENV['SMTP_HOST'];
                        $mail->SMTPAuth = true;
                        $mail->Username = $_ENV['SMTP_USERNAME'];
                        $mail->Password = $_ENV['SMTP_PASSWORD'];
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = $_ENV['SMTP_PORT'];
                        
                        $mail->setFrom($_ENV['SMTP_USERNAME'], 'Quest Planner');
                        $mail->addAddress($user['email'], $user['username']);
                        
                        $mail->isHTML(true);
                        $mail->Subject = 'Password Reset Successful - Quest Planner';
                        $mail->Body = "
                         <div style='font-family: \"Courier New\", monospace; max-width: 600px; margin: 0 auto; background-color: #F5E6D3; border: 4px solid #2F1810; padding: 20px;'>
                            <div style='background-color: #FF824E; color: white; text-align: center; padding: 15px; border: 4px solid #2F1810; margin: -20px -20px 20px -20px;'>
                                <h1 style='margin: 0; font-size: 24px; letter-spacing: 2px;'>QUEST PLANNER</h1>
                            </div>
                            
                            <div style='background-color: white; border: 4px solid #2F1810; padding: 20px; margin-bottom: 20px;'>
                                <h2 style='color: #FF824E; font-size: 20px; margin-top: 0;'>Password Reset Successful!</h2>
                                <p style='color: #2F1810; line-height: 1.5;'>Hey {$user['username']}, your password has been successfully reset.</p>
                                
                                <div style='text-align: center; margin: 30px 0;'>
                                    <a href='{$_ENV['APP_URL']}/auth/login.php' 
                                    style='background-color: #FF824E; 
                                            color: white; 
                                            padding: 12px 24px; 
                                            text-decoration: none; 
                                            border: 3px solid #2F1810;
                                            font-weight: bold;
                                            display: inline-block;
                                            text-transform: uppercase;
                                            letter-spacing: 1px;'>
                                        🗝️ Login Now 🗝️
                                    </a>
                                </div>
                                
                                <div style='border-top: 2px solid #2F1810; margin-top: 20px; padding-top: 20px; font-size: 14px;'>
                                    <p style='color: #2F1810;'>If you didn't reset your password, please contact support immediately.</p>
                                </div>
                            </div>
                            
                            <div style='text-align: center; color: #2F1810; font-size: 12px;'>
                                <p>This is an automated message, please do not reply.</p>
                            </div>
                        </div>
                        ";

                        $mail->AltBody = "
                        Password Reset Successful - Quest Planner
                        =======================================
                    
                        Hey {$user['username']},
                        
                        Your password has been successfully reset.
                        
                        You can now login with your new password at:
                        {$_ENV['APP_URL']}/auth/login.php
                        
                        If you didn't reset your password, please contact support immediately.
                        
                        This is an automated message, please do not reply.
                        ";
                        
                        $mail->send();
                    } catch(Exception $e) {
                        error_log("Failed to send confirmation email: " . $e->getMessage());
                    }
                    
                    $success = "Password has been reset successfully. You can now login with your new password.";
                    
                  
                    echo "<script>
                        window.onload = function() {
                            alert('Password reset successful! Redirecting to login page...');
                            setTimeout(function() {
                                window.location.href = 'login.php';
                            }, 2000);
                        }
                    </script>";
                } else {
                    $error = "Failed to update password. Please try again.";
                }
            } else {
                $error = "Invalid or expired reset link. Please request a new one.";
            }
        } catch(PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $error = "An error occurred. Please try again.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Quest Planner</title>
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
            --dark-text: #000000;

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
            color: #000;
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


        .auth-input[type="password"] {
            font-family: Arial, sans-serif !important; /* Our font KongText dont support special characters so wala tayong choice, kung hindi gumamit ng standard font */
            letter-spacing: 2px;
            color: #000000;
        }


        .auth-button {
            width: 100%;
            padding: 12px;
            background-color: var(--button-orange);
            color: white;
            border: 3px solid var(--border-brown);
            cursor: pointer;
            text-transform: uppercase;
            font-family: var(--pixel-font);
            font-size: 14px;
            transition: opacity 0.3s;
        }

        .auth-button:hover {
            opacity: 0.9;
        }

        .password-field {
            position: relative;
            width: 100%;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 35%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--border-brown);
            width: 20px;
            height: 20px;
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

            <div class="auth-form">
                <div class="auth-header">RESET PASSWORD</div>

                <?php if($success): ?>
                    <div class="bg-green-100 border-2 border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($success); ?>
                        <p class="mt-2">
                            <a href="login.php" class="text-green-700 underline">Go to Login</a>
                        </p>
                    </div>
                <?php else: ?>
                    <?php if($error): ?>
                        <div class="bg-red-100 border-2 border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" onsubmit="return validateForm()">
                        <div class="form-group">
                            <div class="password-field">
                                <input type="password" name="password" id="password" class="auth-input" placeholder="NEW PASSWORD" required>
                                <svg class="password-toggle" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="password-field">
                                <input type="password" name="confirm_password" id="confirm_password" class="auth-input" placeholder="CONFIRM PASSWORD" required>
                                <svg class="password-toggle" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                        <button type="submit" class="auth-button">Reset Password</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>


    
    <script>
    function validateForm() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (password !== confirmPassword) {
            alert('Passwords do not match!');
            return false;
        }
        return true;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const toggles = document.querySelectorAll('.password-toggle');
        
        toggles.forEach(toggle => {
            toggle.addEventListener('click', function() {
                const inputField = this.previousElementSibling;
                const currentType = inputField.getAttribute('type');
                
                if (currentType === 'password') {
                    inputField.setAttribute('type', 'text');
                    this.innerHTML = `
                        <path d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" />
                        <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                    `;
                } else {
                    inputField.setAttribute('type', 'password');
                    this.innerHTML = `
                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                    `;
                }
            });
        });
    });
    </script>

    <script src="../assets/js/script.js"></script>
</body>
</html>