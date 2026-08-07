<?php
// 1. Include database connection configuration
require_once 'config.php';

// 2. Check login session and verify Academic Advisor role (Compatible with 'advisor' and 'Academic Advisor')
if (function_exists('requireLogin')) {
    requireLogin();
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $current_role = strtolower($_SESSION['user_role'] ?? $_SESSION['role'] ?? '');
    if (!isset($_SESSION['user_id']) || ($current_role !== 'academic advisor' && $current_role !== 'advisor')) {
        header("Location: login.php");
        exit();
    }
}

// 3. Get Academic Advisor session info
$pa_id = $_SESSION['user_id'] ?? 0;
$name  = $_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'Academic Advisor';
$role  = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'Academic Advisor';

$message = '';
$error = '';

// 4. Handle Advisor decision (Approve / Reject) and write result to MySQL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $application_id = filter_var($_POST['application_id'] ?? 0, FILTER_VALIDATE_INT);
    $action_type    = $_POST['action_type']; // 'forwarded' or 'rejected'
    $remarks        = trim($_POST['remarks'] ?? '');

    if ($application_id > 0 && isset($pdo)) {
        try {
            $pdo->beginTransaction();

            // Fetch uploaded certificates for this application from MySQL
            $stmtCerts = $pdo->prepare("SELECT * FROM certificates WHERE application_id = ?");
            $stmtCerts->execute([$application_id]);
            $certs = $stmtCerts->fetchAll(PDO::FETCH_ASSOC);

            // Calculate official merit marks according to Borang APCP 02 (A) rules
            $score_a = 0; $score_b = 0; $score_c = 0; $score_d = 0; $score_e = 0;

            foreach ($certs as $c) {
                // FIXED: Use certificate_type (compatible with component_type)
                $comp = strtolower($c['certificate_type'] ?? $c['component_type'] ?? '');
                $extra_raw = $c['extra_data'] ?? '';
                $data = json_decode($extra_raw, true);

                if ($comp === 'a') {
                    // (a) Academic: CGPA * 10 (Max 40 marks)
                    $cgpa = floatval($extra_raw);
                    $score_a = min($cgpa * 10, 40);
                } elseif ($comp === 'b') {
                    // (b) Leadership: Top 1 position score (Max 10 marks)
                    $b_marks = [
                        'JPP_YDP' => 10, 'JPP_NYDP' => 9, 
                        'JPP_SU_BEND' => 8, 'JPP_SU_BENDAHARI' => 8, 'JPP_EXCO' => 7,
                        'CLUB_PENGERUSI' => 6, 'CLUB_NAIB' => 5, 
                        'CLUB_SU_BEND' => 4, 'CLUB_SU_BENDAHARI' => 4, 'CLUB_AJK' => 3, 'CLUB_AHLI' => 1
                    ];
                    $pos = is_array($data) ? ($data['position'] ?? '') : '';
                    $score_b = $b_marks[$pos] ?? 0;
                } elseif ($comp === 'c') {
                    // (c) Event Organizing: Role score + Level score (Max 20 marks)
                    $role_m  = ['PENGARAH' => 5, 'TIMBALAN' => 4, 'SU_BEND' => 3, 'SU_BENDAHARI' => 3, 'AJK' => 2, 'AHLI' => 1];
                    $level_m = ['ANTARABANGSA' => 5, 'KEBANGSAAN' => 5, 'NEGERI' => 4, 'POLITEKNIK' => 3, 'JABATAN' => 2];
                    if (is_array($data)) {
                        $score_c += ($role_m[$data['role'] ?? ''] ?? 0) + ($level_m[$data['level'] ?? ''] ?? 0);
                    }
                } elseif ($comp === 'd') {
                    // (d) Event Participation: Level score (Max 30 marks)
                    $level_m = ['ANTARABANGSA' => 10, 'KEBANGSAAN' => 10, 'NEGERI' => 9, 'POLITEKNIK' => 8, 'JABATAN' => 7];
                    if (is_array($data)) {
                        $score_d += $level_m[$data['level'] ?? ''] ?? 0;
                    }
                } elseif ($comp === 'e') {
                    // (e) Bonus Features: +2 marks each (Max 6 marks)
                    $score_e += 2;
                }
            }

            // Apply max caps for sections
            $score_c = min($score_c, 20);
            $score_d = min($score_d, 30);
            $score_e = min($score_e, 6);
            $total_calculated_score = $score_a + $score_b + $score_c + $score_d + $score_e;

            // Save or Update verification details dynamically to prevent duplicate key crashes
            $stmtVerify = $pdo->prepare("
                INSERT INTO application_verifications (application_id, advisor_id, calculated_score, remarks)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    advisor_id = VALUES(advisor_id),
                    calculated_score = VALUES(calculated_score),
                    remarks = VALUES(remarks),
                    verification_date = CURRENT_TIMESTAMP
            ");
            $stmtVerify->execute([$application_id, $pa_id, $total_calculated_score, $remarks]);

            // Update status in award_applications table
            $status_target = ($action_type === 'forwarded') ? 'verified' : 'rejected';
            $stmtApp = $pdo->prepare("UPDATE award_applications SET status = ? WHERE application_id = ?");
            $stmtApp->execute([$status_target, $application_id]);

            $pdo->commit();
            $message = "Application #" . $application_id . " has been " . ($status_target === 'verified' ? 'verified & forwarded to Committee' : 'rejected') . " successfully!";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Failed to update verification status: " . $e->getMessage();
        }
    }
}

