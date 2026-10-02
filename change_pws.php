<?php
require_once 'config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['require_password_change'])) {
    header("Location: login.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        try {
            $hashed_password = function_exists('hashPassword') ? hashPassword($new_password) : password_hash($new_password, PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password = ?, must_change_password = 0 
                WHERE user_id = ?
            ");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            session_unset();
            session_destroy();

            
            header("Location: login.php?type=staff&msg=" . urlencode("Password updated successfully! Please log in with your new password."));
            exit();
            
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - First-time Password Change</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { 
            background: linear-gradient(135deg, #0f172a 0%, #1a2332 100%);
            min-height: 100vh; 
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .login-card { 
            background-color: #1e293b; 
            border: none; 
            max-width: 440px;
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            padding: 36px 32px;
        }
        .form-control { 
            background-color: #334155; 
            border: 1px solid #475569; 
            color: white; 
            border-radius: 10px;
            padding: 12px 14px;
        }
        .form-control:focus { 
            background-color: #334155; 
            color: white; 
            border-color: #2563eb; 
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }
        .form-label { color: #94a3b8; font-size: 13px; font-weight: 500; }
        .btn-primary {
            background-color: #2563eb;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
        }
        .btn-primary:hover { background-color: #1d4ed8; }
        .alert { border-radius: 10px; font-size: 13px; }
        .info-notice {
            background: #2563eb20;
            border: 1px solid #2563eb40;
            border-radius: 12px;
            padding: 14px;
            color: #93c5fd;
            font-size: 13px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="card login-card">
    <div class="text-center mb-3">
        <h4 class="text-white fw-bold"><i class="bi bi-shield-lock text-warning me-2"></i>Change Password</h4>
        <p class="text-secondary small">For security reasons, you must change your default password on first login.</p>
    </div>

    <div class="info-notice">
        <i class="bi bi-info-circle me-1"></i> Account: <strong><?php echo htmlspecialchars($_SESSION['user_email']); ?></strong>
    </div>

    <?php if(!empty($error)): ?>
        <div class="alert alert-danger py-2 text-center">
            <i class="bi bi-exclamation-circle me-1"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="change_pws.php">
        <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Enter new password" required>
        </div>
        <div class="mb-4">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-key me-2"></i>Update Password & Re-login
        </button>
    </form>
</div>
</body>
</html>