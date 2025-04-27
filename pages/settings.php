<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Include database connection and required libraries
require_once '../config/database.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Function to send password change notification email
function sendPasswordChangeEmail($email, $username) {
    try {
        $mail = new PHPMailer(true);
        
        // Enable debugging
        $mail->SMTPDebug = 2; // 2 = client and server messages
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug: $str");
        };
        
        // Setup
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'] ?? '';
        $mail->Password = $_ENV['SMTP_PASSWORD'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $_ENV['SMTP_PORT'] ?? 587;
        
        // Log missing env vars
        if (empty($_ENV['SMTP_HOST'])) error_log("SMTP_HOST environment variable is missing");
        if (empty($_ENV['SMTP_USERNAME'])) error_log("SMTP_USERNAME environment variable is missing");
        if (empty($_ENV['SMTP_PASSWORD'])) error_log("SMTP_PASSWORD environment variable is missing");
        if (empty($_ENV['SMTP_PORT'])) error_log("SMTP_PORT environment variable is missing");
        if (empty($_ENV['APP_URL'])) error_log("APP_URL environment variable is missing");
        
        // Debug email values
        error_log("Sending email to: $email, username: $username");
        
        $mail->setFrom($_ENV['SMTP_USERNAME'] ?? 'noreply@questplanner.com', 'Quest Planner');
        $mail->addAddress($email, $username);
        
        $mail->isHTML(true);
        $mail->Subject = 'Password Changed - Quest Planner';
        
        $mail->Body = "
            <div style='font-family: \"Courier New\", monospace; max-width: 600px; margin: 0 auto; background-color: #F5E6D3; border: 4px solid #2F1810; padding: 20px;'>
                <div style='background-color: #FF824E; color: white; text-align: center; padding: 15px; border: 4px solid #2F1810; margin: -20px -20px 20px -20px;'>
                    <h1 style='margin: 0; font-size: 24px; letter-spacing: 2px;'>QUEST PLANNER</h1>
                </div>
                
                <div style='background-color: white; border: 4px solid #2F1810; padding: 20px; margin-bottom: 20px;'>
                    <h2 style='color: #FF824E; font-size: 20px; margin-top: 0;'>Hey {$username}!</h2>
                    <p style='color: #2F1810; line-height: 1.5;'>Your password for Quest Planner has been successfully changed.</p>
                    
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
                            🔐 Login Now 🔐
                        </a>
                    </div>
                    
                    <div style='border-top: 2px solid #2F1810; margin-top: 20px; padding-top: 20px; font-size: 14px;'>
                        <p style='color: #2F1810;'>If you did not make this change, please contact support immediately or reset your password.</p>
                    </div>
                </div>
                
                <div style='text-align: center; color: #2F1810; font-size: 12px;'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        ";
        
        $mail->AltBody = "
            Password Changed - Quest Planner
            ===============================
            
            Hey {$username},
            
            Your password for Quest Planner has been successfully changed.
            
            You can login at: {$_ENV['APP_URL']}/auth/login.php
            
            If you did not make this change, please contact support immediately or reset your password.
            
            This is an automated message. Please do not reply to this email.
        ";
        
        try {
            $result = $mail->send();
            error_log("Email send result: " . ($result ? "Success" : "Failed"));
            return $result;
        } catch (Exception $e) {
            error_log("Email sending exception: " . $e->getMessage());
            return false;
        }
    } catch (Exception $e) {
        error_log("Email setup exception: " . $e->getMessage());
        return false;
    }
}

