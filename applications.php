<?php
require_once 'config.php';

if (function_exists('requireLogin')) {
    requireLogin();
} else {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

$user_id = $_SESSION['user_id'];
$name = $_SESSION['user_name'] ?? 'Committee Member';
$role = $_SESSION['user_role'] ?? 'Evaluation Committee';

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

$selected_category_id = $_GET['cat_id'] ?? 'all';

$applications = [];
$error_msg = '';
if (isset($pdo)) {
    try {
        $sql = "
            SELECT a.application_id, a.status, a.application_date, 
                   u.full_name AS student_name, s.matric_no, 
                   c.category_name, v.calculated_score AS score
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            JOIN award_categories c ON a.category_id = c.category_id
            LEFT JOIN application_verifications v ON a.application_id = v.application_id
            WHERE a.status IN ('verified', 'evaluated', 'nominated') AND a.session_name = ?
        ";
        $params = [$current_session];

        if ($selected_category_id !== 'all') {
            $sql .= " AND a.category_id = ?";
            $params[] = $selected_category_id;
        }
        $sql .= " ORDER BY a.application_date DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
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
                <div class="rounded-circle bg-secondary d-flex justify-content-center align-items-center text-white fw-bold shadow-sm" style="width: 42px; height: 42px; font-size: 1.2rem;">
                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                </div>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold text-dark mb-1">Applications Management</h4>
                    <p class="text-secondary small mb-0">Review and manage student award applications verified by PA.</p>
                </div>
                <span class="badge bg-primary px-3 py-2 fs-6 shadow-sm"><i class="bi bi-calendar-check me-2"></i><?php echo htmlspecialchars($current_session); ?></span>
            </div>

            <div class="custom-card p-4 shadow-sm bg-white mb-4 border-0 rounded-4">
                <h6 class="fw-bold text-primary mb-3"><i class="bi bi-funnel-fill me-2"></i>Filter Applications by Category</h6>
                <form method="GET" action="applications.php">
                    <div class="input-group">
                        <select name="cat_id" class="form-select border-primary fw-semibold" onchange="this.form.submit()">
                            <option value="all">-- Papar Semua Kategori (All Awards) --</option>
                            <?php foreach($all_categories as $c): ?>
                                <option value="<?php echo $c['category_id']; ?>" <?php echo $selected_category_id == $c['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <div class="custom-card p-4 shadow-sm bg-white">
                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th width="5%">NO.</th>
                                <th width="25%">STUDENT NAME</th>
                                <th width="15%">MATRIC NO</th>
                                <th width="30%">AWARD CATEGORY</th>
                                <th width="10%">STATUS</th>
                                <th width="5%">SCORE</th>
                                <th width="10%" class="text-end">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($applications)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 text-secondary d-block mb-2"></i>
                                        No verified applications found for this selection.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $counter = 1; foreach ($applications as $app): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($app['student_name']); ?></td>
                                        <td class="text-secondary"><?php echo htmlspecialchars($app['matric_no']); ?></td>
                                        <td><span style="font-size: 0.85rem;"><?php echo htmlspecialchars($app['category_name']); ?></span></td>
                                        <td>
                                            <?php if ($app['status'] == 'nominated'): ?>
                                                <span class="badge bg-warning text-dark px-2 py-1 rounded-pill shadow-sm"><i class="bi bi-star-fill me-1"></i> WINNER</span>
                                            <?php elseif ($app['status'] == 'evaluated'): ?>
                                                <span class="badge bg-success-subtle text-success border px-2 py-1 rounded-pill">Evaluated</span>
                                            <?php elseif ($app['status'] == 'verified'): ?>
                                                <span class="badge bg-primary-subtle text-primary border px-2 py-1 rounded-pill">PA Verified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold <?php echo ($app['status'] == 'nominated') ? 'text-success' : 'text-primary'; ?>"><?php echo $app['score'] !== null ? number_format($app['score'], 1) . '%' : '-'; ?></td>
                                        <td class="text-end">
                                            <a href="evaluate.php?id=<?php echo $app['application_id']; ?>" class="btn btn-sm <?php echo ($app['status'] == 'nominated') ? 'btn-outline-secondary' : 'btn-outline-primary'; ?> fw-bold">
                                                <?php echo ($app['status'] == 'nominated' || $app['status'] == 'evaluated') ? 'View Result' : 'Evaluate'; ?>
                                            </a>
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
</body>
</html>