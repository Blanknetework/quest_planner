<?php
session_start();
require_once '../config/database.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Update user as verified
        $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        $_SESSION['verification_success'] = true;
        header("Location: login.php");
        exit();
    } else {
        $_SESSION['verification_error'] = "Invalid verification token";
        header("Location: login.php");
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Email Sent - Quest Planner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="game-container">
        <div class="container mx-auto px-4 py-4">
            <div class="auth-form">
                <div class="auth-header">Email Verification</div>
                <p class="text-center mb-4">
                    A verification email has been sent to your email address.
                    Please check your inbox and click the verification link to complete your registration.
                </p>
                <a href="login.php" class="auth-button">Return to Login</a>
            </div>
        </div>
    </div>
</body>
</html>