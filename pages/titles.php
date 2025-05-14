<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../landing.php');
    exit;
}

require_once '../config/database.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Equip a title
    if ($action === 'equip_title' && isset($_POST['title_id'])) {
        $titleId = (int)$_POST['title_id'];
        
        try {
            // First, unequip all titles
            $stmt = $pdo->prepare("UPDATE titles SET is_equipped = 0 WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            // Then equip the selected title
            $stmt = $pdo->prepare("UPDATE titles SET is_equipped = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$titleId, $_SESSION['user_id']]);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
        } catch (PDOException $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Database error']);
                exit;
            }
        }
    }
    
    // Redirect after processing to prevent form resubmission
    if (!$isAjax) {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get user's titles
$titles = [];
try {
    // Check if titles table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'titles'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT * FROM titles WHERE user_id = ? ORDER BY earned_date DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $titles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Handle error silently
}

// Get user data
$stmt = $pdo->prepare("SELECT username, level, xp FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Default values if no data found
$username = $user['username'] ?? 'Adventurer';
$level = $user['level'] ?? 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Titles - Quest Planner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<style>
    .game-container {
        background-image: url('../../assets/images/dashboard.jpg');
        background-size: cover;
        background-attachment: fixed;
        min-height: 100vh;
        overflow-x: hidden;
        padding: 20px;
    }

    .title-banner {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        margin-bottom: 20px;    
        padding: 10px;
    }

    .title-image {
        max-width: 250px;
        height: auto;
        align-items: center;
        justify-content: center;
        margin-top: 10px;
    }

    .back-button {
        background-color: #FFAA4B;
        border: 5px solid #4D2422;
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: bold;
        cursor: pointer;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        margin-bottom: 20px;
    }

    .back-button img {
        width: 20px;
        height: 20px;
        margin-right: 8px;
    }

    .titles-container {
        background-color: #75341A;
        border: 7px solid #FF9926;
        border-radius: 13px;
        padding: 20px;
        margin-bottom: 30px;
    }

    .titles-header {
        background: linear-gradient(90deg, #FFAA4B, #FF824E);
        border: 7px solid #8A4B22;
        border-radius: 8px;
        text-align: center;
        padding: 10px 0;
        color: white;
        font-weight: bold;
        text-transform: uppercase;
        font-size: 24px;
        margin: -40px auto 20px;
        width: 80%;
    }

    .title-item {
        background-color: #8B4513;
        border: 3px solid #FF9926;
        border-radius: 5px;
        padding: 15px;
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .title-name {
        color: white;
        font-weight: bold;
        font-size: 18px;
    }

    .title-equipped {
        background-color: #4BFF4B;
        color: #4D2422;
        padding: 5px 10px;
        border-radius: 5px;
        font-weight: bold;
        font-size: 12px;
    }

    .equip-button {
        background-color: #FFAA4B;
        border: 3px solid #4D2422;
        color: white;
        padding: 5px 15px;
        border-radius: 5px;
        font-weight: bold;
        cursor: pointer;
    }
</style>

<div class="game-container">
    <div class="container mx-auto px-4 py-4">
        <!-- Title Banner -->
        <div class="title-banner">
            <img src="../../assets/images/Quest-Planner.png" alt="QUEST PLANNER" class="title-image">
        </div>
        
        <!-- Back Button -->
        <button class="back-button" onclick="location.href='../index.php'">
            <img src="../../assets/images/arrow-left.png" alt="Back">
            BACK TO QUESTS
        </button>
        
        <!-- Titles Section -->
        <div class="titles-container">
            <div class="titles-header">YOUR TITLES</div>
            
            <?php if (empty($titles)): ?>
                <div class="text-center text-white py-10">You haven't earned any titles yet. Complete quests and maintain streaks to earn titles!</div>
            <?php else: ?>
                <?php foreach ($titles as $title): ?>
                    <div class="title-item" data-id="<?php echo $title['id']; ?>">
                        <div class="title-name">"<?php echo htmlspecialchars($title['title']); ?>"</div>
                        <div>
                            <?php if ($title['is_equipped']): ?>
                                <span class="title-equipped">EQUIPPED</span>
                            <?php else: ?>
                                <button class="equip-button" data-id="<?php echo $title['id']; ?>">EQUIP</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Handle equip button clicks
    document.querySelectorAll('.equip-button').forEach(button => {
        button.addEventListener('click', function() {
            const titleId = this.getAttribute('data-id');
            
            // Send AJAX request to equip title
            fetch('titles.php', {
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
                    // Update UI to show equipped title
                    document.querySelectorAll('.title-equipped').forEach(span => {
                        // Replace with equip button
                        const parentDiv = span.parentNode;
                        const titleItem = parentDiv.parentNode;
                        const itemId = titleItem.getAttribute('data-id');
                        
                        parentDiv.innerHTML = '<button class="equip-button" data-id="' + itemId + '">EQUIP</button>';
                        
                        // Add event listener to new button
                        parentDiv.querySelector('.equip-button').addEventListener('click', function() {
                            const titleId = this.getAttribute('data-id');
                            // Same AJAX call as above
                            // This is a simplified version - in production, you'd want to extract this to a function
                            fetch('titles.php', {
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
                                    window.location.reload(); // Simplified: just reload the page
                                }
                            });
                        });
                    });
                    
                    // Update current button's parent to show equipped
                    const parentDiv = this.parentNode;
                    parentDiv.innerHTML = '<span class="title-equipped">EQUIPPED</span>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    });
</script>

</body>
</html> 