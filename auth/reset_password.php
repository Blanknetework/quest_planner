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
    <style>
        @font-face {
            font-family: 'KongText';
            src: url('../assets/fonts/kongtext/kongtext.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        
        body {
            font-family: 'KongText', monospace;
            background-image: url('../assets/images/bggg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        
        .password-field {
            position: relative;
            width: 100%;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #8A4B22;
            width: 20px;
            height: 20px;
        }
        
        .title-gradient {
            background: linear-gradient(90deg, #FFAA4B, #FF824E);
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
                        },
                        inputBg: '#FFEAE4',
                        buttonOrange: '#FDB21C'
                    }
                }
            }
        };
    </script>
</head>
<body class="min-h-screen flex justify-center items-center m-0 p-0">
    <div>
        <!-- Header Box: Center the title box -->
        <div class="title-gradient border-[7px] border-[#8A4B22] w-[450px] py-2 px-4 text-center mb-[-25px] z-10 relative mx-auto">
            <h1 class="text-white text-[16px] font-bold">CREATE NEW PASSWORD</h1>
        </div>
        
        <!-- Main Box -->
        <div class="bg-[#75341A] border-[5px] border-[#FF9926] rounded-[13px] w-[600px] h-[500px] py-8 px-8 pt-10 z-0 relative">
            <?php if($success): ?>
                <div class="bg-green-800/20 border-2 border-green-500 text-white p-3 mb-4 rounded text-xs text-center">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php else: ?>
                <?php if($error): ?>
                    <div class="bg-red-800/20 border-2 border-red-500 text-white p-3 mb-4 rounded text-xs text-center">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" onsubmit="return validateForm()" class="h-full flex flex-col">
                    <!-- New Password Field -->
                    <div class="mb-6">
                        <label class="block text-white text-sm mb-2">NEW PASSWORD</label>
                        <input type="password" name="password" id="password" 
                            class="w-full bg-white border-[5px] border-buttonOrange rounded-md py-3 px-3 font-sans" required>
                    </div>
                    
                    <!-- Re-enter Password Field -->
                    <div class="mb-6">
                        <label class="block text-white text-sm mb-2">RE-ENTER PASSWORD</label>
                        <input type="password" name="confirm_password" id="confirm_password" 
                            class="w-full bg-white border-[5px] border-buttonOrange rounded-md py-3 px-3 font-sans" required>
                    </div>
                    
                    <!-- Requirements -->
                    <div class="text-white text-sm mb-auto">
                        MUST CONTAIN:
                        <ul class="ml-6 mt-2 leading-loose">
                            <li>8 CHARACTERS OR MORE</li>
                            <li>UPPERCASE</li>
                            <li>LOWERCASE</li>
                            <li>NUMBER</li>
                            <li>SPECIAL CHARACTER</li>
                        </ul>
                    </div>
                    
                    <!-- Submit Button: positioned at bottom -->
                    <div class="mt-auto flex justify-center mb-8">
                        <button type="submit" 
                            class="bg-gradient-to-r from-orange-light to-orange-dark border-[5px] border-brown-dark rounded-lg w-[320px] py-3 text-white text-center font-bold text-sm uppercase tracking-wider">
                            Log in
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    function validateForm() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        // Check if passwords match
        if (password !== confirmPassword) {
            alert('Passwords do not match!');
            return false;
        }
        
        // Check password requirements
        const minLength = password.length >= 8;
        const hasUppercase = /[A-Z]/.test(password);
        const hasLowercase = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
        
        if (!minLength || !hasUppercase || !hasLowercase || !hasNumber || !hasSpecial) {
            alert('Password must contain at least 8 characters, uppercase, lowercase, number, and special character!');
            return false;
        }
        
        return true;
    }
    </script>
</body>
</html>