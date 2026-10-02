<?php
// 1. Include database configuration
require_once 'config.php';

// 2. Start session and verify authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (function_exists('requireLogin')) {
    requireLogin();
} elseif (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 3. CRITICAL SECURITY PATCH: Strict Admin Role Verification
$user_role = strtolower($_SESSION['user_role'] ?? $_SESSION['role'] ?? '');
if ($user_role !== 'admin') {
    // Kick out unauthorized users
    header("Location: login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// ==========================================
// 4. Process Form Submission: Update System Settings 
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $current_session = trim($_POST['current_session'] ?? '');
    $system_status = trim($_POST['system_status'] ?? 'open');

    if (!empty($current_session) && isset($pdo)) {
        try {
            // Update Academic Session
            $stmt1 = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'current_session'");
            $stmt1->execute([$current_session]);

            // Update Global System Status
            $stmt2 = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'system_status'");
            $stmt2->execute([$system_status]);

            $success_msg = "System settings and status have been successfully updated.";
        } catch (PDOException $e) {
            $error_msg = "Database Error: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please enter a valid session name.";
    }
}

// ==========================================
// 5. Fetch Current System Settings
// ==========================================
$current_session_value = '';
$system_status_value = 'open';

if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['setting_key'] === 'current_session') {
                $current_session_value = $row['setting_value'];
            }
            if ($row['setting_key'] === 'system_status') {
                $system_status_value = $row['setting_value'];
            }
        }
    } catch (PDOException $e) {
        $error_msg = "Failed to load settings: " . $e->getMessage();
    }
}

