<?php
require_once 'database.php';
startSession();
requireLogin();

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$error = '';
$success = '';


$conn = getDBConnection();
if ($user_type === 'artisan') {
    $sql = "SELECT * FROM artisans WHERE artisanId = ?";
} else {
    $sql = "SELECT * FROM clients WHERE clientId = ?";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Security validation failed.';
    } else {
        $action = $_POST['action'] ?? 'update_profile';
        
        if ($action === 'update_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'All password fields are required.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New passwords do not match.';
            } elseif (strlen($new_password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                // Verify password
                $tbl = ($user_type === 'artisan') ? 'artisans' : 'clients';
                $id_col = ($user_type === 'artisan') ? 'artisanId' : 'clientId';
                
                $stmt = $conn->prepare("SELECT password FROM $tbl WHERE $id_col = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $userData = $res->fetch_assoc();
                
                if ($userData && verifyPassword($current_password, $userData['password'])) {
                    $new_hash = hashPassword($new_password);
                    $upd = $conn->prepare("UPDATE $tbl SET password = ? WHERE $id_col = ?");
                    $upd->bind_param("si", $new_hash, $user_id);
                    if ($upd->execute()) {
                        $success = 'Password updated successfully.';
                    } else {
                        $error = 'Failed to update password.';
                    }
                } else {
                    $error = 'Incorrect current password.';
                }
            }
        } else {
        
            $name = sanitize($_POST['name'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $location = sanitize($_POST['location'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            
            
                $profile_image_path = $user['profile_image'] ?? null;
                
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    $filename = $_FILES['profile_image']['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (in_array($ext, $allowed) && $_FILES['profile_image']['size'] <= 5 * 1024 * 1024) {
                        $upload_dir = __DIR__ . '/uploads/profiles/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $new_filename = 'profile_' . $user_id . '_' . uniqid() . '.' . $ext;
                        $target_path = $upload_dir . $new_filename;
                        $db_path = 'uploads/profiles/' . $new_filename;
                        
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
                    
                            if ($profile_image_path && file_exists(__DIR__ . '/' . $profile_image_path)) {
                                @unlink(__DIR__ . '/' . $profile_image_path);
                            }
                            $profile_image_path = $db_path;
                        }
                    }
                }

                if ($user_type === 'artisan') {
                    $category = sanitize($_POST['category'] ?? '');
                    $specialty = sanitize($_POST['specialty'] ?? '');
                    $experience = (int)($_POST['experience_years'] ?? 0);
                    $hourly_rate = (float)($_POST['hourly_rate'] ?? 0);
                    
                    $sql = "UPDATE artisans SET name = ?, phone = ?, location = ?, 
                            description = ?, category = ?, specialty = ?, experience_years = ?, hourly_rate = ?, profile_image = ?
                            WHERE artisanId = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssssiidsi", $name, $phone, $location, $description, 
                                     $category, $specialty, $experience, $hourly_rate, $profile_image_path, $user_id);
                } else {
                    $sql = "UPDATE clients SET name = ?, phone = ?, location = ?, profile_image = ? WHERE clientId = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssi", $name, $phone, $location, $profile_image_path, $user_id);
                }
            
            if ($stmt->execute()) {
                $success = 'Profile updated successfully!';
                $_SESSION['user_name'] = $name;
                // Refresh user data
                if ($user_type === 'artisan') {
                     $sql = "SELECT * FROM artisans WHERE artisanId = ?";
                } else {
                     $sql = "SELECT * FROM clients WHERE clientId = ?";
                }
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
            } else {
                $error = 'Failed to update profile. Please try again. ' . $conn->error;
            }
        }
    }
}

$conn->close();
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ArtisanConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <div class="container">
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                   <?php if (!empty($user['profile_image'])): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                   <?php else: ?>
                        <i class="fas fa-user"></i>
                   <?php endif; ?>
                </div>
                <h1>Edit Profile</h1>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" class="form-control"
                           value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                    <small style="color: #666;">Email cannot be changed</small>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control"
                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="location">Location *</label>
                    <input type="text" id="location" name="location" class="form-control"
                           value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="profile_image">Profile Picture</label>
                    <input type="file" id="profile_image" name="profile_image" accept="image/*" class="form-control">
                    <small style="color: #666;">Recommended: Square image, max 5MB.</small>

                </div>


                
                <?php if ($user_type === 'artisan'): ?>

                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="Carpenter" <?php echo ($user['category'] ?? '') === 'Carpenter' ? 'selected' : ''; ?>>Carpenter</option>
                            <option value="Hairstylist" <?php echo ($user['category'] ?? '') === 'Hairstylist' ? 'selected' : ''; ?>>Hairstylist</option>
                            <option value="Plumber" <?php echo ($user['category'] ?? '') === 'Plumber' ? 'selected' : ''; ?>>Plumber</option>
                            <option value="Tailor" <?php echo ($user['category'] ?? '') === 'Tailor' ? 'selected' : ''; ?>>Tailor</option>
                            <option value="Painter" <?php echo ($user['category'] ?? '') === 'Painter' ? 'selected' : ''; ?>>Painter</option>
                            <option value="Electrician" <?php echo ($user['category'] ?? '') === 'Electrician' ? 'selected' : ''; ?>>Electrician</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="specialty">Specialty</label>
                        <input type="text" id="specialty" name="specialty" class="form-control"
                               value="<?php echo htmlspecialchars($user['specialty'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="experience_years">Years of Experience</label>
                        <input type="number" id="experience_years" name="experience_years" class="form-control" min="0" max="50"
                               value="<?php echo htmlspecialchars($user['experience_years'] ?? 0); ?>">
                    </div>

                    <div class="form-group">
                        <label for="hourly_rate">Hourly Charge (GHS)</label>
                        <input type="number" id="hourly_rate" name="hourly_rate" class="form-control" min="0" step="0.01"
                               value="<?php echo htmlspecialchars($user['hourly_rate'] ?? 0); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Bio / Description</label>
                        <textarea id="description" name="description" class="form-control"><?php echo htmlspecialchars($user['description'] ?? ''); ?></textarea>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn">Update Profile</button>
                <a href="dashboard.php" class="back-link">Cancel</a>
            </form>
            <!-- Password update Section -->
            <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #eee;">
                <h2 style="margin-bottom: 1.5rem;">Security Settings</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_password">
                    
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn" style="background: var(--secondary-color);">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>