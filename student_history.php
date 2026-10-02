<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = strtoupper($_SESSION['user_name'] ?? '');

// Fetch student base data
$stmtS = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmtS->execute([$user_id]);
$student = $stmtS->fetch(PDO::FETCH_ASSOC);

// Check pending status for sidebar badge
$pending = 0;
$stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM award_applications WHERE student_id = ? AND status = 'pending'");
$stmtCheck->execute([$student['student_id']]);
$pending = $stmtCheck->fetchColumn();

// Logic for List View vs Detail View
$view_application = null;
$view_certificates = [];

// Variables for scoring logic
$scoreA = 0; $scoreB = 0; $scoreC = 0; $scoreD = 0; $scoreE = 0;
$cappedB = 0; $cappedC = 0; $cappedD = 0; $cappedE = 0; $baseScore = 0;
$certsHTML = '';

if (isset($_GET['view_app']) && is_numeric($_GET['view_app'])) {
    $app_id = $_GET['view_app'];
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, c.category_name, cert.extra_data AS student_cgpa,
                   v.remarks AS pa_remarks, v.calculated_score AS pa_score, v.verification_date AS pa_verified_at,
                   e.remarks AS eval_remarks, e.total_score AS eval_score, e.evaluation_date AS eval_at,
                   p.project_title, p.group_members
            FROM award_applications a
            JOIN award_categories c ON a.category_id = c.category_id
            LEFT JOIN certificates cert ON a.application_id = cert.application_id AND (cert.certificate_type = 'a' OR LOWER(cert.certificate_type) = 'akademik')
            LEFT JOIN application_verifications v ON a.application_id = v.application_id
            LEFT JOIN evaluations e ON a.application_id = e.application_id
            LEFT JOIN project_award_details p ON a.application_id = p.application_id
            WHERE a.application_id = ? AND a.student_id = ?
        ");
        $stmt->execute([$app_id, $student['student_id']]);
        $view_application = $stmt->fetch();
        
        if ($view_application) {
            $stmtC = $pdo->prepare("SELECT * FROM certificates WHERE application_id = ? ORDER BY certificate_type ASC");
            $stmtC->execute([$app_id]);
            $view_certificates = $stmtC->fetchAll(PDO::FETCH_ASSOC);

            // =================================================================
            // RECOMPUTE SCORES AND BUILD RICH UI FOR STUDENT HISTORY VIEW
            // =================================================================
            if ($view_application['category_id'] == 2) {
                // Category 2: Projek Terbaik
                $projTitle = !empty($view_application['project_title']) ? $view_application['project_title'] : 'Tiada Rekod';
                $projMembers = !empty($view_application['group_members']) ? nl2br(htmlspecialchars($view_application['group_members'])) : 'Tiada Rekod';

                $certsHTML .= '
                <div class="list-group-item p-3 mb-3 border-primary bg-light">
                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-folder-fill me-2"></i>Butiran Projek (Kategori FYP)</h6>
                    <div class="mb-2"><span class="text-secondary small d-block">Tajuk Projek:</span> <strong class="text-dark fs-6">'.htmlspecialchars($projTitle).'</strong></div>
                    <div><span class="text-secondary small d-block">Ahli Kumpulan:</span> <span class="text-dark" style="font-size:13px;">'.$projMembers.'</span></div>
                </div>
                <h6 class="fw-bold text-dark mt-4 mb-3">Bukti Dimuat Naik Oleh Pelajar:</h6>';

                if (!empty($view_certificates)) {
                    foreach ($view_certificates as $c) {
                        $typeLabel = "Dokumen Biasa";
                        $badgeClass = "bg-secondary";
                        $fileLink = !empty($c['file_name']) ? '<a href="uploads/'.rawurlencode($c['file_name']).'" target="_blank" class="btn btn-sm btn-outline-primary fw-bold"><i class="bi bi-file-earmark-pdf me-1"></i>View Evidence</a>' : '';
                        
                        $extraData = json_decode($c['extra_data'], true) ?? [];
                        $cType = strtolower($c['certificate_type'] ?? '');
                        
                        if (($extraData['type'] ?? '') === 'Bukti_Pencapaian_FYP' || $cType === 'projek_bukti') {
                            $typeLabel = "Bukti Pencapaian FYP (WAJIB)";
                            $badgeClass = "bg-success";
                        } elseif (($extraData['type'] ?? '') === 'Sijil_Penghargaan_Industri' || $cType === 'projek_industri') {
                            $typeLabel = "Sijil Penghargaan Industri (Pilihan)";
                            $badgeClass = "bg-warning text-dark";
                        }

                        $certsHTML .= '
                        <div class="list-group-item p-3 d-flex justify-content-between align-items-center mb-2 shadow-sm border rounded">
                            <div><span class="badge '.$badgeClass.' mb-1">'.$typeLabel.'</span><br><span class="text-muted small">'.htmlspecialchars($c['file_name'] ?? '').'</span></div> 
                            '.$fileLink.'
                        </div>';
                    }
                }
            } else {
                // Category 1 & 3-7: Calculate marks
                foreach ($view_certificates as $c) {
                    $extra = json_decode($c['extra_data'], true) ?? [];
                    if (!is_array($extra)) $extra = []; 
                    
                    $cType = strtolower($c['certificate_type'] ?? '');
                    $fileLink = !empty($c['file_name']) ? '<a href="uploads/'.rawurlencode($c['file_name']).'" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Lihat Dokumen</a>' : '<span class="badge bg-light text-muted border">Form Entry</span>';

                    $typeLabel = '';
                    $badgeClass = '';
                    $itemDetails = '';
                    $itemScore = 0;

                    if ($cType === 'a' || $cType === 'akademik') { 
                        $cgpa = floatval($c['extra_data']);
                        $itemScore = min($cgpa * 10, 40);
                        $scoreA = $itemScore;
                        $typeLabel = 'Komp A - Akademik';
                        $badgeClass = 'bg-primary';
                        $itemDetails = '<div class="fw-bold text-dark">CGPA / HPNM: '.number_format($cgpa, 2).'</div>';
                    } elseif ($cType === 'b' || $cType === 'organisasi_jpp' || $cType === 'organisasi_kelab') { 
                        $bMap = ['JPP_YDP'=>10, 'JPP_NYDP'=>9, 'JPP_SU_BEND'=>8, 'JPP_EXCO'=>7, 'CLUB_PENGERUSI'=>6, 'CLUB_NAIB'=>5, 'CLUB_SU_BEND'=>4, 'CLUB_AJK'=>3, 'CLUB_AHLI'=>1];
                        $itemScore = $bMap[$extra['position'] ?? ''] ?? 0; 
                        $scoreB += $itemScore; 

                        $isJPP = ($cType === 'organisasi_jpp' || ($extra['type'] ?? '') === 'JPP');
                        $typeLabel = $isJPP ? 'Komp B - Organisasi (JPP)' : 'Komp B - Organisasi (Kelab)';
                        $badgeClass = 'bg-info text-dark';
                        $itemDetails = '
                            <div class="text-dark small mb-1"><strong>Nama Organisasi:</strong> '.htmlspecialchars($extra['org_name'] ?? '-').'</div>
                            <div class="text-dark small"><strong>Jawatan:</strong> '.htmlspecialchars($extra['position'] ?? '-').'</div>
                        ';
                    } elseif ($cType === 'c' || $cType === 'penganjuran_program') {
                        $rMap = ['PENGARAH'=>5, 'TIMBALAN'=>4, 'SU_BEND'=>3, 'AJK'=>2, 'AHLI'=>1];
                        $lMap = ['ANTARABANGSA'=>5, 'KEBANGSAAN'=>5, 'NEGERI'=>4, 'POLITEKNIK'=>3, 'JABATAN'=>2];
                        $rScore = $rMap[$extra['role'] ?? ''] ?? 0;
                        $lScore = $lMap[$extra['level'] ?? ''] ?? 0;
                        $itemScore = $rScore + $lScore;
                        $scoreC += $itemScore;

                        $typeLabel = 'Komp C - Penganjuran Program';
                        $badgeClass = 'bg-warning text-dark';
                        $itemDetails = '
                            <div class="text-dark small mb-1"><strong>Nama Program:</strong> '.htmlspecialchars($extra['name'] ?? '-').'</div>
                            <div class="text-dark small"><strong>Jawatan:</strong> '.htmlspecialchars($extra['role'] ?? '-').' ('.$rScore.' pt) &nbsp;|&nbsp; <strong>Peringkat:</strong> '.htmlspecialchars($extra['level'] ?? '-').' ('.$lScore.' pt)</div>
                        ';
                    } elseif ($cType === 'd' || $cType === 'penyertaan_program') {
                        $lMap = ['ANTARABANGSA'=>10, 'KEBANGSAAN'=>10, 'NEGERI'=>9, 'POLITEKNIK'=>8, 'JABATAN'=>7];
                        $itemScore = $lMap[$extra['level'] ?? ''] ?? 0;
                        $scoreD += $itemScore;

                        $typeLabel = 'Komp D - Penyertaan Program';
                        $badgeClass = 'bg-success';
                        $itemDetails = '
                            <div class="text-dark small mb-1"><strong>Acara:</strong> '.htmlspecialchars($extra['name'] ?? '-').'</div>
                            <div class="text-dark small"><strong>Peringkat:</strong> '.htmlspecialchars($extra['level'] ?? '-').'</div>
                        ';
                    } elseif ($cType === 'e' || strpos($cType, 'bonus_') === 0) { 
                        $itemScore = 2;
                        $scoreE += $itemScore; 

                        $bonusType = strtoupper(str_replace('bonus_', '', $cType));
                        if($bonusType === 'E') $bonusType = 'BONUS';
                        
                        $typeLabel = 'Komp E - ' . $bonusType;
                        $badgeClass = 'bg-secondary';
                        $itemDetails = '<div class="text-dark small"><strong>Bukti Penglibatan Tambahan Dimuat Naik</strong></div>';
                    }

                    if ($typeLabel !== '') {
                        $certsHTML .= '
                        <div class="list-group-item p-3 mb-2 detail-item-bg shadow-sm border rounded">
                            <div class="d-flex justify-content-between align-items-start mb-2 border-bottom pb-2">
                                <span class="badge '.$badgeClass.' fw-bold" style="font-size:12px;">'.$typeLabel.'</span>
                                <span class="badge bg-light text-dark border px-2 py-1"><i class="bi bi-plus text-success fw-bold"></i>'.$itemScore.' Markah</span>
                            </div>
                            <div class="mb-3">'.$itemDetails.'</div>
                            <div class="d-flex justify-content-between align-items-center bg-white p-2 border rounded">
                                <span class="text-muted small text-truncate" style="max-width: 70%;"><i class="bi bi-file-earmark-pdf text-danger me-1"></i>'.htmlspecialchars($c['file_name'] ?? '').'</span>
                                '.$fileLink.'
                            </div>
                        </div>';
                    }
                }

                // Capping Logic Silently
                $cappedB = min($scoreB, 10);
                $cappedC = min($scoreC, 20); 
                $cappedD = min($scoreD, 30); 
                $cappedE = min($scoreE, 6);
                $baseScore = $scoreA + $cappedB + $cappedC + $cappedD;
            }
        }
    } catch(PDOException $e) {}
} else {
    // =========================================================================
    // LIST VIEW LOGIC: ORDER BY application_id ASC (To display APP-0001 at top)
    // =========================================================================
    $stmtAll = $pdo->prepare("
        SELECT a.*, c.category_name, cert.extra_data AS student_cgpa, v.remarks AS pa_remarks 
        FROM award_applications a 
        JOIN award_categories c ON a.category_id = c.category_id 
        LEFT JOIN certificates cert ON a.application_id = cert.application_id AND (cert.certificate_type = 'a' OR LOWER(cert.certificate_type) = 'akademik') 
        LEFT JOIN application_verifications v ON a.application_id = v.application_id 
        WHERE a.student_id = ? 
        ORDER BY a.application_id DESC
    ");
    $stmtAll->execute([$student['student_id']]);
    $applications = $stmtAll->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - Submission History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .score-pill { font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 6px; }
        .detail-item-bg { background-color: #fdfdfd; }
    </style>
</head>
<body class="dashboard-body">

<div class="d-flex">
    <aside class="sidebar p-4 d-flex flex-column justify-content-between" style="min-height: 100vh;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="fa-solid fa-graduation-cap text-warning me-2 fs-4"></i>
                <span class="fs-5 fw-bold text-white">EduRank</span>
            </div>
            <div class="text-uppercase text-secondary text-xs fw-bold mb-3" style="font-size: 11px; letter-spacing: 1px;">MENU</div>
            <ul class="nav nav-pills flex-column gap-2">
                <li class="nav-item"><a href="student_dashboard.php" class="nav-link d-flex align-items-center gap-3"><i class="bi bi-house-fill"></i> Dashboard</a></li>
                <li class="nav-item"><a href="student_history.php" class="nav-link active d-flex align-items-center gap-3"><i class="bi bi-clock-history"></i> Submission History <?php if($pending > 0): ?><span class="badge bg-warning text-dark ms-auto"><?php echo $pending; ?></span><?php endif; ?></a></li>
                <li class="nav-item"><a href="student_apply.php" class="nav-link d-flex align-items-center gap-3"><i class="bi bi-plus-circle"></i> Submit Application</a></li>
                <li class="nav-item"><a href="student_profile.php" class="nav-link d-flex align-items-center gap-3"><i class="bi bi-person"></i> My Profile</a></li>
            </ul>
        </div>
        <div class="border-top border-secondary pt-3">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px;">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <div>
                    <div class="fw-bold text-sm text-white" style="font-size: 14px;"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="text-secondary" style="font-size: 12px;">Student</div>
                </div>
            </div>
            <a href="logout.php" class="text-danger text-decoration-none text-sm fw-semibold d-flex align-items-center gap-2 ms-1"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </aside>

    <div class="main-content flex-grow-1 d-flex flex-column" style="min-height: 100vh;">
        <header class="top-header shadow-sm">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <?php if (file_exists('notification.php')) include 'notification.php'; ?>
        </header>

        <div class="p-4 flex-grow-1">
            <div class="mb-4">
                <h4 class="fw-bold text-dark mb-1">APCP Submission History & Workflow Status</h4>
                <p class="text-secondary text-sm">Track your application review status through JKP and JIP.</p>
            </div>

            <?php if($view_application): ?>
                <!-- DETAIL VIEW -->
                <div class="card bg-white custom-card shadow-sm p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Application <?php echo sprintf('APP-%04d', $view_application['application_id']); ?></h5>
                        <a href="student_history.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back to List</a>
                    </div>
                    
                    <div class="row mb-4 bg-light p-3 rounded mx-0 border">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <p class="mb-1"><strong>Category:</strong> <span class="text-primary fw-semibold"><?php echo htmlspecialchars($view_application['category_name']); ?></span></p>
                            <p class="mb-0"><strong>Submission Date:</strong> <?php echo date('F d, Y', strtotime($view_application['application_date'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>HPNM / CGPA:</strong> <span class="fw-bold text-dark fs-5"><?php $cgpa_val = floatval($view_application['student_cgpa'] ?? $view_application['gpa'] ?? 0); echo $cgpa_val > 0 ? number_format($cgpa_val, 2) : '-'; ?></span></p>
                            <p class="mb-0"><strong>Status:</strong> 
                                <?php if ($view_application['status'] === 'pending'): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-pill">Pending JKP Review</span>
                                <?php elseif ($view_application['status'] === 'verified'): ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 rounded-pill">Verified by JKP</span>
                                <?php elseif ($view_application['status'] === 'evaluated'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Endorsed by JIP</span>
                                <?php elseif ($view_application['status'] === 'nominated'): ?>
                                    <span class="badge bg-warning text-dark border px-3 py-1 rounded-pill shadow-sm"><i class="bi bi-star-fill me-1"></i> FINAL WINNER</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">Rejected</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <!-- PERCENTAGE DISPLAY UI -->
                    <?php if ($view_application['category_id'] != 2): ?>
                        <div class="p-3 bg-light rounded-3 mb-4 border">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-secondary text-xs fw-bold mb-1">OFFICIAL APCP RATING</div>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="fs-2 fw-bold text-primary"><span><?php echo number_format($baseScore, 1); ?></span><span class="fs-5 text-primary">%</span></div>
                                        <?php if($cappedE > 0): ?>
                                            <div>
                                                <span class="badge bg-warning text-dark fs-6 shadow-sm border border-warning rounded-pill px-3 py-2"><i class="bi bi-star-fill me-1"></i>+<?php echo $cappedE; ?> Bonus</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2 d-flex gap-2">
                                        <span class="badge bg-secondary py-1 px-2" style="font-size:12px;">Markah Asas: <?php echo number_format($baseScore, 1); ?> / 100</span>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <span class="score-pill bg-primary-subtle text-primary border">A: <?php echo number_format($scoreA, 1); ?></span>
                                    <span class="score-pill bg-info-subtle text-info border">B: <?php echo $cappedB; ?></span>
                                    <span class="score-pill bg-warning-subtle text-warning border">C: <?php echo $cappedC; ?></span>
                                    <span class="score-pill bg-success-subtle text-success border">D: <?php echo $cappedD; ?></span>
                                    <span class="score-pill bg-secondary-subtle text-dark border">Bonus: <?php echo $cappedE; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- VERIFICATION NOTES -->
                    <?php if (!empty($view_application['pa_remarks'])): ?>
                        <div class="card border-primary bg-primary-subtle mb-4">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2 border-primary-subtle">
                                    <h6 class="fw-bold text-primary mb-0"><i class="bi bi-chat-left-text me-2"></i>JKP Verification Notes</h6>
                                    <?php if(!empty($view_application['pa_verified_at'])): ?><small class="text-secondary">Processed: <?php echo date('M d, Y', strtotime($view_application['pa_verified_at'])); ?></small><?php endif; ?>
                                </div>
                                <span class="text-secondary d-block small fw-bold text-uppercase">Review Remarks:</span>
                                <p class="mb-0 text-dark fw-medium fs-6">"<?php echo nl2br(htmlspecialchars($view_application['pa_remarks'])); ?>"</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($view_application['eval_remarks'])): ?>
                        <div class="card border-success bg-success-subtle mb-4">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2 border-success-subtle">
                                    <h6 class="fw-bold text-success mb-0"><i class="bi bi-award me-2"></i>JIP Endorsement</h6>
                                    <?php if(!empty($view_application['eval_at'])): ?><small class="text-secondary">Endorsed: <?php echo date('M d, Y', strtotime($view_application['eval_at'])); ?></small><?php endif; ?>
                                </div>
                                <div class="row align-items-center">
                                    <div class="col-md-8 mb-2 mb-md-0">
                                        <span class="text-secondary d-block small fw-bold text-uppercase">Committee Decision:</span>
                                        <p class="mb-0 text-dark fw-medium fs-6">"<?php echo htmlspecialchars($view_application['eval_remarks']); ?>"</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-card-list text-primary me-2"></i>Detailed Form Submission & Evidence</h6>
                    <div class="list-group mb-3">
                        <?php echo $certsHTML ?: '<div class="p-3 text-muted text-center border rounded bg-light">No certificates found.</div>'; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- LIST VIEW -->
                <div class="card bg-white custom-card shadow-sm p-4">
                    <?php if(!empty($applications) && count($applications) > 0): ?>
                        <div class="table-responsive">
                            <table class="table align-middle border-0">
                                <thead class="table-light text-secondary text-uppercase" style="font-size: 11px;">
                                    <tr><th class="border-0">FORM NO.</th><th class="border-0">AWARD CATEGORY</th><th class="border-0">SUBMISSION DATE</th><th class="border-0">HPNM / CGPA</th><th class="border-0 text-end">STATUS</th><th class="border-0 text-end">ACTION</th></tr>
                                </thead>
                                <tbody style="font-size: 14px;">
                                    <?php foreach($applications as $app): ?>
                                        <tr class="border-bottom">
                                            <td class="fw-bold text-dark"><?php echo sprintf('APP-%04d', $app['application_id']); ?></td>
                                            <td class="fw-semibold text-dark"><i class="bi bi-award me-1 text-primary"></i><?php echo htmlspecialchars($app['category_name'] ?? 'N/A'); ?></td>
                                            <td class="text-secondary"><?php echo date('M d, Y', strtotime($app['application_date'])); ?></td>
                                            <td class="text-secondary fw-semibold"><?php $gpa_value = floatval($app['student_cgpa'] ?? $app['gpa'] ?? $app['extra_data'] ?? 0); echo $gpa_value > 0 ? number_format($gpa_value, 2) : '-'; ?></td>
                                            <td class="text-end">
                                                <?php if ($app['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-pill">Pending JKP</span>
                                                <?php elseif ($app['status'] === 'verified'): ?>
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 rounded-pill">Verified</span>
                                                <?php elseif ($app['status'] === 'evaluated'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Endorsed</span>
                                                <?php elseif ($app['status'] === 'nominated'): ?>
                                                    <span class="badge bg-warning text-dark border px-3 py-1 rounded-pill shadow-sm"><i class="bi bi-star-fill me-1"></i> FINAL WINNER</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <a href="student_history.php?view_app=<?php echo $app['application_id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-secondary"><i class="bi bi-inbox fs-1 mb-2 d-block text-muted"></i><p class="mb-0 fw-medium">No applications submitted yet.</p></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
    <footer class="text-center p-4 mt-5 text-muted">
        <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
    </footer>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>