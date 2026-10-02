<?php
// 1. Include database connection configuration
require_once 'config.php';

// 2. Check login session and enforce PA role restrictions
if (function_exists('requireLogin')) {
    requireLogin();
} else {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $current_role = strtolower($_SESSION['user_role'] ?? $_SESSION['role'] ?? '');
    if (!isset($_SESSION['user_id']) || ($current_role !== 'academic advisor' && $current_role !== 'advisor')) {
        header("Location: login.php");
        exit();
    }
}

$pa_id = $_SESSION['user_id'] ?? 0;
$name  = $_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'Academic Advisor';
$role  = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'Academic Advisor';

$message = '';
$error = '';

// 3. Handle PA Verification Submission (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $application_id = filter_var($_POST['application_id'] ?? 0, FILTER_VALIDATE_INT);
    $action_type    = $_POST['action_type']; 
    $remarks        = trim($_POST['remarks'] ?? '');

    if ($application_id > 0 && isset($pdo)) {
        try {
            $pdo->beginTransaction();

            $stmtCat = $pdo->prepare("SELECT category_id FROM award_applications WHERE application_id = ?");
            $stmtCat->execute([$application_id]);
            $app_category_id = $stmtCat->fetchColumn();

            $final_percentage = 0;

            // =========================================================================
            // SCORING LOGIC (BASE OUT OF 100 + SEPARATE BONUS)
            // =========================================================================
            if ($app_category_id == 2) {
                // Category 2: Projek Terbaik - NO SCORING REQUIRED
                $final_percentage = 0; 
            } else {
                // Categories 1, 3-7: Use Automatic APCP Formula (Max 100 Base)
                $stmtCerts = $pdo->prepare("SELECT * FROM certificates WHERE application_id = ?");
                $stmtCerts->execute([$application_id]);
                $certs = $stmtCerts->fetchAll(PDO::FETCH_ASSOC);

                $score_a = 0; $score_b = 0; $score_c = 0; $score_d = 0; $score_e = 0;

                foreach ($certs as $c) {
                    // SUPER FUZZY MATCHER FOR BACKWARD COMPATIBILITY
                    $comp = strtolower($c['certificate_type'] ?? $c['component_type'] ?? '');
                    $comp = str_replace(['component', 'komponen', '_', '-', ' '], '', $comp);

                    $extra_raw = $c['extra_data'] ?? '';
                    $data = json_decode($extra_raw, true) ?: [];

                    if ($comp === 'a' || strpos($comp, 'akademik') !== false) {
                        $cgpa = floatval($extra_raw);
                        if (!is_nan($cgpa)) $score_a = min($cgpa * 10, 40);
                    } elseif ($comp === 'b' || strpos($comp, 'organisasi') !== false) {
                        $b_marks = ['JPP_YDP'=>10, 'JPP_NYDP'=>9, 'JPP_SU_BEND'=>8, 'JPP_EXCO'=>7, 'CLUB_PENGERUSI'=>6, 'CLUB_NAIB'=>5, 'CLUB_SU_BEND'=>4, 'CLUB_AJK'=>3, 'CLUB_AHLI'=>1];
                        $score_b += $b_marks[$data['position'] ?? ''] ?? 0;
                    } elseif ($comp === 'c' || strpos($comp, 'penganjuran') !== false) {
                        $role_m  = ['PENGARAH'=>5, 'TIMBALAN'=>4, 'SU_BEND'=>3, 'AJK'=>2, 'AHLI'=>1];
                        $level_m = ['ANTARABANGSA'=>5, 'KEBANGSAAN'=>5, 'NEGERI'=>4, 'POLITEKNIK'=>3, 'JABATAN'=>2];
                        $score_c += ($role_m[$data['role'] ?? ''] ?? 0) + ($level_m[$data['level'] ?? ''] ?? 0);
                    } elseif ($comp === 'd' || strpos($comp, 'penyertaan') !== false) {
                        $level_m = ['ANTARABANGSA'=>10, 'KEBANGSAAN'=>10, 'NEGERI'=>9, 'POLITEKNIK'=>8, 'JABATAN'=>7];
                        $score_d += $level_m[$data['level'] ?? ''] ?? 0;
                    } elseif ($comp === 'e' || strpos($comp, 'bonus') !== false) {
                        $score_e += 2;
                    }
                }

                // Capping the individual base components
                $score_b = min($score_b, 10);
                $score_c = min($score_c, 20); 
                $score_d = min($score_d, 30); 
                $score_e = min($score_e, 6);

                $base_score = $score_a + $score_b + $score_c + $score_d;
                $final_percentage = $base_score;
            }

            // Save Verification Decision & Remarks to DB
            $stmtVerify = $pdo->prepare("
                INSERT INTO application_verifications (application_id, advisor_id, calculated_score, remarks)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    advisor_id = VALUES(advisor_id), calculated_score = VALUES(calculated_score),
                    remarks = VALUES(remarks), verification_date = CURRENT_TIMESTAMP
            ");
            $stmtVerify->execute([$application_id, $pa_id, $final_percentage, $remarks]);

            // Update application routing status based on PA decision
            $status_target = ($action_type === 'forwarded') ? 'verified' : 'rejected';
            $stmtApp = $pdo->prepare("UPDATE award_applications SET status = ?, is_read = 0 WHERE application_id = ?");
            $stmtApp->execute([$status_target, $application_id]);

            $pdo->commit();
            $message = "Application verified & forwarded successfully!";

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Update Error: " . $e->getMessage();
        }
    }
}

