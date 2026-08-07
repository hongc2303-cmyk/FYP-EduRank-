<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$name = $_SESSION['user_name'] ?? 'Committee Member';

$role = $_SESSION['user_role'] ?? 'Evaluation Committee'; 


$notifications = [];
$unread_count = 0;

if (isset($pdo)) {
    try {
       
        $stmt_count = $pdo->query("SELECT COUNT(*) FROM award_applications WHERE status = 'verified'");
        $unread_count = $stmt_count->fetchColumn();

       
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
        // 忽略错误
    }
}

if (isset($_GET['delete_id'])) {
    if (isset($pdo)) {
        $del_stmt = $pdo->prepare("DELETE FROM report_logs WHERE log_id = ?");
        $del_stmt->execute([$_GET['delete_id']]);
        header("Location: reports.php?msg=deleted");
        exit();
    }
}

if (isset($_GET['clear_all'])) {
    if (isset($pdo)) {
        $pdo->query("DELETE FROM report_logs");
        header("Location: reports.php?msg=cleared");
        exit();
    }
}

$categories = [];
if (isset($pdo)) {
    $stmt = $pdo->query("SELECT * FROM award_categories");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


$recent_reports = [];
if (isset($pdo)) {
    try {
        $stmt_logs = $pdo->query("SELECT * FROM report_logs ORDER BY created_at DESC LIMIT 10");
        while ($row = $stmt_logs->fetch(PDO::FETCH_ASSOC)) {
            $recent_reports[] = [
                'id' => $row['log_id'], 
                'title' => $row['report_title'],
                'type' => ucfirst($row['report_type']) . ' Report',
                'date' => date('Y-m-d h:i A', strtotime($row['created_at'])), 
                'size' => 'PDF', 
                'link' => 'generate_report.php?report_type=' . urlencode($row['report_type']) . '&category_id=' . urlencode($row['category_id']) . '&log=0'
            ];
        }
    } catch (PDOException $e) {
        
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - Report Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-body { background-color: #f8f9fa; }
    </style>
</head>
<body class="dashboard-body">

    
    <div class="sidebar d-flex flex-column justify-content-between p-4" id="sidebar">
        <div>
            
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-mortarboard-fill text-warning fs-3"></i>
                <span class="fs-4 fw-bold text-white">EduRank AI</span>
            </div>
            <div class="text-uppercase text-secondary mb-3" style="font-size: 0.75rem; font-weight: 600; padding-left: 12px;">Menu</div>
            <a href="committee_dashboard.php" class="nav-link"><i class="bi bi-grid-fill me-2"></i> Dashboard</a>
            <a href="applications.php" class="nav-link"><i class="bi bi-file-earmark-text me-2"></i> Applications</a>
            <a href="reports.php" class="nav-link active"><i class="bi bi-bar-chart-line me-2"></i> Reports</a>
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

    <div class="main-content">
        <header class="top-header shadow-sm d-flex justify-content-between align-items-center pe-4">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            
           
            <div class="dropdown">
                <a href="#" class="text-white text-decoration-none position-relative" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell fs-5"></i>
                  
                    <?php if ($unread_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" style="width: 10px; height: 10px;">
                        <span class="visually-hidden">New alerts</span>
                    </span>
                    <?php endif; ?>
                </a>
                
               
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
           
            <?php if (isset($_GET['msg'])): ?>
                <?php if ($_GET['msg'] == 'deleted'): ?>
                    <div class="alert alert-success alert-dismissible fade show small py-2" role="alert">
                        <i class="bi bi-check-circle me-2"></i>Report record deleted successfully.
                        <button type="button" class="btn-close" style="padding: 0.75rem;" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php elseif ($_GET['msg'] == 'cleared'): ?>
                    <div class="alert alert-success alert-dismissible fade show small py-2" role="alert">
                        <i class="bi bi-check-circle me-2"></i>All report history cleared successfully.
                        <button type="button" class="btn-close" style="padding: 0.75rem;" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            
            <div class="custom-card p-4 mb-4 shadow-sm bg-white">
                <h5 class="fw-bold mb-1">📄 Generate System Report</h5>
                <p class="text-muted mb-4 small">Select parameters to generate official PDF reports for department records.</p>
                <form class="row g-3 align-items-end" action="generate_report.php" method="GET" target="_blank">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Report Type</label>
                        <select name="report_type" class="form-select" required>
                            <option value="evaluation">Evaluation Report</option>
                            <option value="application">Application Report</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Award Category Filter</label>
                        <select name="category_id" class="form-select">
                            <option value="all">All Awards</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small">From Date</label>
                        <input type="date" name="start_date" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small">To Date</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <input type="hidden" name="log" value="1">
                        <button type="submit" class="btn btn-danger w-100 fw-bold">
                            <i class="bi bi-file-earmark-pdf me-2"></i>Generate
                        </button>
                    </div>
                </form>
            </div>

           
            <div class="custom-card p-4 shadow-sm bg-white">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">🗂️ Recent Reports Generated</h5>
                    <?php if (!empty($recent_reports)): ?>
                        <a href="reports.php?clear_all=1" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Are you sure you want to clear all history?');">
                            <i class="bi bi-trash3 me-1"></i>Clear History
                        </a>
                    <?php endif; ?>
                </div>
                
                <table class="table table-borderless align-middle">
                    <thead class="border-bottom text-muted small">
                        <tr>
                            <th>DOCUMENT NAME</th>
                            <th>REPORT TYPE</th>
                            <th>GENERATED AT</th>
                            <th>FORMAT</th>
                            <th class="text-end">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_reports)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No reports generated yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_reports as $report): ?>
                            <tr class="border-bottom">
                                <td class="fw-bold text-dark">📄 <?php echo htmlspecialchars($report['title']); ?></td>
                                <td class="text-secondary"><?php echo htmlspecialchars($report['type']); ?></td>
                                <td class="text-muted"><?php echo htmlspecialchars($report['date']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($report['size']); ?></span></td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="<?php echo htmlspecialchars($report['link']); ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                        <a href="reports.php?delete_id=<?php echo $report['id']; ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Delete this record?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <footer class="text-center p-4 mt-5 text-muted">
    <p class="mb-0 small">&copy; 2026 EduRank AI System - Designed By JWC</p>
    </footer>
</body>
</html>