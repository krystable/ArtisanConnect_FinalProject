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

$message = '';
$error = '';

// Handle Status Updates (Artisan only for Confirm/Complete, Client/Artisan for Cancel)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed.';
    } else {
        $booking_id = (int)$_POST['booking_id'];
        $action = $_POST['action'];
        
        // Verify ownership
        if ($user_type === 'artisan') {
            $check_sql = "SELECT * FROM bookings WHERE bookingId = ? AND artisanId = ?";
        } else {
            $check_sql = "SELECT * FROM bookings WHERE bookingId = ? AND clientId = ?";
        }
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $booking_id, $user_id);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        
        if ($booking) {
            $new_status = '';
            if ($action === 'confirm' && $user_type === 'artisan') {
                $new_status = 'confirmed';
            } elseif ($action === 'complete' && $user_type === 'artisan') {
                $new_status = 'completed';
            } elseif ($action === 'cancel') {
                $new_status = 'cancelled';
            }
            
            if ($new_status) {
                $update_stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE bookingId = ?");
                $update_stmt->bind_param("si", $new_status, $booking_id);
                if ($update_stmt->execute()) {
                    $message = "Booking status updated to " . ucfirst($new_status);
                } else {
                    $error = "Failed to update booking.";
                }
            }
        } else {
            $error = "Booking not found or permission denied.";
        }
    }
}

// Fetch Bookings
if ($user_type === 'artisan') {
    $sql = "SELECT b.*, c.name as other_name, c.profile_image as other_image 
            FROM bookings b 
            JOIN clients c ON b.clientId = c.clientId 
            WHERE b.artisanId = ? 
            ORDER BY b.booking_date ASC, b.booking_time ASC";
} else {
    $sql = "SELECT b.*, a.name as other_name, a.profile_image as other_image, a.category 
            FROM bookings b 
            JOIN artisans a ON b.artisanId = a.artisanId 
            WHERE b.clientId = ? 
            ORDER BY b.booking_date ASC, b.booking_time ASC";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$upcoming = [];
$pending = [];
$past = [];

$today = date('Y-m-d');

while ($row = $result->fetch_assoc()) {
    if ($row['status'] === 'pending') {
        $pending[] = $row;
    } elseif ($row['status'] === 'confirmed') {
        if ($row['booking_date'] >= $today) {
            $upcoming[] = $row;
        } else {
            $past[] = $row; 
        }
    } else {
        $past[] = $row; 
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
    <title>My Bookings - ArtisanConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>


    <div class="container">
        <div class="page-header">
            <h1><?php echo ($user_type === 'artisan') ? 'My Jobs' : 'My Appointments'; ?></h1>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" onclick="showTab('upcoming')">Upcoming</div>
            <div class="tab" onclick="showTab('pending')">Pending</div>
            <div class="tab" onclick="showTab('past')">History</div>
        </div>
        
        <div id="upcoming" class="tab-content">
            <?php renderBookings($upcoming, $user_type, $csrf_token); ?>
        </div>
        
        <div id="pending" class="tab-content" style="display: none;">
            <?php renderBookings($pending, $user_type, $csrf_token); ?>
        </div>
        
        <div id="past" class="tab-content" style="display: none;">
            <?php renderBookings($past, $user_type, $csrf_token); ?>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.getElementById(tabName).style.display = 'block';
            
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            event.target.classList.add('active');
        }
    </script>
    </div>
</body>
</html>

<?php
function renderBookings($bookings, $user_type, $csrf_token) {
    if (empty($bookings)) {
        echo '<p style="color: #666; padding: 2rem; text-align: center;">No bookings found.</p>';
        return;
    }
    
    foreach ($bookings as $b) {
        $status_class = 'status-' . $b['status'];
        echo '<div class="booking-card-list">';
        echo '<div class="booking-info">';
        echo '<div class="booking-header">';
        echo '<div class="user-avatar" style="background-image: url(\'' . (!empty($b['other_image']) ? htmlspecialchars($b['other_image']) : 'https://via.placeholder.com/50') . '\')"></div>';
        echo '<div>';
        echo '<h3 style="margin: 0;">' . htmlspecialchars($b['other_name']) . '</h3>';
        echo '<div class="status-badge ' . $status_class . '" style="display: inline-block; margin-top: 5px;">' . ucfirst($b['status']) . '</div>';
        echo '</div>';
        echo '</div>'; // End header
        
        echo '<div class="booking-details">';
        echo '<div class="detail-item"><i class="fas fa-calendar"></i> ' . date('M d, Y', strtotime($b['booking_date'])) . '</div>';
        echo '<div class="detail-item"><i class="fas fa-clock"></i> ' . date('h:i A', strtotime($b['booking_time'])) . ' (' . $b['duration_hours'] . 'h)</div>';
        echo '<div class="detail-item"><i class="fas fa-money-bill"></i> $' . $b['total_cost'] . '</div>';
        echo '<div class="detail-item"><i class="fas fa-tools"></i> ' . htmlspecialchars($b['service_type']) . '</div>';
        echo '</div>';
        echo '<p style="margin-top: 10px; color: #555;">"' . htmlspecialchars($b['description']) . '"</p>';
        echo '</div>'; // End info
        
        echo '<div class="booking-actions">';
        echo '<a href="messages.php?compose=true&recipient_id=' . ($user_type === 'client' ? $b['artisanId'] : $b['clientId']) . '" class="btn" style="background: #f0f0f0; color: #333;">Message</a>';
        
        if ($b['status'] === 'pending') {
            if ($user_type === 'artisan') {
                echo '<form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="' . $csrf_token . '">
                        <input type="hidden" name="booking_id" value="' . $b['bookingId'] . '">
                        <input type="hidden" name="action" value="confirm">
                        <button type="submit" class="btn btn-confirm" style="width:100%">Confirm</button>
                      </form>';
            }
            echo '<form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="' . $csrf_token . '">
                    <input type="hidden" name="booking_id" value="' . $b['bookingId'] . '">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-danger" style="width:100%" onclick="return confirm(\'Are you sure?\')">Cancel</button>
                  </form>';
        } elseif ($b['status'] === 'confirmed') {
            if ($user_type === 'artisan') {
                echo '<form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="' . $csrf_token . '">
                        <input type="hidden" name="booking_id" value="' . $b['bookingId'] . '">
                        <input type="hidden" name="action" value="complete">
                        <button type="submit" class="btn btn-complete" style="width:100%">Mark Complete</button>
                      </form>';
            }
             echo '<form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="' . $csrf_token . '">
                    <input type="hidden" name="booking_id" value="' . $b['bookingId'] . '">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-danger" style="width:100%" onclick="return confirm(\'Are you sure?\')">Cancel</button>
                  </form>';
        }
        
        echo '</div>'; 
        echo '</div>'; 
    }
}
?>
