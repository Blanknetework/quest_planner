<?php
require_once '../vendor/autoload.php';
require_once '../config/database.php';

use Google\Client as GoogleClient;
use Google\Service\Oauth2;
use Dotenv\Dotenv;

session_start();

// Load environment variables
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Debug environment variables
error_log('Google Client ID: ' . $_ENV['GOOGLE_CLIENT_ID']);
error_log('Google Redirect URI: ' . $_ENV['GOOGLE_REDIRECT_URI']);

// Initialize Google Client
$client = new GoogleClient();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);
$client->addScope('email');
$client->addScope('profile');

// Generate login URL
$auth_url = $client->createAuthUrl();
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));