<?php
require_once 'config.php';

// 1. Verify authentication
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

// --- Fetch Current Session & Calculate Pending Evaluations ---
$current_session = '';
$pending_evaluations = 0;
if (isset($pdo)) {
    $stmtSes = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'current_session'");
    $current_session = $stmtSes->fetchColumn() ?: 'Default Session';
    
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM award_applications WHERE status = 'verified' AND session_name = ?");
    $stmtCount->execute([$current_session]);
    $pending_evaluations = $stmtCount->fetchColumn();
}
// -------------------------------------------------------------

// --- Handle Nomination Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nominate_candidate'])) {
    $app_id = $_POST['application_id'];
    $cat_id = $_POST['category_id'];

    if (isset($pdo)) {
        try {
            $pdo->beginTransaction();
            // Demote any existing nominated candidate in this category for this session back to 'evaluated'
            $stmtReset = $pdo->prepare("UPDATE award_applications SET status = 'evaluated', is_read = 0 WHERE category_id = ? AND status = 'nominated' AND session_name = ?");
            $stmtReset->execute([$cat_id, $current_session]);
            
            // Nominate the newly selected candidate
            $stmtNominate = $pdo->prepare("UPDATE award_applications SET status = 'nominated', is_read = 0 WHERE application_id = ?");
            $stmtNominate->execute([$app_id]);
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_msg = "Nomination Error: " . $e->getMessage();
        }
    }
}

// --- Fetch Dashboard Statistics and Data ---
$stats = ['total' => 0, 'pending' => 0, 'ready' => 0, 'evaluated' => 0];
$categories = [];
$all_candidates = [];
$action_required = [];
$progress_percentage = 0;

