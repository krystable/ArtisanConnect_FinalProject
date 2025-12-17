<?php
require_once 'database.php';
startSession();

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['user_type'] === 'artisan') {
    header('Location: dashboard_artisan.php');
} else {
    header('Location: dashboard_client.php');
}
exit();
?>