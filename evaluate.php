<?php
require_once 'config.php';

// Check login status
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

$committee_id = $_SESSION['user_id'];
$name = $_SESSION['user_name'] ?? 'Committee Member';
$app_id = $_GET['id'] ?? null;

if (!$app_id) {
    die("Error: Application ID is required.");
}

$error_msg = '';

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
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {
    
    // Retrieve remarks from the form
    $remarks = trim($_POST['committee_remarks'] ?? '');
    
    // Set status to evaluated directly upon submission
    $decision = 'evaluated'; 

    try {
        $pdo->beginTransaction();

        // 1. Update application status
        $update_stmt = $pdo->prepare("UPDATE award_applications SET status = ? WHERE application_id = ?");
        $update_stmt->execute([$decision, $app_id]);

        // 2. Retrieve PA score
        $score_stmt = $pdo->prepare("SELECT calculated_score FROM application_verifications WHERE application_id = ?");
        $score_stmt->execute([$app_id]);
        $pa_data = $score_stmt->fetch(PDO::FETCH_ASSOC);
        $total_score = $pa_data ? $pa_data['calculated_score'] : 0;

        // 3. Save evaluation record and remarks into the evaluations table
        $insert_stmt = $pdo->prepare("
            INSERT INTO evaluations (application_id, committee_id, total_score, remarks) 
            VALUES (?, ?, ?, ?)
        ");
        $insert_stmt->execute([$app_id, $committee_id, $total_score, $remarks]);

        $pdo->commit();
        
        // Redirect back to applications.php upon successful submission
        header("Location: applications.php");
        exit(); 
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_msg = "Database Error during submission: " . $e->getMessage();
    }
}

// Retrieve complete application details, PA verification results, and committee evaluation results
$app_details = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.status, a.application_date, 
                   u.full_name AS student_name, s.matric_no, 
                   c.category_name, 
                   v.calculated_score, v.remarks AS pa_remarks,
                   e.remarks AS committee_remarks
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            JOIN award_categories c ON a.category_id = c.category_id
            LEFT JOIN application_verifications v ON a.application_id = v.application_id
            LEFT JOIN evaluations e ON a.application_id = e.application_id
            WHERE a.application_id = ?
        ");
        $stmt->execute([$app_id]);
        $app_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$app_details && empty($error_msg)) {
            $error_msg = "Application not found or database query failed.";
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
    <title>EduRank - Evaluate Application</title>
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
        <div class="border-top border-secondary pt-3 mt-auto">
            <form method="POST" action="logout.php">
                <button type="submit" class="btn text-danger p-0 border-0 text-sm fw-semibold d-flex align-items-center gap-2 ms-1 bg-transparent" style="cursor: pointer;">
                    <i class="bi bi-box-arrow-right fs-5"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        
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
            <div class="d-flex justify-content-between align-items-center mb-4">
               
                <h4 class="fw-bold text-dark mb-0">
                    <?php if (!empty($app_details) && ($app_details['status'] === 'evaluated' || $app_details['status'] === 'rejected')): ?>
                        Evaluation Record: <?php echo htmlspecialchars($app_details['student_name'] ?? 'Candidate'); ?>
                    <?php else: ?>
                        Evaluate Candidate: <?php echo htmlspecialchars($app_details['student_name'] ?? 'Candidate'); ?>
                    <?php endif; ?>
                </h4>
                <a href="applications.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back to List</a>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <div class="row g-4">
                
                <div class="col-md-7">
                    <div class="custom-card p-4 shadow-sm bg-white mb-4">
                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">Student Information</h6>
                        <table class="table table-borderless mb-0 small">
                            <tbody>
                                <tr>
                                    <th width="35%" class="text-secondary">Student Name:</th>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($app_details['student_name'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-secondary">Matric No:</th>
                                    <td><?php echo htmlspecialchars($app_details['matric_no'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-secondary">Award Category:</th>
                                    <td>
                                        <?php if(isset($app_details['category_name'])): ?>
                                            <span class="badge bg-primary-subtle text-primary border"><?php echo htmlspecialchars($app_details['category_name']); ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-secondary">Applied Date:</th>
                                    <td><?php echo htmlspecialchars($app_details['application_date'] ?? '-'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="custom-card p-4 shadow-sm bg-white">
                        <h6 class="fw-bold text-success mb-3 border-bottom pb-2">PA Verification Result</h6>
                        <div class="mb-3">
                            <label class="text-secondary small fw-semibold d-block mb-1">APCP 02 Calculated Score:</label>
                            <span class="fs-1 fw-bold text-success">
                                <?php echo (isset($app_details['calculated_score']) && $app_details['calculated_score'] !== null) ? number_format($app_details['calculated_score'], 1) : 'N/A'; ?>
                            </span>
                            <span class="text-muted ms-1">pts</span>
                        </div>
                        <div>
                            <label class="text-secondary small fw-semibold d-block mb-1">PA Remarks:</label>
                            <div class="p-3 bg-light rounded border text-dark" style="min-height: 80px;">
                                <?php echo htmlspecialchars($app_details['pa_remarks'] ?? 'No remarks provided.'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Committee Evaluation Form -->
                <div class="col-md-5">
                    <div class="custom-card p-4 shadow-sm bg-white h-100 border-top border-4 border-warning">
                        <h5 class="fw-bold text-dark mb-4"><i class="bi bi-pencil-square text-warning me-2"></i>Committee Evaluation</h5>
                        
                        <?php if (!empty($app_details) && ($app_details['status'] === 'evaluated' || $app_details['status'] === 'rejected')): ?>
                            <!-- Evaluated status: hide form, show results -->
                            <div class="alert alert-info mb-4">
                                <strong>Status:</strong> This application has already been <span class="text-uppercase fw-bold"><?php echo htmlspecialchars($app_details['status']); ?></span>.
                            </div>
                            
                            <!-- Display submitted remarks -->
                            <div>
                                <label class="text-secondary small fw-semibold d-block mb-1">Your Evaluation Remarks:</label>
                                <div class="p-3 bg-light rounded border text-dark" style="min-height: 100px; white-space: pre-wrap;"><?php echo htmlspecialchars($app_details['committee_remarks'] ?? 'No remarks provided.'); ?></div>
                            </div>
                            
                        <?php elseif (!empty($app_details)): ?>
                            <!-- Unevaluated: show form -->
                            <form method="POST" action="">
                                
                                <!-- AI Insight Section -->
                                <div class="mb-4">
                                    <label class="form-label text-secondary small fw-semibold">
                                        <i class="bi bi-stars text-primary me-1"></i>AI Insight
                                    </label>
                                    <div class="p-3 bg-primary-subtle text-primary-emphasis rounded border border-primary-subtle small mb-2">
                                        <em>Click the button below to generate AI analysis based on APCP 02 criteria and PA remarks.</em>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary w-100 fw-bold shadow-sm" onclick="alert('AI Insight feature is coming soon!')">
                                        <i class="bi bi-magic me-2"></i>Generate AI Insight
                                    </button>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label text-secondary small fw-semibold">Committee Remarks / Justification</label>
                                    <textarea name="committee_remarks" class="form-control" rows="5" placeholder="Enter evaluation remarks here (Please state the reasons for the final decision. This will be saved as the official record.)" required></textarea>
                                </div>

                                <button type="submit" name="submit_evaluation" class="btn btn-warning w-100 fw-bold text-dark shadow-sm">
                                    <i class="bi bi-check-circle me-2"></i>Submit Final Evaluation
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="text-muted text-center pt-5">
                                <i class="bi bi-slash-circle fs-1"></i>
                                <p class="mt-2">Form unavailable due to data error.</p>
                            </div>
                        <?php endif; ?>
                    </div>
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