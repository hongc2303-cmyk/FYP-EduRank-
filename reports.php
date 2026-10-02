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

$all_categories = [];
if (isset($pdo)) {
    $stmtCats = $pdo->query("SELECT category_id, category_name FROM award_categories ORDER BY category_id ASC");
    $all_categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
}

if (isset($_GET['delete_id']) && isset($pdo)) {
    $del_stmt = $pdo->prepare("DELETE FROM report_logs WHERE log_id = ?");
    $del_stmt->execute([$_GET['delete_id']]);
    header("Location: reports.php?msg=deleted");
    exit();
}

if (isset($_GET['clear_all']) && isset($pdo)) {
    $pdo->query("DELETE FROM report_logs");
    header("Location: reports.php?msg=cleared");
    exit();
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
    } catch (PDOException $e) {}
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
    <style>.dashboard-body { background-color: #f8f9fa; }</style>
</head>
<body class="dashboard-body">

    <!-- 🌟 SMART SIDEBAR (Automatically highlights active page) -->
    <div class="sidebar d-flex flex-column justify-content-between p-4" id="sidebar">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-mortarboard-fill text-warning fs-3"></i>
                <span class="fs-4 fw-bold text-white">EduRank</span>
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

    <div class="main-content">
        <header class="top-header shadow-sm d-flex justify-content-between align-items-center pe-4">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <?php include 'notification.php'; ?>
        </header>

        <div class="p-4">
            <?php if (isset($_GET['msg'])): ?>
                <?php if ($_GET['msg'] == 'deleted'): ?>
                    <div class="alert alert-success alert-dismissible fade show small py-2" role="alert"><i class="bi bi-check-circle me-2"></i>Report record deleted successfully.<button type="button" class="btn-close" style="padding: 0.75rem;" data-bs-dismiss="alert"></button></div>
                <?php elseif ($_GET['msg'] == 'cleared'): ?>
                    <div class="alert alert-success alert-dismissible fade show small py-2" role="alert"><i class="bi bi-check-circle me-2"></i>All report history cleared successfully.<button type="button" class="btn-close" style="padding: 0.75rem;" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="custom-card p-4 mb-4 shadow-sm bg-white">
                <h5 class="fw-bold mb-1">📄 Generate System Report</h5>
                <p class="text-muted mb-4 small">Generate official PDF reports for any specific award category, or an overall summary.</p>
                <form class="row g-3 align-items-end" action="generate_report.php" method="GET" target="_blank">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Report Type</label>
                        <select name="report_type" class="form-select" required>
                            <option value="evaluation">Evaluation Report</option>
                            <option value="application">Application Report</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Award Category</label>
                        <select name="category_id" class="form-select border-primary fw-bold" required>
                            <option value="all">-- Keseluruhan (All Awards) --</option>
                            <?php foreach ($all_categories as $c): ?>
                                <option value="<?php echo $c['category_id']; ?>"><?php echo htmlspecialchars($c['category_name']); ?></option>
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
                        <a href="reports.php?clear_all=1" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Are you sure you want to clear all history?');"><i class="bi bi-trash3 me-1"></i>Clear History</a>
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
                            <tr><td colspan="5" class="text-center py-4 text-muted">No reports generated yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_reports as $report): ?>
                            <tr class="border-bottom">
                                <td class="fw-bold text-dark">📄 <?php echo htmlspecialchars($report['title']); ?></td>
                                <td class="text-secondary"><?php echo htmlspecialchars($report['type']); ?></td>
                                <td class="text-muted"><?php echo htmlspecialchars($report['date']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($report['size']); ?></span></td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="<?php echo htmlspecialchars($report['link']); ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-eye me-1"></i>View</a>
                                        <a href="reports.php?delete_id=<?php echo $report['id']; ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Delete this record?');"><i class="bi bi-trash"></i></a>
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

    <footer class="text-center p-4 mt-5 text-muted">
    <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>