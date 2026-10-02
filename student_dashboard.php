<?php
// 1. Include database configuration file
require_once 'config.php';

// 2. Check if user is logged in as Student
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit();
}

// 3. Get student session details
$user_id = $_SESSION['user_id'];
$user_name = strtoupper($_SESSION['user_name'] ?? '');

// 4. Fetch student profile, applications and stats
try {
    $stmt = $pdo->prepare("SELECT s.* FROM students s WHERE s.user_id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();

    if (!$student) {
        session_destroy();
        header("Location: login.php");
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT a.*, c.category_name, cert.extra_data AS student_cgpa, v.remarks AS pa_remarks 
        FROM award_applications a 
        JOIN award_categories c ON a.category_id = c.category_id 
        LEFT JOIN certificates cert ON a.application_id = cert.application_id AND cert.certificate_type = 'a' 
        LEFT JOIN application_verifications v ON a.application_id = v.application_id 
        WHERE a.student_id = ? 
        ORDER BY a.application_date DESC
    ");
    $stmt->execute([$student['student_id']]);
    $applications = $stmt->fetchAll();
    
    $total_applications = count($applications);
    $pending = 0; $verified = 0; $rejected = 0; $evaluated = 0;
    
    foreach($applications as $app) {
        switch($app['status']) {
            case 'pending': $pending++; break;
            case 'verified': $verified++; break;
            case 'rejected': $rejected++; break;
            case 'evaluated': 
            case 'nominated': // Nominated counts as evaluated/completed
                $evaluated++; break;
        }
    }
} catch(PDOException $e) {
    die("System error. Please try again later.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">

<div class="d-flex">
    <!-- MASTER SIDEBAR -->
    <aside class="sidebar p-4 d-flex flex-column justify-content-between" style="min-height: 100vh;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="fa-solid fa-graduation-cap text-warning me-2 fs-4"></i>
                <span class="fs-5 fw-bold text-white">EduRank</span>
            </div>
            <div class="text-uppercase text-secondary text-xs fw-bold mb-3" style="font-size: 11px; letter-spacing: 1px;">MENU</div>
            <ul class="nav nav-pills flex-column gap-2">
                <li class="nav-item"><a href="student_dashboard.php" class="nav-link active d-flex align-items-center gap-3"><i class="bi bi-house-fill"></i> Dashboard</a></li>
                <li class="nav-item"><a href="student_history.php" class="nav-link d-flex align-items-center gap-3"><i class="bi bi-clock-history"></i> Submission History <?php if($pending > 0): ?><span class="badge bg-warning text-dark ms-auto"><?php echo $pending; ?></span><?php endif; ?></a></li>
                <li class="nav-item"><a href="student_apply.php" class="nav-link d-flex align-items-center gap-3"><i class="bi bi-plus-circle"></i> Submit Application</a></li>
                <li class="nav-item"><a href="student_profile.php" class="nav-link d-flex align-items-center gap-3"><i class="bi bi-person"></i> My Profile</a></li>
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
            <form method="POST" action="logout.php">
                <button type="submit" class="btn text-danger p-0 border-0 text-sm fw-semibold d-flex align-items-center gap-2 ms-1 bg-transparent"><i class="bi bi-box-arrow-right"></i> Logout</button>
            </form>
        </div>
    </aside>

    <div class="main-content flex-grow-1 d-flex flex-column" style="min-height: 100vh;">
        <header class="top-header shadow-sm">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <?php if (file_exists('notification.php')) include 'notification.php'; ?>
        </header>

        <div class="p-4 flex-grow-1">
            <div class="mb-4">
                <h4 class="fw-bold text-dark mb-1">Student Portal</h4>
                <p class="text-secondary text-sm">Welcome back, <?php echo htmlspecialchars($user_name); ?></p>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3"><div class="card bg-white custom-card shadow-sm p-3"><div class="d-flex align-items-center gap-3"><div class="card-icon-bg bg-primary-subtle text-primary fs-4"><i class="bi bi-file-text"></i></div><div><div class="text-secondary text-sm" style="font-size: 12px;">Total Applications</div><div class="fs-4 fw-bold text-dark"><?php echo $total_applications; ?></div></div></div></div></div>
                <div class="col-md-3"><div class="card bg-white custom-card shadow-sm p-3"><div class="d-flex align-items-center gap-3"><div class="card-icon-bg bg-warning-subtle text-warning fs-4"><i class="bi bi-clock"></i></div><div><div class="text-secondary text-sm" style="font-size: 12px;">Pending PA Review</div><div class="fs-4 fw-bold text-dark"><?php echo $pending; ?></div></div></div></div></div>
                <div class="col-md-3"><div class="card bg-white custom-card shadow-sm p-3"><div class="d-flex align-items-center gap-3"><div class="card-icon-bg bg-info-subtle text-info fs-4"><i class="bi bi-check-circle"></i></div><div><div class="text-secondary text-sm" style="font-size: 12px;">Verified</div><div class="fs-4 fw-bold text-dark"><?php echo $verified; ?></div></div></div></div></div>
                <div class="col-md-3"><div class="card bg-white custom-card shadow-sm p-3"><div class="d-flex align-items-center gap-3"><div class="card-icon-bg bg-success-subtle text-success fs-4"><i class="bi bi-award"></i></div><div><div class="text-secondary text-sm" style="font-size: 12px;">Evaluated</div><div class="fs-4 fw-bold text-dark"><?php echo $evaluated; ?></div></div></div></div></div>
            </div>

            <div class="card bg-white custom-card shadow-sm p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-dark">Recent Submissions</h6>
                    <a href="student_history.php" class="text-primary text-decoration-none fw-semibold" style="font-size: 13px;">View All</a>
                </div>
                <?php if(count($applications) > 0): ?>
                    <div class="table-responsive">
                        <table class="table align-middle border-0">
                            <thead class="table-light text-secondary text-uppercase" style="font-size: 11px;">
                                <tr><th class="border-0">FORM NO.</th><th class="border-0">AWARD CATEGORY</th><th class="border-0">SUBMISSION DATE</th><th class="border-0">GPA / CGPA</th><th class="border-0 text-end">STATUS</th></tr>
                            </thead>
                            <tbody style="font-size: 14px;">
                                <?php foreach(array_slice($applications, 0, 5) as $app): ?>
                                    <tr class="border-bottom">
                                        <td class="fw-bold text-dark"><?php echo sprintf('APP-%04d', $app['application_id']); ?></td>
                                        <td class="fw-semibold text-dark"><i class="bi bi-award me-1 text-primary"></i><?php echo htmlspecialchars($app['category_name'] ?? 'N/A'); ?></td>
                                        <td class="text-secondary"><i class="bi bi-calendar3 me-1"></i><?php echo !empty($app['application_date']) ? date('M d, Y', strtotime($app['application_date'])) : '-'; ?></td>
                                        <td class="text-secondary fw-semibold">
                                            <?php 
                                                $gpa_value = floatval($app['student_cgpa'] ?? $app['gpa'] ?? $app['extra_data'] ?? 0);
                                                echo $gpa_value > 0 ? number_format($gpa_value, 2) : '<span class="text-muted">-</span>';
                                            ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if (($app['status'] ?? '') === 'pending'): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-pill">Pending PA</span>
                                            <?php elseif (($app['status'] ?? '') === 'verified'): ?>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 rounded-pill">Verified</span>
                                            <?php elseif (($app['status'] ?? '') === 'evaluated'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Evaluated</span>
                                            <?php elseif (($app['status'] ?? '') === 'nominated'): ?>
                                                <span class="badge bg-warning text-dark border px-3 py-1 rounded-pill shadow-sm"><i class="bi bi-star-fill me-1"></i> FINAL WINNER</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-secondary">
                        <i class="bi bi-inbox fs-2 mb-2 d-block"></i><p class="mb-1">No applications submitted yet.</p><a href="student_apply.php" class="text-primary text-decoration-none fw-semibold">Submit your first application</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    <footer class="text-center p-4 mt-5 text-muted">
        <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
    </footer>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>