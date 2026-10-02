<?php
require_once 'config.php';

if (function_exists('requireLogin')) {
    requireLogin();
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // --- 🚨 FIX: CRITICAL SECURITY PATCH (Role Verification) ---
    $user_role = strtolower($_SESSION['user_role'] ?? $_SESSION['role'] ?? '');
    if (!isset($_SESSION['user_id']) || $user_role !== 'admin') {
        header("Location: login.php");
        exit();
    }
    // -----------------------------------------------------------
}

$name = $_SESSION['user_name'] ?? 'System Admin';

// --- FIX: Fetch Current Session for dashboard data isolation ---
$current_session = '';
if (isset($pdo)) {
    $stmtSes = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'current_session'");
    $current_session = $stmtSes->fetchColumn() ?: 'Default Session';
}
// ---------------------------------------------------------------

$total_students = 0;
$total_pas = 0;
$total_committees = 0;
$total_applications = 0;
$recent_activities = []; 
$error_msg = '';

if (isset($pdo)) {
    try {
        // Users are global (no session restriction needed)
        $total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Student' AND is_active = 1")->fetchColumn();
        $total_pas = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Academic Advisor' AND is_active = 1")->fetchColumn();
        $total_committees = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Evaluation Committee' AND is_active = 1")->fetchColumn();
        
        // FIX: Count only applications from the CURRENT SESSION
        $stmt_app = $pdo->prepare("SELECT COUNT(*) FROM award_applications WHERE session_name = ?");
        $stmt_app->execute([$current_session]);
        $total_applications = $stmt_app->fetchColumn();
        
        // FIX: Fetch recent activities ONLY for the CURRENT SESSION
        $stmt_activity = $pdo->prepare("
            SELECT a.application_id, a.status, a.application_date, u.full_name, c.category_name 
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            JOIN award_categories c ON a.category_id = c.category_id
            WHERE a.session_name = ?
            ORDER BY a.application_date DESC
            LIMIT 5
        ");
        $stmt_activity->execute([$current_session]);
        $recent_activities = $stmt_activity->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $error_msg = "Database Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">

    <div class="sidebar d-flex flex-column justify-content-between p-4 no-print" id="sidebar">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-mortarboard-fill text-warning fs-3"></i>
                <span class="fs-4 fw-bold text-white">EduRank</span>
            </div>
            <div class="text-uppercase text-secondary mb-3" style="font-size: 0.75rem; font-weight: 600; padding-left: 12px;">Admin Menu</div>
            
            <a href="admin_dashboard.php" class="nav-link active"><i class="bi bi-grid-fill me-2"></i> Dashboard</a>
            <a href="manage_users.php" class="nav-link"><i class="bi bi-people-fill me-2"></i> Manage Users</a>
            <a href="manage_awards.php" class="nav-link"><i class="bi bi-award-fill me-2"></i> Award Settings</a>
            <a href="manage_classes.php" class="nav-link"><i class="bi bi-building me-2"></i> Manage Classes</a>
            <a href="global_reports.php" class="nav-link"><i class="bi bi-bar-chart-fill me-2"></i> Global Reports</a>
            <a href="audit_logs.php" class="nav-link"><i class="bi bi-journal-text me-2"></i> Audit Logs</a>
            <a href="system_setting.php" class="nav-link"><i class="bi bi-gear-fill me-2"></i> System Settings</a>
        </div>
        <div class="border-top border-secondary pt-3 mt-auto">
            <form method="POST" action="logout.php">
                <button type="submit" class="btn text-danger p-0 border-0 text-sm fw-semibold d-flex align-items-center gap-2 ms-1 bg-transparent" style="cursor: pointer;">
                    <i class="bi bi-box-arrow-right fs-5"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <header class="top-header shadow-sm d-flex justify-content-between align-items-center pe-4">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <div class="text-white small fw-bold">
                <i class="bi bi-person-circle me-1"></i> Welcome, <?php echo htmlspecialchars($name); ?>
            </div>
        </header>

        <div class="p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-dark mb-0">System Overview</h4>
                <span class="badge bg-primary px-3 py-2 fs-6 shadow-sm"><i class="bi bi-calendar-check me-2"></i><?php echo htmlspecialchars($current_session); ?></span>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="custom-card p-4 shadow-sm bg-white border-start border-4 border-primary h-100">
                        <div class="text-secondary small fw-bold mb-1">TOTAL ACTIVE STUDENTS</div>
                        <div class="fs-2 fw-bold text-dark"><?php echo $total_students; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="custom-card p-4 shadow-sm bg-white border-start border-4 border-success h-100">
                        <div class="text-secondary small fw-bold mb-1">ACTIVE P.A.</div>
                        <div class="fs-2 fw-bold text-dark"><?php echo $total_pas; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="custom-card p-4 shadow-sm bg-white border-start border-4 border-warning h-100">
                        <div class="text-secondary small fw-bold mb-1">ACTIVE COMMITTEES</div>
                        <div class="fs-2 fw-bold text-dark"><?php echo $total_committees; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="custom-card p-4 shadow-sm bg-white border-start border-4 border-info h-100">
                        <div class="text-secondary small fw-bold mb-1">CURRENT SESSION APPS</div>
                        <div class="fs-2 fw-bold text-dark"><?php echo $total_applications; ?></div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-8">
                    <div class="custom-card p-4 shadow-sm bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-activity text-primary me-2"></i>Recent Applications Activity (Current Session)</h6>
                            <a href="global_reports.php" class="text-decoration-none small">View Full Report</a>
                        </div>
                        
                        <?php if (empty($recent_activities)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                <span class="small">No recent activities found for the current academic session.</span>
                            </div>
                        <?php else: ?>
                            <div class="activity-feed ps-2">
                                <?php foreach ($recent_activities as $activity): ?>
                                    <?php 
                                        $statusColor = 'text-warning';
                                        $statusIcon = 'bi-hourglass-split';
                                        
                                        if (strtolower($activity['status']) === 'verified') { 
                                            $statusColor = 'text-info'; 
                                            $statusIcon = 'bi-shield-check'; 
                                        } elseif (strtolower($activity['status']) === 'evaluated' || strtolower($activity['status']) === 'approved') { 
                                            $statusColor = 'text-success'; 
                                            $statusIcon = 'bi-check-circle-fill'; 
                                        } elseif (strtolower($activity['status']) === 'nominated') { 
                                            $statusColor = 'text-warning'; 
                                            $statusIcon = 'bi-star-fill'; 
                                        } elseif (strtolower($activity['status']) === 'rejected') { 
                                            $statusColor = 'text-danger'; 
                                            $statusIcon = 'bi-x-circle-fill'; 
                                        }
                                    ?>
                                    <div class="d-flex mb-3 align-items-start position-relative">
                                        <div class="me-3 mt-1 <?php echo $statusColor; ?> bg-light rounded-circle p-2 d-flex justify-content-center align-items-center" style="width: 35px; height: 35px;">
                                            <i class="bi <?php echo $statusIcon; ?> fs-6"></i>
                                        </div>
                                        
                                        <div class="flex-grow-1 pb-2" style="border-bottom: 1px dashed #f1f1f1;">
                                            <div class="small fw-bold text-dark" style="line-height: 1.4;">
                                                <?php echo htmlspecialchars($activity['full_name']); ?> 
                                                <span class="fw-normal text-secondary">submitted an application for</span> 
                                                <span class="text-primary"><?php echo htmlspecialchars($activity['category_name']); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-1">
                                                <span class="text-muted" style="font-size: 0.75rem;">
                                                    <i class="bi bi-clock me-1"></i><?php echo date('d M Y, h:i A', strtotime($activity['application_date'])); ?>
                                                </span>
                                                <span class="badge <?php echo str_replace('text-', 'bg-', $statusColor); ?> text-uppercase <?php echo strtolower($activity['status']) === 'verified' || strtolower($activity['status']) === 'nominated' ? 'text-dark' : ''; ?>" style="font-size: 0.65rem;">
                                                    <?php echo htmlspecialchars($activity['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="custom-card p-4 shadow-sm bg-white h-100">
                        <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <a href="manage_users.php" class="btn btn-outline-primary text-start"><i class="bi bi-person-plus-fill me-2"></i> Register New User</a>
                            <a href="global_reports.php" class="btn btn-outline-success text-start"><i class="bi bi-file-earmark-spreadsheet me-2"></i> Export Global Report</a>
                            <a href="system_setting.php" class="btn btn-outline-dark text-start"><i class="bi bi-calendar-check me-2"></i> Manage Academic Session</a>
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