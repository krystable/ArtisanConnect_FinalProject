<?php
require_once 'database.php';
startSession();

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Redirect if not client
if ($_SESSION['user_type'] !== 'client') {
    header('Location: dashboard_artisan.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$conn = getDBConnection();

$sql = "SELECT location FROM clients WHERE clientId = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

// Client Stats
$msg_sql = "SELECT COUNT(*) as count FROM messages WHERE receiverId = ? AND senderType = 'artisan' AND is_read = 0";
$msg_stmt = $conn->prepare($msg_sql);
$msg_stmt->bind_param("i", $user_id);
$msg_stmt->execute();
$client_messages = $msg_stmt->get_result()->fetch_assoc()['count'];
$msg_stmt->close();

$appt_sql = "SELECT COUNT(*) as count FROM bookings WHERE clientId = ? AND status = 'confirmed' AND booking_date >= CURDATE()";
$appt_stmt = $conn->prepare($appt_sql);
$appt_stmt->bind_param("i", $user_id);
$appt_stmt->execute();
$upcoming_appts = $appt_stmt->get_result()->fetch_assoc()['count'];
$appt_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - ArtisanConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="container">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <div class="user-info">
                <h1>Welcome, <?php echo htmlspecialchars($user_name); ?>! </h1>
                <p>Find the best artisans for your needs.</p>
                <div class="user-badges">
                    <span class="badge badge-primary"><i class="fas fa-user"></i> Client</span>
                    <?php if (!empty($user_info['location'])): ?>
                        <span class="badge badge-warning"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($user_info['location']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <a href="profile.php" class="btn"><i class="fas fa-edit"></i> Edit Profile</a>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-search"></i>
                <h3>Find</h3>
                <p>Artisans</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-calendar"></i>
                <h3><?php echo $upcoming_appts; ?></h3>
                <p>Upcoming Appointments</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-comments"></i>
                <h3><?php echo $client_messages; ?></h3>
                <p>Messages</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-heart"></i>
                <h3><?php echo rand(3, 15); ?></h3>
                <p>Saved Artisans</p>
            </div>
        </div>
        
        <!-- Artisans Section -->
        <div class="featured-section">
            <div class="section-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h2>Featured Artisans</h2>
                <a href="browse.php" class="btn-outline">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="featured-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem;">
                <?php
                $fa_sql = "SELECT * FROM artisans ORDER BY RAND() LIMIT 3";
                $fa_result = $conn->query($fa_sql);
                
                if ($fa_result && $fa_result->num_rows > 0):
                    while($artisan = $fa_result->fetch_assoc()):
                        $has_image = !empty($artisan['profile_image']);
                        $image_url = $has_image ? htmlspecialchars($artisan['profile_image']) : '';
                ?>
                    <div class="artisan-card">
                        <div class="artisan-image" style="<?php echo $has_image ? "background-image: url('$image_url')" : "background-color: var(--card-bg); display: flex; align-items: center; justify-content: center;"; ?>">
                            <?php if (!$has_image): ?>
                                <i class="fas fa-user-circle" style="font-size: 4rem; color: var(--text-light); opacity: 0.5;"></i>
                            <?php endif; ?>
                            
                            <?php if ($artisan['is_verified']): ?>
                                <div class="verified-badge">
                                    <i class="fas fa-check-circle"></i> Verified
                                </div>
                            <?php endif; ?>
                            <?php if ($artisan['hourly_rate']): ?>
                                <div class="hourly-rate">
                                    GHS <?php echo number_format($artisan['hourly_rate'], 2); ?>/hr
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="artisan-content">
                            <div>
                                <h3 class="artisan-name"><?php echo htmlspecialchars($artisan['name']); ?></h3>
                                <span class="artisan-category"><?php echo htmlspecialchars($artisan['category'] ?? 'General'); ?></span>
                            </div>
                            
                            <div class="artisan-details">
                                <div class="detail-item">
                                    <i class="fas fa-star" style="color: #ffc107;"></i>
                                    <span style="font-weight: 700; color: var(--text-dark);"><?php echo number_format($artisan['rating'], 1); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($artisan['location'] ?? 'Location not specified'); ?></span>
                                </div>
                            </div>

                            <div class="artisan-actions">
                                <a href="artisan_profile.php?id=<?php echo $artisan['artisanId']; ?>" class="btn-outline">
                                    View Profile
                                </a>
                                <a href="book.php?artisan_id=<?php echo $artisan['artisanId']; ?>" class="btn">
                                    Book Now
                                </a>
                            </div>
                        </div>
                    </div>
                <?php 
                    endwhile;
                else: 
                ?>
                    <p style="grid-column: 1/-1; text-align: center; color: var(--text-light);">No artisans available at the moment.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Access Client -->
        <h2 style="margin-bottom:1.5rem; margin-top:3rem;">Quick Actions</h2>
        <div class="features-grid">
             <a href="browse.php" class="feature-card">
                <i class="fas fa-search"></i>
                <h3>Find Pros</h3>
                <p>Discover skilled artisans</p>
            </a>
            <a href="bookings.php" class="feature-card">
                <i class="fas fa-calendar-check"></i>
                <h3>Bookings</h3>
                <p>Track your appointments</p>
            </a>
            <a href="messages.php" class="feature-card">
                <i class="fas fa-comments"></i>
                <h3>Messages</h3>
                <p>Chat with artisans</p>
            </a>
        </div>
    </div>
    
    <div class="footer">
        <p>© 2024 ArtisanConnect. All rights reserved.</p>
    </div>
</body>
</html>