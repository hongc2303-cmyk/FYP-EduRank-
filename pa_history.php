<?php
// 1. Include database connection configuration
require_once 'config.php';

// 2. Check login session and verify Academic Advisor role access
if (function_exists('requireLogin')) {
    requireLogin();
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Universal role check
    $user_role = strtolower($_SESSION['user_role'] ?? '');
    if (!isset($_SESSION['user_id']) || ($user_role !== 'advisor' && $user_role !== 'academic advisor')) {
        header("Location: login.php");
        exit();
    }
}

// 3. Get Academic Advisor session details
$pa_id = $_SESSION['user_id'] ?? 0;
$name  = $_SESSION['user_name'] ?? 'Academic Advisor';
$role  = $_SESSION['user_role'] ?? 'Academic Advisor';

$history_records = [];
$error_msg = '';

// 4. Fetch processed application history records directly from MySQL database
if (isset($pdo) && $pa_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.application_id, 
                   a.application_date AS submitted_at, 
                   a.status,
                   u.full_name AS student_name, 
                   u.email AS student_email,
                   s.matric_no, 
                   s.programme,
                   c.category_name, 
                   v.calculated_score,
                   v.remarks AS pa_remarks, 
                   v.verification_date AS verified_at
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            JOIN award_categories c ON a.category_id = c.category_id
            LEFT JOIN application_verifications v ON a.application_id = v.application_id
            WHERE s.advisor_id = ? AND a.status != 'pending'
            ORDER BY a.application_date DESC
        ");
        $stmt->execute([$pa_id]);
        $history_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_msg = "Database Error: " . $e->getMessage();
        $history_records = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank AI - Advisee Application History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">

<div class="d-flex">
    <!-- Sidebar -->
    <aside class="sidebar p-4 d-flex flex-column justify-content-between">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="fa-solid fa-graduation-cap text-warning me-2 fs-4"></i>
                <span class="fs-5 fw-bold text-white">EduRank AI</span>
            </div>

            <div class="text-uppercase text-secondary text-xs fw-bold mb-3" style="font-size: 11px; letter-spacing: 1px;">MENU</div>

            <ul class="nav nav-pills flex-column gap-2">
                <li class="nav-item">
                    <a href="pa_dashboard.php" class="nav-link d-flex align-items-center gap-3">
                        <i class="bi bi-grid-fill"></i> Dashboard Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a href="pa_review.php" class="nav-link d-flex align-items-center gap-3">
                        <i class="bi bi-check-circle"></i> Review Applications
                    </a>
                </li>
                <li class="nav-item">
                    <a href="pa_history.php" class="nav-link active d-flex align-items-center gap-3">
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
            <a href="logout.php" class="text-danger text-decoration-none text-sm fw-semibold d-flex align-items-center gap-2 ms-1">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content Panel -->
    <div class="main-content flex-grow-1">
        
        <header class="top-header shadow-sm">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <?php include 'notification.php'; ?>
        </header>

        <div class="p-4">
            <h4 class="fw-bold mb-4 text-dark">Advisor Portal</h4>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger py-2 text-sm mb-4"><i class="bi bi-exclamation-circle me-1"></i> <?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <div class="card bg-white custom-card shadow-sm p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">Advisee Application History</h5>
                        <p class="text-secondary text-sm mb-0" style="font-size: 13px;">Review all past processed student nominations, calculated scores, and submitted verification remarks.</p>
                    </div>
                    <span class="badge bg-secondary-subtle text-dark border px-3 py-2 rounded-pill">
                        Total Processed: <?php echo count($history_records); ?>
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle border-0">
                        <thead class="table-light text-secondary text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                            <tr>
                                <th class="border-0">NO.</th>
                                <th class="border-0">STUDENT NAME</th>
                                <th class="border-0">AWARD CATEGORY</th>
                                <th class="border-0">SUBMISSION DATE</th>
                                <th class="border-0">SCORE TALLY</th>
                                <th class="border-0">VERIFICATION STATUS</th>
                                <th class="border-0">ADVISOR REMARKS</th>
                                <th class="border-0 text-center">ACTION</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: 14px;">
                            <?php if (!empty($history_records)): ?>
                                <?php foreach ($history_records as $index => $row): ?>
                                    <tr class="border-bottom">
                                        <!-- Sequential Index (1, 2, 3...) -->
                                        <td class="fw-bold text-dark"><?php echo $index + 1; ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($row['matric_no']); ?></div>
                                        </td>
                                        <td class="fw-semibold text-primary">
                                            <i class="bi bi-award me-1"></i><?php echo htmlspecialchars($row['category_name']); ?>
                                        </td>
                                        <td class="text-secondary">
                                            <?php echo date('M d, Y', strtotime($row['submitted_at'])); ?>
                                        </td>
                                        <td>
                                            <?php if (isset($row['calculated_score']) && $row['calculated_score'] !== null): ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                                    <?php echo number_format($row['calculated_score'], 1); ?> Marks
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] === 'verified'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">
                                                    <i class="bi bi-check-circle me-1"></i> Verified & Forwarded
                                                </span>
                                            <?php elseif ($row['status'] === 'evaluated'): ?>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 rounded-pill">
                                                    <i class="bi bi-award me-1"></i> Evaluated by Committee
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">
                                                    <i class="bi bi-x-circle me-1"></i> Rejected
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-secondary" style="max-width: 200px;">
                                            <?php echo htmlspecialchars($row['pa_remarks'] ?? '-'); ?>
                                        </td>
                                        <td class="text-center">
                                            <!-- Icon Button to trigger Modal Popup -->
                                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#historyModal<?php echo $row['application_id']; ?>" title="View Application Details">
                                                <i class="bi bi-eye-fill me-1"></i> 
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Modal Popup for Viewing Past Application Details -->
                                    <div class="modal fade" id="historyModal<?php echo $row['application_id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header bg-light">
                                                    <h6 class="modal-title fw-bold text-dark">
                                                        <i class="bi bi-file-earmark-text text-primary me-2"></i>Application Details (Record #<?php echo $index + 1; ?>)
                                                    </h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <!-- Student Info -->
                                                    <div class="row mb-3 pb-3 border-bottom">
                                                        <div class="col-md-6">
                                                            <div class="text-muted small">Student Name</div>
                                                            <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="text-muted small">Matric Number & Programme</div>
                                                            <div class="fw-semibold text-dark"><?php echo htmlspecialchars($row['matric_no']); ?> (<?php echo htmlspecialchars($row['programme'] ?? 'DIT'); ?>)</div>
                                                        </div>
                                                    </div>

                                                    <!-- Nomination Info -->
                                                    <div class="row mb-3 pb-3 border-bottom">
                                                        <div class="col-md-6">
                                                            <div class="text-muted small">Award Category</div>
                                                            <div class="fw-semibold text-primary"><i class="bi bi-award me-1"></i><?php echo htmlspecialchars($row['category_name']); ?></div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="text-muted small">Submission Date</div>
                                                            <div class="fw-semibold text-dark"><?php echo date('F d, Y', strtotime($row['submitted_at'])); ?></div>
                                                        </div>
                                                    </div>

                                                    <!-- Scores & Status -->
                                                    <div class="row mb-3 pb-3 border-bottom">
                                                        <div class="col-md-6">
                                                            <div class="text-muted small">Calculated Score Marks</div>
                                                            <div class="fs-5 fw-bold text-primary"><?php echo number_format($row['calculated_score'] ?? 0, 1); ?> / 106 Marks</div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="text-muted small">Verification Status</div>
                                                            <div>
                                                                <?php if ($row['status'] === 'verified'): ?>
                                                                    <span class="badge bg-success-subtle text-success border px-3 py-1 rounded-pill">Verified & Forwarded</span>
                                                                <?php elseif ($row['status'] === 'evaluated'): ?>
                                                                    <span class="badge bg-info-subtle text-info border px-3 py-1 rounded-pill">Evaluated by Committee</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-danger-subtle text-danger border px-3 py-1 rounded-pill">Rejected</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- PA Remarks -->
                                                    <div class="mb-2">
                                                        <div class="text-muted small mb-1">Advisor Verification Remarks</div>
                                                        <div class="p-3 bg-light rounded text-dark border">
                                                            <?php echo htmlspecialchars($row['pa_remarks'] ?? 'No remarks recorded.'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light p-2">
                                                    <button type="button" class="btn btn-secondary btn-sm fw-semibold" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- End Modal Popup -->

                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-secondary py-5">
                                        <i class="bi bi-clock-history fs-1 text-muted mb-2 d-block"></i>
                                        <p class="mb-0 fw-medium">No historical records found.</p>
                                        <small class="text-muted">Applications verified or rejected in the Review section will appear here automatically.</small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        </div>
    </div>
</div>

<footer class="text-center p-4 mt-5 text-muted">
    <p class="mb-0 small">&copy; 2026 EduRank AI System - Designed By JWC</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>