<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Please log in first");
}

require_once 'config/database.php';

echo "<h1>Avatar Debug Tool</h1>";

// 1. Check current user avatar data
$stmt = $pdo->prepare("SELECT id, username, skin_color, hairstyle, eye_shape, mouth_shape, gender, boy_style FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Current User Data:</h2>";
echo "<pre>";
print_r($user);
echo "</pre>";

// 2. Check if user_avatar table exists and has data
$stmt = $pdo->query("SHOW TABLES LIKE 'user_avatar'");
$userAvatarTableExists = $stmt->rowCount() > 0;

echo "<h2>user_avatar table exists: " . ($userAvatarTableExists ? "Yes" : "No") . "</h2>";

if ($userAvatarTableExists) {
    $stmt = $pdo->prepare("SELECT * FROM user_avatar WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $avatarData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>Current user_avatar Data:</h2>";
    echo "<pre>";
    print_r($avatarData);
    echo "</pre>";
    
    if (!$avatarData) {
        echo "<p>No avatar data found for this user. Creating default entry...</p>";
        
        // Create default entry
        $stmt = $pdo->prepare("INSERT INTO user_avatar (user_id, skin_color, hairstyle, eye_shape, mouth_shape, gender) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $user['skin_color'] ?? 'base1',
            $user['hairstyle'] ?? 'hair',
            $user['eye_shape'] ?? 'eye',
            $user['mouth_shape'] ?? 'lip',
            $user['gender'] ?? 'Male'
        ]);
        
        echo "<p>Default entry created!</p>";
    }
} else {
    echo "<p>Creating user_avatar table...</p>";
    
    // Create the table
    $pdo->exec("CREATE TABLE user_avatar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        skin_color VARCHAR(50) DEFAULT 'base1',
        eye_color VARCHAR(50) DEFAULT '#4A4A4A',
        hair_color VARCHAR(50) DEFAULT '#4A4A4A',
        hairstyle VARCHAR(50) DEFAULT 'hair',
        eye_shape VARCHAR(50) DEFAULT 'eye',
        mouth_shape VARCHAR(50) DEFAULT 'lip',
        brow_shape VARCHAR(50) DEFAULT 'brow',
        gender VARCHAR(20) DEFAULT 'Male',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Insert default data
    $stmt = $pdo->prepare("INSERT INTO user_avatar (user_id, skin_color, hairstyle, eye_shape, mouth_shape, gender) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        $user['skin_color'] ?? 'base1',
        $user['hairstyle'] ?? 'hair',
        $user['eye_shape'] ?? 'eye',
        $user['mouth_shape'] ?? 'lip',
        $user['gender'] ?? 'Male'
    ]);
    
    echo "<p>Table created and default data inserted!</p>";
}

// 3. Test updating avatar
if (isset($_POST['update'])) {
    try {
        // Update users table
        $stmt = $pdo->prepare("UPDATE users SET 
            skin_color = ?,
            hairstyle = ?,
            eye_shape = ?,
            mouth_shape = ?,
            gender = ?,
            boy_style = ?
            WHERE id = ?");
            
        $gender = $_POST['gender'];
        $skinColor = $_POST['skin_color'];
        $boyStyle = ($gender === 'Male') ? $skinColor : null;
        
        $stmt->execute([
            $skinColor,
            $_POST['hairstyle'],
            $_POST['eye_shape'],
            $_POST['mouth_shape'],
            $gender,
            $boyStyle,
            $_SESSION['user_id']
        ]);
        
        // Update user_avatar table if it exists
        if ($userAvatarTableExists) {
            $stmt = $pdo->prepare("UPDATE user_avatar SET 
                skin_color = ?,
                eye_color = ?,
                hair_color = ?,
                hairstyle = ?,
                eye_shape = ?,
                mouth_shape = ?,
                brow_shape = ?,
                gender = ?
                WHERE user_id = ?");
                
            $stmt->execute([
                $skinColor,
                $_POST['eye_color'],
                $_POST['hair_color'],
                $_POST['hairstyle'],
                $_POST['eye_shape'],
                $_POST['mouth_shape'],
                $_POST['brow_shape'],
                $gender,
                $_SESSION['user_id']
            ]);
        }
        
        echo "<p style='color: green;'>Avatar updated successfully!</p>";
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT id, username, skin_color, hairstyle, eye_shape, mouth_shape, gender, boy_style FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h2>Updated User Data:</h2>";
        echo "<pre>";
        print_r($user);
        echo "</pre>";
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
}

// 4. Test form
?>

<h2>Test Avatar Update</h2>
<form method="post">
    <input type="hidden" name="update" value="1">
    
    <div>
        <label>Skin Color:</label>
        <select name="skin_color">
            <option value="base1" <?php echo ($user['skin_color'] == 'base1') ? 'selected' : ''; ?>>Base 1</option>
            <option value="base2" <?php echo ($user['skin_color'] == 'base2') ? 'selected' : ''; ?>>Base 2</option>
            <option value="base3" <?php echo ($user['skin_color'] == 'base3') ? 'selected' : ''; ?>>Base 3</option>
            <option value="boy" <?php echo ($user['skin_color'] == 'boy') ? 'selected' : ''; ?>>Boy</option>
            <option value="boy2" <?php echo ($user['skin_color'] == 'boy2') ? 'selected' : ''; ?>>Boy 2</option>
            <option value="boy3" <?php echo ($user['skin_color'] == 'boy3') ? 'selected' : ''; ?>>Boy 3</option>
        </select>
    </div>
    
    <div>
        <label>Gender:</label>
        <select name="gender">
            <option value="Male" <?php echo ($user['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
            <option value="Female" <?php echo ($user['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
        </select>
    </div>
    
    <div>
        <label>Eye Color:</label>
        <select name="eye_color">
            <option value="#4A4A4A">Dark Gray</option>
            <option value="#3B5998">Blue</option>
            <option value="#8B4513">Brown</option>
            <option value="#006400">Green</option>
            <option value="#4B0082">Purple</option>
        </select>
    </div>
    
    <div>
        <label>Hair Color:</label>
        <select name="hair_color">
            <option value="#4A4A4A">Black</option>
            <option value="#8B4513">Brown</option>
            <option value="#FFD700">Blonde</option>
            <option value="#FF6347">Red</option>
            <option value="#800080">Purple</option>
            <option value="#FF0000">Bright Red</option>
        </select>
    </div>
    
    <div>
        <label>Hairstyle:</label>
        <select name="hairstyle">
            <option value="hair" <?php echo ($user['hairstyle'] == 'hair') ? 'selected' : ''; ?>>Style 1</option>
            <option value="hair2" <?php echo ($user['hairstyle'] == 'hair2') ? 'selected' : ''; ?>>Style 2</option>
            <option value="hair3" <?php echo ($user['hairstyle'] == 'hair3') ? 'selected' : ''; ?>>Style 3</option>
            <option value="hair4" <?php echo ($user['hairstyle'] == 'hair4') ? 'selected' : ''; ?>>Style 4</option>
            <option value="hair5" <?php echo ($user['hairstyle'] == 'hair5') ? 'selected' : ''; ?>>Style 5</option>
        </select>
    </div>
    
    <div>
        <label>Eye Shape:</label>
        <select name="eye_shape">
            <option value="eye" <?php echo ($user['eye_shape'] == 'eye') ? 'selected' : ''; ?>>Style 1</option>
            <option value="eye2" <?php echo ($user['eye_shape'] == 'eye2') ? 'selected' : ''; ?>>Style 2</option>
            <option value="eye3" <?php echo ($user['eye_shape'] == 'eye3') ? 'selected' : ''; ?>>Style 3</option>
            <option value="eye4" <?php echo ($user['eye_shape'] == 'eye4') ? 'selected' : ''; ?>>Style 4</option>
        </select>
    </div>
    
    <div>
        <label>Mouth Shape:</label>
        <select name="mouth_shape">
            <option value="lip" <?php echo ($user['mouth_shape'] == 'lip') ? 'selected' : ''; ?>>Style 1</option>
            <option value="lip2" <?php echo ($user['mouth_shape'] == 'lip2') ? 'selected' : ''; ?>>Style 2</option>
            <option value="lip3" <?php echo ($user['mouth_shape'] == 'lip3') ? 'selected' : ''; ?>>Style 3</option>
            <option value="lip4" <?php echo ($user['mouth_shape'] == 'lip4') ? 'selected' : ''; ?>>Style 4</option>
        </select>
    </div>
    
    <div>
        <label>Brow Shape:</label>
        <select name="brow_shape">
            <option value="brow">Style 1</option>
            <option value="brow2">Style 2</option>
            <option value="brow3">Style 3</option>
        </select>
    </div>
    
    <button type="submit">Update Avatar</button>
</form>

<h2>Return to Inventory</h2>
<a href="pages/inventory.php">Go back to inventory page</a> 