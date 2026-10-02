<?php
require_once 'config.php';

// verify the role
if (function_exists('requireLogin')) {
    requireLogin();
} else {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// get the PA information at database
try {
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $pa_info = $stmt->fetch();
} catch (PDOException $e) {
    $error_msg = "Database error: " . $e->getMessage();
}

// 2. update personal information form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $email     = sanitizeInput($_POST['email'] ?? '');

    if (empty($full_name) || empty($email)) {
        $error_msg = "Please fill in all profile fields.";
    } elseif (!validateEmail($email)) {
        $error_msg = "Invalid email address format.";
    } else {
        try {
            // update the new information at database
            $update_stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
            $update_stmt->execute([$full_name, $email, $user_id]);

            $_SESSION['user_name']  = $full_name;
            $_SESSION['user_email'] = $email;
            
            $pa_info['full_name'] = $full_name;
            $pa_info['email']     = $email;

            $success_msg = "Profile updated successfully!";
        } catch (PDOException $e) {
            $error_msg = "Failed to update profile: " . $e->getMessage();
        }
    }
}

// 3. change the password form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_msg = "Please fill in all password fields.";
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_msg = "New password must be at least 8 characters long.";
    } else {
        try {
            $pwd_stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
            $pwd_stmt->execute([$user_id]);
            $user_data = $pwd_stmt->fetch();

            //check the old password right or wrong
            if ($user_data && verifyPassword($current_password, $user_data['password'])) {
                // upadate new password
                $new_hash = hashPassword($new_password);
                $update_pwd = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $update_pwd->execute([$new_hash, $user_id]);

                $success_msg = "Password changed successfully!";
            } else {
                $error_msg = "Incorrect current password.";
            }
        } catch (PDOException $e) {
            $error_msg = "Password update failed: " . $e->getMessage();
        }
    }
}

$name = $pa_info['full_name'] ?? $_SESSION['user_name'] ?? 'Dr. Sarah Smith';
$role = $_SESSION['user_role'] ?? 'Academic Advisor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PA Profile - EduAwards</title>
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- use CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">

<div class="d-flex">
    
    <aside class="sidebar p-4 d-flex flex-column justify-content-between">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="fa-solid fa-graduation-cap text-warning me-2 fs-4"></i>
                <span class="fs-5 fw-bold text-white">EduRank</span>
            </div>


            <div class="text-uppercase text-secondary text-xs fw-bold mb-3" style="font-size: 11px; letter-spacing: 1px;">MENU</div>

            <ul class="nav nav-pills flex-column gap-2">
                <li class="nav-item">
                    <a href="pa_dashboard.php" class="nav-link d-flex align-items-center gap-3">
                        <i class="bi bi-grid-fill"></i> Dashboard Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a href="pa_review.php" class="nav-link d-flex align-items-center gap-3">
                        <i class="bi bi-check-circle"></i> Review Applications
                    </a>
                </li>
                <li class="nav-item">
                    <a href="pa_history.php" class="nav-link d-flex align-items-center gap-3">
                        <i class="bi bi-clock-history"></i> Advisee History
                    </a>
                </li>
                <li class="nav-item">
                    <a href="pa_profile.php" class="nav-link active d-flex align-items-center gap-3">
                        <i class="bi bi-person"></i> Profile
                    </a>
                </li>
            </ul>
        </div>

        <div class="border-top border-secondary pt-3">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px;">
                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                </div>
                <div>
                    <div class="fw-bold text-sm text-white" style="font-size: 14px;"><?php echo htmlspecialchars($name); ?></div>
                    <div class="text-secondary" style="font-size: 12px;"><?php echo htmlspecialchars($role); ?></div>
                </div>
            </div>
            <a href="logout.php" class="text-danger text-decoration-none text-sm fw-semibold d-flex align-items-center gap-2 ms-1">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Area -->
    <div class="main-content flex-grow-1">
        
        <header class="top-header shadow-sm">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <?php include 'notification.php'; ?>
        </header>

        <div class="p-4" style="max-width: 900px;">
            <h4 class="fw-bold mb-4 text-dark">My Profile</h4>

            <!-- (Success / Error) -->
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo $success_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- (Personal Information) -->
                <div class="col-md-6">
                    <div class="card bg-white custom-card shadow-sm p-4 h-100">
                        <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Personal Info</h5>
                        <hr class="text-secondary opacity-25">

                        <form method="POST" action="pa_profile.php">
                            <div class="mb-3">
                                <label class="form-label text-secondary fw-medium" style="font-size: 13px;">Full Name</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($pa_info['full_name'] ?? $name); ?>" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label text-secondary fw-medium" style="font-size: 13px;">Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($pa_info['email'] ?? ''); ?>" required>
                            </div>

                            <button type="submit" name="update_profile" class="btn btn-primary fw-bold w-100 py-2">
                                <i class="bi bi-save me-1"></i> Update Profile
                            </button>
                        </form>
                    </div>
                </div>

                <!-- (Change Password Form) -->
                <div class="col-md-6">
                    <div class="card bg-white custom-card shadow-sm p-4 h-100">
                        <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-shield-lock-fill me-2 text-warning"></i>Change Password</h5>
                        <hr class="text-secondary opacity-25">

                        <form method="POST" action="pa_profile.php">
                            <div class="mb-3">
                                <label class="form-label text-secondary fw-medium" style="font-size: 13px;">Current Password</label>
                                <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-secondary fw-medium" style="font-size: 13px;">New Password</label>
                                <input type="password" name="new_password" class="form-control" placeholder="Minimum 8 characters" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label text-secondary fw-medium" style="font-size: 13px;">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required>
                            </div>

                            <button type="submit" name="change_password" class="btn btn-dark fw-bold w-100 py-2">
                                <i class="bi bi-key me-1"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<footer class="text-center p-4 mt-5 text-muted">
    <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
</footer>
</body>
</html>