// Get user data
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's a password change request
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate passwords
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $passwordError = "All password fields are required";
        } elseif ($newPassword !== $confirmPassword) {
            $passwordError = "New passwords do not match! Please ensure both passwords are identical.";
            // Add a session variable to ensure error persists after form submission
            $_SESSION['password_error'] = $passwordError;
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $userData = $stmt->fetch();
            
            if (password_verify($currentPassword, $userData['password'])) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
                
                // Send notification email
                $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $userInfo = $stmt->fetch();
                
                error_log("Attempting to send password change email to: " . $userInfo['email']);
                $emailResult = sendPasswordChangeEmail($userInfo['email'], $userInfo['username']);
                error_log("Email send function result: " . ($emailResult ? "Success" : "Failed"));
                
                // Redirect after password update
                header('Location: settings.php?success=password');
                exit;
            } else {
                $passwordError = "Current password is incorrect";
            }
        }
    } 
    // Regular form submission
    else {
        $fullname = $_POST['fullname'] ?? '';
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $dob = $_POST['dob'] ?? '';
        
        // Update user settings
        $updateFields = [];
        $params = [];
        
        if (!empty($fullname)) {
            $updateFields[] = "full_name = ?";
            $params[] = $fullname;
        }
        
        if (!empty($username)) {
            $updateFields[] = "username = ?";
            $params[] = $username;
        }
        
        if (!empty($email)) {
            $updateFields[] = "email = ?";
            $params[] = $email;
        }
        
        if (!empty($gender)) {
            $updateFields[] = "gender = ?";
            $params[] = $gender;
        }
        
        if (!empty($dob)) {
            $updateFields[] = "dob = ?";
            $params[] = $dob;
        }
        
        if (!empty($updateFields)) {
            $params[] = $_SESSION['user_id'];
            $sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Refresh user data
            header('Location: settings.php?success=profile');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Quest Planner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        @font-face {
            font-family: 'kongtext';
            src: url('../assets/fonts/kongtext/kongtext.ttf') format('truetype');
        }
        
        body {
            background-image: url('../assets/images/settings-bg.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            font-family: 'kongtext', monospace;
        }
        
        .gradient-orange {
            background: linear-gradient(to bottom, #FC8C1F, #FDB21C, #DDB21F);
        }
        
        .gradient-button {
            background: linear-gradient(90deg, #FFAA4B, #FF824E);
        }
        
        .gradient-red {
            background: linear-gradient(90deg, #FF6B6B, #FF4B4B);
        }
        
        /* Notification styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-family: 'kongtext', monospace;
            font-size: 14px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 1000;
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s, transform 0.3s;
            max-width: 350px;
        }
        
        .notification.success {
            background: linear-gradient(90deg, #4CAF50, #2E7D32);
            border: 3px solid #1B5E20;
        }
        
        .notification.error {
            background: linear-gradient(90deg, #F44336, #C62828);
            border: 3px solid #B71C1C;
        }
        
        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Password modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
        }
        
        .modal-content {
            background-color: #75341A;
            border: 5px solid #FF9926;
            border-radius: 13px;
            width: 90%;
            max-width: 500px;
            position: relative;
            margin: 10% auto;
            padding: 30px;
        }
        
        .modal-title {
            background: linear-gradient(90deg, #FFAA4B, #FF824E);
            border: 5px solid #8A4B22;
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            margin: -50px auto 20px;
            width: 80%;
            color: white;
        }
        
        .modal input[type="password"] {
            font-family: Arial, sans-serif !important;
            font-size: 16px !important;
            color: #000000 !important;
            background-color: white !important;
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
                        }
                    }
                }
            }
        };
    </script>
</head>
<body class="min-h-screen flex flex-col justify-center items-center">
    <!-- Back Button -->
    <a href="../index.php" class="absolute top-5 left-12 md:left-20 w-16 h-14 gradient-button border-[5px] border-brown-dark rounded-md flex items-center justify-center cursor-pointer">
        <img src="../assets/images/arrow-left.png" alt="Back" class="w-6 h-6">
    </a>
    
    <!-- Settings Title -->
    <div class="absolute top-5 left-32 md:left-[200px] w-64 md:w-72 h-14 gradient-orange border-[8px] border-brown-dark rounded-lg flex items-center justify-center">
        <h1 class="text-white text-sm uppercase">SETTINGS</h1>
    </div>
    
    <div class="container mx-auto pt-20 px-4">
        <!-- Account Settings Title -->
        <div class="gradient-button border-[6px] border-brown-dark w-80 md:w-96 h-16 mx-auto -mb-7 rounded-xl z-10 relative flex items-center justify-center">
            <h1 class="text-white text-lg md:text-xl">ACCOUNT SETTINGS</h1>
        </div>

        <!-- Main Box -->
        <div class="bg-brown w-full max-w-xl md:max-w-2xl mx-auto border-[5px] border-orange rounded-xl pt-10 pb-6 px-6">
            <?php if (isset($_GET['success']) && $_GET['success'] === 'password'): ?>
                <div class="bg-green-500 text-white p-4 mb-4 rounded">
                    Password updated successfully!
                </div>
            <?php elseif (isset($_GET['success']) && $_GET['success'] === 'profile'): ?>
                <div class="bg-green-500 text-white p-4 mb-4 rounded">
                    Profile updated successfully!
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-500 text-white p-4 mb-4 rounded">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="settings.php" id="profileForm">
                <!-- First Row -->
                <div class="flex flex-col md:flex-row gap-4 mb-5">
                    <div class="flex-1">
                        <label class="block text-white text-xs uppercase mb-2">FULL NAME</label>
                        <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" 
                               class="w-full h-12 px-4 bg-white border-[4px] border-orange rounded-lg font-[kongtext] text-sm text-black">
                    </div>
                    <div class="flex-1">
                        <label class="block text-white text-xs uppercase mb-2">GENDER</label>
                        <select name="gender" 
                                class="w-full h-12 px-4 bg-white border-[4px] border-orange rounded-lg font-[kongtext] text-sm text-black appearance-none bg-no-repeat bg-right-4"
                                style="background-image: url('data:image/svg+xml;utf8,<svg fill=&quot;black&quot; height=&quot;24&quot; viewBox=&quot;0 0 24 24&quot; width=&quot;24&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;><path d=&quot;M7 10l5 5 5-5z&quot;/></svg>'); background-position: right 10px center;">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <!-- Second Row -->
                <div class="flex flex-col md:flex-row gap-4 mb-5">
                    <div class="flex-1">
                        <label class="block text-white text-xs uppercase mb-2">USERNAME</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
                               class="w-full h-12 px-4 bg-white border-[4px] border-orange rounded-lg font-[kongtext] text-sm text-black">
                    </div>
                    <div class="flex-1">
                        <label class="block text-white text-xs uppercase mb-2">DATE OF BIRTH</label>
                        <input type="text" name="dob"
                               class="w-full h-12 px-4 bg-white border-[4px] border-orange rounded-lg font-[kongtext] text-sm text-black">
                    </div>
                </div>

                <!-- Email Row -->
                <div class="mb-6">
                    <label class="block text-white text-xs uppercase mb-2">EMAIL ADDRESS</label>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                               class="flex-1 h-12 px-4 bg-white border-[4px] border-orange rounded-lg font-[kongtext] text-sm text-black">
                        <button type="button" id="changePasswordBtn"
                                class="bg-[#F8BD00] border-[4px] border-brown-dark rounded-lg text-white px-4 py-2 font-[kongtext] text-xs font-bold whitespace-nowrap">
                            CHANGE PASSWORD
                        </button>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex justify-center gap-5 mt-6">
                    <button type="button" onclick="location.href='../auth/logout.php'" 
                            class="gradient-red border-[4px] border-brown-dark rounded-xl w-40 h-12 text-white font-[kongtext] text-sm uppercase">
                        Log out
                    </button>
                    <button type="submit" 
                            class="gradient-button border-[4px] border-brown-dark rounded-xl w-40 h-12 text-white font-[kongtext] text-sm uppercase">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Password Change Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <div class="modal-title">
                <h2 class="text-white text-lg">CHANGE PASSWORD</h2>
            </div>
            
            <?php if (isset($passwordError) || isset($_SESSION['password_error'])): ?>
                <div class="bg-red-500 text-white p-3 mb-4 rounded text-sm font-bold">
                    <?php 
                        echo htmlspecialchars($passwordError ?? $_SESSION['password_error']); 
                        // Clear the session error after displaying
                        if(isset($_SESSION['password_error'])) unset($_SESSION['password_error']);
                    ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="settings.php" id="passwordForm">
                <input type="hidden" name="action" value="change_password">
                
                <div class="mb-4">
                    <label class="block text-white text-xs uppercase mb-2">CURRENT PASSWORD</label>
                    <input type="password" name="current_password" 
                           class="w-full h-12 px-4 bg-white border-[4px] border-orange rounded-lg text-sm text-black password-input">
                </div>
                
                <div class="mb-4">
                    <label class="block text-white text-xs uppercase mb-2">NEW PASSWORD</label>
                    <input type="password" name="new_password" 
                           class="w-full h-12 px-4 bg-white border-[4px] border-orange rounded-lg text-sm text-black password-input">
                </div>
                
                <div class="mb-6">
                    <label class="block text-white text-xs uppercase mb-2">CONFIRM PASSWORD</label>
                    <input type="password" name="confirm_password" 
                           class="w-full h-12 px-4 bg-white border-[4px] border-orange rounded-lg text-sm text-black password-input">
                </div>
                
                <div class="flex justify-between gap-4">
                    <button type="button" id="cancelPasswordBtn" 
                            class="gradient-red border-[4px] border-brown-dark rounded-xl px-4 py-2 text-white font-[kongtext] text-sm uppercase">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="gradient-button border-[4px] border-brown-dark rounded-xl px-4 py-2 text-white font-[kongtext] text-sm uppercase">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Notification Elements -->
    <div id="successNotification" class="notification success">
        Password updated successfully!
    </div>
    <div id="profileNotification" class="notification success">
        Profile updated successfully!
    </div>
    <div id="errorNotification" class="notification error">
        An error occurred. Please try again.
    </div>
    
    <script>
        // Get the modal
        const modal = document.getElementById("passwordModal");
        
        // Get the button that opens the modal
        const btn = document.getElementById("changePasswordBtn");
        
        // Get the cancel button
        const cancelBtn = document.getElementById("cancelPasswordBtn");
        
        // When the user clicks the button, open the modal 
        btn.onclick = function() {
            modal.style.display = "block";
        }
        
        // When the user clicks on cancel, close the modal
        cancelBtn.onclick = function() {
            modal.style.display = "none";
        }
        
        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
        
        // Password validation and highlighting
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.querySelector('input[name="new_password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');
            
            if (newPassword && confirmPassword) {
                // Live validation as user types
                confirmPassword.addEventListener('input', function() {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.style.borderColor = '#FF0000';
                        newPassword.style.borderColor = '#FF0000';
                    } else {
                        confirmPassword.style.borderColor = '#FF9926';
                        newPassword.style.borderColor = '#FF9926';
                    }
                });
                
                newPassword.addEventListener('input', function() {
                    if (confirmPassword.value && newPassword.value !== confirmPassword.value) {
                        confirmPassword.style.borderColor = '#FF0000';
                        newPassword.style.borderColor = '#FF0000';
                    } else if (confirmPassword.value) {
                        confirmPassword.style.borderColor = '#FF9926';
                        newPassword.style.borderColor = '#FF9926';
                    }
                });
                
                // Form submission validation
                document.getElementById('passwordForm').addEventListener('submit', function(e) {
                    if (newPassword.value !== confirmPassword.value) {
                        e.preventDefault();
                        confirmPassword.style.borderColor = '#FF0000';
                        newPassword.style.borderColor = '#FF0000';
                        
                        // Create or update error message
                        let errorDiv = document.querySelector('.modal-content .bg-red-500');
                        if (!errorDiv) {
                            errorDiv = document.createElement('div');
                            errorDiv.className = 'bg-red-500 text-white p-3 mb-4 rounded text-sm font-bold';
                            const formElement = document.getElementById('passwordForm');
                            formElement.parentNode.insertBefore(errorDiv, formElement);
                        }
                        errorDiv.textContent = 'New passwords do not match! Please ensure both passwords are identical.';
                    }
                });
            }
        });
        
        // Notification functions
        function showNotification(element, duration = 3000) {
            element.classList.add('show');
            setTimeout(() => {
                element.classList.remove('show');
            }, duration);
        }
        
        // Check for URL parameters to show notifications
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.get('success') === 'password') {
                showNotification(document.getElementById('successNotification'));
            } else if (urlParams.get('success') === 'profile') {
                showNotification(document.getElementById('profileNotification'));
            }
        });
        
        // Form submission with notification
        const profileForm = document.querySelector('form:not(#passwordForm)');
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                localStorage.setItem('showProfileNotification', 'true');
            });
        }
        
        const passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                localStorage.setItem('showPasswordNotification', 'true');
            });
        }
        
        // Check for notifications from previous submissions
        if (localStorage.getItem('showPasswordNotification') === 'true') {
            showNotification(document.getElementById('successNotification'));
            localStorage.removeItem('showPasswordNotification');
        }
        
        if (localStorage.getItem('showProfileNotification') === 'true') {
            showNotification(document.getElementById('profileNotification'));
            localStorage.removeItem('showProfileNotification');
        }
    </script>
</body>
</html> 