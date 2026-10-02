<?php
require_once 'config.php';
date_default_timezone_set('Asia/Kuala_Lumpur'); 

$error = '';
$success = '';

// Step 1: Securely capture the reset token from the URL or POST request
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (empty($token)) {
    header("Location: login.php");
    exit();
}

// Set the default fallback portal redirection
$back_type = 'student'; 

try {
    // Step 2: Smart Routing Mechanism for Universal User Support
    // Fetch the user's assigned role associated with the token to dictate post-reset redirection
    $stmtUI = $pdo->prepare("
        SELECT u.role 
        FROM password_resets pr 
        JOIN users u ON pr.email = u.email 
        WHERE pr.token = ?
    ");
    $stmtUI->execute([$token]);
    $ui_data = $stmtUI->fetch();

    // Re-route to the staff portal if the user is identified as non-student (e.g., Admin, Staff)
    if ($ui_data && trim(strtolower($ui_data['role'])) !== 'student') {
        $back_type = 'staff';
    }
} catch (PDOException $e) {
    // Catch block silently ignores minor fetch errors to maintain the default student routing baseline
}

try {
    // Step 3: Validate Token Authenticity & User Account Status
    $stmt = $pdo->prepare("
        SELECT pr.*, u.full_name, u.email as user_email, u.role, u.is_active 
        FROM password_resets pr 
        JOIN users u ON pr.email = u.email 
        WHERE pr.token = ? AND pr.used = 0 AND u.is_active = 1
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    // Verify token lifecycle against the 1-hour expiration timestamp
    if ($reset && strtotime($reset['expiry']) < time()) {
        $reset = false; 
    }

    if (!$reset) {
        $error = "This password reset link is invalid or has expired. Please request a new one.";
    } else {
        // Step 4: Process Password Update Submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($new_password) || empty($confirm_password)) {
                $error = "Please fill in all fields.";
            } elseif (strlen($new_password) < 8) {
                $error = "Password must be at least 8 characters long.";
            } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
                $error = "Password must contain uppercase, lowercase letters and numbers.";
            } elseif ($new_password !== $confirm_password) {
                $error = "Passwords do not match.";
            } else {
                // Cryptographically hash the new password entry
                $hashed = function_exists('hashPassword') ? hashPassword($new_password) : password_hash($new_password, PASSWORD_BCRYPT);

                // Update the user's password and simultaneously disable any mandatory first-time login restrictions
                $stmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE email = ?");
                $stmt->execute([$hashed, $reset['email']]);

                // Flag the processed reset token as 'used' to strictly enforce single-use security policies
                $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                $stmt->execute([$token]);

                // Step 5: Execute seamless user redirection determined by the Smart Routing logic in Step 2
                header("Location: login.php?type=" . $back_type . "&msg=" . urlencode("Password reset successful! Please log in."));
                exit();
            }
        }
    }
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
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
        body { background: linear-gradient(135deg, #0f172a 0%, #1a2332 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; padding: 20px; }
        .card { background: #1e293b; border-radius: 20px; max-width: 440px; width: 100%; padding: 36px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
        .form-control { background: #334155; border: 1px solid #475569; color: white; border-radius: 10px; padding: 12px; }
        .form-control:focus { background: #334155; color: white; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.15); }
        .btn-primary { background: #2563eb; border: none; border-radius: 10px; padding: 12px; font-weight: 600; }
        .btn-primary:hover { background: #1d4ed8; }
        .text-secondary { color: #94a3b8 !important; }
        .password-requirements { background: #0f172a; padding: 12px 16px; border-radius: 8px; border: 1px solid #334155; margin-top: 10px; }
        .password-requirements ul { margin-bottom: 0; padding-left: 20px; }
        .password-requirements li { color: #cbd5e1; font-size: 12px; margin-bottom: 4px; }
    </style>
</head>
<body>
<div class="card text-white">
    <div class="text-center mb-3">
        <h4 class="fw-bold"><i class="bi bi-shield-lock text-warning me-2"></i>Reset Password</h4>
        <?php if(!empty($reset['full_name'])): ?>
            <p class="text-secondary small">Hello, <?php echo htmlspecialchars($reset['full_name']); ?></p>
        <?php endif; ?>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger py-2 text-center"><?php echo $error; ?></div>
        <?php if(strpos($error, 'invalid or expired') !== false): ?>
            <!-- Dynamic Retry Action: Navigates the user back to the correct forgot password portal type -->
            <div class="text-center mt-3"><a href="forgot_password.php?type=<?php echo $back_type; ?>" class="btn btn-outline-primary w-100">Request New Reset Link</a></div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if(!$error && !empty($reset)): ?>
        <form method="POST" action="reset_password.php?token=<?php echo urlencode($token); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="mb-3">
                <label class="form-label text-secondary">New Password</label>
                <input type="password" name="new_password" class="form-control" placeholder="Create a strong password" required>

                <div class="password-requirements">
                    <div class="text-secondary small mb-1"><i class="bi bi-info-circle me-1"></i>Password must contain:</div>
                    <ul>
                        <li>At least <strong>8 characters</strong></li>
                        <li>One <strong>uppercase</strong> letter (A-Z)</li>
                        <li>One <strong>lowercase</strong> letter (a-z)</li>
                        <li>One <strong>number</strong> (0-9)</li>
                    </ul>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label text-secondary">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter your new password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-key me-2"></i>Reset Password</button>
        </form>
    <?php endif; ?>

    <div class="text-center mt-4">
        <!-- Dynamic Return Navigation: Accurately directs the user to either the Student or Staff login interface -->
        <a href="login.php?type=<?php echo $back_type; ?>" class="text-decoration-none small text-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Login
        </a>
    </div>
</div>
</body>
</html>