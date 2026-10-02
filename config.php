<?php
// config.php

// ==========================================
// 1. Session Management
// ==========================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Kuala_Lumpur');

// ==========================================
// 2. Environment Configuration
// ==========================================
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');

if ($is_localhost) {
    // ------------------------------------------
    // [MODE A] Local Environment Setup
    // ------------------------------------------
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'edurank_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');

    // Replace '/FYP' with the actual local project folder name if different
    define('BASE_URL', 'http://localhost/FYP'); 

    // Enable error reporting for local debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

} else {
    // ------------------------------------------
    // [MODE B] Live Hosting Environment Setup
    // ------------------------------------------
    define('DB_HOST', 'sql312.infinityfree.com'); 
    
    // IMPORTANT: Assuming you named your database 'edurank' in cPanel. 
    // If you named it something else, change the word 'edurank' below!
    define('DB_NAME', 'if0_42872761_edurank_db'); 
    
    define('DB_USER', 'if0_42872761');   
    define('DB_PASS', 'eL6qIGqEbWOsNr'); 

    // 🚀 这里换成了你真实的 InfinityFree 域名！
    define('BASE_URL', 'https://edurankpsmza.free.nf'); 

    // Disable standard error reporting for security
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ==========================================
// 3. PDO Database Connection
// ==========================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    if ($is_localhost) {
        die("Connection failed (Local): " . $e->getMessage());
    } else {
        // TEMPORARY DEBUG MODE: Prints the exact SQL error to the screen.
        die("Live DB Error: " . $e->getMessage()); 
    }
}

// ==========================================
// 4. Helper Functions
// ==========================================

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Sanitize input to prevent XSS attacks
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

// Redirect unauthorized users to the login page
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
}
?>