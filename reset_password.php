<?php

require_once 'config.php';

date_default_timezone_set('Asia/Kuala_Lumpur'); // or your timezone
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: login.php");
    exit();
}

try {
    $stmt = $pdo->prepare("
    SELECT pr.*, u.full_name, u.email as user_email 
    FROM password_resets pr 
    JOIN users u ON pr.email = u.email 
    WHERE pr.token = ? 
    AND pr.used = 0 
    AND u.is_active = 1
");
$stmt->execute([$token]);
$reset = $stmt->fetch();

// Manually check expiry in PHP instead of MySQL
if ($reset && strtotime($reset['expiry']) < time()) {
    $reset = false; // Token expired
}
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        $error = "This password reset link is invalid or has expired. Please request a new one.";
    } else {
        // Handle password reset
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validation
            if (empty($new_password) || empty($confirm_password)) {
                $error = "Please fill in all fields.";
            } elseif (strlen($new_password) < 8) {
                $error = "Password must be at least 8 characters long.";
            } elseif (!preg_match('/[A-Z]/', $new_password)) {
                $error = "Password must contain at least one uppercase letter.";
            } elseif (!preg_match('/[a-z]/', $new_password)) {
                $error = "Password must contain at least one lowercase letter.";
            } elseif (!preg_match('/[0-9]/', $new_password)) {
                $error = "Password must contain at least one number.";
            } elseif ($new_password !== $confirm_password) {
                $error = "Passwords do not match.";
            } else {
                // Hash new password
                $hashed = function_exists('hashPassword') ? hashPassword($new_password) : password_hash($new_password, PASSWORD_BCRYPT);
                
                // Update user's password and disable must_change_password flag
                $stmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE email = ?");
                $stmt->execute([$hashed, $reset['email']]);
                
                // Mark token as used
                $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                $stmt->execute([$token]);
                
                // Redirect to login with success message
                header("Location: login.php?msg=" . urlencode("Password reset successful! Please log in with your new password."));
                exit();
            }
        }
    }
} catch (PDOException $e) {
    $error = "System error. Please try again later.";
    // For debugging: $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1a2332 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            padding: 20px;
        }
        .card {
            background: #1e293b;
            border-radius: 20px;
            max-width: 440px;
            width: 100%;
            padding: 36px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .form-control {
            background: #334155;
            border: 1px solid #475569;
            color: white;
            border-radius: 10px;
            padding: 12px;
        }
        .form-control:focus {
            background: #334155;
            color: white;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }
        .btn-primary {
            background: #2563eb;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .text-secondary {
            color: #94a3b8 !important;
        }
        .password-requirements {
            background: #334155;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .password-requirements ul {
            margin-bottom: 0;
            padding-left: 20px;
            color: #94a3b8;
            font-size: 13px;
        }
        .password-requirements li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
<div class="card text-white">
    <div class="text-center mb-3">
        <h4 class="fw-bold">
            <i class="bi bi-shield-lock text-warning me-2"></i>Reset Password
        </h4>
        <?php if(isset($reset['full_name'])): ?>
            <p class="text-secondary small">Hello, <?php echo htmlspecialchars($reset['full_name']); ?></p>
        <?php endif; ?>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger py-2">
            <i class="bi bi-exclamation-circle me-1"></i>
            <?php echo $error; ?>
        </div>
        <?php if(strpos($error, 'invalid or expired') !== false): ?>
            <div class="text-center mt-3">
                <a href="forgot_password.php" class="btn btn-outline-primary w-100">
                    Request New Reset Link
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if(!$error && isset($reset)): ?>
        <div class="password-requirements mb-3">
            <small class="text-secondary d-block mb-2">Password Requirements:</small>
            <ul>
                <li>At least 8 characters</li>
                <li>At least one uppercase letter</li>
                <li>At least one lowercase letter</li>
                <li>At least one number</li>
            </ul>
        </div>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label text-secondary">New Password</label>
                <input type="password" name="new_password" class="form-control" 
                       placeholder="Enter new password" required>
            </div>

            <div class="mb-4">
                <label class="form-label text-secondary">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" 
                       placeholder="Re-enter new password" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-key me-2"></i>Reset Password
            </button>
        </form>
    <?php endif; ?>

    <div class="text-center mt-3">
        <a href="login.php" class="text-decoration-none small text-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Login
        </a>
    </div>
</div>
</body>
</html>