<?php

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
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = $_POST['full_name'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? '';

    //  password validation
    if (strlen($password) < 8 || strlen($password) > 20) {
        $error = "Password must be between 8 and 20 characters long";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
    try {
        // Generate verification token
        $verification_token = bin2hex(random_bytes(32));
        
        // Insert user into database
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, verification_token, full_name, gender, date_of_birth, level) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $verification_token, $full_name, $gender, $date_of_birth, 1]);
        
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
            $success = "Registration successful! We've sent a verification link to your email.";
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
            border-radius: 0; 
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
            border-radius: 0; 
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
            border-radius: 0;
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
            border-radius: 0;
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
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .success-message, .error-message {
            font-family: var(--pixel-font);
            font-size: 12px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success-message svg, .error-message svg {
            display: inline;
            vertical-align: middle;
        }

        .success-message a {
            color: var(--button-orange);
            text-decoration: underline;
        }

        .success-message a:hover {
            opacity: 0.8;
        }

        .password-field {
            position: relative;
            width: 100%;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
                top: 40%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--border-brown);
            width: 20px;
            height: 20px;
        }

        .auth-input[type="password"],
        .auth-input[type="text"] {
            color: var(--dark-text);
        }

            .password-requirements {
                font-family: var(--pixel-font);
                font-size: 12px;
                margin-top: -10px;
                margin-bottom: 15px;
                padding-left: 8px;
            }

            .requirements-invalid {
                color: #ef4444;
            }

            .requirements-valid {
                color: #059669;
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
            
            <!-- Registration Form -->
            <div class="auth-form">
                <div class="auth-header">CREATE YOUR ACCOUNT</div>
                
                <?php if(isset($success)): ?>
                    <div class="success-message">
                        <div class="flex items-center p-4 mb-4 text-green-800 bg-green-100 border-2 border-green-400 rounded">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    </div>
                <?php elseif(isset($error)): ?>
                    <div class="error-message">
                        <div class="flex items-center p-4 mb-4 text-red-800 bg-red-100 border-2 border-red-400 rounded">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    </div>
                <?php endif; ?>

       
                <?php if(!isset($success)): ?>
                        <form method="POST" action="register.php" id="registerForm" onsubmit="return validateForm()">
                        <div class="form-group">
                            <input type="text" name="username" class="auth-input" placeholder="USERNAME" required>
                        </div>
                        <div class="form-group">
                            <input type="email" name="email" class="auth-input" placeholder="EMAIL" required>
                        </div>
                        <div class="form-group">
                            <div class="password-field">
                                    <input 
                                        type="password" 
                                        name="password" 
                                        id="password"
                                        class="auth-input" 
                                        placeholder="PASSWORD" 
                                        required
                                        minlength="8"
                                        maxlength="20"
                                    >
                                <svg class="password-toggle" viewBox="0 0 20 20" fill="currentColor" data-password="password">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                                    
                                </div>
                                <div id="passwordRequirements" class="password-requirements requirements-invalid">
                                    Password must be 8-20 characters
                                </div>
                        </div>
                        <div class="form-group">
                            <div class="password-field">
                                    <input 
                                        type="password" 
                                        name="confirm_password" 
                                        id="confirm_password"
                                        class="auth-input" 
                                        placeholder="CONFIRM PASSWORD" 
                                        required
                                        minlength="8"
                                        maxlength="20"
                                    >
                                <svg class="password-toggle" viewBox="0 0 20 20" fill="currentColor" data-password="confirm_password">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>


                                </div>

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
                            <!---- Google ------>
                            <a href="google-auth.php" class="social-button google-btn">
                            <svg class="w-5 h-5 mr-2 inline-block" viewBox="0 0 24 24">
                                    <path fill="currentColor" d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z"/>
                                </svg>
                            </a>
                            <!-------Facebook-------->
                                <a href="facebook-auth.php" class="social-button facebook-btn">
                                    <svg class="w-5 h-5 mr-2 inline-block" viewBox="0 0 24 24">
                                        <path fill="currentColor" d="M12 2.04C6.5 2.04 2 6.53 2 12.06C2 17.06 5.66 21.21 10.44 21.96V14.96H7.9V12.06H10.44V9.85C10.44 7.34 11.93 5.96 14.22 5.96C15.31 5.96 16.45 6.15 16.45 6.15V8.62H15.19C13.95 8.62 13.56 9.39 13.56 10.18V12.06H16.34L15.89 14.96H13.56V21.96A10 10 0 0 0 22 12.06C22 6.53 17.5 2.04 12 2.04Z"/>
                                    </svg>
                                </a>
                    </div>
                <?php else: ?>
                    <div class="text-center mt-4">
                        <p class="mb-4">Please check your email to verify your account.</p>
                        <a href="login.php" class="auth-button inline-block">GO TO LOGIN</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
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

          
            document.getElementById('password').addEventListener('input', checkPasswords);
            document.getElementById('confirm_password').addEventListener('input', checkPasswords);

            function checkPasswords() {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                const requirements = document.getElementById('passwordRequirements');
                
                if (password.length < 8 || password.length > 20) {
                    requirements.textContent = "Password must be 8-20 characters";
                    requirements.classList.remove('requirements-valid');
                    requirements.classList.add('requirements-invalid');
                } else if (confirmPassword && password !== confirmPassword) {
                    requirements.textContent = "Passwords do not match!";
                    requirements.classList.remove('requirements-valid');
                    requirements.classList.add('requirements-invalid');
                } else if (confirmPassword && password === confirmPassword) {
                    requirements.textContent = "Passwords match!";
                    requirements.classList.remove('requirements-invalid');
                    requirements.classList.add('requirements-valid');
                } else {
                    requirements.textContent = "Password must be 8-20 characters";
                    requirements.classList.remove('requirements-invalid');
                    requirements.classList.add('requirements-valid');
                }
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
</body>
</html>
