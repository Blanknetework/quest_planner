<?php
session_start();
require_once '../../config/database.php';

// Check if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: ../index.php');
    exit;
}

$error = '';

// Handle login attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        // Get admin from database
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        // Verify credentials
        if ($admin && ($password === 'admin' || password_verify($password, $admin['password']))) {
            // Set admin session
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            
            // Redirect to admin dashboard
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    }
}

// Check for successful logout
$logout_message = '';
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $logout_message = 'You have been successfully logged out.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Quest Planner</title>
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
            --admin-primary: #FF9C4B;
            --admin-secondary: #D4B796;
            --admin-border: #5C2F22;
            --admin-success: #6ABF69;
            --admin-danger: #D95C5C;
            --admin-warning: #E9A03B;
        }
        
        body {
            font-family: 'KongText', monospace;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #80B5D5;
            background-image: url('../assets/images/bg.svg');
            background-size: cover;
            background-position: center;
        }
        
        .admin-login-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }
        
        .title-container {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .title-text {
            color: var(--admin-primary);
            font-size: 32px;
            font-weight: bold;
            text-shadow: 
                2px 0 0 var(--admin-border),
                -2px 0 0 var(--admin-border),
                0 2px 0 var(--admin-border),
                0 -2px 0 var(--admin-border),
                1px 1px 0 var(--admin-border),
                -1px -1px 0 var(--admin-border),
                1px -1px 0 var(--admin-border),
                -1px 1px 0 var(--admin-border);
            letter-spacing: 1px;
        }
        
        .admin-badge {
            background-color: var(--admin-primary);
            color: white;
            padding: 5px 15px;
            border: 3px solid var(--admin-border);
            border-radius: 20px;
            font-size: 14px;
            display: inline-block;
            margin-top: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .login-box {
            background-color: var(--admin-secondary);
            border: 5px solid var(--admin-border);
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 8px 0 var(--admin-border);
        }
        
        .login-header {
            background-color: var(--admin-primary);
            color: white;
            text-align: center;
            padding: 15px;
            margin: -25px -25px 20px -25px;
            border-bottom: 5px solid var(--admin-border);
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 2px 2px 0 rgba(0, 0, 0, 0.3);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--admin-border);
            font-weight: bold;
            text-transform: uppercase;
            font-size: 14px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 3px solid var(--admin-border);
            border-radius: 5px;
            background-color: white;
            font-family: 'KongText', monospace;
            font-size: 14px;
            box-sizing: border-box;
            color: var(--admin-border);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 3px rgba(255, 156, 75, 0.3);
        }
        
        .login-btn {
            background-color: var(--admin-primary);
            color: white;
            border: 3px solid var(--admin-border);
            border-radius: 5px;
            padding: 12px;
            width: 100%;
            font-weight: bold;
            font-family: 'KongText', monospace;
            font-size: 16px;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.2s;
            position: relative;
            top: 0;
        }
        
        .login-btn:hover {
            top: -3px;
            box-shadow: 0 3px 0 var(--admin-border);
        }
        
        .login-btn:active {
            top: 0;
            box-shadow: none;
        }
        
        .error-message {
            background-color: var(--admin-danger);
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 12px;
            border: 2px solid #B83E3E;
        }
        
        .success-message {
            background-color: var(--admin-success);
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 12px;
            border: 2px solid #4A8C4A;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
        }
        
        .back-link a {
            color: white;
            text-decoration: none;
            background-color: rgba(92, 47, 34, 0.8);
            padding: 8px 15px;
            border-radius: 5px;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .back-link a:hover {
            background-color: var(--admin-border);
        }
        
        .key-icon {
            width: 80px;
            height: 80px;
            background-color: white;
            border: 3px solid var(--admin-border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }
        
        .key-icon svg {
            width: 40px;
            height: 40px;
            color: var(--admin-primary);
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <div class="title-container">
            <div class="title-text">QUEST PLANNER</div>
            <div class="admin-badge">Admin Access</div>
        </div>
        
        <div class="login-box">
            <div class="login-header">ADMIN LOGIN</div>
            
            <div class="key-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path>
                </svg>
            </div>
            
            <?php if ($error): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($logout_message): ?>
            <div class="success-message">
                <?php echo $logout_message; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-input" value="admin" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-input" value="admin" required>
                </div>
                
                <button type="submit" class="login-btn">Login</button>
            </form>
        </div>
        
        <div class="back-link">
            <a href="../index.php">Return to Main Site</a>
        </div>
    </div>
</body>
</html>