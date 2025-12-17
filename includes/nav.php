<?php
// Function to check if a link is active
function isActive($page) {
    return basename($_SERVER['PHP_SELF']) === $page ? 'active' : '';
}
?>
<nav class="navbar">
    <a href="dashboard.php" class="logo">
        <i class="fas fa-hands-helping"></i>
        <span>ArtisanConnect</span>
    </a>
    <div class="nav-links">
        <a href="dashboard.php" class="<?php echo isActive('dashboard.php'); ?>">Dashboard</a>
        
        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'client'): ?>
            <a href="browse.php" class="<?php echo isActive('browse.php'); ?>">Browse</a>
        <?php endif; ?>

        <a href="bookings.php" class="<?php echo isActive('bookings.php'); ?>">My Bookings</a>
        <a href="messages.php" class="<?php echo isActive('messages.php'); ?>">Messages</a>
        
        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'artisan'): ?>
            <a href="portfolio.php" class="<?php echo isActive('portfolio.php'); ?>">Portfolio</a>
        <?php endif; ?>

        <a href="profile.php" class="<?php echo isActive('profile.php'); ?>">Profile</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</nav>

<script>
    // 1. Mark session as active whenever a protected page loads
    sessionStorage.setItem('session_active', 'true');

    // 2. Handle Back Button Navigation
    window.addEventListener('pageshow', function(event) {
        // Optimization: If session flag is gone (user logged out), redirect instantly!
        // This avoids the network delay of reloading the page.
        if (!sessionStorage.getItem('session_active')) {
            window.location.replace('login.php');
            return;
        }

        // Security: If page loaded from back-forward cache (bfcache), force reload
        // to verify session with server
        if (event.persisted) {
            window.location.reload();
        }
    });

    // 3. Handle Explicit Logout
    const logoutBtn = document.querySelector('.logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            // Remove the flag so back button knows we are logged out
            sessionStorage.removeItem('session_active');
        });
    }

    // 4. Prevent 'flash' of content by blanking the page on exit
    window.addEventListener('pagehide', function() {
       document.body.style.opacity = '0';
    });
</script>
