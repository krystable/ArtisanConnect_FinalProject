<?php

$servername = "sql311.infinityfree.com";
$username = "if0_40706869";
$db_password = "54WdqUG3JjLVb";
$dbname = "if0_40706869_artisanconnect";

function getDBConnection() {
    global $servername, $username, $db_password, $dbname;
    
    $conn = new mysqli($servername, $username, $db_password, $dbname);
    
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        die("System unavailable. Please try again later.");
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
// Sanitize input
function sanitize($input) {
    if (is_array($input)) {
        foreach($input as $key => $value) {
            $input[$key] = sanitize($value);
        }
        return $input;
    }
    return trim($input);
}
// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
// Start secure session
function startSession() {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

    if (session_status() === PHP_SESSION_NONE) {
        
        $lifetime = 0; 
        $path = '/';
        $domain = ''; 
        $secure = isset($_SERVER['HTTPS']); 
        $httponly = true; 
        
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Strict' 
        ]);
        
        session_start();
    }
}
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}
function logout() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params["path"],
            'domain' => $params["domain"],
            'secure' => $params["secure"],
            'httponly' => $params["httponly"],
            'samesite' => $params["samesite"] ?? 'Strict'
        ]);
    }
    
    session_destroy();
    header('Location: login.php');
    exit();
}
?>
