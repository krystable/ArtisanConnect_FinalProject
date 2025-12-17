<?php
require_once 'database.php';
startSession();

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            $conn = getDBConnection();
            
            $sql = "SELECT * FROM artisans WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $type = 'artisan';
            $id_field = 'artisanId';
            
            if (!$user) {
                // Check clients
                 $sql = "SELECT * FROM clients WHERE email = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $type = 'client';
                $id_field = 'clientId';
            }
            
            if ($user && verifyPassword($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user[$id_field];
                $_SESSION['user_type'] = $type;
                $_SESSION['user_name'] = $user['name'];
                
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
            
            $stmt->close();
            $conn->close();
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ArtisanConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-body">
    <div class="auth-container reverse">
        <div class="image-section" style="background-image: url('https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80');">
            <div class="image-content">
                <h2>Connecting Artisans with Opportunities</h2>
            </div>
        </div>
        
        <div class="auth-card">
            <div class="logo-area">
                <h1><i class="fas fa-hammer"></i> ArtisanConnect</h1>
                <p>Welcome back! Please login to continue.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form action="login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-with-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" class="form-control" required placeholder="you@example.com">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password">
                </div>
            </div>

            <button type="submit" class="btn">
                Login <i class="fas fa-sign-in-alt" style="margin-left: 8px;"></i>
            </button>
        </form>
            
            <div class="divider">
                <span>New to ArtisanConnect?</span>
            </div>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php">Join our community</a></p>
            </div>
            
    
        </div>
    </div>
    
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in both email and password fields.');
            }
        });
    </script>
</body>
</html>