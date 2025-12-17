<?php
require_once 'database.php';
startSession();

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Only clients can book
if ($_SESSION['user_type'] !== 'client') {
    die("Only clients can make bookings.");
}

$client_id = $_SESSION['user_id'];
$artisan_id = isset($_GET['artisan_id']) ? (int)$_GET['artisan_id'] : 0;
$error = '';
$conn = getDBConnection();

if ($artisan_id === 0) {
    header('Location: browse.php');
    exit();
}

// Get Artisan Info
$stmt = $conn->prepare("SELECT name, hourly_rate, specialty FROM artisans WHERE artisanId = ?");
$stmt->bind_param("i", $artisan_id);
$stmt->execute();
$artisan_result = $stmt->get_result();

if ($artisan_result->num_rows === 0) {
    die("Artisan not found.");
}

$artisan = $artisan_result->fetch_assoc();
$hourly_rate = $artisan['hourly_rate'];

// Handle Booking Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed.';
    } else {
        $service_type = sanitize($_POST['service_type'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $booking_date = $_POST['booking_date'] ?? '';
        $booking_time = $_POST['booking_time'] ?? '';
        $duration = (int)($_POST['duration'] ?? 1);
        
        // Validation
        if (empty($service_type)) $error = 'Please enter a service type.';
        elseif (empty($booking_date)) $error = 'Please select a date.';
        elseif (empty($booking_time)) $error = 'Please select a time.';
        elseif ($duration < 1) $error = 'Duration must be at least 1 hour.';
        else {
            $total_cost = $hourly_rate * $duration;
            
            $stmt = $conn->prepare("INSERT INTO bookings (clientId, artisanId, service_type, description, booking_date, booking_time, duration_hours, total_cost, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("iissssis", $client_id, $artisan_id, $service_type, $description, $booking_date, $booking_time, $duration, $total_cost);
            
            if ($stmt->execute()) {
                header('Location: bookings.php?success=1');
                exit();
            } else {
                $error = 'Booking failed: ' . $conn->error;
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
    <title>Book Artisan - ArtisanConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="logo">
            <i class="fas fa-hands-helping"></i>
            <span>ArtisanConnect</span>
        </a>
        <a href="dashboard.php" class="btn btn-outline" style="width: auto; padding: 8px 16px; margin-left: auto;">Back to Dashboard</a>
    </nav>

    <div class="container">
        <div class="booking-card">
            <h1 style="text-align: center; color: var(--text-dark);">Book Appointment</h1>
            <p class="subtitle" style="text-align: center; color: var(--text-light); margin-bottom: 2rem;">with <?php echo htmlspecialchars($artisan['name']); ?></p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="service_type">Service Type</label>
                    <input type="text" id="service_type" name="service_type" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['service_type'] ?? $artisan['specialty']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="booking_date">Date</label>
                    <input type="date" id="booking_date" name="booking_date" class="form-control" 
                           min="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo htmlspecialchars($_POST['booking_date'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="booking_time">Time</label>
                    <input type="time" id="booking_time" name="booking_time" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['booking_time'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="duration">Duration (Hours)</label>
                    <input type="number" id="duration" name="duration" class="form-control" min="1" max="12" 
                           value="<?php echo htmlspecialchars($_POST['duration'] ?? 1); ?>" required>
                </div>
                
                <div class="cost-summary">
                    <span>Estimated Total</span>
                    <span class="total-cost" id="total_cost">GHS <?php echo number_format($hourly_rate, 2); ?></span>
                </div>
                
                <div class="form-group">
                    <label for="description">Description / Notes</label>
                    <textarea id="description" name="description" class="form-control" placeholder="Describe what you need help with..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Confirm Booking</button>
            </form>
        </div>
    </div>
    
    <script>
        const hourlyRate = parseFloat(<?php echo $hourly_rate ?? 0; ?>);
        const durationInput = document.getElementById('duration');
        const totalDisplay = document.getElementById('total_cost');
        
        function updateTotal() {
            const hours = parseInt(durationInput.value) || 0;
            const total = (hours * hourlyRate).toFixed(2);
            totalDisplay.textContent = 'GHS ' + total;
        }
        
        durationInput.addEventListener('input', updateTotal);
        durationInput.addEventListener('change', updateTotal); 
    </script>
</body>
</html>
