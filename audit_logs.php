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

// Handle Clear All Logs Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("TRUNCATE TABLE report_logs");
            $stmt->execute();
            $success_msg = "Success! All system audit logs have been cleared.";
        } catch (PDOException $e) {
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}

// Retrieve Audit Logs
$logs = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT * FROM report_logs ORDER BY created_at DESC");
        if ($stmt) {
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error_msg = "Database query failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - System Audit Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">

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
            <a href="audit_logs.php" class="nav-link active"><i class="bi bi-journal-text me-2"></i> Audit Logs</a>
            <a href="system_setting.php" class="nav-link"><i class="bi bi-gear-fill me-2"></i> System Settings</a>
        </div>
        <div class="border-top border-secondary pt-3 mt-auto">
            <form method="POST" action="logout.php">
                <button type="submit" class="btn text-danger p-0 border-0 text-sm fw-semibold d-flex align-items-center gap-2 ms-1 bg-transparent">
                    <i class="bi bi-box-arrow-right fs-5"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <header class="top-header shadow-sm d-flex justify-content-between align-items-center pe-4">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <div class="text-white small fw-bold">
                <i class="bi bi-person-circle me-1"></i> Admin Portal
            </div>
        </header>

        <div class="p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-dark mb-0">System Activity & Audit Logs</h4>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="input-group shadow-sm rounded w-50">
                    <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control border-0 ps-0" placeholder="Search by title, action type, or date...">
                </div>
                
                <form method="POST" action="" onsubmit="return confirm('WARNING: Are you sure you want to permanently delete all log records? This action cannot be undone.');">
                    <button type="submit" name="clear_logs" class="btn btn-outline-danger fw-bold shadow-sm">
                        <i class="bi bi-trash3-fill me-1"></i> Clear All Logs
                    </button>
                </form>
            </div>

            <div class="custom-card p-0 shadow-sm bg-white overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="logTable">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 px-4 text-secondary small fw-semibold" style="width: 50px;">NO.</th>
                                <th class="py-3 px-3 text-secondary small fw-semibold">DATE & TIME</th>
                                <th class="py-3 px-3 text-secondary small fw-semibold">ACTION / REPORT TITLE</th>
                                <th class="py-3 px-3 text-secondary small fw-semibold">MODULE TYPE</th>
                                <th class="py-3 px-3 text-secondary small fw-semibold text-center">TARGET CATEGORY</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($logs)): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No audit logs found in the system.</td></tr>
                            <?php else: ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="px-4 text-muted fw-medium"><?php echo $counter++; ?></td>
                                        
                                        <td class="px-3 fw-bold text-dark">
                                            <?php echo date('d M Y, h:i A', strtotime($log['created_at'])); ?>
                                        </td>
                                        
                                        <td class="px-3 text-dark">
                                            <?php echo htmlspecialchars($log['report_title']); ?>
                                        </td>

                                        <td class="px-3 text-secondary">
                                            <span class="badge bg-secondary text-uppercase" style="font-size: 0.7rem;">
                                                <?php echo htmlspecialchars($log['report_type']); ?>
                                            </span>
                                        </td>
                                        
                                        <td class="px-3 text-center text-primary fw-semibold">
                                            <?php 
                                                if ($log['category_id'] === 'all') {
                                                    echo 'All Categories';
                                                } else {
                                                    echo '#' . htmlspecialchars($log['category_id']); 
                                                }
                                            ?>
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

    <footer class="text-center p-4 mt-5 text-muted">
    <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Table filtering script
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#logTable tbody tr');
            
            rows.forEach(row => {
                if (row.cells.length === 1) return; 
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>
    
</body>
</html>