if (isset($pdo)) {
    try {
        // Fetch all award categories
        $stmtCats = $pdo->query("SELECT * FROM award_categories ORDER BY category_id ASC");
        $categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

        // Calculate evaluation progress stats
        $stmtStats = $pdo->prepare("SELECT status, COUNT(*) as count FROM award_applications WHERE status IN ('verified', 'evaluated', 'nominated') AND session_name = ? GROUP BY status");
        $stmtStats->execute([$current_session]);
        while ($row = $stmtStats->fetch()) {
            if ($row['status'] === 'verified') $stats['ready'] += $row['count'];
            if ($row['status'] === 'evaluated' || $row['status'] === 'nominated') $stats['evaluated'] += $row['count'];
            $stats['total'] += $row['count'];
        }
        $stats['pending'] = 0;
        $total_to_evaluate = $stats['ready'] + $stats['evaluated'];
        $progress_percentage = ($total_to_evaluate > 0) ? round(($stats['evaluated'] / $total_to_evaluate) * 100) : 0;

        // Fetch Top 5 Action Required Candidates (Verified but not yet Evaluated)
        $stmtAction = $pdo->prepare("
            SELECT a.application_id, u.full_name AS name, s.matric_no, c.category_name, v.calculated_score AS score
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            JOIN award_categories c ON a.category_id = c.category_id
            LEFT JOIN application_verifications v ON a.application_id = v.application_id
            WHERE a.status = 'verified' AND a.session_name = ?
            ORDER BY v.calculated_score DESC
            LIMIT 5
        ");
        $stmtAction->execute([$current_session]);
        $action_required = $stmtAction->fetchAll(PDO::FETCH_ASSOC);

        // Fetch All Candidates for the Leaderboard
        $stmtCands = $pdo->prepare("
            SELECT a.application_id, a.status, u.full_name AS name, s.matric_no, 
                   c.category_id, c.category_name, v.calculated_score AS score
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            JOIN award_categories c ON a.category_id = c.category_id
            LEFT JOIN application_verifications v ON a.application_id = v.application_id
            WHERE a.status IN ('verified', 'evaluated', 'nominated') AND a.session_name = ?
        ");
        $stmtCands->execute([$current_session]);
        $all_candidates = $stmtCands->fetchAll(PDO::FETCH_ASSOC);

        // 🌟 Core Optimization: Group by Category ID first, then sort by Score (Descending) within each category
        usort($all_candidates, function($a, $b) {
            if ($a['category_id'] == $b['category_id']) {
                return floatval($b['score']) <=> floatval($a['score']);
            }
            return $a['category_id'] <=> $b['category_id'];
        });

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
    <title>EduRank - Committee Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <link rel="stylesheet" href="style.css">
    <style>
        .rank-gold { background-color: #fbbf24; color: #fff; width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 6px rgba(251, 191, 36, 0.3); }
        .rank-silver { background-color: #94a3b8; color: #fff; width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 6px rgba(148, 163, 184, 0.3); }
        .rank-bronze { background-color: #b45309; color: #fff; width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 6px rgba(180, 83, 9, 0.3); }
        .rank-other { color: #64748b; width: 32px; text-align: center; display: inline-block; font-weight: bold; }
        .search-box input { border-radius: 20px; padding-left: 35px; border: 1px solid #ced4da; }
        .search-box i { position: absolute; left: 12px; top: 9px; color: #6c757d; }
        .search-box { position: relative; width: 250px; }
        .nominated-row { background-color: #f0fdf4 !important; border-left: 4px solid #22c55e !important; }
        .category-header-row td { background: #eff6ff !important; color: #1e3a8a !important; border-top: 2px solid #bfdbfe !important; border-bottom: 2px solid #bfdbfe !important; }
    </style>
</head>
<body class="dashboard-body">
    
    <!-- SMART SIDEBAR -->
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
                <button type="submit" class="btn text-danger p-0 border-0 text-sm fw-bold d-flex align-items-center gap-2 ms-1 bg-transparent" style="cursor: pointer;">
                    <i class="bi bi-box-arrow-right fs-5"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content" id="mainContent">
        <header class="top-header shadow-sm d-flex justify-content-between align-items-center pe-4">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <?php include 'notification.php'; ?>
        </header>
        <div class="p-4">
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="fw-bold text-dark mb-1">Evaluation Committee Portal</h2>
                        <p class="text-secondary text-md">Welcome back, <?php echo htmlspecialchars($name); ?>. You have full access to evaluate all award categories.</p>
                    </div>
                    <span class="badge bg-primary px-3 py-2 fs-6 shadow-sm"><i class="bi bi-calendar-check me-2"></i><?php echo htmlspecialchars($current_session); ?></span>
                </div>
            </div>

            <!-- Statistic Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3"><div class="custom-card p-3 shadow-sm bg-white"><div class="text-secondary small">Total Applications</div><div class="fs-3 fw-bold"><?php echo $stats['total']; ?></div></div></div>
                <div class="col-md-3"><div class="custom-card p-3 shadow-sm bg-white"><div class="text-secondary small">Pending PA Review</div><div class="fs-3 fw-bold text-muted"><?php echo $stats['pending']; ?></div></div></div>
                <div class="col-md-3"><div class="custom-card p-3 shadow-sm bg-white"><div class="text-secondary small">Ready for Evaluation</div><div class="fs-3 fw-bold text-primary"><?php echo $stats['ready']; ?></div></div></div>
                <div class="col-md-3"><div class="custom-card p-3 shadow-sm bg-white"><div class="text-secondary small">Evaluated</div><div class="fs-3 fw-bold text-success"><?php echo $stats['evaluated']; ?></div></div></div>
            </div>

            <!-- Progress and Action Required -->
            <div class="row g-4 mb-4">
                <div class="col-lg-4 d-flex flex-column gap-4">
                    <div class="custom-card p-4 shadow-sm bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-dark">Overall Evaluation Progress</span>
                            <span class="fw-bold text-primary"><?php echo $progress_percentage; ?>%</span>
                        </div>
                        <div class="progress mb-2" style="height: 10px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $progress_percentage; ?>%"></div>
                        </div>
                        <div class="text-muted small">
                            <?php echo $stats['evaluated']; ?> out of <?php echo $total_to_evaluate; ?> applications evaluated.
                        </div>
                    </div>
                    
                    <div class="custom-card p-4 shadow-sm bg-white flex-grow-1">
                        <h6 class="fw-bold text-danger mb-3"><i class="bi bi-exclamation-circle-fill me-2"></i>Action Required</h6>
                        <?php if (empty($action_required)): ?>
                            <div class="text-center text-muted small py-4">
                                <i class="bi bi-check2-circle fs-2 text-success"></i><br>All caught up! No pending evaluations.
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($action_required as $req): ?>
                                    <div class="list-group-item px-0 py-2 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?php echo htmlspecialchars($req['name']); ?></div>
                                                <div class="text-secondary" style="font-size: 0.75rem;"><?php echo htmlspecialchars($req['category_name']); ?> | <?php echo number_format($req['score'], 1); ?> pts</div>
                                            </div>
                                            <a href="evaluate.php?id=<?php echo $req['application_id']; ?>" class="btn btn-sm btn-danger px-3 py-1" style="font-size: 0.8rem;">Evaluate</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Chart Section -->
                <div class="col-lg-8">
                    <div class="custom-card p-4 shadow-sm bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="fw-bold text-dark mb-1">Real-time Leaderboard</h5>
                                <p class="text-secondary small mb-0">Overview of candidate scores based on APCP formulas.</p>
                            </div>
                        </div>
                        <div style="height: 300px;"><canvas id="rankingChart"></canvas></div>
                    </div>
                </div>
            </div>

            <!-- Rankings Table Section -->
            <div class="custom-card p-4 shadow-sm bg-white">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-3 border-bottom">
                    <h5 class="fw-bold text-dark mb-0">Award Rankings & Nomination</h5>
                    
                    <div class="d-flex gap-3 align-items-center flex-wrap">
                        <div class="input-group" style="width: auto;">
                            <span class="input-group-text bg-light text-primary border-primary"><i class="bi bi-funnel-fill"></i></span>
                            <select id="awardFilterSelect" class="form-select border-primary fw-bold text-primary" style="min-width: 250px;">
                                <option value="all">-- Semua Kategori (All Awards) --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input type="text" id="candidateSearch" class="form-control form-control-sm py-2" placeholder="Search name or matric no...">
                        </div>
                        
                        <a href="generate_lampiran.php?cat_id=1" id="printLampiranBtn" target="_blank" class="btn btn-dark fw-bold shadow-sm rounded-pill px-4 py-2">
                            <i class="bi bi-printer-fill me-2"></i> Cetak Lampiran
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="text-center" width="8%">RANK</th>
                                <th width="25%">CANDIDATE NAME</th>
                                <th width="15%">MATRIC NO</th>
                                <th width="25%">CATEGORY</th>
                                <th width="10%">STATUS</th>
                                <th width="7%" class="text-end">SCORE</th>
                                <th width="10%" class="text-end">ACTION</th>
                            </tr>
                        </thead>
                        <tbody id="candidateTableBody">
                            <?php 
                                $current_rendered_category = -1;
                                $rankCounter = 1;
                                
                                foreach ($all_candidates as $row): 
                                    $cId = $row['category_id'];
                                    
                                    // 🌟 Automatically render category header rows to perfectly separate different awards
                                    if ($current_rendered_category !== $cId) {
                                        $current_rendered_category = $cId;
                                        $rankCounter = 1; // Reset rank to #1 when switching to a new category
                                        ?>
                                        <tr class="category-header-row" data-category-id="<?php echo $cId; ?>">
                                            <td colspan="7" class="fw-bold px-3 py-2">
                                                <i class="bi bi-trophy-fill text-warning me-2"></i> <?php echo htmlspecialchars($row['category_name']); ?>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                    
                                    $rank = $rankCounter++;
                                    $score = floatval($row['score']);
                                    $is_nominated = ($row['status'] === 'nominated');
                            ?>
                                <tr class="candidate-row <?php echo $is_nominated ? 'nominated-row' : ''; ?>" data-category-id="<?php echo $cId; ?>" data-name="<?php echo strtolower(htmlspecialchars($row['name'])); ?>" data-matric="<?php echo strtolower(htmlspecialchars($row['matric_no'])); ?>">
                                    <td class="text-center">
                                        <?php 
                                            if ($rank == 1) echo "<span class='rank-gold'>#1</span>";
                                            elseif ($rank == 2) echo "<span class='rank-silver'>#2</span>";
                                            elseif ($rank == 3) echo "<span class='rank-bronze'>#3</span>";
                                            else echo "<span class='rank-other'>#$rank</span>";
                                        ?>
                                    </td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td class="text-secondary"><?php echo htmlspecialchars($row['matric_no']); ?></td>
                                    <td class="small text-secondary"><?php echo htmlspecialchars($row['category_name']); ?></td>
                                    <td>
                                        <?php if ($is_nominated): ?>
                                            <span class="badge bg-success px-2 py-1 shadow-sm"><i class="bi bi-star-fill text-warning me-1"></i> Winner</span>
                                        <?php elseif ($row['status'] == 'evaluated'): ?>
                                            <span class="badge bg-success-subtle text-success border px-2 py-1 rounded-pill">Evaluated</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary-subtle text-primary border px-2 py-1 rounded-pill">PA Verified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold fs-5 <?php echo $is_nominated ? 'text-success' : ''; ?>"><?php echo number_format($score, 1); ?>%</td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="evaluate.php?id=<?php echo $row['application_id']; ?>" 
                                               class="btn btn-sm <?php echo ($row['status'] == 'evaluated' || $row['status'] == 'nominated') ? 'btn-outline-secondary' : 'btn-primary'; ?>">
                                                <?php echo ($row['status'] == 'evaluated' || $row['status'] == 'nominated') ? 'View Result' : 'Evaluate'; ?>
                                            </a>
                                            
                                            <?php if (($row['status'] === 'evaluated' || $row['status'] === 'verified') && !$is_nominated): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="application_id" value="<?php echo $row['application_id']; ?>">
                                                    <input type="hidden" name="category_id" value="<?php echo $row['category_id']; ?>">
                                                    <button type="submit" name="nominate_candidate" class="btn btn-sm btn-warning fw-bold shadow-sm" onclick="return confirm('Adakah anda pasti untuk memilih pelajar ini sebagai pemenang mutlak (Top 1) bagi kategori ini?');">
                                                        <i class="bi bi-trophy-fill"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="emptyTableMessage" class="text-center py-5 text-muted" style="display: none;">
                        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i> No candidates found for this selection.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="text-center p-4 mt-5 text-muted">
        <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const allCandidates = <?php echo json_encode($all_candidates); ?>;
        
        Chart.register(ChartDataLabels);
        const ctx = document.getElementById('rankingChart').getContext('2d');
        
        let rankingChart = new Chart(ctx, {
            type: 'bar',
            data: { labels: [], datasets: [{ label: 'Score', data: [], backgroundColor: [], borderRadius: 4 }] },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false, layout: { padding: { right: 50 } },
                plugins: {
                    legend: { display: false },
                    datalabels: { anchor: 'end', align: 'end', font: { weight: 'bold' }, formatter: (val) => Number(val).toFixed(1) + '%' }
                },
                scales: { x: { beginAtZero: true, max: 100 } }
            }
        });

        function updateDashboardView(categoryId) {
            // Update Chart Logic (Sort by absolute score for the Top 5 globally or per category)
            let filtered = categoryId === 'all' ? allCandidates : allCandidates.filter(c => c.category_id == categoryId);
            filtered.sort((a, b) => b.score - a.score);
            filtered = filtered.slice(0, 5); 
            
            rankingChart.data.labels = filtered.map(c => c.name);
            rankingChart.data.datasets[0].data = filtered.map(c => parseFloat(c.score));
            rankingChart.data.datasets[0].backgroundColor = filtered.map((c, i) => {
                if (i === 0) return '#fbbf24'; 
                if (i === 1) return '#94a3b8'; 
                if (i === 2) return '#b45309'; 
                return '#93c5fd'; 
            });
            rankingChart.update();

            // 🌟 Update Table UI Logic (Handles headers and candidate rows smoothly)
            let candRows = document.querySelectorAll('.candidate-row');
            let headRows = document.querySelectorAll('.category-header-row');
            let visibleCount = 0;
            let searchTerm = document.getElementById('candidateSearch').value.toLowerCase();

            // Toggle category headers: Hide them if user is searching to keep results clean
            headRows.forEach(row => {
                let rowCatId = row.getAttribute('data-category-id');
                if (searchTerm !== '' || (categoryId !== 'all' && rowCatId !== categoryId)) {
                    row.style.display = 'none';
                } else {
                    row.style.display = '';
                }
            });

            // Toggle candidate rows based on filters
            candRows.forEach(row => {
                let name = row.getAttribute('data-name');
                let matric = row.getAttribute('data-matric');
                let rowCatId = row.getAttribute('data-category-id');
                
                let matchesCategory = (categoryId === 'all' || rowCatId === categoryId);
                let matchesSearch = (name.includes(searchTerm) || matric.includes(searchTerm));

                if (matchesCategory && matchesSearch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('emptyTableMessage').style.display = (visibleCount === 0) ? '' : 'none';

            let printBtn = document.getElementById('printLampiranBtn');
            if (categoryId === 'all') {
                printBtn.style.display = 'none'; 
            } else {
                printBtn.style.display = '';
                printBtn.href = 'generate_lampiran.php?cat_id=' + categoryId;
            }
        }

        // Initialize table and filters
        updateDashboardView('all');
        document.getElementById('awardFilterSelect').addEventListener('change', function() { updateDashboardView(this.value); });
        document.getElementById('candidateSearch').addEventListener('keyup', function() { updateDashboardView(document.getElementById('awardFilterSelect').value); });
    </script>
</body>
</html>