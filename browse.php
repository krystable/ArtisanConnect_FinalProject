<?php
require_once 'database.php';
startSession();

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

$conn = getDBConnection();
$categories_result = $conn->query("SELECT * FROM categories ORDER BY name");
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

$sql = "SELECT * FROM artisans WHERE 1=1";
$params = [];
$types = "";

// Filter by search
if (!empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $sql .= " AND (name LIKE ? OR description LIKE ? OR specialty LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "sss";
}

// Filter by category
if (!empty($_GET['category'])) {
    $sql .= " AND category = ?";
    $params[] = $_GET['category'];
    $types .= "s";
}

// Filter by location
if (!empty($_GET['location'])) {
    $location = "%" . $_GET['location'] . "%";
    $sql .= " AND location LIKE ?";
    $params[] = $location;
    $types .= "s";
}

$sql .= " ORDER BY rating DESC, created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$artisans = [];
while ($row = $result->fetch_assoc()) {
    $artisans[] = $row;
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Artisans - ArtisanConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container">
        <div class="search-section">
            <h1>Find Skilled Artisans</h1>
            <p>Discover talented professionals for your next project. Filter by category, location, or search by name.</p>
            
            <!-- Active Filters -->
            <?php if (!empty($_GET['search']) || !empty($_GET['category']) || !empty($_GET['location'])): ?>
            <div style="margin-bottom: 20px;">
                <span style="font-weight: 600; color: var(--text-dark); margin-right: 10px;">Active Filters:</span>
                <?php if (!empty($_GET['search'])): ?>
                    <div class="filter-tag">
                        <i class="fas fa-search"></i> <?php echo htmlspecialchars($_GET['search']); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($_GET['category'])): ?>
                    <div class="filter-tag">
                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($_GET['category']); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($_GET['location'])): ?>
                    <div class="filter-tag">
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($_GET['location']); ?>
                    </div>
                <?php endif; ?>
                <a href="browse.php" style="color: var(--text-light); text-decoration: underline; font-size: 0.9rem;">
                    <i class="fas fa-times"></i> Clear All
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Search Form -->
            <form action="" method="GET" class="search-form">
                <div class="form-group">
                    <label for="search"><i class="fas fa-search"></i> Keywords</label>
                    <input type="text" id="search" name="search" class="form-control" 
                           placeholder="Name, service, specialty..." 
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="category"><i class="fas fa-tags"></i> Category</label>
                    <select id="category" name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['name']); ?>" 
                                <?php echo ($_GET['category'] ?? '') === $cat['name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="location"><i class="fas fa-map-marker-alt"></i> Location</label>
                    <input type="text" id="location" name="location" class="form-control" 
                           placeholder="City, area, or region" 
                           value="<?php echo htmlspecialchars($_GET['location'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">
                        <i class="fas fa-search"></i> Search Artisans
                    </button>
                </div>
            </form>
        </div>

        <!-- Results Count -->
        <?php if (count($artisans) > 0): ?>
        <div style="margin-bottom: 2rem; color: var(--text-light); font-size: 1.1rem;">
            <i class="fas fa-users"></i> Found <?php echo count($artisans); ?> artisan<?php echo count($artisans) !== 1 ? 's' : ''; ?>
        </div>
        <?php endif; ?>

        <!-- Artisans Grid -->
        <div class="artisans-grid">
            <?php if (count($artisans) > 0): ?>
                <?php foreach ($artisans as $artisan): 
                    $has_image = !empty($artisan['profile_image']);
                    $image_url = $has_image ? htmlspecialchars($artisan['profile_image']) : '';
                ?>
                <div class="artisan-card">
                    <div class="artisan-image" style="<?php echo $has_image ? "background-image: url('$image_url')" : "background-color: var(--card-bg); display: flex; align-items: center; justify-content: center;"; ?>">
                        <?php if (!$has_image): ?>
                            <i class="fas fa-user-circle" style="font-size: 4rem; color: var(--text-light); opacity: 0.5;"></i>
                        <?php endif; ?>
                        
                        <?php if ($artisan['is_verified']): ?>
                            <div class="verified-badge"><i class="fas fa-check-circle"></i> Verified</div>
                        <?php endif; ?>
                        <?php if ($artisan['hourly_rate']): ?>
                           <div class="hourly-rate">GHS <?php echo number_format($artisan['hourly_rate'], 2); ?>/hr</div>
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
                                <div class="detail-item">
                                    <i class="fas fa-briefcase"></i>
                                    <span><?php echo $artisan['experience_years']; ?> years exp.</span>
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
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 5rem; background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
                    <i class="fas fa-search" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                    <h3>No artisans found</h3>
                    <p style="color: var(--text-light);">Try adjusting your search criteria</p>
                    <a href="browse.php" class="btn" style="margin-top: 1rem;">Clear Filters</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>