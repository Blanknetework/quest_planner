<?php
require_once '../vendor/autoload.php';

use Facebook\Facebook;
use Dotenv\Dotenv;

session_start();

// Load environment variables
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Facebook SDK initialization
$fb = new Facebook([
    'app_id' => $_ENV['FACEBOOK_APP_ID'],
    'app_secret' => $_ENV['FACEBOOK_APP_SECRET'],
    'default_graph_version' => 'v18.0',
]);

$helper = $fb->getRedirectLoginHelper();

// Adding state parameter for security
$_SESSION['FBRLH_state'] = bin2hex(random_bytes(32));

$permissions = ['email', 'public_profile'];
$loginUrl = $helper->getLoginUrl(
    $_ENV['FACEBOOK_REDIRECT_URI'],
    $permissions
);

// Redirect to Facebook login page
header('Location: ' . filter_var($loginUrl, FILTER_SANITIZE_URL));
exit;