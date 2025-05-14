<?php
session_start();
require_once '../config/database.php';

// Enable error logging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/avatar_errors.log');
error_reporting(E_ALL);

$userId = $_SESSION['user_id'] ?? null;

error_log("Session started - User ID: " . ($userId ?? 'not set'));

if (!$userId) {
    error_log("No user ID found in session, redirecting to login");
    // Redirect to login if not logged in
    header('Location: login.php');
    exit;
}

// Check verification status
$stmt = $pdo->prepare("SELECT email_verified FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

error_log("User verification status: " . ($user['email_verified'] ?? 'not set'));

// Add verification status to session for JavaScript access
$_SESSION['is_verified'] = $user['email_verified'] ?? false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST request received at " . date('Y-m-d H:i:s'));
    error_log("POST data: " . print_r($_POST, true));
    
    try {
        // Get avatar customization data
        $gender = $_POST['gender'] ?? 'Female';
        error_log("Gender selected: " . $gender);
        
        // Set defaults based on gender
        if ($gender === 'Male') {
            $skinColor = $_POST['skin_color'] ?? 'boy';
            error_log("Male skin color: " . $skinColor);
        } else {
            $skinColor = $_POST['skin_color'] ?? 'base1';
            error_log("Female skin color: " . $skinColor);
        }
        
        $eyeColor = $_POST['eye_color'] ?? 'eye';
        $hairColor = $_POST['hair_color'] ?? 'hair';
        $hairstyle = $_POST['hairstyle'] ?? 'hair';
        $eyeShape = $_POST['eye_shape'] ?? 'eye';
        $mouthShape = $_POST['mouth_shape'] ?? 'lip';
        
        error_log("Avatar customization data: " . json_encode([
            'gender' => $gender,
            'skinColor' => $skinColor,
            'eyeColor' => $eyeColor,
            'hairColor' => $hairColor,
            'hairstyle' => $hairstyle,
            'eyeShape' => $eyeShape,
            'mouthShape' => $mouthShape
        ]));
        
        // Update user profile with avatar data
        $stmt = $pdo->prepare("UPDATE users SET 
            skin_color = ?, 
            hairstyle = ?,
            eye_shape = ?,
            mouth_shape = ?,
            boy_style = ?,
            gender = ?,
            avatar_created = 1
            WHERE id = ?");
        
        error_log("Executing database update for user ID: " . $userId);
        
        $result = $stmt->execute([
            $skinColor,
            $hairstyle,
            $eyeShape,
            $mouthShape,
            $gender === 'Male' ? $skinColor : 'boy', // Store male skin style in boy_style
            $gender,
            $userId
        ]);
        
        if ($result) {
            error_log("Database update successful");
            error_log("Redirecting to index.php");
            // Redirect to the main application
            header('Location: ../index.php');
            exit;
        } else {
            error_log("Database update failed - no rows affected");
            throw new PDOException("Failed to update user profile - no rows affected");
        }
        
    } catch (PDOException $e) {
        error_log("Database error occurred: " . $e->getMessage());
        error_log("Error code: " . $e->getCode());
        error_log("Error trace: " . $e->getTraceAsString());
        $error = "Failed to save avatar: " . $e->getMessage();
    } catch (Exception $e) {
        error_log("General error occurred: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        $error = "An unexpected error occurred: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Avatar - Quest Planner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Add notification function
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            } text-white font-bold transition-opacity duration-500`;
            notification.textContent = message;
            document.body.appendChild(notification);

            // Remove notification after 3 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 500);
            }, 3000);
        }

        // Check verification status on page load
        document.addEventListener('DOMContentLoaded', function() {
            const isVerified = <?php echo json_encode($_SESSION['is_verified'] ?? false); ?>;
            if (isVerified) {
                showNotification('Your account is verified! You can create your avatar.', 'success');
            } else {
                showNotification('Your account is not verified. Please verify your account first.', 'error');
            }
        });

        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'quest-brown': '#75341A',
                        'quest-orange': '#FF9926',
                        'quest-dark-brown': '#8A4B22',
                    },
                    fontFamily: {
                        'kongtext': ['KongText', 'monospace'],
                    },
                }
            }
        }
    </script>
    <style>
        @font-face {
            font-family: 'KongText';
            src: url('../assets/fonts/kongtext/kongtext.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        
        body {
            background-image: url('../assets/images/bggg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: 'KongText', monospace;
        }
        
        .avatar-layer {
            position: absolute;
            top: 10px;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            image-rendering: pixelated;
            object-fit: contain;
            max-width: 100%;
            max-height: 100%;
        }
        
        /* Special styling for male character layers */
        .male-avatar-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: contain !important;
            background-position: center !important;
            background-repeat: no-repeat;
            image-rendering: pixelated;
            object-fit: contain;
            max-width: 100%;
            max-height: 100%;
            transform: scale(1.0); /* Adjust if needed */
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
        
        .title-gradient {
            background: linear-gradient(90deg, #FFAA4B, #FF824E);
        }
        
        .ongoing-quest-header {
            background: linear-gradient(90deg, #FFAA4B, #FF824E);
            border: 7px solid #8A4B22;
            border-radius: 8px;
            text-align: center;
            padding: 10px;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        
        .particle {
            position: absolute;
            background-color: rgba(255, 255, 255, 0.6);
            border-radius: 50%;
            pointer-events: none;
            animation: float-up 15s linear infinite;
        }
        
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            pointer-events: none;
            z-index: 0;
        }
        
        @keyframes float-up {
            0% {
                transform: translateY(0) translateX(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) translateX(var(--tx));
                opacity: 0;
            }
        }
    </style>
</head>
<body class="min-h-screen overflow-hidden flex flex-col items-center justify-center m-0 p-0">
    <div class="particles" id="particles"></div>
    
    <div class="flex flex-col items-center justify-center w-full min-h-screen relative py-10">
        
        <!-- Title Box -->
        <div class="ongoing-quest-header w-[350px] md:w-[450px] mb-[-25px] z-10 relative">
            CREATE YOUR AVATAR
        </div>
        
        <!-- Avatar Container -->
        <div class="bg-quest-brown border-[5px] border-quest-orange rounded-[13px] p-5 pt-8 w-[700px] h-[400px] flex flex-row items-center justify-between z-0">
            <div class="flex flex-col items-center">


                
                <!-- Avatar Preview -->
                <div class="bg-quest-brown border-3 bottom-[45px] rounded-[13px] border-quest-orange w-[200px] h-[200px] relative overflow-hidden flex items-center justify-center" id="avatarPreview" style="border-width: 3px;">
                    <!-- Loading indicator -->
                    <div id="loadingIndicator" class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center text-white text-xs">Loading...</div>
                </div>
            </div>
            
            <!-- Customization Options -->
            <div class="flex-1 ml-5">
                <!-- Skin Color -->
                <div class="flex items-center justify-between mb-2.5 text-white py-0.5">
                    <div class="text-white text-sm cursor-pointer select-none w-[15px] text-center prev-option" data-option="skin_color">◄</div>
                    <div class="text-xs uppercase flex-1 text-center">SKIN COLOR</div>
                    
                    <div class="text-white text-sm cursor-pointer select-none w-[15px] text-center next-option" data-option="skin_color">►</div>
                    <input type="hidden" id="skin_color" value="">
                </div>
                
                <!-- Gender -->
                <div class="flex items-center justify-between mb-2.5 text-white py-0.5">
                    <div class="text-white text-sm cursor-pointer select-none w-[15px] text-center prev-option" data-option="gender">◄</div>
                    <div class="text-xs uppercase flex-1 text-center">GENDER</div>
                    <div class="flex items-center justify-center w-[24px] h-[20px] border-2 border-quest-orange rounded" id="genderPreview">
                        <span id="genderIcon" class="text-sm flex items-center justify-center font-bold">♀</span>
                    </div>
                    <div class="text-white text-sm cursor-pointer select-none w-[15px] text-center next-option" data-option="gender">►</div>
                    <input type="hidden" id="gender" value="Female">
                </div>
                
                <!-- Eye Color -->
                <div class="flex items-center justify-between mb-2.5 text-white py-0.5">
                    <div class="text-white text-sm cursor-pointer select-none w-[15px] text-center prev-option" data-option="eye_color">◄</div>
                    <div class="text-xs uppercase flex-1 text-center">EYE COLOR</div>
                    <div class="text-white text-sm cursor-pointer select-none w-[15px] text-center next-option" data-option="eye_color">►</div>
                    <input type="hidden" id="eye_color" value="eye">
                </div>
                
                <!-- Hair Color -->
                <div class="flex items-center justify-between mb-2.5 text-white py-0.5">
                    <div class="text-white text-sm cursor-pointer select-none w-[15px] text-center prev-option" data-option="hair_color">◄</div>
                    <div class="text-xs uppercase flex-1 text-center">HAIR COLOR</div>
                    <div class="text-white text-sm cursor-pointer select-none w-[15px] text-center next-option" data-option="hair_color">►</div>
                    <input type="hidden" id="hair_color" value="hair">
                </div>
                
                <!-- Hairstyle -->
                <div class="flex items-center justify-between mb-2.5 text-white py-0.5">
                    <div class="text-white text-sm cursor-pointer select-none w-[15px] text-center prev-option" data-option="hairstyle">◄</div>
                    <div class="text-xs uppercase flex-1 text-center">HAIRSTYLE</div>
                    <div class="text-white text-sm cursor-pointer select-none w-[15px] text-center next-option" data-option="hairstyle">►</div>
                    <input type="hidden" id="hairstyle" value="hair">
                </div>
                
                <!-- Eye Shape -->
                <div class="flex items-center justify-between mb-2.5 text-white py-0.5">
                    <div class="text-white text-sm cursor-pointer select-none w-[15px] text-center prev-option" data-option="eye_shape">◄</div>
                    <div class="text-xs uppercase flex-1 text-center">EYE SHAPE</div>
                    <div class="text-white text-sm cursor-pointer select-none w-[15px] text-center next-option" data-option="eye_shape">►</div>
                    <input type="hidden" id="eye_shape" value="eye">
                </div>
                
                <!-- Mouth Shape -->
                <div class="flex items-center justify-between mb-2.5 text-white py-0.5">
                    <div class="text-white text-sm cursor-pointer select-none w-[15px] text-center prev-option" data-option="mouth_shape">◄</div>
                    <div class="text-xs uppercase flex-1 text-center">MOUTH SHAPE</div>
                    <div class="text-white text-sm cursor-pointer select-none w-[15px] text-center next-option" data-option="mouth_shape">►</div>
                    <input type="hidden" id="mouth_shape" value="lip">
                </div>
                
                <!-- Submit Button -->
                <button type="button" id="submitBtn" class="bg-gradient-to-r from-[#FFAA4B] to-[#FF824E] text-white border-[5px] border-[#8A4B22] rounded-[13px] py-2 w-full text-center text-sm mt-4 cursor-pointer uppercase hover:translate-y-[-2px] transition-transform duration-200">SUBMIT</button>
            </div>
        </div>
        
        <!-- Terms Container -->
        <div class="bg-quest-brown border-[5px] border-quest-orange rounded-[13px] p-4 w-[700px] mt-3">
            <div class="flex items-center mb-1 text-white text-xs">
                <input type="checkbox" id="termsCheck1" class="mr-2.5">
                <label for="termsCheck1">I don't accept the terms and conditions</label>
            </div>
            <div class="flex items-center mb-1 text-white text-xs">
                <input type="checkbox" id="termsCheck2" class="mr-2.5">
                <label for="termsCheck2">I accept the terms and conditions</label>
            </div>
        </div>
        
        <!-- Back button in the corner -->
        <div class="absolute bottom-8 left-8">
            <a href="register.php" class="background block bg-gradient-to-r from-[#FFAA4B] to-[#FF824E] border-[5px] border-[#8A4B22] rounded-[13px] w-[150px] py-3 text-center text-white font-bold text-lg uppercase hover:translate-y-[-2px] transition-transform duration-200">
                Back
            </a>
        </div>
        
        </div>
    </div>
    
    <script>
        // Create floating particles effect
        const particlesContainer = document.getElementById('particles');
        const particleCount = 30;
        
        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.classList.add('particle', 'animate-float-up');
            
            // Random position
            const posX = Math.random() * 100;
            const posY = Math.random() * 100;
            const size = 3 + Math.random() * 5;
            const delay = Math.random() * 5;
            const translateX = -100 + Math.random() * 200;
            
            particle.style.left = `${posX}%`;
            particle.style.bottom = `${posY}%`;
            particle.style.width = `${size}px`;
            particle.style.height = `${size}px`;
            particle.style.animationDelay = `${delay}s`;
            particle.style.setProperty('--tx', `${translateX}px`);
            
            particlesContainer.appendChild(particle);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Options and their values
            const options = {
                skin_color: {
                    values: ['base1', 'base2', 'base3'],
                    colors: ['#F5D0A9', '#E0AC69', '#8D5524'],
                    currentIndex: 0,
                    // Add male skin options (will be used when gender is Male)
                    maleValues: ['boy', 'boy2', 'boy3'],
                    maleColors: ['#F5D0A9', '#E0AC69', '#8D5524']
                },
                gender: {
                    values: ['Female', 'Male'],
                    colors: ['#FF9926', '#75341A'],
                    currentIndex: 0
                },
                eye_color: {
                    values: ['eye', 'eye2'],
                    colors: ['#6B8E23', '#4682B4'],
                    currentIndex: 0
                },
                hair_color: {
                    values: ['hair', 'hair2', 'hair3', 'hair4'],
                    colors: ['#4B0082', '#8B4513', '#FFD700', '#000000'], // Purple, Brown, Blonde, Black
                    currentIndex: 0
                },
                hairstyle: {
                    values: ['hair', 'hair2', 'hair3', 'hair4'],
                    currentIndex: 0,
                    // Add male hairstyle options (will be used when gender is Male)
                    maleValues: ['56', '57', '58', '59'],
                    currentMaleIndex: 0
                },
                eye_shape: {
                    values: ['eye', 'eye2'],
                    currentIndex: 0
                },
                mouth_shape: {
                    values: ['lip', 'lip2', 'lip3'],
                    currentIndex: 0
                }
            };
            
            // Navigation arrows click handlers
            document.querySelectorAll('.prev-option').forEach(arrow => {
                arrow.addEventListener('click', function() {
                    const optionName = this.getAttribute('data-option');
                    navigateOption(optionName, -1);
                });
            });
            
            document.querySelectorAll('.next-option').forEach(arrow => {
                arrow.addEventListener('click', function() {
                    const optionName = this.getAttribute('data-option');
                    navigateOption(optionName, 1);
                });
            });
            
            function navigateOption(optionName, direction) {
                const option = options[optionName];
                option.currentIndex = (option.currentIndex + direction + option.values.length) % option.values.length;
                
                // Special handling for options based on gender
                const currentGender = options.gender.values[options.gender.currentIndex];
                
                console.log(`Navigating ${optionName} for gender ${currentGender}, new index: ${option.currentIndex}`);
                
                // Update hidden input
                if (optionName === 'skin_color') {
                    if (currentGender === 'Male') {
                        // For male characters, use the male skin values (boy.png, boy2.png, boy3.png)
                        document.getElementById(optionName).value = option.maleValues[option.currentIndex];
                        console.log(`Setting male skin to: ${option.maleValues[option.currentIndex]}`);
                    } else {
                        document.getElementById(optionName).value = option.values[option.currentIndex];
                        console.log(`Setting female skin to: ${option.values[option.currentIndex]}`);
                    }
                } else if (optionName === 'hairstyle') {
                    if (currentGender === 'Male') {
                        // For male characters, use numerical hairstyles (56.png, 57.png, etc.)
                        // Update the male hair index and store the value
                        option.currentMaleIndex = (option.currentMaleIndex + direction + option.maleValues.length) % option.maleValues.length;
                        document.getElementById(optionName).value = option.maleValues[option.currentMaleIndex];
                        console.log(`Setting male hairstyle to: ${option.maleValues[option.currentMaleIndex]}`);
                    } else {
                        document.getElementById(optionName).value = option.values[option.currentIndex];
                    }
                } else {
                    document.getElementById(optionName).value = option.values[option.currentIndex];
                }
                
                // Update color preview if applicable
                if (option.colors && document.getElementById(optionName + 'Preview')) {
                    if (optionName === 'skin_color' && currentGender === 'Male') {
                        document.getElementById(optionName + 'Preview').style.backgroundColor = option.maleColors[option.currentIndex];
                    } else if (optionName === 'gender') {
                        // For gender, update the icon instead of the background color
                        const genderIcon = document.getElementById('genderIcon');
                        const genderPreview = document.getElementById('genderPreview');
                        if (genderIcon && genderPreview) {
                            if (option.values[option.currentIndex] === 'Male') {
                                genderIcon.textContent = '♂';
                                genderIcon.style.color = '#75341A'; // Male color
                                genderPreview.style.backgroundColor = '#B8CAE4'; // Light blue background for male
                            } else {
                                genderIcon.textContent = '♀';
                                genderIcon.style.color = '#FF9926'; // Female color
                                genderPreview.style.backgroundColor = '#FFD6E5'; // Light pink background for female
                            }
                        }
                    } else {
                        document.getElementById(optionName + 'Preview').style.backgroundColor = option.colors[option.currentIndex];
                    }
                }
                
                // Special case for gender
                if (optionName === 'gender') {
                    // If Male is selected, update all features to be more masculine
                    if (option.values[option.currentIndex] === 'Male') {
                        console.log("Changing to Male character");
                        
                        // Change to a more masculine hair color (brown or black)
                        const maleHairColorIndex = 3; // Black hair (index 3)
                        options.hair_color.currentIndex = maleHairColorIndex;
                        document.getElementById('hair_color').value = options.hair_color.values[maleHairColorIndex];
                        if (document.getElementById('hairColorPreview')) {
                            document.getElementById('hairColorPreview').style.backgroundColor = options.hair_color.colors[maleHairColorIndex];
                        }
                        
                        // Change to a male hairstyle using the numbered files (56-59.png)
                        options.hairstyle.currentMaleIndex = 0; // Set to first male hairstyle (56.png)
                        document.getElementById('hairstyle').value = options.hairstyle.maleValues[0];
                        console.log(`Setting initial male hairstyle to: ${options.hairstyle.maleValues[0]}`);
                        
                        // Change to a more masculine eye color (blue)
                        const maleEyeColorIndex = 1; // Blue eyes (index 1)
                        options.eye_color.currentIndex = maleEyeColorIndex;
                        document.getElementById('eye_color').value = options.eye_color.values[maleEyeColorIndex];
                        if (document.getElementById('eyeColorPreview')) {
                            document.getElementById('eyeColorPreview').style.backgroundColor = options.eye_color.colors[maleEyeColorIndex];
                        }
                        
                        // Change to a more masculine eye shape
                        options.eye_shape.currentIndex = 1; // Set to second eye shape which is more masculine
                        document.getElementById('eye_shape').value = options.eye_shape.values[1];
                        
                        // Change to a more masculine mouth shape
                        options.mouth_shape.currentIndex = 2; // Set to third mouth shape which is more masculine
                        document.getElementById('mouth_shape').value = options.mouth_shape.values[2];
                        
                        // Update skin color to use male skin (boy.png)
                        options.skin_color.currentIndex = 0; // Reset to first skin option
                        document.getElementById('skin_color').value = options.skin_color.maleValues[0];
                        console.log("Setting male skin to:", options.skin_color.maleValues[0]);
                        
                        // Force a refresh of the skin_color input
                        setTimeout(function() {
                            if (document.getElementById('skin_color').value !== options.skin_color.maleValues[0]) {
                                console.log("Re-forcing male skin to:", options.skin_color.maleValues[0]);
                                document.getElementById('skin_color').value = options.skin_color.maleValues[0];
                            }
                        }, 50);
                        
                        // Update UI for male gender
                        updateUIForGender('Male');
                        
                    } else {
                        console.log("Changing to Female character");
                        
                        // Set to none for boy style and update all features to be more feminine
                        
                        // Change to a more feminine hairstyle (hair)
                        options.hairstyle.currentIndex = 0; // Set to first hairstyle which is more feminine
                        document.getElementById('hairstyle').value = options.hairstyle.values[0];
                        
                        // Change to a more feminine hair color (purple)
                        const femaleHairColorIndex = 0; // Purple hair (index 0)
                        options.hair_color.currentIndex = femaleHairColorIndex;
                        document.getElementById('hair_color').value = options.hair_color.values[femaleHairColorIndex];
                        if (document.getElementById('hairColorPreview')) {
                            document.getElementById('hairColorPreview').style.backgroundColor = options.hair_color.colors[femaleHairColorIndex];
                        }
                        
                        // Change to a more feminine eye color (green)
                        const femaleEyeColorIndex = 0; // Green eyes (index 0)
                        options.eye_color.currentIndex = femaleEyeColorIndex;
                        document.getElementById('eye_color').value = options.eye_color.values[femaleEyeColorIndex];
                        if (document.getElementById('eyeColorPreview')) {
                            document.getElementById('eyeColorPreview').style.backgroundColor = options.eye_color.colors[femaleEyeColorIndex];
                        }
                        
                        // Change to a more feminine eye shape
                        options.eye_shape.currentIndex = 0; // Set to first eye shape which is more feminine
                        document.getElementById('eye_shape').value = options.eye_shape.values[0];
                        
                        // Change to a more feminine mouth shape
                        options.mouth_shape.currentIndex = 0; // Set to first mouth shape which is more feminine
                        document.getElementById('mouth_shape').value = options.mouth_shape.values[0];
                        
                        // Update skin color to use female skin
                        options.skin_color.currentIndex = 0; // Reset to first skin option
                        document.getElementById('skin_color').value = options.skin_color.values[0];
                        console.log("Setting female skin to:", options.skin_color.values[0]);
                        
                        // Update UI for female gender
                        updateUIForGender('Female');
                    }
                    
                    // Force update avatar preview after changing gender
                    updateAvatarPreview();
                }
                
                // Always update avatar preview when any option changes
                updateAvatarPreview();
            }
            
            function submitForm() {
                console.log('Submit button clicked');
                
                if (!document.getElementById('termsCheck2').checked) {
                    alert('Please accept the terms and conditions to continue.');
                    return;
                }
                
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.href; // Use current URL to ensure correct path
                
                // Add all option values as hidden inputs
                const formData = {};
                for (const [key, option] of Object.entries(options)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    
                    // Get the value from the hidden input field
                    const element = document.getElementById(key);
                    if (element) {
                        input.value = element.value;
                        formData[key] = element.value;
                        form.appendChild(input);
                    } else {
                        console.error(`Element not found for key: ${key}`);
                    }
                }
                
                // Log the form data for debugging
                console.log('Submitting avatar data:', formData);
                
                // Add form to document and submit
                document.body.appendChild(form);
                
                // Log the form HTML for debugging
                console.log('Form HTML:', form.outerHTML);
                
                // Submit the form
                form.submit();
            }
            
            // Add event listener to submit button
            document.getElementById('submitBtn').addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default button behavior
                console.log('Submit button clicked');
                submitForm();
            });
            
            // Initialize avatar preview
            updateAvatarPreview();
            
            // Initialize skin color value based on gender
            const currentGender = options.gender.values[options.gender.currentIndex];
            if (currentGender === 'Male') {
                document.getElementById('skin_color').value = options.skin_color.maleValues[0]; // boy.png
                console.log("Initializing male skin to:", options.skin_color.maleValues[0]);
            } else {
                document.getElementById('skin_color').value = options.skin_color.values[0]; // base1.png
                console.log("Initializing female skin to:", options.skin_color.values[0]);
            }
            
            // Run initial avatar preview update with a slight delay to ensure values are set
            setTimeout(function() {
                // Double-check gender again and force correct skin
                const gender = options.gender.values[options.gender.currentIndex];
                if (gender === 'Male' && document.getElementById('skin_color').value !== options.skin_color.maleValues[0]) {
                    console.log("Correcting male skin value from", document.getElementById('skin_color').value, "to", options.skin_color.maleValues[0]);
                    document.getElementById('skin_color').value = options.skin_color.maleValues[0];
                }
                updateAvatarPreview();
            }, 100);
            
            // Initialize UI based on gender
            updateUIForGender(options.gender.values[options.gender.currentIndex]);
            
            // Initialize gender icon
            const initialGender = options.gender.values[options.gender.currentIndex];
            const genderIcon = document.getElementById('genderIcon');
            const genderPreview = document.getElementById('genderPreview');
            if (genderIcon && genderPreview) {
                if (initialGender === 'Male') {
                    genderIcon.textContent = '♂';
                    genderIcon.style.color = '#75341A'; // Male color
                    genderPreview.style.backgroundColor = '#B8CAE4'; // Light blue background for male
                } else {
                    genderIcon.textContent = '♀';
                    genderIcon.style.color = '#FF9926'; // Female color
                    genderPreview.style.backgroundColor = '#FFD6E5'; // Light pink background for female
                }
            }
            
            function updateUIForGender(gender) {
                const hairstyleContainer = document.querySelector('[data-option="hairstyle"]').closest('.flex');
                
                // Get other customization options
                const eyeShapeContainer = document.querySelector('[data-option="eye_shape"]').closest('.flex');
                const eyeColorContainer = document.querySelector('[data-option="eye_color"]').closest('.flex');
                const mouthShapeContainer = document.querySelector('[data-option="mouth_shape"]').closest('.flex');
                
                if (gender === 'Male') {
                    // For male characters, update UI elements
                    // Enable all options for male characters
                    [hairstyleContainer, eyeShapeContainer, eyeColorContainer, mouthShapeContainer].forEach(container => {
                        container.style.opacity = '1';
                        container.querySelectorAll('.prev-option, .next-option').forEach(el => {
                            el.style.cursor = 'pointer';
                        });
                    });
                } else {
                    // For female characters, update UI elements
                    // Enable all options for female characters
                    [hairstyleContainer, eyeShapeContainer, eyeColorContainer, mouthShapeContainer].forEach(container => {
                        container.style.opacity = '1';
                        container.querySelectorAll('.prev-option, .next-option').forEach(el => {
                            el.style.cursor = 'pointer';
                        });
                    });
                }
            }
            
            function updateAvatarPreview() {
                const preview = document.getElementById('avatarPreview');
                const loadingIndicator = document.getElementById('loadingIndicator');
                
                // Show loading indicator
                loadingIndicator.style.display = 'flex';
                
                // Clear previous preview except loading indicator
                const existingLayers = preview.querySelectorAll('.avatar-layer, .male-avatar-layer');
                existingLayers.forEach(layer => layer.remove());
                
                // Get current gender for styling
                const currentGender = options.gender.values[options.gender.currentIndex];
                
                // Adjust preview container for better fit based on gender
                if (currentGender === 'Male') {
                    preview.style.overflow = 'visible';
                } else {
                    preview.style.overflow = 'hidden';
                }
                
                // Create a container for all layers to help with positioning
                const layersContainer = document.createElement('div');
                layersContainer.className = 'absolute inset-0 flex items-center justify-center';
                
                // Resize container based on gender for proper fit
                if (currentGender === 'Male') {
                    layersContainer.style.width = '100%';
                    layersContainer.style.height = '100%';
                } else {
                    layersContainer.style.width = '90%';
                    layersContainer.style.height = '90%';
                }
                
                layersContainer.style.margin = 'auto';
                
                preview.appendChild(layersContainer);
                
                // Create character layers
                const layers = [];
                
                console.log(`Rendering avatar for gender: ${currentGender}`);
                
                if (currentGender === 'Male') {
                    // Get the actual skin value from the input field
                    let skinValue = document.getElementById('skin_color').value;
                    
                    // Force male skin if not set correctly
                    if (!skinValue.startsWith('boy')) {
                        console.log(`Correcting male skin value from ${skinValue} to ${options.skin_color.maleValues[0]}`);
                        skinValue = options.skin_color.maleValues[0];
                        document.getElementById('skin_color').value = skinValue;
                    }
                    
                    console.log(`Male avatar: Using skin from input: ${skinValue}`);
                    
                    // Get the actual hairstyle value from the input field
                    const hairstyleValue = document.getElementById('hairstyle').value;
                    console.log(`Male avatar: Using hairstyle from input: ${hairstyleValue}`);
                    
                    // Base skin layer (using boy.png, boy2.png, or boy3.png)
                    layers.push({
                        src: `../assets/images/character/${skinValue}.png`,
                        zIndex: 1
                    });
                    
                    // Eye layer
                    layers.push({
                        src: `../assets/images/character/${options.eye_shape.values[options.eye_shape.currentIndex]}.png`,
                        zIndex: 3
                    });
                    
                    // Mouth layer
                    layers.push({
                        src: `../assets/images/character/${options.mouth_shape.values[options.mouth_shape.currentIndex]}.png`,
                        zIndex: 2
                    });
                    
                    // Hair layer - use numbered files for male (56-59.png)
                    // Define adjustments for each hairstyle
                    let hairTopPosition = '-15px'; // Default
                    let hairLeftPosition = '0'; // Default
                    let hairScale = '1.0'; // Default
                    
                    // Specific adjustments for each hairstyle
                    switch(hairstyleValue) {
                        case '56':
                            hairTopPosition = '10px';
                            hairLeftPosition = '7px';
                            hairScale = '1.0';
                            break;
                        case '57':
                            hairTopPosition = '10px';
                            hairLeftPosition = '7px';
                            hairScale = '1.0';
                            break;
                        case '58':
                            hairTopPosition = '10px';
                            hairLeftPosition = '7px';
                            hairScale = '1.0';
                            break;
                        case '59':
                            hairTopPosition = '10px';
                            hairLeftPosition = '7';
                            hairScale = '1.0';
                            break;
                    }
                    
                    layers.push({
                        src: `../assets/images/character/${hairstyleValue}.png`,
                        zIndex: 4,
                        customStyle: true,
                        top: hairTopPosition,
                        left: hairLeftPosition,
                        scale: hairScale
                    });
                } else {
                    // For female characters, use the standard approach
                    console.log(`Female avatar: Using standard layers`);
                    
                    // Base skin layer
                    layers.push({
                        src: `../assets/images/character/${options.skin_color.values[options.skin_color.currentIndex]}.png`,
                        zIndex: 1
                    });
                    
                    // Eye layer
                    layers.push({
                        src: `../assets/images/character/${options.eye_shape.values[options.eye_shape.currentIndex]}.png`,
                        zIndex: 3
                    });
                    
                    // Mouth layer
                    layers.push({
                        src: `../assets/images/character/${options.mouth_shape.values[options.mouth_shape.currentIndex]}.png`,
                        zIndex: 2
                    });
                    
                    // Hair layer
                    layers.push({
                        src: `../assets/images/character/${options.hairstyle.values[options.hairstyle.currentIndex]}.png`,
                        zIndex: 4
                    });
                }
                
                let loadedImages = 0;
                const totalImages = layers.length;
                
                // Add layers to preview
                layers.forEach(layer => {
                    const layerDiv = document.createElement('div');
                    
                    // Use specific class for male character layers
                    if (currentGender === 'Male') {
                        layerDiv.className = 'male-avatar-layer';
                    } else {
                        layerDiv.className = 'avatar-layer';
                    }
                    
                    // Add an onload handler to track image loading
                    const img = new Image();
                    img.onload = () => {
                        loadedImages++;
                        console.log(`Successfully loaded image: ${layer.src}`);
                        console.log(`Image dimensions: ${img.width}x${img.height}`);
                        
                        layerDiv.style.backgroundImage = `url('${layer.src}')`;
                        layerDiv.style.backgroundSize = 'contain';
                        layerDiv.style.backgroundPosition = 'center';
                        layerDiv.style.zIndex = layer.zIndex;
                        
                        // Apply custom styling if specified
                        if (layer.customStyle) {
                            if (layer.top) layerDiv.style.top = layer.top;
                            if (layer.left) layerDiv.style.left = layer.left;
                            if (layer.scale) layerDiv.style.transform = `scale(${layer.scale})`;
                        }
                        
                        layersContainer.appendChild(layerDiv);
                        
                        // Hide loading indicator when all images are loaded
                        if (loadedImages === totalImages) {
                            loadingIndicator.style.display = 'none';
                        }
                    };
                    
                    img.onerror = () => {
                        loadedImages++;
                        console.error(`Failed to load image: ${layer.src}`);
                        
                        // Add error message to the preview
                        const errorMsg = document.createElement('div');
                        errorMsg.className = 'absolute inset-0 flex items-center justify-center text-red-500 text-xs text-center p-2';
                        errorMsg.textContent = `Failed to load: ${layer.src.split('/').pop()}`;
                        preview.appendChild(errorMsg);
                        
                        // Hide loading indicator when all images are processed
                        if (loadedImages === totalImages) {
                            loadingIndicator.style.display = 'none';
                        }
                    };
                    
                    console.log(`Attempting to load image: ${layer.src}`);
                    img.src = layer.src;
                });
            }
            
            // Add global adjustment function for easy debugging
            window.adjustHairPosition = function(hairstyleNumber, top, left, scale) {
                console.log(`Adjusting hairstyle ${hairstyleNumber}: top=${top}, left=${left}, scale=${scale}`);
                
                // Update the current avatar if it's showing this hairstyle
                const currentGender = options.gender.values[options.gender.currentIndex];
                const currentHairstyle = document.getElementById('hairstyle').value;
                
                if (currentGender === 'Male' && currentHairstyle === hairstyleNumber) {
                    // Find and update the hair layer
                    const hairLayers = document.querySelectorAll('.male-avatar-layer');
                    hairLayers.forEach(layer => {
                        if (layer.style.zIndex === '4') {
                            if (top !== undefined) layer.style.top = `${top}px`;
                            if (left !== undefined) layer.style.left = `${left}px`;
                            if (scale !== undefined) layer.style.transform = `scale(${scale})`;
                        }
                    });
                }
                
                // Log instructions for updating the code
                console.log('To make this permanent, update the switch case in the code.');
            };
            
            // Terms checkboxes should be mutually exclusive
            document.getElementById('termsCheck1').addEventListener('change', function() {
                if (this.checked) {
                    document.getElementById('termsCheck2').checked = false;
                }
            });
            
            document.getElementById('termsCheck2').addEventListener('change', function() {
                if (this.checked) {
                    document.getElementById('termsCheck1').checked = false;
                }
            });
        });
    </script>
</body>
</html> 