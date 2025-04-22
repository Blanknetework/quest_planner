<?php
session_start();

// Initialize notifications array if it doesn't exist
if (!isset($_SESSION['notifications'])) {
    $_SESSION['notifications'] = [];
}

// Return all notifications
echo json_encode($_SESSION['notifications']); 