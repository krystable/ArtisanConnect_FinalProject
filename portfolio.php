<?php
require_once 'database.php';
startSession();

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];


if ($user_type !== 'artisan') {
    header('Location: dashboard.php');
    exit();
}

$conn = getDBConnection();
$message = '';
$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed.';
    } else {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $category = sanitize($_POST['category']); 
        
        if (isset($_FILES['image'])) {
            if ($_FILES['image']['error'] === 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $filename = $_FILES['image']['name'];
                $filetype = $_FILES['image']['type'];
                $filesize = $_FILES['image']['size'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowed)) {
                    $error = 'Invalid file format. Only JPG, PNG, WEBP allowed.';
                } elseif ($filesize > 5 * 1024 * 1024) { 
                    $error = 'File size too large. Max 5MB.';
                } else {
                   
                    $upload_dir = __DIR__ . '/uploads/portfolio/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $new_filename = uniqid() . '.' . $ext;
                    $target_file = $upload_dir . $new_filename;
                    
                  
                    $db_path = 'uploads/portfolio/' . $new_filename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        $stmt = $conn->prepare("INSERT INTO portfolios (artisanId, title, description, image_url, category) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("issss", $user_id, $title, $description, $db_path, $category);
                        if ($stmt->execute()) {
                            $message = 'Portfolio item added successfully.';
                        } else {
                            $error = 'Database error: ' . $conn->error;
                        }
                    } else {
                        $error = 'Failed to move uploaded file. Check directory permissions.';
                    }
                }
            } else {
        
                switch ($_FILES['image']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                        $error = 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $error = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error = 'The uploaded file was only partially uploaded.';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error = 'No file was uploaded.';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error = 'Missing a temporary folder.';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error = 'Failed to write file to disk.';
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $error = 'A PHP extension stopped the file upload.';
                        break;
                    default:
                        $error = 'Unknown upload error.';
                        break;
                }
            }
        } else {
            $error = 'Please select an image.';
        }
    }
}

//Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed.';
    } else {
        $portfolio_id = (int)$_POST['portfolio_id'];
        
        // Check 
        $stmt = $conn->prepare("SELECT image_url FROM portfolios WHERE portfolioId = ? AND artisanId = ?");
        $stmt->bind_param("ii", $portfolio_id, $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($row = $res->fetch_assoc()) {
           
            if (file_exists($row['image_url'])) {
                unlink($row['image_url']);
            }
            

            $del_stmt = $conn->prepare("DELETE FROM portfolios WHERE portfolioId = ?");
            $del_stmt->bind_param("i", $portfolio_id);
            $del_stmt->execute();
            $message = 'Item deleted.';
        } else {
            $error = 'Item not found or permission denied.';
        }
    }
}


$stmt = $conn->prepare("SELECT * FROM portfolios WHERE artisanId = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

$conn->close();
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Portfolio - ArtisanConnect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Manage Portfolio</h1>
            <button class="btn btn-primary" onclick="toggleUpload()"><i class="fas fa-plus"></i> Add New Item</button>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div id="uploadForm" class="upload-section" style="display: none; background: white; padding: 2rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); margin-bottom: 2rem; border: 1px solid rgba(0,0,0,0.03);">
            <h3 style="margin-bottom: 1.5rem;">Add New Portfolio Item</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="upload">
                
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" class="form-control" placeholder="e.g. Modern, Renovation, etc.">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*" required>
                    <small style="color: #666; display: block; margin-top: 5px;">Supported formats: JPG, PNG, WEBP. Max size: 5MB.</small>
                </div>
                
                <button type="submit" class="btn btn-primary">Upload Item</button>
                <button type="button" class="btn btn-secondary" onclick="toggleUpload()">Cancel</button>
            </form>
        </div>
        
        <div class="portfolio-grid">
            <?php foreach ($items as $item): ?>
                <div class="portfolio-item" style="position: relative;">
                    <div class="portfolio-img" style="background-image: url('<?php echo htmlspecialchars($item['image_url']); ?>')"></div>
                    
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this item?');" style="position: absolute; top: 10px; right: 10px;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="portfolio_id" value="<?php echo $item['portfolioId']; ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8rem;"><i class="fas fa-trash"></i></button>
                    </form>
                    
                    <div class="portfolio-info">
                        <div class="portfolio-title"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div class="portfolio-desc"><?php echo htmlspecialchars($item['description']); ?></div>
                        <span class="badge badge-primary" style="margin-top: 10px; display: inline-block;"><?php echo htmlspecialchars($item['category'] ?? ''); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($items)): ?>
            <p style="text-align: center; color: #666; margin-top: 3rem;">No items in your portfolio yet. Add some to showcase your work!</p>
        <?php endif; ?>
    </div>
    
    <script>
        function toggleUpload() {
            var form = document.getElementById('uploadForm');
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
               
                form.scrollIntoView({behavior: 'smooth'});
            } else {
                form.style.display = 'none';
            }
        }
    </script>
    </div>
</body>
</html>