// 4. Fetch Pending Applications for this specific PA
$applications = [];
if (isset($pdo) && $pa_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.application_date AS submitted_at, a.status,
                   u.full_name AS student_name, s.student_id, s.matric_no, s.programme,
                   c.category_name AS award_name, c.category_id
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            JOIN award_categories c ON a.category_id = c.category_id
            WHERE s.advisor_id = ? AND a.status = 'pending'
            ORDER BY a.application_date ASC
        ");
        $stmt->execute([$pa_id]);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Append certificates and extra project details to each application array
        foreach ($applications as &$app) {
            $stmtC = $pdo->prepare("SELECT * FROM certificates WHERE application_id = ? ORDER BY certificate_type ASC");
            $stmtC->execute([$app['application_id']]);
            $app['certificates'] = $stmtC->fetchAll(PDO::FETCH_ASSOC);

            if ($app['category_id'] == 2) {
                $stmtProj = $pdo->prepare("SELECT project_title, group_members FROM project_award_details WHERE application_id = ?");
                $stmtProj->execute([$app['application_id']]);
                $app['project_details'] = $stmtProj->fetch(PDO::FETCH_ASSOC);
            }
        }
        unset($app); // Prevent reference pointer overwriting bug

    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - Review Applications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .student-item { cursor: pointer; transition: all 0.2s ease; border-left: 4px solid transparent; }
        .student-item:hover { background-color: #f1f5f9; }
        .student-item.active { background-color: #eff6ff; border-left-color: #2563eb; }
        .score-pill { font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 6px; }
        .detail-item-bg { background-color: #fdfdfd; }
    </style>
</head>
<body class="dashboard-body">

<div class="d-flex">
    <!-- SIDEBAR NAVIGATION -->
    <aside class="sidebar p-4 d-flex flex-column justify-content-between">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="fa-solid fa-graduation-cap text-warning me-2 fs-4"></i>
                <span class="fs-5 fw-bold text-white">EduRank</span>
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
            <form method="POST" action="logout.php">
                <button type="submit" class="btn text-danger p-0 border-0 text-sm fw-semibold d-flex align-items-center gap-2 ms-1 bg-transparent">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </button>
            </form>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <div class="main-content flex-grow-1">
        <header class="top-header shadow-sm">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <?php include 'notification.php'; ?>
        </header>

        <div class="p-4">
            <h4 class="fw-bold mb-2 text-dark">Advisor Verification Portal</h4>
            
            <?php if (!empty($message)): ?><div class="alert alert-success py-2 text-sm mb-4"><i class="bi bi-check-circle me-1"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="alert alert-danger py-2 text-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <div class="row g-4">
                <!-- LEFT PANEL: Pending Applications List -->
                <div class="col-md-4">
                    <div class="card bg-white custom-card shadow-sm p-3">
                        <h6 class="fw-bold text-dark mb-3">Pending Reviews (<?php echo count($applications); ?>)</h6>
                        <div class="list-group list-group-flush">
                            <?php foreach ($applications as $row): ?>
                                <div class="student-item p-3 mb-2 rounded border" onclick='selectStudent(<?php echo json_encode($row, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>, this)'>
                                    <div class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                    <div class="text-primary fw-semibold mb-1" style="font-size: 12px;"><?php echo htmlspecialchars($row['award_name']); ?></div>
                                </div>
                            <?php endforeach; ?>
                            <?php if(empty($applications)) echo "<div class='text-center py-4 text-secondary'><i class='bi bi-inbox fs-2 text-muted mb-2 d-block'></i><p class='mb-0 fw-medium'>No pending applications</p></div>"; ?>
                        </div>
                    </div>
                </div>

                <!-- RIGHT PANEL: Detailed Application Review UI -->
                <div class="col-md-8">
                    <!-- Default Empty State -->
                    <div id="emptyState" class="card bg-white custom-card shadow-sm p-5 text-center text-secondary">
                        <i class="bi bi-cursor-fill fs-1 text-primary mb-2"></i><h5>No Application Selected</h5>
                    </div>

                    <!-- Dynamic Details Card -->
                    <div id="detailCard" class="card bg-white custom-card shadow-sm p-4 d-none">
                        <div class="d-flex justify-content-between align-items-start mb-4 border-bottom pb-3">
                            <div>
                                <h4 class="fw-bold text-dark mb-1" id="displayAwardName">Application Review</h4>
                                <div id="displayStudentHeader" class="text-secondary fw-medium"></div>
                            </div>
                        </div>

                        <!-- PERCENTAGE DISPLAY UI -->
                        <div id="percentageDisplayBox" class="p-3 bg-light rounded-3 mb-4 border">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-secondary text-xs fw-bold mb-1">OFFICIAL APCP RATING</div>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="fs-2 fw-bold text-primary"><span id="displayPercentage">0</span><span class="fs-5 text-primary">%</span></div>
                                        <div id="bigBonusContainer" class="d-none">
                                            <span class="badge bg-warning text-dark fs-6 shadow-sm border border-warning rounded-pill px-3 py-2"><i class="bi bi-star-fill me-1"></i>+<span id="displayBigBonus">0</span> Bonus</span>
                                        </div>
                                    </div>
                                    <div class="mt-1" id="rawScoreText"></div>
                                </div>
                                <div id="autoScorePills" class="d-flex gap-2">
                                    <span class="score-pill bg-primary-subtle text-primary border">A: <span id="scoreA">0</span></span>
                                    <span class="score-pill bg-info-subtle text-info border">B: <span id="scoreB">0</span></span>
                                    <span class="score-pill bg-warning-subtle text-warning border">C: <span id="scoreC">0</span></span>
                                    <span class="score-pill bg-success-subtle text-success border">D: <span id="scoreD">0</span></span>
                                    <span class="score-pill bg-secondary-subtle text-dark border">Bonus: <span id="scoreE">0</span></span>
                                </div>
                            </div>
                            <div id="capWarningsContainer" class="mt-3"></div>
                        </div>

                        <div id="minimumMarkWarning" class="alert alert-danger mb-4 d-none text-sm fw-bold"></div>

                        <div class="mb-4">
                            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-ui-checks text-primary me-2"></i>Borang Permohonan Pelajar</h5>
                            <div id="certificatesContainer"></div>
                        </div>

                        <!-- VERIFICATION FORM (Remarks & Submission Actions) -->
                        <form method="POST" action="pa_review.php">
                            <input type="hidden" name="application_id" id="inputAppId">
                            
                            <div class="mb-4">
                                <label class="form-label text-dark fw-bold">Verification Remarks (Ulasan Pengesahan PA) <span class="text-danger">*</span></label>
                                <textarea name="remarks" class="form-control" rows="3" placeholder="Provide justification or evaluation remarks..." required></textarea>
                            </div>
                            
                            <div class="d-flex gap-3 justify-content-end pt-2 border-top">
                                <button type="submit" name="action_type" value="rejected" class="btn btn-light border text-danger fw-bold px-4 py-2">Reject to Student</button>
                                <button type="submit" name="action_type" value="forwarded" class="btn btn-primary fw-bold px-4 py-2" id="btnForward">Verify & Forward</button>
                            </div>
                        </form>
                        
                        <footer class="text-center p-4 mt-5 text-muted">
                            <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
                        </footer>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CLIENT-SIDE RENDERING SCRIPT -->
<script>
    function selectStudent(appData, element) {
        // UI Selection Highlight
        document.querySelectorAll('.student-item').forEach(item => item.classList.remove('active'));
        element.classList.add('active');

        document.getElementById('emptyState').classList.add('d-none');
        document.getElementById('detailCard').classList.remove('d-none');

        // Populate Headers and Hidden Form Value
        document.getElementById('displayAwardName').textContent = appData.award_name;
        document.getElementById('displayStudentHeader').textContent = "Nama Pelajar: " + appData.student_name + " | No. Pendaftaran: " + appData.matric_no;
        document.getElementById('inputAppId').value = appData.application_id;

        let certsHTML = '';
        let warningDiv = document.getElementById('minimumMarkWarning');
        let percentageDisplayBox = document.getElementById('percentageDisplayBox');
        let displayPercentage = document.getElementById('displayPercentage');
        let rawScoreText = document.getElementById('rawScoreText');
        let bigBonusContainer = document.getElementById('bigBonusContainer');
        let displayBigBonus = document.getElementById('displayBigBonus');
        
        warningDiv.classList.add('d-none');
        bigBonusContainer.classList.add('d-none');

        // Helper Function: Safely parse extra JSON data
        const getExtra = (c) => {
            if (!c) return {};
            try { return typeof c.extra_data === 'string' && c.extra_data.trim().startsWith('{') ? JSON.parse(c.extra_data) : c.extra_data; } catch(e){ return {}; }
        };

        // NEW SUPER FUZZY MATCHER: Catch Component A, Komponen A, Akademik, etc.
        const getCleanType = (c) => {
            let t = c.certificate_type || c.component_type || c.type || '';
            return t.toLowerCase().replace(/component/g, '').replace(/komponen/g, '').replace(/[_\-\s]/g, '');
        };

        // =========================================================================
        // CATEGORY 2: PROJEK TERBAIK
        // =========================================================================
        if (appData.category_id == 2) {
            percentageDisplayBox.classList.add('d-none');

            let projTitle = (appData.project_details && appData.project_details.project_title) ? appData.project_details.project_title : '';
            let projMembers = (appData.project_details && appData.project_details.group_members) ? appData.project_details.group_members : '';
            let formattedMembers = projMembers ? projMembers.replace(/\n/g, '<br>') : '<span class="text-muted">Tiada Rekod Ahli</span>';

            let cProj = appData.certificates.find(c => getCleanType(c).includes('projekbukti') || getExtra(c).type === 'Bukti_Pencapaian_FYP');
            let cInd = appData.certificates.find(c => getCleanType(c).includes('projekindustri') || getExtra(c).type === 'Sijil_Penghargaan_Industri');

            certsHTML = `
                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-folder-check me-2"></i>Butiran Projek & Bukti (Kategori Projek)</h6>
                    <div class="border border-primary-subtle rounded p-3 bg-light">
                        <div class="mb-3">
                            <label class="form-label text-dark fw-bold" style="font-size: 13px;">Tajuk Projek</label>
                            <input type="text" class="form-control bg-white" value="${projTitle}" readonly>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-dark fw-bold" style="font-size: 13px;">Ahli Kumpulan</label>
                            <div class="p-3 border rounded bg-white" style="font-size:13px; line-height:1.6;">${formattedMembers}</div>
                        </div>
                        <div class="row g-3 border-top pt-3">
                            <div class="col-md-6">
                                <label class="form-label text-dark fw-bold" style="font-size: 13px;"><i class="bi bi-file-earmark-check text-success me-1"></i>Bukti Pencapaian FYP (Wajib)</label>
                                <div>${cProj && cProj.file_name ? `<a href="uploads/${cProj.file_name}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Dokumen Wajib</a>` : '<span class="text-danger fw-bold">Tiada Dokumen Dimuat Naik!</span>'}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-dark fw-bold" style="font-size: 13px;"><i class="bi bi-award text-warning me-1"></i>Sijil Penghargaan Industri</label>
                                <div>${cInd && cInd.file_name ? `<a href="uploads/${cInd.file_name}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Sijil Industri</a>` : '<span class="text-muted">Tiada (Pilihan)</span>'}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        } 
        // =========================================================================
        // OTHER CATEGORIES: Automatic Calculations
        // =========================================================================
        else {
            percentageDisplayBox.classList.remove('d-none');

            // 1. Fuzzy Map Certificates
            let certA = appData.certificates.find(c => { let t = getCleanType(c); return t === 'a' || t.includes('akademik'); });
            let certBs = appData.certificates.filter(c => { let t = getCleanType(c); return t === 'b' || t.includes('organisasi'); });
            let certCs = appData.certificates.filter(c => { let t = getCleanType(c); return t === 'c' || t.includes('penganjuran'); });
            let certDs = appData.certificates.filter(c => { let t = getCleanType(c); return t === 'd' || t.includes('penyertaan'); });
            
            let certB_JPP = certBs.find(c => getCleanType(c).includes('jpp') || getExtra(c).type === 'JPP' || (getExtra(c).position || '').includes('JPP')) || certBs[0];
            let certB_Kelab = certBs.find(c => c !== certB_JPP) || certBs[1];

            let certE_Prof = appData.certificates.find(c => getCleanType(c).includes('profesional') || getExtra(c).type === 'Bonus_Profesional');
            let certE_Luar = appData.certificates.find(c => getCleanType(c).includes('luar') || getExtra(c).type === 'Bonus_Luar');
            let certE_Intl = appData.certificates.find(c => getCleanType(c).includes('antarabangsa') || getExtra(c).type === 'Bonus_Antarabangsa');

            // 2. Extract Data Objects safely
            let extJPP = getExtra(certB_JPP);
            let extKelab = getExtra(certB_Kelab);
            let extC1 = getExtra(certCs[0]), extC2 = getExtra(certCs[1]);
            let extD1 = getExtra(certDs[0]), extD2 = getExtra(certDs[1]), extD3 = getExtra(certDs[2]);

            // 3. Recalculate Scores
            let cgpaVal = certA && certA.extra_data ? parseFloat(certA.extra_data) : 0;
            if (isNaN(cgpaVal)) cgpaVal = 0;
            let scoreA = Math.min(cgpaVal * 10, 40);
            
            const bMap = {'JPP_YDP':10, 'JPP_NYDP':9, 'JPP_SU_BEND':8, 'JPP_EXCO':7, 'CLUB_PENGERUSI':6, 'CLUB_NAIB':5, 'CLUB_SU_BEND':4, 'CLUB_AJK':3, 'CLUB_AHLI':1};
            let scoreB = (bMap[extJPP.position] || 0) + (bMap[extKelab.position] || 0);

            const rMap = {'PENGARAH':5, 'TIMBALAN':4, 'SU_BEND':3, 'AJK':2, 'AHLI':1};
            const lMap = {'ANTARABANGSA':5, 'KEBANGSAAN':5, 'NEGERI':4, 'POLITEKNIK':3, 'JABATAN':2};
            let scoreC = (rMap[extC1.role] || 0) + (lMap[extC1.level] || 0) + (rMap[extC2.role] || 0) + (lMap[extC2.level] || 0);

            const lMapD = {'ANTARABANGSA':10, 'KEBANGSAAN':10, 'NEGERI':9, 'POLITEKNIK':8, 'JABATAN':7};
            let scoreD = (lMapD[extD1.level] || 0) + (lMapD[extD2.level] || 0) + (lMapD[extD3.level] || 0);

            let scoreE = (certE_Prof ? 2 : 0) + (certE_Luar ? 2 : 0) + (certE_Intl ? 2 : 0);

            let cappedB = Math.min(scoreB, 10);
            let cappedC = Math.min(scoreC, 20); 
            let cappedD = Math.min(scoreD, 30); 
            let cappedE = Math.min(scoreE, 6);
            let baseScore = scoreA + cappedB + cappedC + cappedD;

            // 4. Construct the Form-Style UI
            certsHTML = `
                <!-- Section A -->
                <div class="mb-4">
                    <h6 class="fw-bold text-primary mb-2"><i class="bi bi-mortarboard me-2"></i>(a) Pencapaian Akademik / HPNM</h6>
                    <div class="row border rounded p-3 bg-light mx-0">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label text-secondary fw-medium" style="font-size: 13px;">HPNM / CGPA</label>
                            <input type="text" class="form-control bg-white fw-bold" value="${cgpaVal > 0 ? cgpaVal.toFixed(2) : '-'}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary fw-medium" style="font-size: 13px;">Transkrip Keputusan</label>
                            <div>${certA && certA.file_name ? `<a href="uploads/${certA.file_name}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Dokumen</a>` : '<input type="text" class="form-control text-muted bg-white" value="Tiada Dokumen Dimuat Naik" readonly>'}</div>
                        </div>
                    </div>
                </div>

                <!-- Section B -->
                <div class="mb-4">
                    <h6 class="fw-bold text-primary mb-2"><i class="bi bi-people me-2"></i>(b) Kokurikulum 1 - Pengurusan Organisasi</h6>
                    <div class="border rounded p-3 bg-light">
                        <!-- JPP -->
                        <div class="mb-4">
                            <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Jawatankuasa Perwakilan Pelajar (JPP)</div>
                            <div class="border border-top-0 rounded-bottom p-3 bg-white">
                                <div class="row g-3 align-items-end">
                                    <div class="col-12">
                                        <label class="text-secondary small fw-medium mb-1">Nama Organisasi</label>
                                        <input type="text" class="form-control bg-white" value="${extJPP.org_name || ''}" placeholder="Tiada Rekod JPP" readonly>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="text-secondary small fw-medium mb-1">Jawatan</label>
                                        <input type="text" class="form-control bg-white" value="${extJPP.position || ''}" placeholder="Tiada Jawatan" readonly>
                                    </div>
                                    <div class="col-md-5">
                                        ${certB_JPP && certB_JPP.file_name ? `<a href="uploads/${certB_JPP.file_name}" target="_blank" class="btn btn-outline-primary w-100"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Surat Pelantikan</a>` : '<input type="text" class="form-control text-muted bg-white text-center" value="Tiada Dokumen Bukti" readonly>'}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Kelab -->
                        <div>
                            <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Kelab / Persatuan & Lain-lain Organisasi</div>
                            <div class="border border-top-0 rounded-bottom p-3 bg-white">
                                <div class="row g-3 align-items-end">
                                    <div class="col-12">
                                        <label class="text-secondary small fw-medium mb-1">Nama Kelab / Persatuan</label>
                                        <input type="text" class="form-control bg-white" value="${extKelab.org_name || ''}" placeholder="Tiada Rekod Kelab" readonly>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="text-secondary small fw-medium mb-1">Jawatan</label>
                                        <input type="text" class="form-control bg-white" value="${extKelab.position || ''}" placeholder="Tiada Jawatan" readonly>
                                    </div>
                                    <div class="col-md-5">
                                        ${certB_Kelab && certB_Kelab.file_name ? `<a href="uploads/${certB_Kelab.file_name}" target="_blank" class="btn btn-outline-primary w-100"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Surat Pelantikan</a>` : '<input type="text" class="form-control text-muted bg-white text-center" value="Tiada Dokumen Bukti" readonly>'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section C -->
                <div class="mb-4">
                    <h6 class="fw-bold text-primary mb-2"><i class="bi bi-calendar-event me-2"></i>(c) Kokurikulum 2 - Penganjuran Program</h6>
                    <div class="border rounded p-3 bg-light">
                        ${[1, 2].map(i => {
                            let ext = i===1 ? extC1 : extC2;
                            let cert = i===1 ? certCs[0] : certCs[1];
                            return `
                            <div class="${i===1 ? 'mb-4' : ''}">
                                <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Penganjuran Program ${i}</div>
                                <div class="border border-top-0 rounded-bottom p-3 bg-white">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-12">
                                            <label class="text-secondary small fw-medium mb-1">Nama Program</label>
                                            <input type="text" class="form-control bg-white" value="${ext.name || ''}" placeholder="Tiada Program ${i} Didaftarkan" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="text-secondary small fw-medium mb-1">Jawatan Dipegang</label>
                                            <input type="text" class="form-control bg-white" value="${ext.role || ''}" placeholder="Tiada" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="text-secondary small fw-medium mb-1">Peringkat Program</label>
                                            <input type="text" class="form-control bg-white" value="${ext.level || ''}" placeholder="Tiada" readonly>
                                        </div>
                                        <div class="col-12 text-end">
                                            ${cert && cert.file_name ? `<a href="uploads/${cert.file_name}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Dokumen / Sijil</a>` : '<span class="text-muted small">Tiada dokumen sokongan dimuat naik.</span>'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            `;
                        }).join('')}
                    </div>
                </div>

                <!-- Section D -->
                <div class="mb-4">
                    <h6 class="fw-bold text-primary mb-2"><i class="bi bi-trophy me-2"></i>(d) Kokurikulum 3 - Penyertaan Program</h6>
                    <div class="border rounded p-3 bg-light">
                        ${[1, 2, 3].map(i => {
                            let ext = i===1 ? extD1 : (i===2 ? extD2 : extD3);
                            let cert = i===1 ? certDs[0] : (i===2 ? certDs[1] : certDs[2]);
                            return `
                            <div class="mb-3">
                                <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Penyertaan Acara / Pertandingan ${i}</div>
                                <div class="border border-top-0 rounded-bottom p-3 bg-white">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-12">
                                            <label class="text-secondary small fw-medium mb-1">Nama Acara</label>
                                            <input type="text" class="form-control bg-white" value="${ext.name || ''}" placeholder="Tiada Penyertaan ${i} Didaftarkan" readonly>
                                        </div>
                                        <div class="col-md-7">
                                            <label class="text-secondary small fw-medium mb-1">Peringkat Acara</label>
                                            <input type="text" class="form-control bg-white" value="${ext.level || ''}" placeholder="Tiada" readonly>
                                        </div>
                                        <div class="col-md-5">
                                            ${cert && cert.file_name ? `<a href="uploads/${cert.file_name}" target="_blank" class="btn btn-outline-primary w-100"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Sijil Penyertaan</a>` : '<input type="text" class="form-control text-muted bg-white text-center" value="Tiada Sijil" readonly>'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            `;
                        }).join('')}
                    </div>
                </div>

                <!-- Section E -->
                <div class="mb-4">
                    <h6 class="fw-bold text-primary mb-2"><i class="bi bi-star me-2"></i>(e) Lain-lain / Bonus</h6>
                    <div class="border rounded p-3 bg-light">
                        <div class="row g-3 align-items-center mb-3">
                            <div class="col-md-5">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" disabled ${certE_Prof ? 'checked' : ''}>
                                    <label class="form-check-label text-dark fw-medium" style="font-size: 14px;">Sijil Profesional (+2 Markah)</label>
                                </div>
                            </div>
                            <div class="col-md-7">${certE_Prof && certE_Prof.file_name ? `<a href="uploads/${certE_Prof.file_name}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Dokumen / Sijil</a>` : '<span class="text-muted small">- Tidak Diisi -</span>'}</div>
                        </div>
                        <div class="row g-3 align-items-center mb-3">
                            <div class="col-md-5">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" disabled ${certE_Luar ? 'checked' : ''}>
                                    <label class="form-check-label text-dark fw-medium" style="font-size: 14px;">Pengiktirafan Luar (+2 Markah)</label>
                                </div>
                            </div>
                            <div class="col-md-7">${certE_Luar && certE_Luar.file_name ? `<a href="uploads/${certE_Luar.file_name}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Dokumen / Sijil</a>` : '<span class="text-muted small">- Tidak Diisi -</span>'}</div>
                        </div>
                        <div class="row g-3 align-items-center">
                            <div class="col-md-5">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" disabled ${certE_Intl ? 'checked' : ''}>
                                    <label class="form-check-label text-dark fw-medium" style="font-size: 14px;">Penglibatan Antarabangsa (+2 Markah)</label>
                                </div>
                            </div>
                            <div class="col-md-7">${certE_Intl && certE_Intl.file_name ? `<a href="uploads/${certE_Intl.file_name}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Dokumen / Sijil</a>` : '<span class="text-muted small">- Tidak Diisi -</span>'}</div>
                        </div>
                    </div>
                </div>
            `;
            
            // Re-apply scores to UI
            document.getElementById('scoreA').textContent = scoreA.toFixed(1);
            document.getElementById('scoreB').textContent = cappedB;
            document.getElementById('scoreC').textContent = cappedC;
            document.getElementById('scoreD').textContent = cappedD;
            document.getElementById('scoreE').textContent = cappedE;
            displayPercentage.textContent = baseScore.toFixed(1);
            if (cappedE > 0) {
                bigBonusContainer.classList.remove('d-none');
                displayBigBonus.textContent = cappedE;
            }
            rawScoreText.innerHTML = `<div class="mt-2 d-flex gap-2"><span class="badge bg-secondary py-1 px-2" style="font-size:12px;">Markah Asas: ${baseScore.toFixed(1)} / 100</span></div>`;
            
            // Validation Warnings
            let minRequired = (appData.category_id == 1) ? 85 : 80;
            if (baseScore < minRequired) {
                warningDiv.classList.remove('d-none');
                warningDiv.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> TIDAK LAYAK: Syarat markah minimum ialah ${minRequired}%. Pelajar ini hanya mencapai ${baseScore.toFixed(1)}%.`;
            }
        }
        
        document.getElementById('certificatesContainer').innerHTML = certsHTML;
    }
</script>
</body>
</html>