// 5. Fetch REAL pending applications submitted by students assigned to this PA
$applications = [];

if (isset($pdo) && $pa_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.application_date AS submitted_at, a.status,
                   u.full_name AS student_name, s.student_id, s.matric_no, s.programme,
                   c.category_name AS award_name
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            JOIN award_categories c ON a.category_id = c.category_id
            WHERE s.advisor_id = ? AND a.status = 'pending'
            ORDER BY a.application_date ASC
        ");
        $stmt->execute([$pa_id]);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch supporting certificates uploaded by each real student
        foreach ($applications as &$app) {
            // FIXED: Using certificate_type column name
            $stmtC = $pdo->prepare("SELECT * FROM certificates WHERE application_id = ? ORDER BY certificate_type ASC");
            $stmtC->execute([$app['application_id']]);
            $app['certificates'] = $stmtC->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
        $applications = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank AI - Review Applications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .student-item {
            cursor: pointer;
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
        }
        .student-item:hover {
            background-color: #f1f5f9;
        }
        .student-item.active {
            background-color: #eff6ff;
            border-left-color: #2563eb;
        }
        .score-pill {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 6px;
        }
    </style>
</head>
<body class="dashboard-body">

<div class="d-flex">
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
                    <a href="pa_review.php" class="nav-link active d-flex align-items-center gap-3">
                        <i class="bi bi-check-circle-fill"></i> Review Applications
                        <?php if (count($applications) > 0): ?>
                            <span class="badge bg-warning text-dark ms-auto"><?php echo count($applications); ?></span>
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
            <a href="logout.php" class="text-danger text-decoration-none text-sm fw-semibold d-flex align-items-center gap-2 ms-1">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </aside>

    <div class="main-content flex-grow-1">
        
        <header class="top-header shadow-sm">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <?php include 'notification.php'; ?>
        </header>

        <div class="p-4">
            <h4 class="fw-bold mb-2 text-dark">Advisor Verification Portal</h4>
            <p class="text-secondary text-sm mb-4">Applications submitted by students will automatically appear in the list below for document review and mark tallying.</p>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success py-2 text-sm mb-4"><i class="bi bi-check-circle me-1"></i> <?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 text-sm mb-4"><i class="bi bi-exclamation-circle me-1"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="row g-4">
                
                <div class="col-md-4">
                    <div class="card bg-white custom-card shadow-sm p-3">
                        <h6 class="fw-bold text-dark mb-3">
                            <i class="bi bi-hourglass-split me-1 text-warning"></i> Pending Reviews (<?php echo count($applications); ?>)
                        </h6>

                        <div class="list-group list-group-flush">
                            <?php if (count($applications) > 0): ?>
                                <?php foreach ($applications as $index => $app): ?>
                                    <div class="student-item p-3 mb-2 rounded border" 
                                         onclick="selectStudent(<?php echo htmlspecialchars(json_encode($app)); ?>, this)">
                                        <div class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($app['student_name']); ?></div>
                                        <div class="text-primary fw-semibold mb-1" style="font-size: 12px;"><?php echo htmlspecialchars($app['award_name']); ?></div>
                                        <div class="text-secondary" style="font-size: 11px;">
                                            Matric: <?php echo htmlspecialchars($app['matric_no']); ?> | Submitted: <?php echo htmlspecialchars(date('M d, Y', strtotime($app['submitted_at']))); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4 text-secondary">
                                    <i class="bi bi-inbox fs-2 text-muted mb-2 d-block"></i>
                                    <p class="mb-0 fw-medium">No pending applications</p>
                                    <small class="text-muted">Applications submitted by students will show up here dynamically.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    
                    <div id="emptyState" class="card bg-white custom-card shadow-sm p-5 text-center text-secondary">
                        <i class="bi bi-cursor-fill fs-1 text-primary mb-2"></i>
                        <h5>No Application Selected</h5>
                        <p class="mb-0 text-muted">Please select an application from the left list to inspect uploaded proof files and perform verification.</p>
                    </div>

                    <div id="detailCard" class="card bg-white custom-card shadow-sm p-4 d-none">
                        
                        <div class="d-flex justify-content-between align-items-start mb-4 border-bottom pb-3">
                            <div>
                                <h4 class="fw-bold text-dark mb-1" id="displayAwardName">Application Review</h4>
                                <div id="displayStudentHeader" class="text-secondary fw-medium"></div>
                            </div>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-pill">
                                Pending PA Review
                            </span>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-4 border">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-secondary text-xs fw-bold mb-1">AUTOMATED TALLY (APCP 02 A FORMULA)</div>
                                    <div class="fs-2 fw-bold text-primary"><span id="displayTotalScore">0</span> <span class="fs-6 text-muted">/ 106 Marks</span></div>
                                </div>
                                <div class="d-flex gap-2">
                                    <span class="score-pill bg-primary-subtle text-primary border border-primary-subtle">A: <span id="scoreA">0</span>m</span>
                                    <span class="score-pill bg-info-subtle text-info border border-info-subtle">B: <span id="scoreB">0</span>m</span>
                                    <span class="score-pill bg-warning-subtle text-warning border border-warning-subtle">C: <span id="scoreC">0</span>m</span>
                                    <span class="score-pill bg-success-subtle text-success border border-success-subtle">D: <span id="scoreD">0</span>m</span>
                                    <span class="score-pill bg-secondary-subtle text-dark border border-secondary-subtle">E: <span id="scoreE">0</span>m</span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-file-earmark-check me-1 text-primary"></i> Uploaded Documents & Certificates</h6>
                            <div id="certificatesContainer" class="list-group">
                            </div>
                        </div>

                        <form method="POST" action="pa_review.php">
                            <input type="hidden" name="application_id" id="inputAppId">
                            
                            <div class="mb-4">
                                <label class="form-label text-dark fw-bold" style="font-size: 14px;">
                                    Academic Advisor Verification Remarks <span class="text-danger">*</span>
                                </label>
                                <textarea name="remarks" class="form-control" rows="3" 
                                          placeholder="Enter verification comments or feedback (Saved into database for record-keeping)..." required></textarea>
                            </div>

                            <div class="d-flex gap-3 justify-content-end pt-2 border-top">
                                <button type="submit" name="action_type" value="rejected" class="btn btn-light border text-danger fw-bold px-4 py-2" onclick="return confirm('Reject this application back to student?');">
                                    <i class="bi bi-x-circle me-1"></i> Reject to Student
                                </button>
                                <button type="submit" name="action_type" value="forwarded" class="btn btn-primary fw-bold px-4 py-2">
                                    <i class="bi bi-check-circle me-1"></i> Verify & Forward to Committee
                                </button>
                            </div>
                        </form>

                    </div>

                </div>

            </div>

        </div>
    </div>
</div>

<script>
    function selectStudent(appData, element) {
        document.querySelectorAll('.student-item').forEach(item => {
            item.classList.remove('active');
        });

        element.classList.add('active');

        document.getElementById('emptyState').classList.add('d-none');
        document.getElementById('detailCard').classList.remove('d-none');

        document.getElementById('displayAwardName').textContent = appData.award_name;
        document.getElementById('displayStudentHeader').textContent = appData.student_name + " (" + appData.matric_no + ") - " + appData.programme;
        document.getElementById('inputAppId').value = appData.application_id;

        let scoreA = 0, scoreB = 0, scoreC = 0, scoreD = 0, scoreE = 0;
        let certsHTML = '';

        if (appData.certificates && appData.certificates.length > 0) {
            appData.certificates.forEach(c => {
                let extra = {};
                try {
                    extra = typeof c.extra_data === 'string' && c.extra_data.trim().startsWith('{') ? JSON.parse(c.extra_data) : c.extra_data;
                } catch(e) { 
                    extra = c.extra_data; 
                }

                let badgeColor = 'bg-primary';
                let labelText = '';
                
                // FIXED: Read certificate_type (with fallback to component_type)
                let cType = (c.certificate_type || c.component_type || '').toLowerCase();

                if (cType === 'a') {
                    let gpaVal = parseFloat(c.extra_data) || 0;
                    scoreA = Math.min(gpaVal * 10, 40);
                    labelText = `(a) Academic Result: CGPA ${gpaVal} x 10 = ${scoreA.toFixed(1)} Marks`;
                } else if (cType === 'b') {
                    badgeColor = 'bg-info';
                    let pos = typeof extra === 'object' && extra !== null ? extra.position : '';
                    let bMap = {
                        'JPP_YDP':10, 'JPP_NYDP':9, 
                        'JPP_SU_BEND':8, 'JPP_SU_BENDAHARI':8, 'JPP_EXCO':7, 
                        'CLUB_PENGERUSI':6, 'CLUB_NAIB':5, 
                        'CLUB_SU_BEND':4, 'CLUB_SU_BENDAHARI':4, 'CLUB_AJK':3, 'CLUB_AHLI':1
                    };
                    scoreB = bMap[pos] || 0;
                    labelText = `(b) Leadership Position: Score = ${scoreB} Marks`;
                } else if (cType === 'c') {
                    badgeColor = 'bg-warning';
                    let rMap = {'PENGARAH':5, 'TIMBALAN':4, 'SU_BEND':3, 'SU_BENDAHARI':3, 'AJK':2, 'AHLI':1};
                    let lMap = {'ANTARABANGSA':5, 'KEBANGSAAN':5, 'NEGERI':4, 'POLITEKNIK':3, 'JABATAN':2};
                    let roleM = (typeof extra === 'object' && extra !== null) ? (rMap[extra.role] || 0) : 0;
                    let levelM = (typeof extra === 'object' && extra !== null) ? (lMap[extra.level] || 0) : 0;
                    scoreC += (roleM + levelM);
                    labelText = `(c) Event Organizing: Role (${roleM}m) + Level (${levelM}m) = ${roleM + levelM} Marks`;
                } else if (cType === 'd') {
                    badgeColor = 'bg-success';
                    let lMap = {'ANTARABANGSA':10, 'KEBANGSAAN':10, 'NEGERI':9, 'POLITEKNIK':8, 'JABATAN':7};
                    let levelM = (typeof extra === 'object' && extra !== null) ? (lMap[extra.level] || 0) : 0;
                    scoreD += levelM;
                    labelText = `(d) Participation: Level Score = ${levelM} Marks`;
                } else if (cType === 'e') {
                    badgeColor = 'bg-secondary';
                    scoreE += 2;
                    labelText = `(e) Bonus Feature: +2 Marks`;
                }

                let fileName = c.file_name || 'Supporting Document';
                
                // FIXED: Direct link to uploads/ folder using file_name
                let fileButtonHTML = (c.file_name && c.file_name.trim() !== '') 
                    ? `<a href="uploads/${encodeURIComponent(c.file_name)}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-pdf"></i> View File</a>`
                    : `<span class="badge bg-light text-muted border">No File Attached</span>`;

                certsHTML += `
                    <div class="list-group-item d-flex justify-content-between align-items-center p-3 mb-2 border rounded">
                        <div>
                            <span class="badge ${badgeColor} text-uppercase me-2">Comp (${cType.toUpperCase()})</span>
                            <strong class="text-dark">${fileName}</strong>
                            <div class="small text-muted mt-1">${labelText}</div>
                        </div>
                        ${fileButtonHTML}
                    </div>
                `;
            });
        } else {
            certsHTML = `<div class="p-3 text-muted text-center border rounded">No supporting certificates attached.</div>`;
        }

        scoreC = Math.min(scoreC, 20);
        scoreD = Math.min(scoreD, 30);
        scoreE = Math.min(scoreE, 6);
        let totalScore = scoreA + scoreB + scoreC + scoreD + scoreE;

        document.getElementById('scoreA').textContent = scoreA.toFixed(1);
        document.getElementById('scoreB').textContent = scoreB;
        document.getElementById('scoreC').textContent = scoreC;
        document.getElementById('scoreD').textContent = scoreD;
        document.getElementById('scoreE').textContent = scoreE;
        document.getElementById('displayTotalScore').textContent = totalScore.toFixed(1);
        document.getElementById('certificatesContainer').innerHTML = certsHTML;
    }
</script>

<footer class="text-center p-4 mt-5 text-muted">
    <p class="mb-0 small">&copy; 2026 EduRank AI System - Designed By JWC</p>
</footer>
</body>
</html>