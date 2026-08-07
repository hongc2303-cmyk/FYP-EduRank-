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

// Initialize statistics and lists
$stats = ['total' => 0, 'pending' => 0, 'ready' => 0, 'evaluated' => 0];
$categories = [];
$candidates = [];
$all_candidates = [];
$action_required = []; 
$progress_percentage = 0; 

if (isset($pdo)) {
$notifications = [];
$unread_count = 0;

if (isset($pdo)) {
    try {
        // 1. Count how many applications have 'verified' status
        $stmt_count = $pdo->query("SELECT COUNT(*) FROM award_applications WHERE status = 'verified'");
        $unread_count = $stmt_count->fetchColumn();

        // 2. Fetch the latest 5 'verified' records to display in the dropdown menu
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

    try {
        // 1. Get statistics data
        $stmtStats = $pdo->query("SELECT status, COUNT(*) as count FROM award_applications WHERE status IN ('verified', 'evaluated') GROUP BY status");
        while ($row = $stmtStats->fetch()) {
            if ($row['status'] === 'verified') $stats['ready'] += $row['count'];
            if ($row['status'] === 'evaluated') $stats['evaluated'] += $row['count'];
            $stats['total'] += $row['count'];
        }
        $stats['pending'] = 0; // Committee does not view pending PA applications

        // Calculate progress bar percentage
        $total_to_evaluate = $stats['ready'] + $stats['evaluated'];
        $progress_percentage = ($total_to_evaluate > 0) ? round(($stats['evaluated'] / $total_to_evaluate) * 100) : 0;

        // 2. Get all award categories
        $stmtCats = $pdo->query("SELECT * FROM award_categories ORDER BY category_id ASC");
        $categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

        // Get Action Required list (fetch top 5 verified applications)
        $stmtAction = $pdo->query("
            SELECT a.application_id, u.full_name AS name, s.matric_no, c.category_name, v.calculated_score AS score
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            JOIN award_categories c ON a.category_id = c.category_id
            LEFT JOIN application_verifications v ON a.application_id = v.application_id
            WHERE a.status = 'verified'
            ORDER BY v.calculated_score DESC
            LIMIT 5
        ");
        $action_required = $stmtAction->fetchAll(PDO::FETCH_ASSOC);

        // 3. Get all verified and evaluated students for the leaderboard
        $stmtCands = $pdo->query("
            SELECT a.application_id, a.status, u.full_name AS name, s.matric_no, 
                   c.category_id, c.category_name, 
                   v.calculated_score AS score
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            JOIN award_categories c ON a.category_id = c.category_id
            LEFT JOIN application_verifications v ON a.application_id = v.application_id
            WHERE a.status IN ('verified', 'evaluated')
            ORDER BY v.calculated_score DESC
        ");
        $all_candidates = $stmtCands->fetchAll(PDO::FETCH_ASSOC);

        foreach ($all_candidates as $cand) {
            $candidates[$cand['category_id']][] = $cand;
        }

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
        /* Gold, Silver, and Bronze medal styles */
        .rank-gold { background-color: #fbbf24; color: #fff; width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 6px rgba(251, 191, 36, 0.3); }
        .rank-silver { background-color: #94a3b8; color: #fff; width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 6px rgba(148, 163, 184, 0.3); }
        .rank-bronze { background-color: #b45309; color: #fff; width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 4px 6px rgba(180, 83, 9, 0.3); }
        .rank-other { color: #64748b; width: 32px; text-align: center; display: inline-block; font-weight: bold; }
        
        .nav-tabs .nav-link { border: none; color: #64748b; padding: 12px 20px; border-bottom: 2px solid transparent; }
        .nav-tabs .nav-link:hover { border-color: transparent; color: #0f172a; }
        .nav-tabs .nav-link.active { border: none; border-bottom: 2px solid #2563eb; color: #2563eb !important; background-color: transparent; }
        
        /* Search box effects */
        .search-box input { border-radius: 20px; padding-left: 35px; border: 1px solid #ced4da; }
        .search-box i { position: absolute; left: 12px; top: 9px; color: #6c757d; }
        .search-box { position: relative; width: 250px; }
    </style>
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
            <a href="committee_dashboard.php" class="nav-link active"><i class="bi bi-grid-fill me-2"></i> Dashboard</a>
            <a href="applications.php" class="nav-link"><i class="bi bi-file-earmark-text me-2"></i> Applications</a>
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
            
            <!-- Logout Button -->
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
    
            <div class="mb-4">
                <h2 class="fw-bold text-dark mb-1">Evaluation Committee Portal</h2>
                <p class="text-secondary text-md">
                    Welcome back, <?php echo htmlspecialchars($name); ?>. Review and evaluate student award applications.</p>
                </div>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3"><div class="custom-card p-3 shadow-sm bg-white"><div class="text-secondary small">Total Applications</div><div class="fs-3 fw-bold"><?php echo $stats['total']; ?></div></div></div>
                <div class="col-md-3"><div class="custom-card p-3 shadow-sm bg-white"><div class="text-secondary small">Pending PA Review</div><div class="fs-3 fw-bold text-muted"><?php echo $stats['pending']; ?></div></div></div>
                <div class="col-md-3"><div class="custom-card p-3 shadow-sm bg-white"><div class="text-secondary small">Ready for Evaluation</div><div class="fs-3 fw-bold text-primary"><?php echo $stats['ready']; ?></div></div></div>
                <div class="col-md-3"><div class="custom-card p-3 shadow-sm bg-white"><div class="text-secondary small">Evaluated</div><div class="fs-3 fw-bold text-success"><?php echo $stats['evaluated']; ?></div></div></div>
            </div>

            <!-- Combined Layout: Progress & To-Do on the left, Chart on the right -->
            <div class="row g-4 mb-4">
                
                <div class="col-lg-4 d-flex flex-column gap-4">
                    <!-- Progress Bar -->
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

                    <!-- Action Required To-Do Section -->
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
                                                <div class="text-secondary" style="font-size: 0.75rem;"><?php echo htmlspecialchars($req['category_name']); ?> • <?php echo number_format($req['score'], 1); ?> pts</div>
                                            </div>
                                            <a href="evaluate.php?id=<?php echo $req['application_id']; ?>" class="btn btn-sm btn-danger px-3 py-1" style="font-size: 0.8rem;">Evaluate</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if($stats['ready'] > 5): ?>
                                <div class="text-center mt-3"><a href="applications.php" class="text-decoration-none small text-primary">View all <?php echo $stats['ready']; ?> pending...</a></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-8">
                    <!-- Dynamic Chart Area -->
                    <div class="custom-card p-4 shadow-sm bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="fw-bold text-dark mb-1">Real-time Leaderboard</h5>
                                <p class="text-secondary small mb-0">Overview of candidate scores based on APCP 02 formulas.</p>
                            </div>
                            <div class="w-25">
                                <select class="form-select form-select-sm" id="chartCategoryFilter">
                                    <option value="all">View All Awards</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div style="height: 300px;"><canvas id="rankingChart"></canvas></div>
                    </div>
                </div>

            </div>

            <!-- Dynamic Tabs and Ranking List -->
            <div class="custom-card p-4 shadow-sm bg-white">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0">Award Rankings</h5>
                    
                    <!-- Quick Search Box -->
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="candidateSearch" class="form-control form-control-sm" placeholder="Search name or matric no...">
                    </div>
                </div>

                <ul class="nav nav-tabs mb-4" id="awardTabs" role="tablist">
                    <?php $isFirst = true; foreach ($categories as $cat): ?>
                        <li class="nav-item">
                            <button class="nav-link fw-bold <?php echo $isFirst ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tab-<?php echo $cat['category_id']; ?>">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </button>
                        </li>
                    <?php $isFirst = false; endforeach; ?>
                </ul>

                <div class="tab-content">
                    <?php $isFirstContent = true; foreach ($categories as $cat): ?>
                        <div class="tab-pane fade <?php echo $isFirstContent ? 'show active' : ''; ?>" id="tab-<?php echo $cat['category_id']; ?>">
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead class="table-light text-secondary small">
                                        <tr>
                                            <th class="text-center" width="8%">RANK</th>
                                            <th>CANDIDATE NAME</th>
                                            <th>MATRIC NO</th>
                                            <th>STATUS</th>
                                            <th class="text-end">SCORE</th>
                                            <th class="text-end">ACTION</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $cat_id = $cat['category_id'];
                                        if (empty($candidates[$cat_id])): ?>
                                            <tr><td colspan="6" class="text-center py-4 text-muted">No candidates found for this award.</td></tr>
                                        <?php else: 
                                            $rank = 1;
                                            foreach ($candidates[$cat_id] as $row): 
                                                $score = floatval($row['score']);
                                        ?>
                                            <!-- Added class and data attributes to facilitate JS search filtering -->
                                            <tr class="candidate-row" data-name="<?php echo strtolower(htmlspecialchars($row['name'])); ?>" data-matric="<?php echo strtolower(htmlspecialchars($row['matric_no'])); ?>">
                                                <td class="text-center">
                                                    <!-- Gold, Silver, and Bronze Visual Representation -->
                                                    <?php 
                                                        if ($rank == 1) echo "<span class='rank-gold'>#1</span>";
                                                        elseif ($rank == 2) echo "<span class='rank-silver'>#2</span>";
                                                        elseif ($rank == 3) echo "<span class='rank-bronze'>#3</span>";
                                                        else echo "<span class='rank-other'>#$rank</span>";
                                                    ?>
                                                </td>
                                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td class="text-secondary"><?php echo htmlspecialchars($row['matric_no']); ?></td>
                                                <td>
                                                    <?php if ($row['status'] == 'evaluated'): ?>
                                                        <span class="badge bg-success-subtle text-success border px-2 py-1 rounded-pill">Evaluated</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary-subtle text-primary border px-2 py-1 rounded-pill">PA Verified</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end fw-bold fs-5"><?php echo number_format($score, 1); ?></td>
                                                <td class="text-end">
                                                        <a href="evaluate.php?id=<?php echo $row['application_id']; ?>" 
                                                        class="btn btn-sm <?php echo $row['status'] == 'evaluated' ? 'btn-outline-secondary' : 'btn-primary'; ?>">
                                                    <?php echo $row['status'] == 'evaluated' ? 'View Result' : 'Evaluate'; ?>
                                                        </a>
                                                </td>
                                            </tr>
                                        <?php $rank++; endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php $isFirstContent = false; endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- Real-time Leaderboard Chart Logic ---
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
                    datalabels: { anchor: 'end', align: 'end', font: { weight: 'bold' }, formatter: (val) => Number(val).toFixed(1) + ' pts' }
                },
                scales: { x: { beginAtZero: true, max: 100 } }
            }
        });

        function updateChart(categoryId) {
            let filtered = categoryId === 'all' ? allCandidates : allCandidates.filter(c => c.category_id == categoryId);
            filtered.sort((a, b) => b.score - a.score);
            filtered = filtered.slice(0, 5);

            rankingChart.data.labels = filtered.map(c => c.name);
            rankingChart.data.datasets[0].data = filtered.map(c => parseFloat(c.score));
            rankingChart.data.datasets[0].backgroundColor = filtered.map((c, i) => {
                if (i === 0) return '#fbbf24'; // 1st place becomes gold
                if (i === 1) return '#94a3b8'; // 2nd place becomes silver
                if (i === 2) return '#b45309'; // 3rd place becomes bronze
                return '#93c5fd'; // Other blue
            });
            rankingChart.update();
        }

        document.getElementById('chartCategoryFilter').addEventListener('change', (e) => updateChart(e.target.value));
        updateChart('all');

        // --- Quick Search Box JS Logic ---
        document.getElementById('candidateSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('.candidate-row');
            
            rows.forEach(row => {
                let name = row.getAttribute('data-name');
                let matric = row.getAttribute('data-matric');
                
                // If name or matric number contains the searched text, show it, otherwise hide it
                if (name.includes(filter) || matric.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
    <footer class="text-center p-4 mt-5 text-muted">
    <p class="mb-0 small">&copy; 2026 EduRank AI System - Designed By JWC</p>
    </footer>
</body>
</html>