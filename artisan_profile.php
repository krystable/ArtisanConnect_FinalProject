<?php
require_once 'database.php';
startSession();

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

if (!isset($_GET['id'])) {
    header('Location: browse.php');
    exit();
}

$artisan_id = (int)$_GET['id'];
$conn = getDBConnection();

// Fetch Artisan Details
$stmt = $conn->prepare("SELECT * FROM artisans WHERE artisanId = ?");
$stmt->bind_param("i", $artisan_id);
$stmt->execute();
$artisan = $stmt->get_result()->fetch_assoc();

if (!$artisan) {
    header('Location: browse.php');
    exit();
}

// Fetch Portfolio
$stmt = $conn->prepare("SELECT * FROM portfolios WHERE artisanId = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $artisan_id);
$stmt->execute();
$portfolio_result = $stmt->get_result();
$portfolio_items = [];
while ($row = $portfolio_result->fetch_assoc()) {
    $portfolio_items[] = $row;
}

$message = '';
$error = '';

$csrf_token = generateCSRFToken();

// Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_review') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
         $error = 'Security validation failed.';
    } elseif ($user_type !== 'client') {
         $error = 'Only clients can leave reviews.';
    } else {
         $rating = (int)$_POST['rating'];
         $comment = sanitize($_POST['comment']);
         
         if ($rating < 1 || $rating > 5) {
             $error = 'Invalid rating.';
         } elseif (empty($comment)) {
             $error = 'Please enter a comment.';
         } else {
             $stmt = $conn->prepare("INSERT INTO reviews (clientId, artisanId, rating, comment) VALUES (?, ?, ?, ?)");
             $stmt->bind_param("iiis", $user_id, $artisan_id, $rating, $comment);
             if ($stmt->execute()) {
                 $message = 'Review submitted successfully!';
                 $avg_sql = "SELECT AVG(rating) as avg_rating FROM reviews WHERE artisanId = ?";
                 $avg_stmt = $conn->prepare($avg_sql);
                 $avg_stmt->bind_param("i", $artisan_id);
                 $avg_stmt->execute();
                 $avg_res = $avg_stmt->get_result()->fetch_assoc();
                 $new_avg = $avg_res['avg_rating'] ?? 0;
                 $avg_stmt->close();
                 
                 $upd_stmt = $conn->prepare("UPDATE artisans SET rating = ? WHERE artisanId = ?");
                 $upd_stmt->bind_param("di", $new_avg, $artisan_id);
                 $upd_stmt->execute();
                 $upd_stmt->close();
                 
                 header("Location: artisan_profile.php?id=$artisan_id");
                 exit();
             } else {
                 $error = 'Failed to submit review: ' . $conn->error;
             }
             $stmt->close();
         }
    }
}