// ==========================================
// 6. NEW: Process Data Purging (Danger Zone)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purge_session_data'])) {
    $target_session = trim($_POST['target_session'] ?? '');
    
    // Security Check: Prevent deleting the active session
    if (empty($target_session)) {
        $error_msg = "Please select a valid session to purge.";
    } elseif ($target_session === $current_session_value) {
        $error_msg = "CRITICAL BLOCK: You cannot delete the currently active academic session!";
    } else {
        if (isset($pdo)) {
            try {
                $pdo->beginTransaction();

                // Step 1: Find all applications related to this old session
                $stmtApp = $pdo->prepare("SELECT application_id FROM award_applications WHERE session_name = ?");
                $stmtApp->execute([$target_session]);
                $appIds = $stmtApp->fetchAll(PDO::FETCH_COLUMN);

                if (count($appIds) > 0) {
                    $placeholders = implode(',', array_fill(0, count($appIds), '?'));

                    // Step 2: Delete child records first to prevent Foreign Key constraints error
                    $pdo->prepare("DELETE FROM certificates WHERE application_id IN ($placeholders)")->execute($appIds);
                    $pdo->prepare("DELETE FROM project_award_details WHERE application_id IN ($placeholders)")->execute($appIds);
                    $pdo->prepare("DELETE FROM application_verifications WHERE application_id IN ($placeholders)")->execute($appIds);
                    $pdo->prepare("DELETE FROM evaluations WHERE application_id IN ($placeholders)")->execute($appIds);

                    // Step 3: Delete the main applications
                    $pdo->prepare("DELETE FROM award_applications WHERE session_name = ?")->execute([$target_session]);
                    
                    $success_msg = "Success! All data (applications, certificates, scores) for session '{$target_session}' has been permanently destroyed.";
                } else {
                    $error_msg = "No data found for session '{$target_session}'.";
                }

                $pdo->commit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error_msg = "Data Purge Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch Past Sessions for the Purge Dropdown
$past_sessions = [];
if (isset($pdo)) {
    try {
        // Exclude the current session from the dropdown list
        $stmt_past = $pdo->prepare("SELECT DISTINCT session_name FROM award_applications WHERE session_name != ? AND session_name IS NOT NULL AND session_name != ''");
        $stmt_past->execute([$current_session_value]);
        $past_sessions = $stmt_past->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - System Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">

    <!-- Sidebar Navigation -->
    <div class="sidebar d-flex flex-column justify-content-between p-4" id="sidebar">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-mortarboard-fill text-warning fs-3"></i>
                <span class="fs-4 fw-bold text-white">EduRank</span>
            </div>
            <div class="text-uppercase text-secondary mb-3" style="font-size: 0.75rem; font-weight: 600; padding-left: 12px;">Admin Menu</div>
            <a href="admin_dashboard.php" class="nav-link"><i class="bi bi-grid-fill me-2"></i> Dashboard</a>
            <a href="manage_users.php" class="nav-link"><i class="bi bi-people-fill me-2"></i> Manage Users</a>
            <a href="manage_awards.php" class="nav-link"><i class="bi bi-award-fill me-2"></i> Award Settings</a>
            <a href="manage_classes.php" class="nav-link"><i class="bi bi-building me-2"></i> Manage Classes</a>
            <a href="global_reports.php" class="nav-link"><i class="bi bi-bar-chart-fill me-2"></i> Global Reports</a>
            <a href="audit_logs.php" class="nav-link"><i class="bi bi-journal-text me-2"></i> Audit Logs</a>
            <a href="system_setting.php" class="nav-link active"><i class="bi bi-gear-fill me-2"></i> System Settings</a>
        </div>
        <div class="border-top border-secondary pt-3 mt-auto">
            <form method="POST" action="logout.php">
                <button type="submit" class="btn text-danger p-0 border-0 text-sm fw-semibold d-flex align-items-center gap-2 ms-1 bg-transparent">
                    <i class="bi bi-box-arrow-right fs-5"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="main-content" id="mainContent">
        <header class="top-header shadow-sm d-flex justify-content-between align-items-center pe-4">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <div class="text-white small fw-bold">
                <i class="bi bi-person-circle me-1"></i> Admin Portal
            </div>
        </header>

        <div class="p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-dark mb-0">System Settings & Maintenance</h4>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <!-- Global Settings Form -->
                    <div class="custom-card p-4 shadow-sm bg-white border-0 mb-4">
                        <h5 class="fw-bold mb-3 text-primary"><i class="bi bi-sliders me-2"></i>Global Application Configurations</h5>
                        <hr class="mb-4">
                        
                        <form method="POST" action="">
                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark">Current Academic Session</label>
                                <input type="text" class="form-control form-control-lg border-primary" name="current_session" value="<?php echo htmlspecialchars($current_session_value); ?>" placeholder="e.g. Sesi 1 2026/2027" required>
                                <div class="form-text text-muted mt-2">
                                    <i class="bi bi-info-circle-fill me-1"></i>
                                    This session name will be automatically tagged to all new award applications. Changing this will hide previous applications from the main dashboards (soft freeze).
                                </div>
                            </div>

                            <!-- Global System Open/Close Toggle -->
                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark">System Application Status (Global Control)</label>
                                <select class="form-select form-select-lg border-primary" name="system_status">
                                    <option value="open" <?php if($system_status_value === 'open') echo 'selected'; ?> class="text-success fw-bold">Open (Students can submit applications)</option>
                                    <option value="closed" <?php if($system_status_value === 'closed') echo 'selected'; ?> class="text-danger fw-bold">Closed (Applications suspended globally)</option>
                                </select>
                                <div class="form-text text-muted mt-2">
                                    <i class="bi bi-info-circle-fill me-1"></i>
                                    If set to Closed, students will see a notice that the application season is currently closed, preventing any new submissions.
                                </div>
                            </div>

                            <button type="submit" name="update_settings" class="btn btn-primary fw-bold px-4 py-2">
                                <i class="bi bi-floppy-fill me-2"></i>Save Settings
                            </button>
                        </form>
                    </div>

                    <!-- 🚨 NEW: DANGER ZONE (Data Purging) 🚨 -->
                    <div class="custom-card p-4 shadow-sm bg-white" style="border: 2px solid #ef4444;">
                        <h5 class="fw-bold mb-3 text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Danger Zone: Data Purging</h5>
                        <p class="text-muted small mb-4">
                            Permanently delete historical award applications, uploaded certificates, and evaluation scores from the database. 
                            <strong>This action cannot be undone.</strong> Only use this to clear out very old data (e.g., beyond 5 years) for server maintenance.
                        </p>
                        
                        <form method="POST" action="" onsubmit="return confirmPurge()">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-8">
                                    <select class="form-select border-danger text-danger fw-bold" name="target_session" required>
                                        <option value="" disabled selected>-- Select Past Session to Delete --</option>
                                        <?php if(empty($past_sessions)): ?>
                                            <option value="" disabled>No past sessions available for deletion</option>
                                        <?php else: ?>
                                            <?php foreach($past_sessions as $ses): ?>
                                                <option value="<?php echo htmlspecialchars($ses['session_name']); ?>">
                                                    Destroy: <?php echo htmlspecialchars($ses['session_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" name="purge_session_data" class="btn btn-danger fw-bold w-100" <?php echo empty($past_sessions) ? 'disabled' : ''; ?>>
                                        <i class="bi bi-trash3-fill me-1"></i> Purge Data
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>

                <div class="col-md-4">
                    <div class="custom-card p-4 shadow-sm bg-light border-0">
                        <h6 class="fw-bold text-secondary mb-3">Administrator Notes</h6>
                        <ul class="text-muted small ps-3 mb-0" style="line-height: 1.8;">
                            <li>Ensure the session format remains consistent (e.g., <strong>Sesi 1 2026/2027</strong>).</li>
                            <li>When a new session starts, simply update the name above. Old data will be safely archived and accessible via Global Reports.</li>
                            <li class="text-danger fw-bold mt-2">Data Purging: Use the Danger Zone ONLY if you want to permanently delete old semester records to free up database storage.</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <footer class="text-center p-4 mt-5 text-muted">
        <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Warning Dialog for Danger Zone -->
    <script>
        function confirmPurge() {
            return confirm("🚨 CRITICAL WARNING 🚨\n\nAre you absolutely sure you want to PERMANENTLY DELETE all student applications, certificates, and scores for the selected session?\n\nThis action CANNOT be reversed. Type OK to proceed.");
        }
    </script>
</body>
</html>