<?php
require_once 'database.php';
startSession();

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['user_type'] !== 'artisan') {
    header('Location: dashboard_client.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$conn = getDBConnection();

$sql = "SELECT location, category, rating, is_verified, profile_image FROM artisans WHERE artisanId = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// Stats
$msg_sql = "SELECT COUNT(*) as count FROM messages WHERE receiverId = ? AND senderType = 'client' AND is_read = 0";
$msg_stmt = $conn->prepare($msg_sql);
$msg_stmt->bind_param("i", $user_id);
$msg_stmt->execute();
$new_messages = $msg_stmt->get_result()->fetch_assoc()['count'];
$msg_stmt->close();

$job_sql = "SELECT COUNT(*) as count FROM bookings WHERE artisanId = ? AND status = 'confirmed' AND booking_date >= CURDATE()";
$job_stmt = $conn->prepare($job_sql);
$job_stmt->bind_param("i", $user_id);
$job_stmt->execute();
$upcoming_jobs = $job_stmt->get_result()->fetch_assoc()['count'];
$job_stmt->close();

$pending_sql = "SELECT COUNT(*) as count FROM bookings WHERE artisanId = ? AND status = 'pending'";
$pending_stmt = $conn->prepare($pending_sql);
$pending_stmt->bind_param("i", $user_id);
$pending_stmt->execute();
$pending_jobs = $pending_stmt->get_result()->fetch_assoc()['count'];
$pending_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artisan Dashboard - ArtisanConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="container">
        <div class="welcome-card">
            <div class="user-info">
                <h1>Welcome, <?php echo htmlspecialchars($user_name); ?>! </h1>
                <p>Manage your artisan business.</p>
                <div class="user-badges">
                    <span class="badge badge-primary"><i class="fas fa-tools"></i> Artisan</span>
                    <?php if (!empty($user_info['category'])): ?>
                        <span class="badge badge-success"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($user_info['category']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($user_info['is_verified'])): ?>
                        <span class="badge badge-success"><i class="fas fa-check-circle"></i> Verified</span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="text-align:right;">
                 <?php if (!empty($user_info['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($user_info['profile_image']); ?>" alt="Profile" style="width: 80px; height: 80px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 10px; object-fit: cover;">
                <?php endif; ?>
                 <br>
                <a href="profile.php" class="btn"><i class="fas fa-edit"></i> Edit Profile</a>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-calendar-check"></i>
                <h3><?php echo $upcoming_jobs; ?></h3>
                <p>Upcoming Jobs</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-comments"></i>
                <h3><?php echo $new_messages; ?></h3>
                <p>New Messages</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-star"></i>
                <h3><?php echo $user_info['rating'] ?? 'N/A'; ?></h3>
                <p>Rating</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-clock" style="color: var(--accent-color);"></i>
                <h3><?php echo $pending_jobs; ?></h3>
                <p>Pending Requests</p>
            </div>
        </div>
        
        <!-- Quick Access Artisan -->
        <h2 style="margin-bottom:1.5rem; margin-top:3rem;">Quick Actions</h2>
        <div class="features-grid">
             <a href="profile.php" class="feature-card">
                <i class="fas fa-id-card"></i>
                <h3>Manage Profile</h3>
                <p>Update skills & services</p>
            </a>
            <a href="bookings.php" class="feature-card">
                <i class="fas fa-calendar-alt"></i>
                <h3>Calendar</h3>
                <p>Manage appointments</p>
            </a>
            <a href="messages.php" class="feature-card">
                <i class="fas fa-comments"></i>
                <h3>Messages</h3>
                <p>Chat with clients</p>
            </a>
            <a href="portfolio.php" class="feature-card">
                <i class="fas fa-images"></i>
                <h3>Portfolio</h3>
                <p>Update your work gallery</p>
            </a>
        </div>
    </div>
    
    <div class="footer">
        <p>© 2024 ArtisanConnect. All rights reserved.</p>
    </div>
</body>
</html>
