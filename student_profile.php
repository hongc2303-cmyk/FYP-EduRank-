<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = strtoupper($_SESSION['user_name'] ?? '');
$success = '';
$error = '';

// Check pending status for sidebar badge
$pending = 0;
if (isset($pdo)) {
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM award_applications a JOIN students s ON a.student_id = s.student_id WHERE s.user_id = ? AND a.status = 'pending'");
    $stmtCheck->execute([$user_id]);
    $pending = $stmtCheck->fetchColumn();
}

// Fetch Academic Advisors (PA) List for the dropdown
$pa_list = [];
if (isset($pdo)) {
    $stmt_pa = $pdo->query("SELECT user_id, full_name FROM users WHERE role = 'Academic Advisor' ORDER BY full_name");
    $pa_list = $stmt_pa->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Active Classes List for the dropdown
$class_list = [];
if (isset($pdo)) {
    $stmt_class = $pdo->query("SELECT class_name FROM classes WHERE is_active = 1 ORDER BY class_name");
    $class_list = $stmt_class->fetchAll(PDO::FETCH_COLUMN);
}

// Process Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $new_phone = trim($_POST['phone'] ?? '');
    $new_kelas = strtoupper(trim($_POST['kelas'] ?? '')); // This now receives data from the dropdown
    $new_pa_id = filter_var($_POST['pa_id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$new_email) {
        $error = "Please enter a valid email address.";
    } elseif (empty($new_kelas) || empty($new_pa_id)) {
        $error = "Please complete your class information and select your Academic Advisor (PA).";
    } else {
        try {
            $pdo->beginTransaction();
            $stmtS = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
            $stmtS->execute([$user_id]);
            $student_rec = $stmtS->fetch();

            if ($student_rec) {
                // Update users table
                $stmtUser = $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?");
                $stmtUser->execute([$new_email, $user_id]);

                // Update students table (Phone, Programme/Class, Advisor)
                $stmtStudent = $pdo->prepare("UPDATE students SET phone = ?, programme = ?, advisor_id = ? WHERE student_id = ?");
                $stmtStudent->execute([$new_phone, $new_kelas, $new_pa_id, $student_rec['student_id']]);

                $pdo->commit();
                $success = "Your profile has been updated successfully!";
            } else {
                throw new Exception("Student record not found.");
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Failed to update profile. Please try again.";
        }
    }
}

// Process Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all password fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long.";
    } else {
        try {
            $pwd_stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
            $pwd_stmt->execute([$user_id]);
            $user_data = $pwd_stmt->fetch();

            if ($user_data && verifyPassword($current_password, $user_data['password'])) {
                $new_hash = hashPassword($new_password);
                $update_pwd = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $update_pwd->execute([$new_hash, $user_id]);
                $success = "Password changed successfully!";
            } else {
                $error = "Incorrect current password.";
            }
        } catch (PDOException $e) {
            $error = "Failed to change password: " . $e->getMessage();
        }
    }
}

// Fetch Profile Data
if (isset($pdo)) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name, u.email, pa.full_name AS pa_name
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN users pa ON s.advisor_id = pa.user_id
        WHERE s.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">

