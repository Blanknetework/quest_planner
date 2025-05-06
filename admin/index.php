<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: auth/login.php');
    exit;
}

$stats = [];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as verified FROM users WHERE email_verified = 1");
$stats['verified_users'] = $stmt->fetch()['verified'];

$stats['unverified_users'] = $stats['total_users'] - $stats['verified_users'];

$stmt = $pdo->query("SELECT COUNT(*) as new_users FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['new_signups'] = $stmt->fetch()['new_users'];

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$total_users = $stmt->fetch()['total'];
$total_pages = ceil($total_users / $limit);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'], $_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        $action = $_POST['action'];

        if ($action === 'verify') {
            $stmt = $pdo->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
            $stmt->execute([$user_id]);
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
        }

        header('Location: index.php');
        exit;
    }
}

$chartData = [
    'labels' => [],
    'totalUsers' => [],
    'verifiedUsers' => []
];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $nextDate = date('Y-m-d', strtotime("-" . ($i - 1) . " days"));

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE created_at < ?");
    $stmt->execute([$nextDate]);
    $totalCount = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email_verified = 1 AND created_at < ?");
    $stmt->execute([$nextDate]);
    $verifiedCount = $stmt->fetch()['count'];

    $chartData['labels'][] = date('M d', strtotime($date));
    $chartData['totalUsers'][] = $totalCount;
    $chartData['verifiedUsers'][] = $verifiedCount;
}

$verifiedCount = $stats['verified_users'];
$unverifiedCount = $stats['unverified_users'];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        // Get the user ID
        $user_id = (int)$_POST['user_id'];
        
        // Perform the deletion
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $result = $stmt->execute([$user_id]);
        
        // Redirect to refresh the page
        header('Location: index.php?deleted=true');
        exit;
    } catch (Exception $e) {
        // Log the error
        error_log("Error deleting user: " . $e->getMessage());
        
        // Display error message
        echo "<div style='color:red; padding:10px; background:#ffcccc; margin:10px;'>
            Error deleting user: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Quest Planner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div class="title-banner">
                <div class="title-logo"></div>
                <div class="title-text">QUEST PLANNER</div>
            </div>
            <div class="admin-title">ADMIN DASHBOARD</div>
            <div class="nav-links">
                <a href="../index.php" class="nav-link">Return to Main</a>
                <a href="auth/logout.php" class="nav-link">Logout</a>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon total-users-icon">👥</div>
                <div class="stat-data">
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon verified-icon">✓</div>
                <div class="stat-data">
                    <div class="stat-value"><?php echo $stats['verified_users']; ?></div>
                    <div class="stat-label">Verified Users</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon unverified-icon">!</div>
                <div class="stat-data">
                    <div class="stat-value"><?php echo $stats['unverified_users']; ?></div>
                    <div class="stat-label">Unverified Users</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon new-users-icon">+</div>
                <div class="stat-data">
                    <div class="stat-value"><?php echo $stats['new_signups']; ?></div>
                    <div class="stat-label">New This Week</div>
                </div>
            </div>
        </div>
        
    
        <div class="admin-flex-container">
            <!-- User Management Section (Left Side) -->
            <div class="admin-section user-management">
                <div class="section-header">
                    <h2>User Management</h2>
                    <div class="search-box">
                        <input type="text" id="userSearch" placeholder="Search users...">
                    </div>
                </div>
                
                <div class="user-table-container">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Registration Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $user['email_verified'] ? 'verified' : 'unverified'; ?>">
                                        <?php echo $user['email_verified'] ? 'Verified' : 'Not Verified'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td class="action-buttons">
                                    <?php if (!$user['email_verified']): ?>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="verify">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="verify-btn">Verify</button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <button class="edit-btn" data-id="<?php echo $user['id']; ?>">Edit</button>
                                    
                                    <button type="button" class="delete-btn" onclick="showDeleteModal(<?php echo $user['id']; ?>)">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="page-btn">&laquo; Prev</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="page-btn">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chart Section (Right Side) -->
            <div class="admin-section chart-section">
                <div class="section-header">
                    <h2>User Statistics</h2>
                </div>
                <div class="chart-wrapper">
                    <canvas id="userChart"></canvas>
                </div>
                <div class="chart-legend">
                    <div class="legend-item">
                        <div class="legend-color verified-color"></div>
                        <div class="legend-text">Verified Users: <?php echo $stats['verified_users']; ?></div>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color unverified-color"></div>
                        <div class="legend-text">Unverified Users: <?php echo $stats['unverified_users']; ?></div>
                    </div>
                </div>
                <div class="chart-info">
                    <div class="info-box">
                        <h3>Verification Rate</h3>
                        <div class="info-value">
                            <?php 
                            $verificationRate = ($stats['total_users'] > 0) 
                                ? round(($stats['verified_users'] / $stats['total_users']) * 100) 
                                : 0; 
                            echo $verificationRate . '%';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button id="closeEditModal" class="close-button">✕</button>
            </div>
            <form id="editUserForm">
                <input type="hidden" id="edit_user_id" name="user_id">
                
                <div class="form-group">
                    <label for="edit_username">Username</label>
                    <input type="text" id="edit_username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select id="edit_status" name="status">
                        <option value="1">Verified</option>
                        <option value="0">Not Verified</option>
                    </select>
                </div>
                
                <button type="submit" class="submit-btn">Update User</button>
            </form>
        </div>
    </div>
    
   
    
    <!-- Delete User Modal -->
    <div id="deleteModal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Deletion</h3>
                <span class="close-button" id="closeDeleteModal">✕</span>
            </div>
            <div class="modal-body">
                <div class="delete-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                </div>
                <p class="delete-message">Are you sure you want to delete this user?</p>
                <p class="delete-warning">This action cannot be undone.</p>
                
                <!-- Delete form with POST method -->
                <form id="deleteForm" method="POST" action="index.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="delete_user_id" name="user_id" value="">
                    
                    <div class="modal-actions">
                        <button type="submit" class="delete-confirm-btn">Delete User</button>
                        <button type="button" class="delete-cancel-btn" id="cancelDelete">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="assets/js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('userChart') && typeof Chart !== 'undefined') {
                try {
                    const ctx = document.getElementById('userChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'doughnut', e
                        data: {
                            labels: ['Verified Users', 'Unverified Users'],
                            datasets: [{
                                data: [<?php echo $verifiedCount; ?>, <?php echo $unverifiedCount; ?>],
                                backgroundColor: [
                                    '#6ABF69', 
                                    '#E9A03B'  
                                ],
                                borderColor: [
                                    '#5C2F22',
                                    '#5C2F22'
                                ],
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '60%',
                            plugins: {
                                legend: {
                                    display: false 
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.raw || 0;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const percentage = Math.round((value / total) * 100);
                                            return `${label}: ${value} (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                } catch (error) {
                    console.error('Error creating chart:', error);
                    document.getElementById('userChart').parentNode.innerHTML = 
                        '<div style="text-align: center; padding: 20px;">Unable to load chart. Please check console for errors.</div>';
                }
            }
        });

        
    </script>
</body>
</html>