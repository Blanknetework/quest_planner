<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Please log in first");
}

require_once 'config/database.php';

$userId = $_SESSION['user_id'];
$success = false;
$message = "";

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form values
        $skinColor = $_POST['skin_color'] ?? 'base1';
        $hairstyle = $_POST['hairstyle'] ?? 'hair';
        $eyeShape = $_POST['eye_shape'] ?? 'eye';
        $mouthShape = $_POST['mouth_shape'] ?? 'lip';
        $gender = $_POST['gender'] ?? 'Male';
        $boyStyle = ($gender === 'Male') ? $skinColor : null;
        
        // Simple direct update to users table only
        $query = "UPDATE users SET 
            skin_color = ?, 
            hairstyle = ?, 
            eye_shape = ?, 
            mouth_shape = ?,
            gender = ?,
            boy_style = ?
            WHERE id = ?";
            
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            $skinColor,
            $hairstyle,
            $eyeShape,
            $mouthShape,
            $gender,
            $boyStyle,
            $userId
        ]);
        
        if ($result) {
            $success = true;
            $message = "Avatar updated successfully!";
            
            // Update session notifications
            if (!isset($_SESSION['notifications'])) {
                $_SESSION['notifications'] = [];
            }
            
            $notification = [
                'title' => 'Avatar Updated',
                'message' => 'Your avatar has been successfully updated!',
                'id' => uniqid(),
                'time' => time()
            ];
            
            array_unshift($_SESSION['notifications'], $notification);
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = "Failed to update avatar. Database didn't return success.";
        }
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
    }
}

// Display current avatar
$skin = ($user['gender'] === 'Male') ? ($user['boy_style'] ?? 'boy') : ($user['skin_color'] ?? 'base1');
$hairstyle = $user['hairstyle'] ?? 'hair';
$eye_shape = $user['eye_shape'] ?? 'eye';
$mouth_shape = $user['mouth_shape'] ?? 'lip';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Direct Avatar Fix</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .avatar-preview {
            width: 200px;
            height: 200px;
            position: relative;
            margin: 0 auto 20px;
        }
        .avatar-preview img {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
        }
        form {
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            font-weight: bold;
        }
        select {
            width: 100%;
            padding: 8px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            cursor: pointer;
        }
        .debug {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .links {
            margin-top: 20px;
        }
        .links a {
            display: inline-block;
            margin-right: 15px;
            padding: 8px 15px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1>Direct Avatar Fix</h1>
    
    <?php if (!empty($message)): ?>
        <div class="message <?php echo $success ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <h2>Current Avatar</h2>
    <div class="avatar-preview">
        <img src="assets/images/character/<?php echo htmlspecialchars($skin); ?>.png" style="z-index:1;" alt="Skin">
        <img src="assets/images/character/<?php echo htmlspecialchars($eye_shape); ?>.png" style="z-index:2;" alt="Eyes">
        <img src="assets/images/character/<?php echo htmlspecialchars($mouth_shape); ?>.png" style="z-index:3;" alt="Mouth">
        <img src="assets/images/character/<?php echo htmlspecialchars($hairstyle); ?>.png" style="z-index:4;" alt="Hair">
    </div>
    
    <form method="post">
        <div class="form-group">
            <label for="skin_color">Skin Color:</label>
            <select name="skin_color" id="skin_color">
                <option value="base1" <?php echo ($user['skin_color'] == 'base1') ? 'selected' : ''; ?>>Base 1</option>
                <option value="base2" <?php echo ($user['skin_color'] == 'base2') ? 'selected' : ''; ?>>Base 2</option>
                <option value="base3" <?php echo ($user['skin_color'] == 'base3') ? 'selected' : ''; ?>>Base 3</option>
                <option value="boy" <?php echo ($user['skin_color'] == 'boy') ? 'selected' : ''; ?>>Boy</option>
                <option value="boy2" <?php echo ($user['skin_color'] == 'boy2') ? 'selected' : ''; ?>>Boy 2</option>
                <option value="boy3" <?php echo ($user['skin_color'] == 'boy3') ? 'selected' : ''; ?>>Boy 3</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="gender">Gender:</label>
            <select name="gender" id="gender">
                <option value="Male" <?php echo ($user['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo ($user['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="hairstyle">Hairstyle:</label>
            <select name="hairstyle" id="hairstyle">
                <option value="hair" <?php echo ($user['hairstyle'] == 'hair') ? 'selected' : ''; ?>>Style 1</option>
                <option value="hair2" <?php echo ($user['hairstyle'] == 'hair2') ? 'selected' : ''; ?>>Style 2</option>
                <option value="hair3" <?php echo ($user['hairstyle'] == 'hair3') ? 'selected' : ''; ?>>Style 3</option>
                <option value="hair4" <?php echo ($user['hairstyle'] == 'hair4') ? 'selected' : ''; ?>>Style 4</option>
                <option value="hair5" <?php echo ($user['hairstyle'] == 'hair5') ? 'selected' : ''; ?>>Style 5</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="eye_shape">Eye Shape:</label>
            <select name="eye_shape" id="eye_shape">
                <option value="eye" <?php echo ($user['eye_shape'] == 'eye') ? 'selected' : ''; ?>>Style 1</option>
                <option value="eye2" <?php echo ($user['eye_shape'] == 'eye2') ? 'selected' : ''; ?>>Style 2</option>
                <option value="eye3" <?php echo ($user['eye_shape'] == 'eye3') ? 'selected' : ''; ?>>Style 3</option>
                <option value="eye4" <?php echo ($user['eye_shape'] == 'eye4') ? 'selected' : ''; ?>>Style 4</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="mouth_shape">Mouth Shape:</label>
            <select name="mouth_shape" id="mouth_shape">
                <option value="lip" <?php echo ($user['mouth_shape'] == 'lip') ? 'selected' : ''; ?>>Style 1</option>
                <option value="lip2" <?php echo ($user['mouth_shape'] == 'lip2') ? 'selected' : ''; ?>>Style 2</option>
                <option value="lip3" <?php echo ($user['mouth_shape'] == 'lip3') ? 'selected' : ''; ?>>Style 3</option>
                <option value="lip4" <?php echo ($user['mouth_shape'] == 'lip4') ? 'selected' : ''; ?>>Style 4</option>
            </select>
        </div>
        
        <button type="submit">Update Avatar</button>
    </form>
    
    <div class="debug">
        <h3>Debug Information</h3>
        <p>User ID: <?php echo $userId; ?></p>
        <p>Current skin_color: <?php echo htmlspecialchars($user['skin_color'] ?? 'Not set'); ?></p>
        <p>Current hairstyle: <?php echo htmlspecialchars($user['hairstyle'] ?? 'Not set'); ?></p>
        <p>Current eye_shape: <?php echo htmlspecialchars($user['eye_shape'] ?? 'Not set'); ?></p>
        <p>Current mouth_shape: <?php echo htmlspecialchars($user['mouth_shape'] ?? 'Not set'); ?></p>
        <p>Current gender: <?php echo htmlspecialchars($user['gender'] ?? 'Not set'); ?></p>
        <p>Current boy_style: <?php echo htmlspecialchars($user['boy_style'] ?? 'Not set'); ?></p>
    </div>
    
    <div class="links">
        <a href="pages/inventory.php">Go to Inventory</a>
        <a href="index.php">Go to Homepage</a>
    </div>
</body>
</html> 