<div class="d-flex">
    <aside class="sidebar p-4 d-flex flex-column justify-content-between" style="min-height: 100vh;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="fa-solid fa-graduation-cap text-warning me-2 fs-4"></i>
                <span class="fs-5 fw-bold text-white">EduRank</span>
            </div>
            <div class="text-uppercase text-secondary text-xs fw-bold mb-3" style="font-size: 11px; letter-spacing: 1px;">MENU</div>
            <ul class="nav nav-pills flex-column gap-2">
                <li class="nav-item"><a href="student_dashboard.php" class="nav-link d-flex align-items-center gap-3"><i class="bi bi-house-fill"></i> Dashboard</a></li>
                <li class="nav-item"><a href="student_history.php" class="nav-link d-flex align-items-center gap-3"><i class="bi bi-clock-history"></i> Submission History <?php if($pending > 0): ?><span class="badge bg-warning text-dark ms-auto"><?php echo $pending; ?></span><?php endif; ?></a></li>
                <li class="nav-item"><a href="student_apply.php" class="nav-link d-flex align-items-center gap-3"><i class="bi bi-plus-circle"></i> Submit Application</a></li>
                <li class="nav-item"><a href="student_profile.php" class="nav-link active d-flex align-items-center gap-3"><i class="bi bi-person"></i> My Profile</a></li>
            </ul>
        </div>
        <div class="border-top border-secondary pt-3">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px;">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <div>
                    <div class="fw-bold text-sm text-white" style="font-size: 14px;"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="text-secondary" style="font-size: 12px;">Student</div>
                </div>
            </div>
            <a href="logout.php" class="text-danger text-decoration-none text-sm fw-semibold d-flex align-items-center gap-2 ms-1"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </aside>

    <div class="main-content flex-grow-1 d-flex flex-column" style="min-height: 100vh;">
        <header class="top-header shadow-sm">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <?php if (file_exists('notification.php')) include 'notification.php'; ?>
        </header>

        <div class="p-4 flex-grow-1" style="max-width: 1000px;">
            <div class="mb-4">
                <h4 class="fw-bold text-dark mb-1">My Profile & APCP Eligibility Record</h4>
                <p class="text-secondary text-sm">View your personal information, update your Academic Advisor (PA), and manage security settings.</p>
            </div>

            <?php if(!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Personal Information Form -->
                <div class="col-md-7">
                    <div class="card bg-white custom-card shadow-sm p-4 h-100 border-0" style="border-top: 4px solid #2563eb !important;">
                        <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-person-vcard-fill me-2 text-primary"></i>Personal Info</h5>
                        <hr class="text-secondary opacity-25">
                        
                        <form method="POST" action="student_profile.php">
                            <input type="hidden" name="update_profile" value="1">
                            <div class="mb-3">
                                <label class="form-label text-secondary fw-medium" style="font-size: 13px;">Full Name</label>
                                <input type="text" class="form-control bg-light text-muted fw-bold" value="<?php echo htmlspecialchars(strtoupper($student['full_name'] ?? '')); ?>" readonly>
                            </div>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary fw-medium" style="font-size: 13px;">Matric No. / Registration No.</label>
                                    <input type="text" class="form-control bg-light text-muted fw-bold" value="<?php echo htmlspecialchars(strtoupper($student['matric_no'] ?? '')); ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary fw-medium" style="font-size: 13px;">Class / Programme <span class="text-danger">*</span></label>
                                    <select name="kelas" class="form-select border-primary" required>
                                        <option value="">-- Select Class --</option>
                                        <?php foreach($class_list as $class_name): ?>
                                            <option value="<?php echo htmlspecialchars($class_name); ?>" 
                                                <?php echo (strtoupper($student['programme'] ?? '') === strtoupper($class_name)) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($class_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary fw-medium" style="font-size: 13px;">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary fw-medium" style="font-size: 13px;">Phone Number <span class="text-danger">*</span></label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" placeholder="e.g. 01139989677" required>
                                </div>
                            </div>

                            <div class="mb-4 p-3 bg-light rounded border border-warning-subtle">
                                <label class="form-label text-dark fw-bold mb-1"><i class="bi bi-person-check-fill text-warning me-2"></i>Academic Advisor (PA)</label>
                                <p class="text-muted mb-2" style="font-size: 12px;">Please ensure your selected PA is accurate. All your award applications will be reviewed by this advisor.</p>
                                <select name="pa_id" class="form-select border-warning fw-medium" required>
                                    <option value="">-- Select your Academic Advisor --</option>
                                    <?php foreach($pa_list as $pa): ?>
                                        <option value="<?php echo $pa['user_id']; ?>" <?php echo (($student['advisor_id'] ?? 0) == $pa['user_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($pa['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary fw-bold w-100 py-2 shadow-sm">
                                <i class="bi bi-save2-fill me-1"></i> Save Profile Updates
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password Form -->
                <div class="col-md-5">
                    <div class="card bg-white custom-card shadow-sm p-4 h-100 border-0" style="border-top: 4px solid #f59e0b !important;">
                        <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-shield-lock-fill me-2 text-warning"></i>Change Password</h5>
                        <hr class="text-secondary opacity-25">
                        
                        <form method="POST" action="student_profile.php">
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
                            <button type="submit" name="change_password" class="btn btn-dark fw-bold w-100 py-2 shadow-sm">
                                <i class="bi bi-key-fill me-1"></i> Update Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
    <footer class="text-center p-4 mt-5 text-muted border-top" style="background-color: #f8f9fa;">
        <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
    </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>