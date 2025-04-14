<?php
session_start();

// Redirect if no Facebook data
if (!isset($_SESSION['facebook_data'])) {
    header('Location: login.php');
    exit;
}

$facebook_data = $_SESSION['facebook_data'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/database.php';
    
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else if (empty($_POST['username'])) {
        $error = "Please choose a username.";
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "
                    <div class='flex items-center justify-center gap-2'>
                        <svg class='w-6 h-6' fill='currentColor' viewBox='0 0 20 20'>
                            <path fill-rule='evenodd' d='M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z' clip-rule='evenodd'/>
                        </svg>
                        <span>This email is already registered!</span>
                    </div>
                    <div class='mt-2 text-sm'>
                        Please try:
                        <ul class='mt-1 list-disc list-inside'>
                            <li>Using a different email address</li>
                            <li><a href='login.php' class='underline hover:text-orange-700'>Login with this email instead</a></li>
                        </ul>
                    </div>";
            } else {
                // Check if username already exists
                $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = "
                        <div class='flex items-center justify-center gap-2'>
                            <svg class='w-6 h-6' fill='currentColor' viewBox='0 0 20 20'>
                                <path fill-rule='evenodd' d='M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z' clip-rule='evenodd'/>
                            </svg>
                            <span>This username is already taken!</span>
                        </div>
                        <div class='mt-2 text-sm'>
                            Please try a different username.
                        </div>";
                } else {
                    // Create new user
                    $random_password = bin2hex(random_bytes(8));
                    $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, facebook_id, email_verified, level) VALUES (?, ?, ?, ?, 1, 1)");
                    $stmt->execute([$username, $email, $hashed_password, $facebook_data['facebook_id']]);
                    
                    $user_id = $pdo->lastInsertId();
                    
                    // Set session and cleanup
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['level'] = 1;
                    unset($_SESSION['facebook_data']);
                    
                    header('Location: ../index.php');
                    exit;
                }
            }
        } catch (PDOException $e) {
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
    <title>Complete Registration - Quest Planner</title>
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
            box-shadow: 0 4px 0 var(--border-brown);
        }

        .auth-header {
            background-color: var(--button-orange);
            color: #FFFFFF;
            font-weight: bold;
            text-align: center;
            padding: 10px 0;
            margin: -20px -20px 20px -20px;
            border-bottom: 4px solid var(--border-brown);
            text-shadow: 2px 2px 0 rgba(0, 0, 0, 0.5);
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-family: var(--pixel-font);
        }

        .auth-input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 3px solid var(--border-brown);
            border-radius: 0;
            background-color: #FFF;
            font-family: var(--pixel-font);
            font-size: 12px;
            color: var(--dark-text);
            transition: all 0.3s ease;
        }

        .auth-input:focus {
            outline: none;
            border-color: var(--button-orange);
            box-shadow: 0 0 0 2px rgba(255, 130, 78, 0.3);
        }

        .auth-button {
            background-color: var(--button-orange);
            color: white;
            border: 3px solid var(--border-brown);
            border-radius: 0;
            padding: 12px 20px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            font-family: var(--pixel-font);
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: all 0.3s ease;
            position: relative;
            top: 0;
        }

        .auth-button:hover {
            background-color: #ff6b3d;
            top: -2px;
            box-shadow: 0 4px 0 var(--border-brown);
        }

        .auth-button:active {
            top: 2px;
            box-shadow: 0 0 0 var(--border-brown);
        }

        .message-box {
            font-family: var(--pixel-font);
            font-size: 12px;
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            border: 3px solid var(--border-brown);
            background-color: #FFF;
            color: var(--dark-text);
        }

        .error-message {
            background-color: #FFE5E5;
            border: 3px solid #FF4444;
            color: #CC0000;
            margin: 20px 0;
            padding: 15px;
            font-family: var(--pixel-font);
            font-size: 12px;
            text-align: left;
            border-radius: 0;
            box-shadow: 0 2px 0 rgba(204, 0, 0, 0.2);
        }

        .error-message svg {
            display: inline-block;
        }

        .error-message a {
            color: var(--button-orange);
            text-decoration: underline;
            transition: all 0.2s ease;
        }

        .error-message a:hover {
            color: #ff6b3d;
        }

        .error-message ul {
            margin-left: 10px;
        }

        .error-message li {
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .error-message li::before {
            content: '→';
            color: #CC0000;
        }

        .facebook-icon {
            width: 24px;
            height: 24px;
            margin-right: 8px;
            vertical-align: middle;
            display: inline-block;
        }

        .welcome-text {
            font-family: var(--pixel-font);
            font-size: 14px;
            color: var(--dark-text);
            text-align: center;
            margin-bottom: 20px;
            line-height: 1.5;
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
                <div class="auth-header">COMPLETE YOUR QUEST</div>
                
                <div class="welcome-text">
                    Welcome, <?php echo htmlspecialchars($facebook_data['name']); ?>!
                    <br>One last step to begin your adventure...
                </div>

                <?php if (isset($error)): ?>
                    <div class="error-message">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="message-box">
                    <svg class="facebook-icon" viewBox="0 0 24 24">
                        <path fill="#4267B2" d="M12 2.04C6.5 2.04 2 6.53 2 12.06C2 17.06 5.66 21.21 10.44 21.96V14.96H7.9V12.06H10.44V9.85C10.44 7.34 11.93 5.96 14.22 5.96C15.31 5.96 16.45 6.15 16.45 6.15V8.62H15.19C13.95 8.62 13.56 9.39 13.56 10.18V12.06H16.34L15.89 14.96H13.56V21.96A10 10 0 0 0 22 12.06C22 6.53 17.5 2.04 12 2.04Z"/>
                    </svg>
                    Please choose a username and provide your email address to complete your registration
                </div>
                
                <form method="POST" action="facebook-email.php">
                    <input 
                        type="text" 
                        name="username" 
                        class="auth-input" 
                        placeholder="CHOOSE A USERNAME" 
                        required
                        autocomplete="username"
                    >
                    <input 
                        type="email" 
                        name="email" 
                        class="auth-input" 
                        placeholder="YOUR EMAIL ADDRESS" 
                        required
                        autocomplete="email"
                    >
                    <button type="submit" class="auth-button">
                        BEGIN ADVENTURE
                        <span class="ml-2">→</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Add focus effect to inputs
        const inputs = document.querySelectorAll('.auth-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.style.transform = 'scale(1.02)';
            });

            input.addEventListener('blur', function() {
                this.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>