<?php
require_once 'database.php';
startSession();

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed. Please refresh the page.';
    } else {
        // Get and sanitize form data
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $user_type = sanitize($_POST['user_type'] ?? '');
        
        // Basic validation
        if (empty($name) || strlen($name) < 2) {
            $error = 'Name must be at least 2 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif (!in_array($user_type, ['client', 'artisan'])) {
            $error = 'Please select a user type.';
        } else {
            // Check if email exists
            $conn = getDBConnection();
            
            // Check both tables
            $sql = "SELECT email FROM artisans WHERE email = ? UNION SELECT email FROM clients WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $email, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Email already registered. Please use a different email.';
            } else {
                // Hash password
                $hashed_password = hashPassword($password);
                
                // Insert into appropriate table
                if ($user_type === 'artisan') {
                    $sql = "INSERT INTO artisans (name, email, password) VALUES (?, ?, ?)";
                } else {
                    $sql = "INSERT INTO clients (name, email, password) VALUES (?, ?, ?)";
                }
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    $error = "System error: could not prepare statement.";
                } else {
                    $stmt->bind_param("sss", $name, $email, $hashed_password);
                    
                    if ($stmt->execute()) {
                        $success = 'Registration successful! You can now login.';
                        // Clear form
                        $_POST = [];
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                }
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
    <title>Register - ArtisanConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-body">
    <div class="auth-container reverse">
        <div class="image-section" style="background-image: url('https://images.unsplash.com/photo-1531206715517-5c0ba140b2b8?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80');">
            <div class="image-content">
                <h2>Empower Your Craft</h2>
            </div>
        </div>
        
        <div class="auth-card">
            <div class="logo-area">
                <h1><i class="fas fa-hammer"></i> ArtisanConnect</h1>
                <p>Join our thriving community</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <p style="margin-top: 10px"><a href="login.php">Click here to Login</a></p>
                </div>
            <?php else: ?>
                <form action="register.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group">
                        <label>I want to join as a:</label>
                        <div class="radio-group">
                            <label class="radio-label" onclick="selectRadio(this)">
                                <input type="radio" name="user_type" value="client" <?php echo (!isset($_POST['user_type']) || $_POST['user_type'] === 'client') ? 'checked' : ''; ?>>
                                <i class="fas fa-user"></i> Client
                            </label>
                            <label class="radio-label" onclick="selectRadio(this)">
                                <input type="radio" name="user_type" value="artisan" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'artisan') ? 'checked' : ''; ?>>
                                <i class="fas fa-tools"></i> Artisan
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required placeholder="John Doe">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required placeholder="you@example.com">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" class="form-control" required placeholder="Min. 6 characters">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">Create Account <i class="fas fa-arrow-right" style="margin-left: 8px;"></i></button>
                </form>
            <?php endif; ?>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>

    <script>
        function selectRadio(element) {
            document.querySelectorAll('.radio-label').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            element.querySelector('input').checked = true;
        }
        

        document.addEventListener('DOMContentLoaded', () => {
            const checked = document.querySelector('input[type="radio"]:checked');
            if(checked) {
                checked.closest('.radio-label').classList.add('selected');
            }


            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const name = document.getElementById('name').value;
                    const email = document.getElementById('email').value;
                    const password = document.getElementById('password').value;
                    let error = '';

     
                    const nameRegex = /^[a-zA-Z\s]+$/;
                    if (!nameRegex.test(name)) {
                        error = 'Name validation failed: Only letters and spaces are allowed.';
                    }

   
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!error && !emailRegex.test(email)) {
                        error = 'Email validation failed: Please enter a valid email address.';
                    }

        
                    const passwordRegex = /^.{6,}$/;
                    if (!error && !passwordRegex.test(password)) {
                        error = 'Password validation failed: Must be at least 6 characters long.';
                    }

                    if (error) {
                        e.preventDefault();
                        alert(error);
                    }
                });
            }
        });
    </script>
</body>
</html>