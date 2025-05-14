<?php
// Enable error reporting and logging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);

session_start();
require_once '../vendor/autoload.php';
require_once '../config/database.php';

// Debug: Log start of registration process
error_log("Registration page accessed at " . date('Y-m-d H:i:s'));

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
    // Debug: Log form submission
    error_log("Form submitted: " . json_encode($_POST));
    
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = $_POST['full_name'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? '';

    //  password validation
    if (strlen($password) < 8 || strlen($password) > 20) {
        $error = "Password must be between 8 and 20 characters long";
        error_log("Validation failed: " . $error);
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
        error_log("Validation failed: " . $error);
    } else {
    try {
        // Generate verification token
        $verification_token = bin2hex(random_bytes(32));
        
        // Debug: Log the database operation attempt
        error_log("Attempting to insert user: {$username}, {$email}");
        
        // Insert user into database
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, verification_token, full_name, gender, date_of_birth, level) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $verification_token, $full_name, $gender, $date_of_birth, 1]);
        
        // Get the user ID of the newly registered user
        $userId = $pdo->lastInsertId();
        
        // Debug: Log successful database insertion
        error_log("User inserted successfully");
        
        // Configure PHPMailer
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
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
            <div style='font-family: \"Courier New\", monospace; max-width: 600px; margin: 0 auto; background-color: #F5E6D3; border: 4px solid #2F1810; padding: 20px;'>
                <div style='background-color: #FF824E; color: white; text-align: center; padding: 15px; border: 4px solid #2F1810; margin: -20px -20px 20px -20px;'>
                    <h1 style='margin: 0; font-size: 24px; letter-spacing: 2px;'>QUEST PLANNER</h1>
                </div>
                
                <div style='background-color: white; border: 4px solid #2F1810; padding: 20px; margin-bottom: 20px;'>
                    <h2 style='color: #FF824E; font-size: 20px; margin-top: 0;'>Welcome, {$username}!</h2>
                    <p style='color: #2F1810; line-height: 1.5;'>Your quest awaits! Please verify your email to begin your adventure.</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$verification_link}' 
                           style='background-color: #FF824E; 
                                  color: white; 
                                  padding: 12px 24px; 
                                  text-decoration: none; 
                                  border: 3px solid #2F1810;
                                  font-weight: bold;
                                  display: inline-block;
                                  text-transform: uppercase;
                                  letter-spacing: 1px;'>
                            ⚔️ Verify Email ⚔️
                        </a>
                    </div>
                    
                    <div style='border-top: 2px solid #2F1810; margin-top: 20px; padding-top: 20px; font-size: 14px;'>
                        <p>If the button doesn't work, copy and paste this link:</p>
                        <p style='background: #F5E6D3; padding: 10px; border: 2px solid #2F1810; word-break: break-all;'>
                            {$verification_link}
                        </p>
                    </div>
                </div>
                
                <div style='text-align: center; color: #2F1810; font-size: 12px;'>
                    <p>If you didn't create this account, no further action is required.</p>
                </div>
            </div>
        ";

        $mail->AltBody = "
        Welcome to QUEST PLANNER!
        ========================

        Greetings, {$username}!
        
        Your quest awaits! Please verify your email to begin your adventure.
        
        Verification Link:
        {$verification_link}
        
        If you didn't create this account, no further action is required.
        ";

        if($mail->send()) {
            // Store user ID in session
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            
            // Redirect to avatar creation page
            header('Location: create_avatar.php');
            exit;
        }
    } catch(PDOException $e) {
        error_log("PDO Exception: " . $e->getMessage() . " - Code: " . $e->getCode());
        if ($e->getCode() == 23000) {
            $error = "Email already exists";
        } else {
            $error = "Registration failed";
        }
    } catch(Exception $e) {
        error_log("Email Exception: " . $e->getMessage());
        $error = "Could not send verification email. Please try again later.";
    }
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
            margin-bottom: 10px;
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

        .background{
            border: 5px solid #8A4B22;
            border-radius: 13px;
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
    </style>
</head>
<body class="flex flex-col items-center justify-center m-0 h-screen">
    <div class="particles" id="particles"></div>
    
    <!-- Title Box -->
    <div class="title-gradient border-[7px] border-[#8A4B22] w-[350px] md:w-[450px] py-2 px-4 text-center mb-[-25px] z-10 relative flex items-center justify-center">
        <h1 class="text-white text-[16px] font-bold">REGISTRATION</h1>
    </div>
    
    <!-- Main Box -->
    <div class="bg-[#75341A] border-[5px] border-[#FF9926] rounded-[13px] w-[320px] md:w-[700px] p-6 pt-10 z-0 relative">
        <?php if($error): ?>
            <div class="bg-red-500 text-white p-2 mb-4 rounded text-center text-xs">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="register.php" id="registerForm" onsubmit="return validateForm()" class="flex flex-col items-center">
            <div class="grid grid-cols-2 gap-4 w-full mb-3">
                <div>
                    <label for="full_name" class="text-white block mb-1 text-xs">FULL NAME</label>
                    <input type="text" name="full_name" id="full_name" class="auth-input" required>
                </div>
                <div>
                    <label for="gender" class="text-white block mb-1 text-xs">GENDER</label>
                    <select name="gender" id="gender" class="auth-input" required>
                        <option value="" disabled selected>Select gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                        <option value="Prefer not to say">Prefer not to say</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 w-full mb-3">
                <div>
                    <label for="username" class="text-white block mb-1 text-xs">USERNAME</label>
                    <input type="text" name="username" id="username" class="auth-input" required>
                </div>
                <div>
                    <label for="date_of_birth" class="text-white block mb-1 text-xs">DATE OF BIRTH</label>
                    <input type="date" name="date_of_birth" id="date_of_birth" class="auth-input" required>
                </div>
            </div>
            
            <div class="w-full mb-3">
                <label for="email" class="text-white block mb-1 text-xs">EMAIL ADDRESS</label>
                <input type="email" name="email" id="email" class="auth-input" required>
            </div>
            
            <div class="w-full mb-3">
                <label for="password" class="text-white block mb-1 text-xs">CREATE PASSWORD</label>
                <div class="password-field">
                    <input type="password" name="password" id="password" class="auth-input" required minlength="8" maxlength="20">
                    <svg class="password-toggle" viewBox="0 0 20 20" fill="currentColor" data-password="password">
                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>
            
            <div class="w-full mb-6">
                <label for="confirm_password" class="text-white block mb-1 text-xs">RE-ENTER PASSWORD</label>
                <div class="password-field">
                    <input type="password" name="confirm_password" id="confirm_password" class="auth-input" required minlength="8" maxlength="20">
                    <svg class="password-toggle" viewBox="0 0 20 20" fill="currentColor" data-password="confirm_password">
                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>
            
            <div class="text-xs text-white mb-4 w-full">
                <p id="passwordRequirements" class="text-center">
                    <?php if(isset($error) && strpos($error, 'Password') !== false): ?>
                        <span class="text-red-400"><?php echo htmlspecialchars($error); ?></span>
                    <?php else: ?>
                        Password must be 8-20 characters
                    <?php endif; ?>
                </p>
            </div>
            
            <button type="submit" class="auth-button w-[80%] py-2 mb-3 uppercase">SIGN</button>
            
            <div class="mt-3 text-center">
                <a href="login.php" class="text-[#FFEAE4] text-xs hover:underline">ALREADY HAVE AN ACCOUNT? LOG IN</a>
            </div>
        </form>
    </div>
    
    <!-- Next button in the corner -->
    <div class="absolute bottom-8 right-8">
        <a href="login.php" class="background block bg-[linear-gradient(to_right,_#FFAA4B,_#FF824E)] rounded-md w-[250px] py-3 text-center text-white font-bold text-xl uppercase" >
            NEXT
        </a>
    </div>
    
    <script>
        // Float particles
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
        
        // Form validation
        function validateForm() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const passwordValue = password.value;
            
            // Check password length
            if (passwordValue.length < 8 || passwordValue.length > 20) {
                alert('Password must be between 8 and 20 characters long');
                password.focus();
                return false;
            }
            
            // Check if passwords match
            if (passwordValue !== confirmPassword.value) {
                alert('Passwords do not match!');
                confirmPassword.focus();
                return false;
            }
            
            return true;
        }
        
        // Password toggle
        document.addEventListener('DOMContentLoaded', function() {
            const toggles = document.querySelectorAll('.password-toggle');
            
            toggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const inputId = this.getAttribute('data-password');
                    const inputField = document.getElementById(inputId);
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
            
            // Password validation feedback
            const password = document.getElementById('password');
            const confirm = document.getElementById('confirm_password');
            const requirements = document.getElementById('passwordRequirements');
            
            function updateRequirements() {
                if (password.value.length > 0 && password.value.length < 8) {
                    requirements.innerHTML = '<span class="text-red-400">Password must be 8-20 characters</span>';
                } else if (confirm.value.length > 0 && password.value !== confirm.value) {
                    requirements.innerHTML = '<span class="text-red-400">Passwords do not match!</span>';
                } else if (password.value.length >= 8 && confirm.value.length > 0 && password.value === confirm.value) {
                    requirements.innerHTML = '<span class="text-green-400">Passwords match!</span>';
                } else if (password.value.length >= 8) {
                    requirements.innerHTML = '<span class="text-green-400">Password length is good</span>';
                }
            }
            
            password.addEventListener('input', updateRequirements);
            confirm.addEventListener('input', updateRequirements);
        });
    </script>
</body>
</html>
