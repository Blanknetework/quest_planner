<?php
require_once '../vendor/autoload.php';
require_once '../config/database.php';

use Google\Client as GoogleClient;
use Google\Service\Oauth2;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

session_start();

// Load environment variables
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Debug environment variables
error_log('Google Client ID: ' . $_ENV['GOOGLE_CLIENT_ID']);
error_log('Google Redirect URI: ' . $_ENV['GOOGLE_REDIRECT_URI']);

try {
    $client = new GoogleClient();
    $client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
    $client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
    $client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);

    if (isset($_GET['code'])) {
        error_log("Auth code received: " . $_GET['code']);
        
        try {
            $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
            error_log("Token received: " . print_r($token, true));
            $client->setAccessToken($token);
        } catch (Exception $e) {
            error_log("Token error: " . $e->getMessage());
            throw $e;
        }

        // Get user info
        $google_oauth = new Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();

        $email = $google_account_info->email;
        $name = $google_account_info->name;
        $google_id = $google_account_info->id;

        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR google_id = ?");
        $stmt->execute([$email, $google_id]);
        $user = $stmt->fetch();

        if (!$user) {
            // Generate random password for Google users
            $random_password = bin2hex(random_bytes(8));
            $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, google_id, email_verified) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$name, $email, $hashed_password, $google_id]);
            
            $user_id = $pdo->lastInsertId();

            // Send welcome email
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
                $mail->addAddress($email, $name);
                
                $mail->isHTML(true);
                $mail->Subject = 'Welcome to Quest Planner!';
                $mail->Body = "
                    <div style='font-family: \"Courier New\", monospace; max-width: 600px; margin: 0 auto; background-color: #F5E6D3; border: 4px solid #2F1810; padding: 20px;'>
                        <div style='background-color: #FF824E; color: white; text-align: center; padding: 15px; border: 4px solid #2F1810; margin: -20px -20px 20px -20px;'>
                            <h1 style='margin: 0; font-size: 24px; letter-spacing: 2px;'>QUEST PLANNER</h1>
                        </div>
                        
                        <div style='background-color: white; border: 4px solid #2F1810; padding: 20px; margin-bottom: 20px;'>
                            <h2 style='color: #FF824E; font-size: 20px; margin-top: 0;'>Welcome, {$name}!</h2>
                            <p style='color: #2F1810; line-height: 1.5;'>Your account has been successfully created using your Google account.</p>
                            
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
                                    🎮 Start Your Quest 🎮
                                </a>
                            </div>
                        </div>
                    </div>
                ";

                $mail->send();
            } catch (Exception $e) {
                error_log("Failed to send welcome email: " . $e->getMessage());
            }
        } else {
            $user_id = $user['id'];
        }

        // Set session and redirect
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $name;
        header('Location: ../index.php');
        exit();
    }
} catch (Exception $e) {
    error_log("Google Auth Error: " . $e->getMessage());
    header('Location: login.php?error=google_auth_failed');
    exit();
}