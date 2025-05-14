<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Include database connection
require_once '../config/database.php';

// Get user data (fetch all avatar fields)
$stmt = $pdo->prepare("SELECT username, skin_color, hairstyle, eye_shape, mouth_shape, gender, boy_style FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$username = $user['username'] ?? 'Adventurer';

// Check if we have an inventory table, if not create one
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_inventory'");
    if ($stmt->rowCount() == 0) {
        // Create the inventory table if it doesn't exist
        $pdo->exec("CREATE TABLE user_inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            item_id INT NOT NULL,
            item_name VARCHAR(100) NOT NULL,
            item_type VARCHAR(50) NOT NULL,
            item_image VARCHAR(255) NOT NULL,
            equipped BOOLEAN DEFAULT 0,
            acquired_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        // No default items - inventory starts empty
    }
    
    // Get user inventory items
    $stmt = $pdo->prepare("SELECT * FROM user_inventory WHERE user_id = ? ORDER BY item_type, equipped DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $inventoryItems = $stmt->fetchAll();
    
    // Organize items by type
    $itemsByType = [];
    foreach ($inventoryItems as $item) {
        $itemsByType[$item['item_type']][] = $item;
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Check if we have user avatar settings, if not create defaults
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_avatar'");
    if ($stmt->rowCount() == 0) {
        // Create the avatar settings table if it doesn't exist
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
        
        // Insert default avatar settings for the current user
        $stmt = $pdo->prepare("INSERT INTO user_avatar (user_id) VALUES (?)");
        $stmt->execute([$_SESSION['user_id']]);
    }
    
    // Get user avatar settings
    $stmt = $pdo->prepare("SELECT * FROM user_avatar WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $avatarSettings = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Get user titles
$titles = [];
$equippedTitle = "New Adventurer"; // Default title
try {
    // Check if titles table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'titles'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT * FROM titles WHERE user_id = ? ORDER BY earned_date DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $titles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get equipped title
        $stmt = $pdo->prepare("SELECT title FROM titles WHERE user_id = ? AND is_equipped = 1 LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $titleData = $stmt->fetch();
        
        if ($titleData) {
            $equippedTitle = $titleData['title'];
        }
    }
} catch (PDOException $e) {
    // Title table might not exist yet, will be created when needed
}

// Process title equip action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'equip_title') {
    $titleId = $_POST['title_id'];
    
    try {
        if ($titleId === 'default') {
            // If default title is selected, unequip all titles
            $stmt = $pdo->prepare("UPDATE titles SET is_equipped = 0 WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $equippedTitle = "New Adventurer";
        } else {
            // First, unequip all titles
            $stmt = $pdo->prepare("UPDATE titles SET is_equipped = 0 WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            // Then equip the selected title
            $stmt = $pdo->prepare("UPDATE titles SET is_equipped = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([(int)$titleId, $_SESSION['user_id']]);
            
            // Get the newly equipped title
            $stmt = $pdo->prepare("SELECT title FROM titles WHERE id = ? AND user_id = ?");
            $stmt->execute([(int)$titleId, $_SESSION['user_id']]);
            $titleData = $stmt->fetch();
            
            if ($titleData) {
                $equippedTitle = $titleData['title'];
            }
        }
        
        // If this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'title' => $equippedTitle]);
            exit;
        }
        
    } catch (PDOException $e) {
        // Handle error
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Database error']);
            exit;
        }
    }
}

// Process avatar save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_avatar') {
    try {
        // Debug output to check what's being received
        error_log("Avatar save data: " . print_r($_POST, true));
        
        // Get the values directly from POST
        $skinColor = $_POST['skin_color'] ?? 'base1';
        $eyeColor = $_POST['eye_color'] ?? '#4A4A4A';
        $hairColor = $_POST['hair_color'] ?? '#4A4A4A';
        $hairstyle = $_POST['hairstyle'] ?? 'hair';
        $eyeShape = $_POST['eye_shape'] ?? 'eye';
        $mouthShape = $_POST['mouth_shape'] ?? 'lip';
        $browShape = $_POST['brow_shape'] ?? 'brow';
        $gender = $_POST['gender'] ?? 'Male';
        
        // Set boy_style based on gender
        $boyStyle = ($gender === 'Male') ? $skinColor : null;
        
        // Direct update to users table only - simplify the process
        $stmt = $pdo->prepare("UPDATE users SET 
            skin_color = ?, 
            hairstyle = ?, 
            eye_shape = ?, 
            mouth_shape = ?,
            gender = ?,
            boy_style = ?
            WHERE id = ?");
            
        $result = $stmt->execute([
            $skinColor,
            $hairstyle,
            $eyeShape,
            $mouthShape,
            $gender,
            $boyStyle,
            $_SESSION['user_id']
        ]);
        
        if (!$result) {
            throw new PDOException("Database update failed");
        }
        
        // Add notification
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
        
        // Redirect to prevent form resubmission
        header('Location: inventory.php?saved=1');
        exit;
    } catch (PDOException $e) {
        $error = "Error saving avatar: " . $e->getMessage();
        error_log($error);
        
        // Show error notification
        if (!isset($_SESSION['notifications'])) {
            $_SESSION['notifications'] = [];
        }
        
        $notification = [
            'title' => 'Error',
            'message' => 'There was a problem saving your avatar: ' . $e->getMessage(),
            'id' => uniqid(),
            'time' => time()
        ];
        
        array_unshift($_SESSION['notifications'], $notification);
        
        // Redirect with error flag
        header('Location: inventory.php?error=1');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Quest Planner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        @font-face {
            font-family: 'KongText';
            src: url('../assets/fonts/kongtext/kongtext.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        
        body {
            font-family: 'KongText', monospace;
            background-image: url('../assets/images/shop-bg.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: white;
        }
        
        .title-option.selected {
            background-color: #FF9926;
            color: #75341A;
        }
        
        .equip-btn:hover {
            opacity: 0.9;
        }
        
        .inventory-container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 600px 700px;
            gap: 30px;
        }
        
        .avatar-panel, .title-panel {
            background-color: #75341A;
            border: 6px solid #FF9926;
            border-radius: 10px;
            position: relative;
        }
        
        .items-panel {
            background-color: #4D2422;
            border: 6px solid #FF9926;
            border-radius: 10px;
            position: relative;
        }
        
        .panel-title {
            position: absolute;
            top: -45px;
            left: 30%;
            transform: translateX(-50%);
            background: linear-gradient(90deg, #FFAA4B, #FF824E);
            color: white;
            padding: 5px 15px;
            border: 3px solid #8A4B22;
            border-radius: 5px;
            font-size: 14px;
            white-space: nowrap;
            margin-top: 10px;
            z-index: 5;
        }
        
        /* Special styling for the inventory title */
        .inventory-title {
            font-size: 24px;
            left: 50%;
            padding: 8px 20px;
        }
        
        .color-swatch {
            width: 20px;
            height: 20px;
            border: 2px solid white;
            display: inline-block;
        }
        
        .avatar-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
        }
        
        .avatar-preview {
            background-color: #5C2F22;
            width: 230px;
            height: 230px;
            border-radius: 5px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .avatar-options {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 340px;
        }
        
        .bottom-options {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        
        .bottom-option {
            display: flex;
            align-items: center;
        }
    </style>
</head>

<body class="min-h-screen p-0 flex flex-col items-center justify-center">
    <!-- Back Button -->
    <a href="../index.php" class="fixed top-5 left-12 md:left-20 w-16 h-14 border-[5px] border-[#8A4B22] rounded-md flex items-center justify-center cursor-pointer z-10" style="background: linear-gradient(90deg, #FFAA4B, #FF824E);">
        <img src="../assets/images/arrow-left.png" alt="Back" class="w-6 h-6">
    </a>
    
    <!-- Main Container with Relative Positioning -->
    <div class="relative w-full">
        <!-- Inventory Title with Absolute Positioning -->
        <h1 class="absolute text-white text-[48px] uppercase tracking-[12px] font-bold top-[20px] right-[250px]">INVENTORY</h1>
        
        <div class="inventory-container mt-[120px]">
            <!-- Left Column -->
            <div class="flex flex-col gap-8">
                <!-- EDIT AVATAR SECTION -->
                <div class="avatar-panel p-5" style="width: 590px; height: auto; margin-bottom: 35px; background-color: #75341A;">
                    <div class="panel-title">EDIT AVATAR</div>
                    
                    <form id="avatarForm" method="POST" action="inventory.php">
                        <input type="hidden" name="action" value="save_avatar">
                        <input type="hidden" name="skin_color" id="skin_color" value="<?php echo $user['skin_color'] ?? 'base1'; ?>">
                        <input type="hidden" name="eye_color" id="eye_color" value="<?php echo $avatarSettings['eye_color'] ?? '#4A4A4A'; ?>">
                        <input type="hidden" name="hair_color" id="hair_color" value="<?php echo $avatarSettings['hair_color'] ?? '#4A4A4A'; ?>">
                        <input type="hidden" name="hairstyle" id="hairstyle" value="<?php echo $user['hairstyle'] ?? 'hair'; ?>">
                        <input type="hidden" name="eye_shape" id="eye_shape" value="<?php echo $user['eye_shape'] ?? 'eye'; ?>">
                        <input type="hidden" name="mouth_shape" id="mouth_shape" value="<?php echo $user['mouth_shape'] ?? 'lip'; ?>">
                        <input type="hidden" name="brow_shape" id="brow_shape" value="<?php echo $avatarSettings['brow_shape'] ?? 'brow'; ?>">
                        <input type="hidden" name="gender" id="gender" value="<?php echo $user['gender'] ?? 'Male'; ?>">
                        
                        <div class="flex">
                            <!-- Avatar Preview on Left -->
                            <div class="avatar-preview" style="position: relative; width: 200px; height: 200px;">
                                <?php
                                // Determine which skin to use
                                $skin = ($user['gender'] === 'Male') ? ($user['boy_style'] ?? 'boy') : ($user['skin_color'] ?? 'base1');
                                $hairstyle = $user['hairstyle'] ?? 'hair';
                                $eye_shape = $user['eye_shape'] ?? 'eye';
                                $mouth_shape = $user['mouth_shape'] ?? 'lip';
                                ?>
                                <img id="skin-img" src="../assets/images/character/<?php echo htmlspecialchars($skin); ?>.png" style="position:absolute;left:0;top:0;width:100%;height:100%;z-index:1;" alt="Skin">
                                <img id="eyes-img" src="../assets/images/character/<?php echo htmlspecialchars($eye_shape); ?>.png" style="position:absolute;left:0;top:0;width:100%;height:100%;z-index:2;" alt="Eyes">
                                <img id="mouth-img" src="../assets/images/character/<?php echo htmlspecialchars($mouth_shape); ?>.png" style="position:absolute;left:0;top:0;width:100%;height:100%;z-index:3;" alt="Mouth">
                                <img id="hair-img" src="../assets/images/character/<?php echo htmlspecialchars($hairstyle); ?>.png" style="position:absolute;left:0;top:0;width:100%;height:100%;z-index:4;" alt="Hair">
                            </div>
                            
                            <!-- Avatar Options on Right -->
                            <div class="avatar-options">
                                <div class="avatar-option">
                                    <span class="cursor-pointer text-xl text-white mr-2" data-control="prev" data-target="skin_color">&lt;</span>
                                    <span class="text-white text-sm uppercase flex-1 text-center">SKIN COLOR</span>
                                    <div class="color-swatch bg-[#F9D9BB] mx-2" id="skin-color-swatch"></div>
                                    <span class="cursor-pointer text-xl text-white ml-2" data-control="next" data-target="skin_color">&gt;</span>
                                </div>
                                
                                <div class="avatar-option">
                                    <span class="cursor-pointer text-xl text-white mr-2" data-control="prev" data-target="gender">&lt;</span>
                                    <span class="text-white text-sm uppercase flex-1 text-center">GENDER</span>
                                    <span class="text-white text-sm uppercase mx-2" id="gender-display"><?php echo $user['gender'] ?? 'Male'; ?></span>
                                    <span class="cursor-pointer text-xl text-white ml-2" data-control="next" data-target="gender">&gt;</span>
                                </div>
                                
                                <div class="avatar-option">
                                    <span class="cursor-pointer text-xl text-white mr-2" data-control="prev" data-target="eye_color">&lt;</span>
                                    <span class="text-white text-sm uppercase flex-1 text-center">EYE COLOR</span>
                                    <div class="color-swatch bg-blue-600 mx-2" id="eye-color-swatch"></div>
                                    <span class="cursor-pointer text-xl text-white ml-2" data-control="next" data-target="eye_color">&gt;</span>
                                </div>
                                
                                <div class="avatar-option">
                                    <span class="cursor-pointer text-xl text-white mr-2" data-control="prev" data-target="hair_color">&lt;</span>
                                    <span class="text-white text-sm uppercase flex-1 text-center">HAIR COLOR</span>
                                    <div class="color-swatch bg-purple-800 mx-2" id="hair-color-swatch"></div>
                                    <span class="cursor-pointer text-xl text-white ml-2" data-control="next" data-target="hair_color">&gt;</span>
                                </div>
                                
                                <div class="avatar-option">
                                    <span class="cursor-pointer text-xl text-white mr-2" data-control="prev" data-target="hairstyle">&lt;</span>
                                    <span class="text-white text-sm uppercase flex-1 text-center">HAIRSTYLE</span>
                                    <span class="cursor-pointer text-xl text-white ml-2" data-control="next" data-target="hairstyle">&gt;</span>
                                </div>
                                
                                <div class="avatar-option">
                                    <span class="cursor-pointer text-xl text-white mr-2" data-control="prev" data-target="eye_shape">&lt;</span>
                                    <span class="text-white text-sm uppercase flex-1 text-center">EYE SHAPE</span>
                                    <span class="cursor-pointer text-xl text-white ml-2" data-control="next" data-target="eye_shape">&gt;</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex mt-3 mx-auto" style="width: 505px;">
                            <div class="w-1/2 flex justify-between items-center pr-4">
                                <span class="cursor-pointer text-xl text-white" data-control="prev" data-target="mouth_shape">&lt;</span>
                                <span class="text-white text-sm uppercase">MOUTH SHAPE</span>
                                <span class="cursor-pointer text-xl text-white" data-control="next" data-target="mouth_shape">&gt;</span>
                            </div>
                            
                            <div class="w-1/2 flex justify-between items-center pl-4">
                                <span class="cursor-pointer text-xl text-white" data-control="prev" data-target="brow_shape">&lt;</span>
                                <span class="text-white text-sm uppercase">BROW SHAPE</span>
                                <span class="cursor-pointer text-xl text-white" data-control="next" data-target="brow_shape">&gt;</span>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- EQUIP TITLE SECTION -->
                <div class="title-panel p-5" style="width: 590px; height: auto; background-color: #75341A;">
                    <div class="panel-title">EQUIP TITLE</div>
                    
                    <div class="flex flex-col items-center py-4 px-6">
                        <!-- Default title always available -->
                        <div class="title-option bg-orange-500 border-4 border-orange-400 text-white text-xs uppercase py-3 text-center cursor-pointer rounded-md flex items-center justify-center <?php echo $equippedTitle === 'New Adventurer' ? 'selected' : ''; ?> w-[280px] mx-auto mb-4" data-title-id="default">
                            New Adventurer
                        </div>
                        
                        <!-- Earned titles -->
                        <?php if (!empty($titles)): ?>
                            <?php foreach($titles as $title): ?>
                                <div class="title-option bg-orange-500 border-4 border-orange-400 text-white text-xs uppercase py-3 text-center cursor-pointer rounded-md flex items-center justify-center <?php echo $title['is_equipped'] ? 'selected' : ''; ?> w-[280px] mx-auto mb-4" data-title-id="<?php echo $title['id']; ?>">
                                    <?php echo htmlspecialchars($title['title']); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <p class="text-orange-300 text-xs text-center mt-2">Complete quests and maintain streaks to unlock more titles!</p>
                    </div>
                </div>
            </div>
            
            <!-- Right Side - Items Grid -->
            <div class="items-panel p-4" style="width: 700px; background-color: #4D2422;">
                <?php if (empty($inventoryItems)): ?>
                <!-- No Items Message -->
                <div class="flex flex-col items-center justify-center h-[400px]">
                    <div class="flex flex-col items-center" style="width: 80%;">
                        <img src="../assets/images/box.png" alt="Empty Box" class="w-32 h-32 mb-4">
                        <div class="text-white text-xl uppercase text-center font-bold mb-2">NO ITEMS YET</div>
                        <div class="text-orange-300 text-sm text-center">Your inventory is empty. Visit the shop to purchase items for your avatar!</div>
                        <a href="guild.php?shop" class="mt-6 px-6 py-2 bg-[#FF7F00] text-white border-2 border-[#5C2F22] rounded-md uppercase hover:bg-[#FF9926] transition-colors">GO TO SHOP</a>
                    </div>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-3 gap-4 mt-2">
                    <?php foreach ($inventoryItems as $item): ?>
                    <div class="bg-[#75341A] border-4 border-[#FF9926] rounded-lg p-3 flex flex-col items-center">
                        <div class="text-white text-xs uppercase text-center mb-2"><?php echo htmlspecialchars($item['item_name']); ?></div>
                        <img src="<?php echo htmlspecialchars($item['item_image']); ?>" class="w-16 h-16 object-contain my-2" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                        <button class="equip-btn bg-[<?php echo $item['equipped'] ? '#75341A' : '#FF7F00'; ?>] text-white border-2 border-[#5C2F22] rounded-md py-1 px-3 text-xs uppercase w-20 text-center mt-2" data-item-id="<?php echo $item['id']; ?>">
                            <?php echo $item['equipped'] ? 'EQUIPPED' : 'EQUIP'; ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Save Button -->
        <div class="w-full flex justify-center mt-8 mb-8">
            <button type="button" id="saveButton" class="bg-[#FF7F00] text-white border-4 border-[#5C2F22] rounded-full py-2 px-16 text-base cursor-pointer">SAVE</button>
        </div>
    </div>
    
    <!-- Notification Modal -->
    <div id="notificationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-[#5C2E1B] border-7 border-[#FF9926] rounded-xl p-6 max-w-md mx-auto">
            <h3 class="text-xl text-white font-bold mb-4">Success!</h3>
            <p class="text-[#FC8C1F] mb-6" id="notificationMessage">Your changes have been saved successfully!</p>
            <div class="flex justify-center">
                <button id="closeNotification" class="bg-[#FFAA4B] border-4 border-[#4D2422] text-white py-2 px-6 rounded-lg">OK</button>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show notification modal function
            function showNotification(title, message) {
                document.querySelector('#notificationModal h3').textContent = title;
                document.getElementById('notificationMessage').textContent = message;
                document.getElementById('notificationModal').classList.remove('hidden');
                console.log(`Notification shown: ${title} - ${message}`);
            }
            
            // Check for URL parameters immediately
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('saved') === '1') {
                showNotification('Success!', 'Your avatar has been successfully updated!');
                console.log("Showing success notification from URL parameter");
            } else if (urlParams.get('error') === '1') {
                showNotification('Error', 'There was a problem saving your avatar. Please try again.');
                console.log("Showing error notification from URL parameter");
            }
            
            // Avatar customization options
            const avatarOptions = {
                skin_color: ['base1', 'base2', 'base3', 'boy', 'boy2', 'boy3'],
                gender: ['Male', 'Female'],
                eye_color: ['#4A4A4A', '#3B5998', '#8B4513', '#006400', '#4B0082'],
                hair_color: ['#4A4A4A', '#8B4513', '#FFD700', '#FF6347', '#800080', '#FF0000'],
                hairstyle: ['hair', 'hair2', 'hair3', 'hair4', 'hair5'],
                eye_shape: ['eye', 'eye2', 'eye3', 'eye4'],
                mouth_shape: ['lip', 'lip2', 'lip3', 'lip4'],
                brow_shape: ['brow', 'brow2', 'brow3']
            };
            
            // Store initial values to check for changes
            const initialValues = {};
            for (const key in avatarOptions) {
                initialValues[key] = document.getElementById(key).value;
            }
            
            // Current indices for each option
            const currentIndex = {
                skin_color: 0,
                gender: 0,
                eye_color: 0,
                hair_color: 0,
                hairstyle: 0,
                eye_shape: 0,
                mouth_shape: 0,
                brow_shape: 0
            };
            
            // Initialize current indices based on form values
            for (const [key, options] of Object.entries(avatarOptions)) {
                const currentValue = document.getElementById(key).value;
                const index = options.indexOf(currentValue);
                if (index !== -1) {
                    currentIndex[key] = index;
                }
            }
            
            // Handle arrow clicks for avatar customization
            document.querySelectorAll('[data-control]').forEach(control => {
                control.addEventListener('click', function() {
                    const direction = this.getAttribute('data-control'); // prev or next
                    const target = this.getAttribute('data-target'); // which attribute to change
                    
                    if (!avatarOptions[target]) return;
                    
                    // Update index based on direction
                    if (direction === 'prev') {
                        currentIndex[target] = (currentIndex[target] - 1 + avatarOptions[target].length) % avatarOptions[target].length;
                    } else {
                        currentIndex[target] = (currentIndex[target] + 1) % avatarOptions[target].length;
                    }
                    
                    // Get the new value
                    const newValue = avatarOptions[target][currentIndex[target]];
                    
                    // Update hidden input
                    document.getElementById(target).value = newValue;
                    
                    // Update display based on target
                    updateAvatarDisplay(target, newValue);
                });
            });
            
            // Function to update avatar display
            function updateAvatarDisplay(target, value) {
                switch(target) {
                    case 'skin_color':
                        // For skin color, we need to consider gender
                        const gender = document.getElementById('gender').value;
                        // Use the appropriate skin image based on gender
                        const skinImg = gender === 'Male' ? value : value;
                        document.getElementById('skin-img').src = `../assets/images/character/${skinImg}.png`;
                        document.getElementById('skin-color-swatch').style.backgroundColor = getSkinColor(value);
                        console.log(`Skin updated: ${value}, gender: ${gender}, image: ${skinImg}`);
                        break;
                    case 'gender':
                        document.getElementById('gender-display').textContent = value;
                        // Update skin based on gender
                        const skinValue = document.getElementById('skin_color').value;
                        updateAvatarDisplay('skin_color', skinValue);
                        console.log(`Gender updated: ${value}, updating skin with: ${skinValue}`);
                        break;
                    case 'eye_color':
                        document.getElementById('eye-color-swatch').style.backgroundColor = value;
                        break;
                    case 'hair_color':
                        document.getElementById('hair-color-swatch').style.backgroundColor = value;
                        break;
                    case 'hairstyle':
                        document.getElementById('hair-img').src = `../assets/images/character/${value}.png`;
                        break;
                    case 'eye_shape':
                        document.getElementById('eyes-img').src = `../assets/images/character/${value}.png`;
                        break;
                    case 'mouth_shape':
                        document.getElementById('mouth-img').src = `../assets/images/character/${value}.png`;
                        break;
                    case 'brow_shape':
                        // If there's a brow image element, update it
                        const browImg = document.getElementById('brow-img');
                        if (browImg) {
                            browImg.src = `../assets/images/character/${value}.png`;
                        }
                        break;
                }
            }
            
            // Helper function to convert skin code to color
            function getSkinColor(skinCode) {
                const skinColors = {
                    'base1': '#F9D9BB',
                    'base2': '#E8C298',
                    'base3': '#C58C5C',
                    'boy': '#F9D9BB',
                    'boy2': '#E8C298',
                    'boy3': '#C58C5C'
                };
                return skinColors[skinCode] || '#F9D9BB';
            }
            
            // Handle save button
            document.getElementById('saveButton').addEventListener('click', function() {
                // Always submit the form when save is clicked
                console.log("Save button clicked, submitting form with values:");
                
                // Log all form values for debugging
                const formValues = {};
                for (const key in avatarOptions) {
                    formValues[key] = document.getElementById(key).value;
                    console.log(`${key}: ${formValues[key]}`);
                }
                
                // Add a notification before submitting
                showNotification('Saving...', 'Saving your avatar changes...');
                
                // Submit the avatar form after a short delay to ensure notification is shown
                setTimeout(function() {
                    document.getElementById('avatarForm').submit();
                }, 500);
            });
            
            // Handle equip buttons
            document.querySelectorAll('.equip-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-item-id');
                    
                    // Reset all buttons to default state
                    document.querySelectorAll('.equip-btn').forEach(btn => {
                        btn.style.backgroundColor = '#FF7F00';
                        btn.textContent = 'EQUIP';
                    });
                    
                    // Set this button to "equipped" state
                    this.style.backgroundColor = '#75341A';
                    this.textContent = 'EQUIPPED';
                });
            });
            
            // Handle title selection
            document.querySelectorAll('.title-option').forEach(title => {
                title.addEventListener('click', function() {
                    const titleId = this.getAttribute('data-title-id');
                    
                    // Remove selected class from all titles
                    document.querySelectorAll('.title-option').forEach(t => {
                        t.classList.remove('selected');
                    });
                    
                    // Add selected class to this title
                    this.classList.add('selected');
                    
                    if (titleId === 'default') {
                        // For default title, update the database to unequip all titles
                        fetch('inventory.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: 'action=equip_title&title_id=default'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                console.log('Default title selected');
                                showNotification('Title Updated', 'Default title "New Adventurer" has been equipped.');
                            } else {
                                console.error('Error selecting default title:', data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                        });
                    } else {
                        // For earned titles, update the database
                        fetch('inventory.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: 'action=equip_title&title_id=' + encodeURIComponent(titleId)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                console.log('Title equipped successfully:', data.title);
                                showNotification('Title Updated', `Title "${data.title}" has been equipped.`);
                            } else {
                                console.error('Error equipping title:', data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                        });
                    }
                });
            });
            
            // Debug function to display avatar data
            function debugAvatarData() {
                console.log("Current Avatar Data:");
                console.log("-------------------");
                const avatarFields = ['skin_color', 'eye_color', 'hair_color', 'hairstyle', 'eye_shape', 'mouth_shape', 'brow_shape', 'gender'];
                avatarFields.forEach(field => {
                    const element = document.getElementById(field);
                    if (element) {
                        console.log(`${field}: ${element.value}`);
                    } else {
                        console.log(`${field}: Element not found`);
                    }
                });
                console.log("-------------------");
            }
            
            // Debug on page load
            debugAvatarData();
            
            // Handle notification close button
            document.getElementById('closeNotification').addEventListener('click', function() {
                document.getElementById('notificationModal').classList.add('hidden');
                
                // Clean up the URL
                const url = new URL(window.location);
                url.searchParams.delete('saved');
                url.searchParams.delete('error');
                window.history.replaceState({}, '', url);
            });
            
            // Handle customization options
            document.querySelectorAll('.cursor-pointer').forEach(arrow => {
                arrow.addEventListener('click', function() {
                    // Animation feedback for clicks
                    this.style.transform = 'scale(1.2)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 100);
                });
            });
        });
    </script>
</body>
</html> 
