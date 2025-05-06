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
    <div class="title-gradient border-[7px] border-[#8A4B22] w-[350px] md:w-[450px] py-2 px-4 text-center mb-[-25px] z-10 relative flex items-center justify-center">
        <h1 class="text-white text-[16px] font-bold">FORGOT PASSWORD</h1>
    </div>
    
    <!-- Main Box -->
    <div class="bg-[#75341A] border-[5px] border-[#FF9926] rounded-[13px] w-[320px] md:w-[600px] p-6 pt-10 z-0 relative">
        <?php if($success): ?>
            <div class="bg-green-500 text-white p-4 mb-4 rounded-lg text-center text-xs">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="bg-red-500 text-white p-2 mb-4 rounded text-center text-xs">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="text-white text-center mb-6 text-sm">
            Enter your email address below, and we'll send you a link to reset your password.
        </div>
        
        <form method="POST" action="forgot_password.php" class="flex flex-col items-center">
            <div class="w-full mb-4">
                <label for="email" class="text-white block mb-2 text-sm">Email</label>
                <input type="email" name="email" id="email" class="auth-input" required>
            </div>
            
            <button type="submit" class="auth-button w-[80%] py-2 mb-3">Send Reset Link</button>
            
            <div class="mt-4 text-center">
                <a href="login.php" class="text-[#FFEAE4] text-xs hover:underline">Return to login</a>
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