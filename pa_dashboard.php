<?php
// 1. Include database configuration
require_once 'config.php';

// 2. Check login status & role verification
if (function_exists('requireLogin')) {
    requireLogin();
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $user_role = strtolower($_SESSION['user_role'] ?? '');
    if (!isset($_SESSION['user_id']) || ($user_role !== 'advisor' && $user_role !== 'academic advisor')) {
        header("Location: login.php");
        exit();
    }
}

// 3. Get PA details from session
$pa_id = $_SESSION['user_id'] ?? 0;
$name  = $_SESSION['user_name'] ?? 'Academic Advisor';
$role  = $_SESSION['user_role'] ?? 'Academic Advisor';

// Variables for stats & tables
$assigned_students = [];
$pending_applications = [];
$student_applications = [];
$total_students = 0;
$pending_count = 0;
$processed_count = 0;

if (isset($pdo) && $pa_id > 0) {
    try {
        // A. Fetch students assigned strictly to THIS PA
        $stmtStudents = $pdo->prepare("
            SELECT s.*, u.full_name, u.email
            FROM students s
            JOIN users u ON s.user_id = u.user_id
            WHERE s.advisor_id = ?
            ORDER BY u.full_name ASC
        ");
        $stmtStudents->execute([$pa_id]);
        $assigned_students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);
        $total_students = count($assigned_students);

        // B. Fetch all applications belonging ONLY to this PA's advisees (For modal popup)
        $stmtApps = $pdo->prepare("
            SELECT a.application_id, a.student_id, a.application_date, a.status,
                   c.category_name, v.calculated_score
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN award_categories c ON a.category_id = c.category_id
            LEFT JOIN application_verifications v ON a.application_id = v.application_id
            WHERE s.advisor_id = ?
            ORDER BY a.application_date DESC
        ");
        $stmtApps->execute([$pa_id]);
        $all_apps = $stmtApps->fetchAll(PDO::FETCH_ASSOC);

        // Group applications array by student_id
        foreach ($all_apps as $app) {
            $student_applications[$app['student_id']][] = $app;
        }

        // C. Fetch pending applications needing review
        $stmtPending = $pdo->prepare("
            SELECT a.application_id, a.application_date, a.status,
                   u.full_name AS student_name, s.matric_no, s.programme,
                   c.category_name
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            JOIN award_categories c ON a.category_id = c.category_id
            WHERE s.advisor_id = ? AND a.status = 'pending'
            ORDER BY a.application_date ASC
        ");
        $stmtPending->execute([$pa_id]);
        $pending_applications = $stmtPending->fetchAll(PDO::FETCH_ASSOC);
        $pending_count = count($pending_applications);

        // D. Fetch total count of processed applications (Including 'nominated')
        $stmtProcessed = $pdo->prepare("
            SELECT COUNT(*) AS processed_total
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            WHERE s.advisor_id = ? AND a.status IN ('verified', 'rejected', 'evaluated', 'nominated')
        ");
        $stmtProcessed->execute([$pa_id]);
        $processed_count = $stmtProcessed->fetchColumn() ?: 0;

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
    <title>EduRank - Advisor Dashboard</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">

<div class="d-flex">
    <!-- Sidebar Navigation -->
    <aside class="sidebar p-4 d-flex flex-column justify-content-between" style="min-height: 100vh;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="fa-solid fa-graduation-cap text-warning me-2 fs-4"></i>
                <span class="fs-5 fw-bold text-white">EduRank</span>
            </div>

            <div class="text-uppercase text-secondary text-xs fw-bold mb-3" style="font-size: 11px; letter-spacing: 1px;">MENU</div>

            <ul class="nav nav-pills flex-column gap-2">
                <li class="nav-item">
                    <a href="pa_dashboard.php" class="nav-link active d-flex align-items-center gap-3">
                        <i class="bi bi-grid-fill"></i> Dashboard Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a href="pa_review.php" class="nav-link d-flex align-items-center gap-3">
                        <i class="bi bi-check-circle"></i> Review Applications
                        <?php if ($pending_count > 0): ?>
                            <span class="badge bg-warning text-dark ms-auto"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="pa_history.php" class="nav-link d-flex align-items-center gap-3">
                        <i class="bi bi-clock-history"></i> Advisee History
                    </a>
                </li>
                <li class="nav-item">
                    <a href="pa_profile.php" class="nav-link d-flex align-items-center gap-3">
                        <i class="bi bi-person"></i> Profile
                    </a>
                </li>
            </ul>
        </div>

        <div class="border-top border-secondary pt-3">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px;">
                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                </div>
                <div>
                    <div class="fw-bold text-sm text-white" style="font-size: 14px;"><?php echo htmlspecialchars($name); ?></div>
                    <div class="text-secondary" style="font-size: 12px;"><?php echo htmlspecialchars($role); ?></div>
                </div>
            </div>
            <form method="POST" action="logout.php">
                <button type="submit" class="btn text-danger p-0 border-0 text-sm fw-semibold d-flex align-items-center gap-2 ms-1 bg-transparent">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </button>
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
                <h4 class="fw-bold text-dark mb-1">Academic Advisor Portal</h4>
                <p class="text-secondary text-sm">Welcome back, <?php echo htmlspecialchars($name); ?>. Manage and verify student award nominations.</p>
            </div>

            <!-- Stats Counter Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card bg-white custom-card shadow-sm p-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="card-icon-bg bg-primary-subtle text-primary fs-4">
                                <i class="bi bi-people"></i>
                            </div>
                            <div>
                                <div class="text-secondary text-sm" style="font-size: 12px;">Assigned Advisees</div>
                                <div class="fs-4 fw-bold text-dark"><?php echo $total_students; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card bg-white custom-card shadow-sm p-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="card-icon-bg bg-warning-subtle text-warning fs-4">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <div>
                                <div class="text-secondary text-sm" style="font-size: 12px;">Pending Verifications</div>
                                <div class="fs-4 fw-bold text-dark"><?php echo $pending_count; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card bg-white custom-card shadow-sm p-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="card-icon-bg bg-success-subtle text-success fs-4">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                            <div>
                                <div class="text-secondary text-sm" style="font-size: 12px;">Total Processed</div>
                                <div class="fs-4 fw-bold text-dark"><?php echo $processed_count; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Applications Table -->
            <div class="card bg-white custom-card shadow-sm p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-exclamation-circle text-warning me-2"></i>Pending Applications for Verification
                    </h6>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-pill">
                        <?php echo $pending_count; ?> Action Required
                    </span>
                </div>

                <?php if (count($pending_applications) > 0): ?>
                    <div class="table-responsive">
                        <table class="table align-middle border-0">
                            <thead class="table-light text-secondary text-uppercase" style="font-size: 11px;">
                                <tr>
                                    <th class="border-0">NO.</th>
                                    <th class="border-0">STUDENT NAME</th>
                                    <th class="border-0">MATRIC NO</th>
                                    <th class="border-0">AWARD CATEGORY</th>
                                    <th class="border-0">SUBMITTED DATE</th>
                                    <th class="border-0 text-end">ACTION</th>
                                </tr>
                            </thead>
                            <tbody style="font-size: 14px;">
                                <?php foreach ($pending_applications as $index => $app): ?>
                                    <tr class="border-bottom">
                                        <td class="fw-bold text-dark"><?php echo $index + 1; ?></td>
                                        <td class="fw-semibold text-dark"><?php echo htmlspecialchars($app['student_name']); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($app['matric_no']); ?></span></td>
                                        <td class="fw-semibold text-primary">
                                            <i class="bi bi-award me-1"></i><?php echo htmlspecialchars($app['category_name']); ?>
                                        </td>
                                        <td class="text-secondary"><?php echo date('M d, Y', strtotime($app['application_date'])); ?></td>
                                        <td class="text-end">
                                            <a href="pa_review.php?app_id=<?php echo $app['application_id']; ?>" class="btn btn-primary btn-sm fw-bold px-3">
                                                <i class="bi bi-pencil-square me-1"></i> Review & Calculate Marks
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-secondary">
                        <i class="bi bi-check-circle-fill fs-2 text-success mb-2 d-block"></i>
                        <p class="mb-0 fw-medium text-dark">No pending applications right now.</p>
                        <small class="text-muted">All nominations submitted by your advisees have been processed.</small>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Assigned Advisees List Table -->
            <div class="card bg-white custom-card shadow-sm p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-dark">Assigned Advisees List</h6>
                    <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="searchAdvisee" class="form-control border-start-0 bg-light shadow-none" placeholder="Search name or matric...">
                    </div>
                </div>
                <!-- Student list table and PA can view the student record -->
                <div class="table-responsive">
                    <table class="table align-middle border-0">
                        <thead class="table-light text-secondary text-uppercase" style="font-size: 11px;">
                            <tr>
                                <th class="border-0">NO.</th>
                                <th class="border-0">STUDENT NAME</th>
                                <th class="border-0">MATRIC NO</th>
                                <th class="border-0">PROGRAMME</th>
                                <th class="border-0">EMAIL</th>
                                <th class="border-0 text-center">ACTION</th>
                            </tr>
                        </thead>
                        
                        <tbody id="adviseeListBody" style="font-size: 14px;">
                            <?php if (!empty($assigned_students)): ?>
                                <?php foreach ($assigned_students as $idx => $student): 
                                    $st_id = $student['student_id'];
                                    $apps_list = $student_applications[$st_id] ?? [];
                                    $apps_count = count($apps_list);
                                ?>
                                    <tr class="border-bottom">
                                        <td class="fw-bold text-dark"><?php echo $idx + 1; ?></td>
                                        <td class="fw-semibold text-dark">
                                            <i class="bi bi-person me-2 text-secondary"></i><?php echo htmlspecialchars($student['full_name']); ?>
                                        </td>
                                        <td class="text-secondary"><?php echo htmlspecialchars($student['matric_no'] ?? 'N/A'); ?></td>
                                        <td class="text-secondary"><?php echo htmlspecialchars($student['programme'] ?? 'DIT'); ?></td>
                                        <td class="text-secondary"><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td class="text-center">
                                            <!-- Action Button: Triggers Modal -->
                                            <button type="button" class="btn btn-sm <?= $apps_count > 0 ? 'btn-primary' : 'btn-outline-secondary' ?> fw-bold" data-bs-toggle="modal" data-bs-target="#appsModal<?= $st_id ?>" title="Click to view student applications">
                                                <i class="bi bi-folder-symlink me-1"></i> View  
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">No advisees currently assigned to your profile.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Render Modals Outside the Table Component to Avoid Layout Distortions -->
            <?php if (!empty($assigned_students)): ?>
                <?php foreach ($assigned_students as $student): 
                    $st_id = $student['student_id'];
                    $apps_list = $student_applications[$st_id] ?? [];
                    $apps_count = count($apps_list);
                ?>
                    <div class="modal fade" id="appsModal<?= $st_id ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content border-0 shadow">
                                <div class="modal-header bg-light">
                                    <h6 class="modal-title fw-bold text-dark">
                                        <i class="bi bi-person-badge text-primary me-2"></i>Applications Record for <?= htmlspecialchars($student['full_name']) ?>
                                    </h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="text-muted small">Matric No: <strong><?= htmlspecialchars($student['matric_no']) ?></strong> | Programme: <strong><?= htmlspecialchars($student['programme'] ?? 'DIT') ?></strong></span>
                                        <span class="badge bg-primary px-3 py-1 fs-6"><?= $apps_count ?> Application Record(s) Found</span>
                                    </div>

                                    <?php if ($apps_count > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle border">
                                                <thead class="table-light small">
                                                    <tr>
                                                        <th>NO.</th>
                                                        <th>AWARD CATEGORY</th>
                                                        <th>SUBMITTED DATE</th>
                                                        <th>CALCULATED SCORE</th>
                                                        <th>STATUS</th>
                                                    </tr>
                                                </thead>
                                                <tbody style="font-size: 13px;">
                                                    <?php foreach ($apps_list as $a_idx => $a_item): ?>
                                                        <tr>
                                                            <td class="fw-bold"><?= $a_idx + 1 ?></td>
                                                            <td class="fw-semibold text-primary">
                                                                <i class="bi bi-award me-1"></i><?= htmlspecialchars($a_item['category_name']) ?>
                                                            </td>
                                                            <td class="text-secondary"><?= date('M d, Y', strtotime($a_item['application_date'])) ?></td>
                                                            <td>
                                                                <?php if (isset($a_item['calculated_score']) && $a_item['calculated_score'] !== null): ?>
                                                                    <!-- FIX: Updated Marks to Percentage % -->
                                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                                        <?= number_format($a_item['calculated_score'], 1) ?>%
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php 
                                                                    $st = $a_item['status'];
                                                                    if ($st === 'pending') {
                                                                        echo '<span class="badge bg-warning-subtle text-warning border px-2 py-1 rounded-pill">Pending PA Review</span>';
                                                                    } elseif ($st === 'verified') {
                                                                        echo '<span class="badge bg-info-subtle text-info border px-2 py-1 rounded-pill">Verified & Forwarded</span>';
                                                                    } elseif ($st === 'evaluated') {
                                                                        echo '<span class="badge bg-success-subtle text-success border px-2 py-1 rounded-pill">Evaluated</span>';
                                                                    } elseif ($st === 'nominated') {
                                                                        // FIX: Added 'nominated' Top 1 Winner status to prevent falling into the Rejected block
                                                                        echo '<span class="badge bg-warning text-dark border px-2 py-1 rounded-pill shadow-sm"><i class="bi bi-star-fill me-1"></i> FINAL WINNER</span>';
                                                                    } else {
                                                                        echo '<span class="badge bg-danger-subtle text-danger border px-2 py-1 rounded-pill">Rejected</span>';
                                                                    }
                                                                ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                                            This advisee has not submitted any award applications yet.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer bg-light p-2">
                                    <button type="button" class="btn btn-secondary btn-sm fw-semibold" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
        
        <footer class="text-center p-4 mt-auto text-muted border-top" style="background-color: #f8f9fa;">
            <p class="mb-0 small fw-medium">&copy; 2026 EduRank System - Designed By JWC</p>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchAdvisee');
    const tableBody = document.getElementById('adviseeListBody');
    
    if (searchInput && tableBody) {
        const rows = tableBody.getElementsByTagName('tr');
        
        searchInput.addEventListener('keyup', function() {
            const filterValue = this.value.toLowerCase();
            
            for (let i = 0; i < rows.length; i++) {
                if (rows[i].cells.length === 1) continue; 
                
                const rowText = rows[i].textContent.toLowerCase();
                if (rowText.includes(filterValue)) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        });
    }
});
</script>
</body>
</html>