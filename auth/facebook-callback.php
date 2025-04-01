<?php
ob_start();
require_once '../vendor/autoload.php';
require_once '../config/database.php';

use Facebook\Facebook;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

try {
    $fb = new Facebook([
        'app_id' => $_ENV['FACEBOOK_APP_ID'],
        'app_secret' => $_ENV['FACEBOOK_APP_SECRET'],
        'default_graph_version' => 'v18.0',
    ]);

    $helper = $fb->getRedirectLoginHelper();
    
    try {
        $accessToken = $helper->getAccessToken();
    } catch (FacebookSDKException $e) {
        error_log('Failed to get access token: ' . $e->getMessage());
        header('Location: login.php?error=facebook_token_error');
        exit;
    }

    if (!$accessToken) {
        header('Location: login.php?error=facebook_no_token');
        exit;
    }

    // Get user info
    try {
        $response = $fb->get('/me?fields=id,name,email', $accessToken);
        $user = $response->getGraphUser();
        
        $facebook_id = $user->getId();
        $name = $user->getName();
        $email = $user->getEmail(); // This might be null

        // Check if we got an email
        if (empty($email)) {
            // Check if user already exists with this Facebook ID
            $stmt = $pdo->prepare("SELECT * FROM users WHERE facebook_id = ?");
            $stmt->execute([$facebook_id]);
            $existing_user = $stmt->fetch();

            if ($existing_user) {
                // User exists, log them in
                $_SESSION['user_id'] = $existing_user['id'];
                $_SESSION['username'] = $existing_user['username'];
                header('Location: ../index.php');
                exit;
            } else {
                // No email and no existing account - redirect to email collection form
                $_SESSION['facebook_data'] = [
                    'facebook_id' => $facebook_id,
                    'name' => $name
                ];
                header('Location: facebook-email.php');
                exit;
            }
        }

        // Continue with normal flow if email exists
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR facebook_id = ?");
        $stmt->execute([$email, $facebook_id]);
        $existing_user = $stmt->fetch();

        if (!$existing_user) {
            // Create new user
            $random_password = bin2hex(random_bytes(8));
            $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, facebook_id, email_verified) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$name, $email, $hashed_password, $facebook_id]);
            
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
                            <p style='color: #2F1810; line-height: 1.5;'>Your account has been successfully created using your Facebook account.</p>
                            
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
            $user_id = $existing_user['id'];
            $name = $existing_user['username'];
        }

        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $name;
        header('Location: ../index.php');
        exit;

    } catch (FacebookResponseException $e) {
        error_log('Graph returned an error: ' . $e->getMessage());
        header('Location: login.php?error=facebook_graph_error');
        exit;
    }

} catch (Exception $e) {
    error_log('General error: ' . $e->getMessage());
    header('Location: login.php?error=facebook_general_error');
    exit;
}