// Fetch Reviews
$stmt = $conn->prepare("SELECT r.*, c.name as client_name, c.profile_image as client_image 
                        FROM reviews r 
                        JOIN clients c ON r.clientId = c.clientId 
                        WHERE r.artisanId = ? 
                        ORDER BY r.created_at DESC");
$stmt->bind_param("i", $artisan_id);
$stmt->execute();
$reviews_result = $stmt->get_result();
$reviews = [];
while ($row = $reviews_result->fetch_assoc()) {
    $reviews[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($artisan['name']); ?> - ArtisanConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <div class="container">
        <!-- Back Navigation -->
        <div style="margin-bottom: 20px;">
            <a href="dashboard.php" class="btn-outline" style="border: none; padding-left: 0; color: var(--text-light);">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php if (!empty($artisan['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($artisan['profile_image']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
                <?php if ($artisan['is_verified']): ?>
                    <div class="verified-badge" style="position: absolute; bottom: 0; right: 0; background: var(--success-color); color: white; padding: 2px 6px; border-radius: 50%; font-size: 0.8rem; border: 2px solid white;"><i class="fas fa-check"></i></div>
                <?php endif; ?>
            </div>
            
            <div class="profile-info">
                <h1 class="profile-name"><?php echo htmlspecialchars($artisan['name']); ?></h1>
                <div class="profile-category">
                    <?php echo htmlspecialchars($artisan['category']); ?> • 
                    <?php echo htmlspecialchars($artisan['specialty']); ?>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($artisan['rating'], 1); ?> <i class="fas fa-star" style="color: #ffc107; font-size: 1rem;"></i></div>
                        <div class="stat-label">Rating</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $artisan['experience_years']; ?> Years</div>
                        <div class="stat-label">Experience</div>
                    </div>
                    <?php if ($artisan['hourly_rate']): ?>
                    <div class="stat-item">
                        <div class="stat-value">GHS <?php echo number_format($artisan['hourly_rate'], 2); ?></div>
                        <div class="stat-label">Hourly Rate</div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="profile-bio">
                    <?php echo nl2br(htmlspecialchars($artisan['description'] ?? 'No description available.')); ?>
                </div>
                
                <div class="action-buttons">
                    <?php if ($user_type === 'client'): ?>
                        <a href="book.php?artisan_id=<?php echo $artisan['artisanId']; ?>" class="btn btn-primary">
                            <i class="fas fa-calendar-check"></i> Book Now
                        </a>
                        <a href="messages.php?compose=true&recipient_id=<?php echo $artisan['artisanId']; ?>" class="btn btn-secondary">
                            <i class="fas fa-comment-alt"></i> Message
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Portfolio -->
        <h2 class="section-title">Portfolio</h2>
        <?php if (count($portfolio_items) > 0): ?>
            <div class="portfolio-grid">
                <?php foreach ($portfolio_items as $item): ?>
                    <div class="portfolio-item">
                        <div class="portfolio-img" style="background-image: url('<?php echo htmlspecialchars($item['image_url']); ?>')"></div>
                        <div class="portfolio-info">
                            <div class="portfolio-title"><?php echo htmlspecialchars($item['title']); ?></div>
                            <div class="portfolio-desc"><?php echo htmlspecialchars($item['description']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: var(--text-light); margin-bottom: 3rem;">No portfolio items uploaded yet.</p>
        <?php endif; ?>
        
        <!-- Reviews -->
        <h2 class="section-title">Client Reviews</h2>
        
        <!-- Review Form -->
        <?php if ($user_type === 'client'): ?>
            <div class="review-form-container" style="background: white; padding: 1.5rem; border-radius: var(--radius-sm); margin-bottom: 2rem; border: 1px solid rgba(0,0,0,0.05);">
                <h3 style="margin-bottom: 1rem;">Leave a Review</h3>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add_review">
                    
                    <div class="form-group">
                        <label for="rating">Rating</label>
                        <select name="rating" id="rating" class="form-control" style="width: auto;" required>
                            <option value="5">5 Stars - Excellent</option>
                            <option value="4">4 Stars - Very Good</option>
                            <option value="3">3 Stars - Good</option>
                            <option value="2">2 Stars - Fair</option>
                            <option value="1">1 Star - Poor</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="comment">Your Comment</label>
                        <textarea name="comment" id="comment" class="form-control" placeholder="Share your experience..." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (count($reviews) > 0): ?>
            <div class="reviews-list">
                <?php foreach ($reviews as $review): 
                    $has_img = !empty($review['client_image']);
                    $c_img = $has_img ? $review['client_image'] : '';
                ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div class="reviewer-info">
                                <div class="reviewer-img" style="<?php echo $has_img ? "background-image: url('$c_img')" : "background-color: #eee; display: flex; align-items: center; justify-content: center;"; ?>">
                                    <?php if (!$has_img): ?>
                                        <i class="fas fa-user-circle" style="color: #999; font-size: 1.5rem;"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($review['client_name']); ?></strong>
                                    <div style="font-size: 0.8rem; color: #999;"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></div>
                                </div>
                            </div>
                            <div class="review-rating">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <i class="fas fa-star" style="color: <?php echo $i <= $review['rating'] ? '#ffc107' : '#ddd'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div><?php echo htmlspecialchars($review['comment']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: var(--text-light);">No reviews yet. Be the first to review!</p>
        <?php endif; ?>
        
    </div>
</body>
</html>
