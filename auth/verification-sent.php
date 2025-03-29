<?php
session_start();
if (!isset($_SESSION['registration_success'])) {
    header("Location: register.php");
    exit();
}
unset($_SESSION['registration_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Sent - Quest Planner</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold text-center text-gray-800 mb-4">Email Verification Sent</h2>
            <div class="text-center mb-6">
                <p class="text-gray-600 mb-4">
                    We've sent a verification link to your email address.
                    Please check your inbox and click the link to verify your account.
                </p>
                <p class="text-sm text-gray-500">
                    If you don't see the email, check your spam folder.
                </p>
            </div>
            <div class="text-center">
                <a href="login.php" class="inline-block bg-orange-500 text-white px-6 py-2 rounded hover:bg-orange-600">
                    Return to Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>