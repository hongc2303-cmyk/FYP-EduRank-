<?php
require_once 'config.php';

// Check login status and role
if (function_exists('requireLogin')) {
    requireLogin();
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

$name = $_SESSION['user_name'] ?? 'Committee Member';
$role = $_SESSION['user_role'] ?? 'Evaluation Committee';


$notifications = [];
$unread_count = 0;

if (isset($pdo)) {
    try {
        // Calculate unread count (applications waiting for evaluation)
        $stmt_count = $pdo->query("SELECT COUNT(*) FROM award_applications WHERE status = 'verified'");
        $unread_count = $stmt_count->fetchColumn();

        // Fetch the latest 5 notifications
        $stmt_notif = $pdo->query("
            SELECT a.application_id, u.full_name, c.category_name, a.application_date 
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            JOIN award_categories c ON a.category_id = c.category_id
            WHERE a.status = 'verified'
            ORDER BY a.application_date DESC 
            LIMIT 5
        ");
        $notifications = $stmt_notif->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        
    }
}

// Get all applications list
$applications = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("
            SELECT a.application_id, a.status, a.application_date, 
                   u.full_name AS student_name, s.matric_no, 
                   c.category_name, v.calculated_score AS score
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            JOIN award_categories c ON a.category_id = c.category_id
            LEFT JOIN application_verifications v ON a.application_id = v.application_id
            WHERE a.status IN ('verified', 'evaluated') 
            ORDER BY a.application_date DESC
        ");
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>EduRank - Applications Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">

    <!-- Sidebar -->
    <div class="sidebar d-flex flex-column justify-content-between p-4" id="sidebar">
        <div>
            
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-mortarboard-fill text-warning fs-3"></i>
                <span class="fs-4 fw-bold text-white">EduRank AI</span>
            </div>
            <div class="text-uppercase text-secondary mb-3" style="font-size: 0.75rem; font-weight: 600; padding-left: 12px;">Menu</div>
            <a href="committee_dashboard.php" class="nav-link"><i class="bi bi-grid-fill me-2"></i> Dashboard</a>
            <a href="applications.php" class="nav-link active"><i class="bi bi-file-earmark-text me-2"></i> Applications</a>
            <a href="reports.php" class="nav-link"><i class="bi bi-bar-chart-line me-2"></i> Reports</a>
            <a href="committee_profile.php" class="nav-link"><i class="bi bi-person me-2"></i> Profile</a>
        </div>
       
<div class="border-top border-secondary pt-4 mt-auto">
            
            <div class="d-flex align-items-center gap-3 mb-4 ms-1">
                
                <div class="rounded-circle bg-secondary d-flex justify-content-center align-items-center text-white fw-bold shadow-sm" style="width: 42px; height: 42px; font-size: 1.2rem;">
                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                </div>
               
                <div class="d-flex flex-column text-white" style="line-height: 1.2; overflow: hidden;">
                    <span class="fw-semibold text-truncate" style="font-size: 0.9rem;" title="<?php echo htmlspecialchars($name); ?>">
                        <?php echo htmlspecialchars($name); ?>
                    </span>
                    <span class="text-secondary text-truncate mt-1" style="font-size: 0.75rem;" title="<?php echo htmlspecialchars($role); ?>">
                        <?php echo htmlspecialchars($role); ?>
                    </span>
                </div>
            </div>
            
            
            <form method="POST" action="logout.php">
                <button type="submit" class="btn text-danger p-0 border-0 text-sm fw-bold d-flex align-items-center gap-2 ms-1 bg-transparent" style="cursor: pointer;">
                    <i class="bi bi-box-arrow-right fs-5"></i> Logout
                </button>
            </form>
        </div>
    </div>

   
    <div class="main-content" id="mainContent">
        
        
        <header class="top-header shadow-sm d-flex justify-content-between align-items-center pe-4">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            
            <!-- Notification bell -->
            <div class="dropdown">
                <a href="#" class="text-white text-decoration-none position-relative" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell fs-5"></i>
                    <!-- Show red dot only if there are real unread notifications -->
                    <?php if ($unread_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" style="width: 10px; height: 10px;">
                        <span class="visually-hidden">New alerts</span>
                    </span>
                    <?php endif; ?>
                </a>
                
                <!-- Pop-up dynamic dropdown menu -->
                <ul class="dropdown-menu dropdown-menu-end shadow-lg mt-3" aria-labelledby="notificationDropdown" style="width: 350px; border-radius: 12px; padding: 0;">
                    <li class="bg-light rounded-top">
                        <div class="dropdown-header d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span class="fw-bold text-dark fs-6">Pending Evaluations</span>
                            <span class="badge bg-danger rounded-pill"><?php echo $unread_count; ?> New</span>
                        </div>
                    </li>
                    
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php if (empty($notifications)): ?>
                            
                            <li>
                                <div class="text-center py-4 text-muted small">
                                    <i class="bi bi-check2-circle fs-3 d-block mb-2 text-success"></i>
                                    All caught up! No pending applications.
                                </div>
                            </li>
                        <?php else: ?>
                           
                            <?php foreach ($notifications as $notif): ?>
                                <li>
                                    <a class="dropdown-item py-3 border-bottom text-wrap" href="evaluate.php?id=<?php echo $notif['application_id']; ?>">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; flex-shrink: 0;">
                                                <i class="bi bi-person-fill-exclamation"></i>
                                            </div>
                                            <div>
                                                <div class="small fw-bold text-dark mb-1">
                                                    Action Required: <?php echo htmlspecialchars($notif['full_name']); ?>
                                                </div>
                                                <div class="text-muted" style="font-size: 0.75rem; line-height: 1.3;">
                                                    Category: <?php echo htmlspecialchars($notif['category_name']); ?> is ready for evaluation.
                                                </div>
                                                <div class="text-primary mt-1 fw-semibold" style="font-size: 0.7rem;">
                                                    <i class="bi bi-calendar-event me-1"></i>
                                                    <?php echo date('d M Y, h:i A', strtotime($notif['application_date'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <li>
                        <a class="dropdown-item text-center small text-primary fw-bold py-2 bg-light rounded-bottom" href="applications.php">
                            View All Applications
                        </a>
                    </li>
                </ul>
            </div>
        </header>

        <div class="p-4">
            <div class="custom-card p-4 shadow-sm bg-white">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">Applications Management</h5>
                        <p class="text-secondary small mb-0">Review and manage student award applications verified by PA.</p>
                    </div>
                </div>

                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th>NO.</th>
                                <th>STUDENT NAME</th>
                                <th>MATRIC NO</th>
                                <th>AWARD CATEGORY</th>
                                <th>STATUS</th>
                                <th>SCORE</th>
                                <th class="text-end">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($applications)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No verified applications found.</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                // Counter, initial value is 1
                                $counter = 1; 
                                foreach ($applications as $app): 
                                ?>
                                    <tr>
                                        <!-- Output $counter, and auto-increment after output -->
                                        <td><?php echo $counter++; ?></td>
                                        
                                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($app['student_name']); ?></td>
                                        <td class="text-secondary"><?php echo htmlspecialchars($app['matric_no']); ?></td>
                                        <td><?php echo htmlspecialchars($app['category_name']); ?></td>
                                        <td>
                                            <?php if ($app['status'] == 'evaluated'): ?>
                                                <span class="badge bg-success-subtle text-success border px-2 py-1 rounded-pill">Evaluated</span>
                                            <?php elseif ($app['status'] == 'verified'): ?>
                                                <span class="badge bg-primary-subtle text-primary border px-2 py-1 rounded-pill">PA Verified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold"><?php echo $app['score'] !== null ? number_format($app['score'], 1) : '-'; ?></td>
                                        <td class="text-end">
                                            <a href="evaluate.php?id=<?php echo $app['application_id']; ?>" class="btn btn-sm btn-outline-primary">Review / Evaluate</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <footer class="text-center p-4 mt-5 text-muted">
    <p class="mb-0 small">&copy; 2026 EduRank AI System - Designed By JWC</p>
    </footer>
</body>
</html>