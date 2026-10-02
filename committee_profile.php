<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$name = $_SESSION['user_name'] ?? 'Committee Member';
$role = $_SESSION['user_role'] ?? 'Evaluation Committee'; 
$success_msg = '';
$error_msg = '';

// --- 🌟 获取当前学期 & 计算全局待评估数量 ---
$current_session = '';
$pending_evaluations = 0;
if (isset($pdo)) {
    $stmtSes = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'current_session'");
    $current_session = $stmtSes->fetchColumn() ?: 'Default Session';
    
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM award_applications WHERE status = 'verified' AND session_name = ?");
    $stmtCount->execute([$current_session]);
    $pending_evaluations = $stmtCount->fetchColumn();
}
// ---------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $new_name = trim($_POST['full_name']);
        if (!empty($new_name)) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
                $stmt->execute([$new_name, $user_id]);
                $_SESSION['user_name'] = $new_name; 
                $name = $new_name;
                $success_msg = "Profile updated successfully!";
            } catch (PDOException $e) {
                $error_msg = "Database Error: " . $e->getMessage();
            }
        } else {
            $error_msg = "Full Name cannot be empty.";
        }
    } 
    elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $error_msg = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error_msg = "New password must be at least 8 characters.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                $is_valid = false;
                if (function_exists('verifyPassword')) {
                    $is_valid = verifyPassword($current_password, $user['password']);
                } else {
                    $is_valid = password_verify($current_password, $user['password']);
                }

                if ($user && $is_valid) {
                    $hashed_password = function_exists('hashPassword') ? hashPassword($new_password) : password_hash($new_password, PASSWORD_BCRYPT);
                    $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $update_stmt->execute([$hashed_password, $user_id]);
                    $success_msg = "Password changed successfully!";
                } else {
                    $error_msg = "Incorrect current password.";
                }
            } catch (PDOException $e) {
                $error_msg = "Database Error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - Committee Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">

    <!-- 🌟 SMART SIDEBAR (Automatically highlights active page) -->
    <div class="sidebar d-flex flex-column justify-content-between p-4" id="sidebar">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-mortarboard-fill text-warning fs-3"></i>
                <span class="fs-4 fw-bold text-white">EduRank </span>
            </div>
            <div class="text-uppercase text-secondary mb-3" style="font-size: 0.75rem; font-weight: 600; padding-left: 12px;">Menu</div>
            
            <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
            
            <a href="committee_dashboard.php" class="nav-link <?php echo $currentPage == 'committee_dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-grid-fill me-2"></i> Dashboard
            </a>
            
            <a href="candidate_discovery.php" class="nav-link <?php echo $currentPage == 'candidate_discovery.php' ? 'active' : ''; ?>">
                <i class="bi bi-compass-fill me-2"></i> Discovery
            </a>
            
            <a href="applications.php" class="nav-link d-flex align-items-center <?php echo in_array($currentPage, ['applications.php', 'evaluate.php']) ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-text me-2"></i> Applications
                <?php if (isset($pending_evaluations) && $pending_evaluations > 0): ?>
                    <span class="badge bg-warning text-dark ms-auto rounded-pill shadow-sm" style="font-size: 0.75rem;"><?php echo $pending_evaluations; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="reports.php" class="nav-link <?php echo $currentPage == 'reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart-line me-2"></i> Reports
            </a>
            
            <a href="ai_analysis.php" class="nav-link <?php echo $currentPage == 'ai_analysis.php' ? 'active' : ''; ?>">
                <i class="bi bi-magic me-2"></i> AI Analysis
            </a>
            
            <a href="committee_profile.php" class="nav-link <?php echo $currentPage == 'committee_profile.php' ? 'active' : ''; ?>">
                <i class="bi bi-person me-2"></i> Profile
            </a>
        </div>
        <div class="border-top border-secondary pt-4 mt-auto">
            <div class="d-flex align-items-center gap-3 mb-4 ms-1">
                <div class="rounded-circle bg-secondary d-flex justify-content-center align-items-center text-white fw-bold shadow-sm" style="width: 42px; height: 42px; font-size: 1.2rem;"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
                <div class="d-flex flex-column text-white" style="line-height: 1.2; overflow: hidden;">
                    <span class="fw-semibold text-truncate" style="font-size: 0.9rem;" title="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></span>
                    <span class="text-secondary text-truncate mt-1" style="font-size: 0.75rem;" title="<?php echo htmlspecialchars($role); ?>"><?php echo htmlspecialchars($role); ?></span>
                </div>
            </div>
            <form method="POST" action="logout.php">
                <button type="submit" class="btn text-danger p-0 border-0 text-sm fw-bold d-flex align-items-center gap-2 ms-1 bg-transparent" style="cursor: pointer;"><i class="bi bi-box-arrow-right fs-5"></i> Logout</button>
            </form>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <header class="top-header shadow-sm d-flex justify-content-between align-items-center pe-4">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <?php include 'notification.php'; ?>
        </header>

        <div class="p-4">
            <h4 class="fw-bold text-dark mb-4">Committee Profile</h4>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success d-flex align-items-center"><i class="bi bi-check-circle-fill me-2"></i><div><?php echo htmlspecialchars($success_msg); ?></div></div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><div><?php echo htmlspecialchars($error_msg); ?></div></div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="custom-card p-4 shadow-sm bg-white h-100">
                        <h5 class="fw-bold mb-4"><i class="bi bi-person-fill text-primary me-2"></i>Personal Info</h5>
                        <form method="POST" action="">
                            <div class="mb-4">
                                <label class="form-label text-secondary small fw-semibold">Full Name / Username</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" required>
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary w-100"><i class="bi bi-save me-2"></i>Save Changes</button>
                        </form>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="custom-card p-4 shadow-sm bg-white h-100">
                        <h5 class="fw-bold mb-4"><i class="bi bi-shield-lock-fill text-warning me-2"></i>Change Password</h5>
                        <form method="POST" action="">
                            <div class="mb-3"><label class="form-label text-secondary small fw-semibold">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                            <div class="mb-3"><label class="form-label text-secondary small fw-semibold">New Password</label><input type="password" name="new_password" class="form-control" placeholder="Min 8 characters" required minlength="8"></div>
                            <div class="mb-4"><label class="form-label text-secondary small fw-semibold">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required minlength="8"></div>
                            <button type="submit" name="change_password" class="btn btn-dark w-100"><i class="bi bi-key me-2"></i>Change Password</button>
                        </form>
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