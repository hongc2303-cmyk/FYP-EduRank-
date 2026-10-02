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

$message = '';
$error = '';

$application_id = filter_var($_GET['id'] ?? $_POST['application_id'] ?? 0, FILTER_VALIDATE_INT);
if (!$application_id) {
    die("Invalid Application ID.");
}

$appData = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.status, a.category_id,
                   u.full_name AS student_name, s.matric_no, s.programme,
                   c.category_name,
                   v.calculated_score, v.remarks AS pa_remarks, v.verification_date,
                   pa_u.full_name AS pa_name,
                   e.remarks AS committee_remarks
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            JOIN award_categories c ON a.category_id = c.category_id
            LEFT JOIN application_verifications v ON a.application_id = v.application_id
            LEFT JOIN users pa_u ON v.advisor_id = pa_u.user_id
            LEFT JOIN evaluations e ON a.application_id = e.application_id
            WHERE a.application_id = ?
        ");
        $stmt->execute([$application_id]);
        $appData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$appData) die("Application not found.");

        $stmtC = $pdo->prepare("SELECT * FROM certificates WHERE application_id = ? ORDER BY certificate_type ASC");
        $stmtC->execute([$application_id]);
        $appData['certificates'] = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        if ($appData['category_id'] == 2) {
            $stmtProj = $pdo->prepare("SELECT project_title, group_members FROM project_award_details WHERE application_id = ?");
            $stmtProj->execute([$application_id]);
            $appData['project_details'] = $stmtProj->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}

$is_read_only = ($appData['status'] === 'nominated');

function getCleanType($c) {
    $t = $c['certificate_type'] ?? $c['component_type'] ?? $c['type'] ?? '';
    return str_replace(['component', 'komponen', '_', '-', ' '], '', strtolower($t));
}
function getExtra($c) {
    if (!$c) return [];
    $raw = $c['extra_data'] ?? '';
    if (is_string($raw) && strpos(trim($raw), '{') === 0) return json_decode($raw, true) ?: [];
    return ['value' => $raw];
}

$certA = $certB_JPP = $certB_Kelab = $certE_Prof = $certE_Luar = $certE_Intl = null;
$certCs = []; $certDs = [];

foreach ($appData['certificates'] as $c) {
    $t = getCleanType($c);
    $ext = getExtra($c);
    $extType = strtolower($ext['type'] ?? '');
    
    if ($t === 'a' || strpos($t, 'akademik') !== false) {
        $certA = $c;
    } elseif ($t === 'b' || strpos($t, 'organisasi') !== false) {
        if (strpos($t, 'jpp') !== false || $extType === 'jpp' || strpos(strtoupper($ext['position'] ?? ''), 'JPP') === 0) {
            $certB_JPP = $c;
        } else {
            $certB_Kelab = $c;
        }
    } elseif ($t === 'c' || strpos($t, 'penganjuran') !== false) {
        $certCs[] = $c;
    } elseif ($t === 'd' || strpos($t, 'penyertaan') !== false) {
        $certDs[] = $c;
    } elseif ($t === 'e' || strpos($t, 'bonus') !== false) {
        if (strpos($t, 'profesional') !== false || $extType === 'bonus_profesional' || $extType === 'profesional') $certE_Prof = $c;
        elseif (strpos($t, 'luar') !== false || $extType === 'bonus_luar' || $extType === 'luar') $certE_Luar = $c;
        elseif (strpos($t, 'antarabangsa') !== false || $extType === 'bonus_antarabangsa' || $extType === 'antarabangsa') $certE_Intl = $c;
    }
}
$certC1 = $certCs[0] ?? null; $certC2 = $certCs[1] ?? null;
$certD1 = $certDs[0] ?? null; $certD2 = $certDs[1] ?? null; $certD3 = $certDs[2] ?? null;

$scoreA = 0; $scoreB = 0; $scoreC = 0; $scoreD = 0; $scoreE = 0;
$cappedB = 0; $cappedC = 0; $cappedD = 0; $cappedE = 0;
$baseScore = 0;

if ($appData['category_id'] != 2) {
    $cgpaVal = ($certA && isset($certA['extra_data'])) ? floatval($certA['extra_data']) : 0;
    $scoreA = min($cgpaVal * 10, 40);

    $bMap = ['JPP_YDP'=>10, 'JPP_NYDP'=>9, 'JPP_SU_BEND'=>8, 'JPP_EXCO'=>7, 'CLUB_PENGERUSI'=>6, 'CLUB_NAIB'=>5, 'CLUB_SU_BEND'=>4, 'CLUB_AJK'=>3, 'CLUB_AHLI'=>1];
    $eJPP = getExtra($certB_JPP); $eKelab = getExtra($certB_Kelab);
    $scoreB = ($bMap[$eJPP['position'] ?? ''] ?? 0) + ($bMap[$eKelab['position'] ?? ''] ?? 0);

    $rMap = ['PENGARAH'=>5, 'TIMBALAN'=>4, 'SU_BEND'=>3, 'AJK'=>2, 'AHLI'=>1];
    $lMap = ['ANTARABANGSA'=>5, 'KEBANGSAAN'=>5, 'NEGERI'=>4, 'POLITEKNIK'=>3, 'JABATAN'=>2];
    $eC1 = getExtra($certC1); $eC2 = getExtra($certC2);
    $scoreC = ($rMap[$eC1['role']??'']??0) + ($lMap[$eC1['level']??'']??0) + ($rMap[$eC2['role']??'']??0) + ($lMap[$eC2['level']??'']??0);

    $lMapD = ['ANTARABANGSA'=>10, 'KEBANGSAAN'=>10, 'NEGERI'=>9, 'POLITEKNIK'=>8, 'JABATAN'=>7];
    $eD1 = getExtra($certD1); $eD2 = getExtra($certD2); $eD3 = getExtra($certD3);
    $scoreD = ($lMapD[$eD1['level']??'']??0) + ($lMapD[$eD2['level']??'']??0) + ($lMapD[$eD3['level']??'']??0);

    $scoreE = ($certE_Prof ? 2 : 0) + ($certE_Luar ? 2 : 0) + ($certE_Intl ? 2 : 0);

    $cappedB = min($scoreB, 10); $cappedC = min($scoreC, 20); $cappedD = min($scoreD, 30); $cappedE = min($scoreE, 6);
    $baseScore = $scoreA + $cappedB + $cappedC + $cappedD;
}

// --- AJAX Handle: AI Evaluation Remarks Generation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_ai_generate'])) {
    ob_clean();
    header('Content-Type: application/json');
    $gemini_api_key = 'AQ.Ab8RN6I8H05iKZ1FNIqwpr1zLgKYnqVH4l80eypbizuWjnmUEA'; 
    
    // 🌟 核心修改：让 AI 变得极其简短、重点突出
    $prompt = "You are an evaluation committee member for a Malaysian Polytechnic student award (APCP). Please write a VERY SHORT, concise, and direct professional evaluation remark (MAXIMUM 1 to 2 sentences) for the following candidate.\n\n";
    $prompt .= "Candidate Name: " . $appData['student_name'] . "\n";
    $prompt .= "Award Category: " . $appData['category_name'] . "\n";
    if ($appData['category_id'] == 2) {
        if (!empty($appData['project_details'])) $prompt .= "Project Title: " . $appData['project_details']['project_title'] . "\n";
        $prompt .= "Note: This is a qualitative project evaluation. No numerical score is required.\n";
    } else {
        $prompt .= "Program Advisor (PA) Rating: " . number_format($appData['calculated_score'], 1) . "%\n";
    }
    $prompt .= "PA's Remarks: " . ($appData['pa_remarks'] ?: 'None provided') . "\n";
    $prompt .= "\nCRITICAL INSTRUCTIONS: Based on the provided data, write a brief, punchy endorsement. Keep it strictly to 1 or 2 short sentences. Focus ONLY on the core reason for approval. Do NOT use introductory phrases like 'Here is the remark'. Output ONLY the final remark paragraph in English.";

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . $gemini_api_key;
    $data = ["contents" => [["parts" => [["text" => $prompt]]]]];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $response = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    
    if ($err) { echo json_encode(['success' => false, 'error' => 'CURL Error: ' . $err]); exit; }
    
    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        echo json_encode(['success' => true, 'remark' => trim($result['candidates'][0]['content']['parts'][0]['text'])]);
    } else {
        echo json_encode(['success' => false, 'error' => "Google API Rejected."]);
    }
    exit; 
}

// --- Submit Evaluation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {
    $committee_remarks = trim($_POST['committee_remarks'] ?? '');
    $final_score = ($appData['category_id'] == 2) ? 0 : filter_var($_POST['final_score'] ?? 0, FILTER_VALIDATE_FLOAT); 

    if (isset($pdo)) {
        try {
            $pdo->beginTransaction();
            $stmtEval = $pdo->prepare("
                INSERT INTO evaluations (application_id, committee_id, total_score, remarks) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                committee_id = VALUES(committee_id), total_score = VALUES(total_score), 
                remarks = VALUES(remarks), evaluation_date = CURRENT_TIMESTAMP
            ");
            $stmtEval->execute([$application_id, $user_id, $final_score, $committee_remarks]);

            $stmtStatus = $pdo->prepare("UPDATE award_applications SET status = 'evaluated', is_read = 0 WHERE application_id = ? AND status = 'verified'");
            $stmtStatus->execute([$application_id]);

            $pdo->commit();
            $message = "Evaluation successfully saved! The candidate is now officially evaluated.";
            
            $appData['committee_remarks'] = $committee_remarks;
            $appData['status'] = 'evaluated';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Failed to save evaluation: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - Committee Evaluation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <style>.score-pill { font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 6px; }</style>
</head>
<body class="dashboard-body bg-light">

<div class="d-flex">
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
                    <span class="fw-semibold text-truncate" style="font-size: 0.9rem;"><?php echo htmlspecialchars($name); ?></span>
                    <span class="text-secondary text-truncate mt-1" style="font-size: 0.75rem;"><?php echo htmlspecialchars($role); ?></span>
                </div>
            </div>
            <form method="POST" action="logout.php">
                <button type="submit" class="btn text-danger p-0 border-0 text-sm fw-bold d-flex align-items-center gap-2 ms-1 bg-transparent">
                    <i class="bi bi-box-arrow-right fs-5"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <div class="main-content flex-grow-1 p-4 p-md-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="committee_dashboard.php" class="text-decoration-none text-secondary mb-2 d-inline-block"><i class="bi bi-arrow-left me-1"></i> Back to Dashboard</a>
                <h3 class="fw-bold text-dark mb-0">Application Evaluation</h3>
            </div>
            <?php if ($appData['status'] === 'nominated'): ?>
                <span class="badge bg-warning text-dark fs-6 px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-star-fill me-1"></i> Final Winner (Locked)</span>
            <?php elseif ($appData['status'] === 'evaluated'): ?>
                <span class="badge bg-success fs-6 px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-check2-all me-1"></i> Evaluated</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($message)): ?><div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i><?php echo $message; ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?></div><?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-primary mb-3 text-uppercase" style="letter-spacing: 1px; font-size: 13px;">Candidate Profile</h6>
                        <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-4">
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center text-primary fw-bold fs-3" style="width: 70px; height: 70px;">
                                <?php echo strtoupper(substr($appData['student_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($appData['student_name']); ?></h4>
                                <div class="text-secondary"><?php echo htmlspecialchars($appData['matric_no']); ?> | <?php echo htmlspecialchars($appData['programme']); ?></div>
                                <div class="badge bg-primary-subtle text-primary mt-2 border border-primary-subtle px-3 py-2"><i class="bi bi-award me-1"></i><?php echo htmlspecialchars($appData['category_name']); ?></div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-ui-checks text-primary me-2"></i>Borang Permohonan Pelajar</h5>
                            
                            <?php if ($appData['category_id'] == 2): ?>
                                <?php 
                                    $projTitle = $appData['project_details']['project_title'] ?? '';
                                    $projMembers = $appData['project_details']['group_members'] ?? '';
                                    $cProj = null; $cInd = null;
                                    foreach($appData['certificates'] as $c) {
                                        $t = getCleanType($c); $ex = getExtra($c);
                                        if (strpos($t, 'projekbukti') !== false || ($ex['type'] ?? '') === 'Bukti_Pencapaian_FYP') $cProj = $c;
                                        if (strpos($t, 'projekindustri') !== false || ($ex['type'] ?? '') === 'Sijil_Penghargaan_Industri') $cInd = $c;
                                    }
                                ?>
                                <div class="mb-4">
                                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-folder-check me-2"></i>Butiran Projek & Bukti (Kategori Projek)</h6>
                                    <div class="border border-primary-subtle rounded p-3 bg-light">
                                        <div class="mb-3">
                                            <label class="form-label text-dark fw-bold" style="font-size: 13px;">Tajuk Projek</label>
                                            <input type="text" class="form-control bg-white" value="<?php echo htmlspecialchars($projTitle); ?>" readonly>
                                        </div>
                                        <div class="mb-4">
                                            <label class="form-label text-dark fw-bold" style="font-size: 13px;">Ahli Kumpulan</label>
                                            <div class="p-3 border rounded bg-white" style="font-size:13px; line-height:1.6;"><?php echo nl2br(htmlspecialchars($projMembers ?: 'Tiada Rekod Ahli')); ?></div>
                                        </div>
                                        <div class="row g-3 border-top pt-3">
                                            <div class="col-md-6">
                                                <label class="form-label text-dark fw-bold" style="font-size: 13px;"><i class="bi bi-file-earmark-check text-success me-1"></i>Bukti Pencapaian FYP (Wajib)</label>
                                                <div><?php echo ($cProj && $cProj['file_name']) ? '<a href="uploads/'.urlencode($cProj['file_name']).'" target="_blank" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Dokumen Wajib</a>' : '<span class="text-danger fw-bold">Tiada Dokumen Dimuat Naik!</span>'; ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-dark fw-bold" style="font-size: 13px;"><i class="bi bi-award text-warning me-1"></i>Sijil Penghargaan Industri</label>
                                                <div><?php echo ($cInd && $cInd['file_name']) ? '<a href="uploads/'.urlencode($cInd['file_name']).'" target="_blank" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Sijil Industri</a>' : '<span class="text-muted">Tiada (Pilihan)</span>'; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="mb-4">
                                    <h6 class="fw-bold text-primary mb-2"><i class="bi bi-mortarboard me-2"></i>(a) Pencapaian Akademik / HPNM</h6>
                                    <div class="row border rounded p-3 bg-light mx-0">
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            <label class="form-label text-secondary fw-medium" style="font-size: 13px;">HPNM / CGPA</label>
                                            <input type="text" class="form-control bg-white fw-bold" value="<?php echo ($certA && isset($certA['extra_data'])) ? number_format((float)$certA['extra_data'], 2) : '-'; ?>" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary fw-medium" style="font-size: 13px;">Transkrip Keputusan</label>
                                            <div><?php echo ($certA && $certA['file_name']) ? '<a href="uploads/'.urlencode($certA['file_name']).'" target="_blank" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Dokumen</a>' : '<input type="text" class="form-control text-muted bg-white" value="Tiada Dokumen" readonly>'; ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h6 class="fw-bold text-primary mb-2"><i class="bi bi-people me-2"></i>(b) Kokurikulum 1 - Pengurusan Organisasi</h6>
                                    <div class="border rounded p-3 bg-light">
                                        <div class="mb-4">
                                            <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Jawatankuasa Perwakilan Pelajar (JPP)</div>
                                            <div class="border border-top-0 rounded-bottom p-3 bg-white">
                                                <div class="row g-3 align-items-end">
                                                    <div class="col-12"><label class="text-secondary small fw-medium mb-1">Nama Organisasi</label><?php $eJPP = getExtra($certB_JPP); ?><input type="text" class="form-control bg-white" value="<?php echo htmlspecialchars($eJPP['org_name'] ?? ''); ?>" placeholder="Tiada Rekod JPP" readonly></div>
                                                    <div class="col-md-7"><label class="text-secondary small fw-medium mb-1">Jawatan</label><input type="text" class="form-control bg-white" value="<?php echo htmlspecialchars($eJPP['position'] ?? ''); ?>" placeholder="Tiada Jawatan" readonly></div>
                                                    <div class="col-md-5"><?php echo ($certB_JPP && $certB_JPP['file_name']) ? '<a href="uploads/'.urlencode($certB_JPP['file_name']).'" target="_blank" class="btn btn-outline-primary w-100"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Surat Pelantikan</a>' : '<input type="text" class="form-control text-muted bg-white text-center" value="Tiada Dokumen" readonly>'; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Kelab / Persatuan & Lain-lain Organisasi</div>
                                            <div class="border border-top-0 rounded-bottom p-3 bg-white">
                                                <div class="row g-3 align-items-end">
                                                    <div class="col-12"><label class="text-secondary small fw-medium mb-1">Nama Kelab / Persatuan</label><?php $eKelab = getExtra($certB_Kelab); ?><input type="text" class="form-control bg-white" value="<?php echo htmlspecialchars($eKelab['org_name'] ?? ''); ?>" placeholder="Tiada Rekod Kelab" readonly></div>
                                                    <div class="col-md-7"><label class="text-secondary small fw-medium mb-1">Jawatan</label><input type="text" class="form-control bg-white" value="<?php echo htmlspecialchars($eKelab['position'] ?? ''); ?>" placeholder="Tiada Jawatan" readonly></div>
                                                    <div class="col-md-5"><?php echo ($certB_Kelab && $certB_Kelab['file_name']) ? '<a href="uploads/'.urlencode($certB_Kelab['file_name']).'" target="_blank" class="btn btn-outline-primary w-100"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Surat Pelantikan</a>' : '<input type="text" class="form-control text-muted bg-white text-center" value="Tiada Dokumen" readonly>'; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h6 class="fw-bold text-primary mb-2"><i class="bi bi-calendar-event me-2"></i>(c) Kokurikulum 2 - Penganjuran Program</h6>
                                    <div class="border rounded p-3 bg-light">
                                        <?php for ($i=1; $i<=2; $i++): $cObj = ($i==1) ? $certC1 : $certC2; $extC = getExtra($cObj); ?>
                                        <div class="<?php echo $i==1 ? 'mb-4':''; ?>">
                                            <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Penganjuran Program <?php echo $i; ?></div>
                                            <div class="border border-top-0 rounded-bottom p-3 bg-white">
                                                <div class="row g-3 align-items-end">
                                                    <div class="col-12"><label class="text-secondary small fw-medium mb-1">Nama Program</label><input type="text" class="form-control bg-white" value="<?php echo htmlspecialchars($extC['name'] ?? ''); ?>" placeholder="Tiada Program" readonly></div>
                                                    <div class="col-md-6"><label class="text-secondary small fw-medium mb-1">Jawatan Dipegang</label><input type="text" class="form-control bg-white" value="<?php echo htmlspecialchars($extC['role'] ?? ''); ?>" placeholder="Tiada" readonly></div>
                                                    <div class="col-md-6"><label class="text-secondary small fw-medium mb-1">Peringkat Program</label><input type="text" class="form-control bg-white" value="<?php echo htmlspecialchars($extC['level'] ?? ''); ?>" placeholder="Tiada" readonly></div>
                                                    <div class="col-12 text-end"><?php echo ($cObj && $cObj['file_name']) ? '<a href="uploads/'.urlencode($cObj['file_name']).'" target="_blank" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Sijil</a>' : '<span class="text-muted small">Tiada dokumen dimuat naik.</span>'; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h6 class="fw-bold text-primary mb-2"><i class="bi bi-trophy me-2"></i>(d) Kokurikulum 3 - Penyertaan Program</h6>
                                    <div class="border rounded p-3 bg-light">
                                        <?php for ($i=1; $i<=3; $i++): $dObj = ($i==1) ? $certD1 : (($i==2) ? $certD2 : $certD3); $extD = getExtra($dObj); ?>
                                        <div class="mb-3">
                                            <div class="bg-secondary text-white text-center py-1 rounded-top fw-bold" style="font-size: 13px;">Penyertaan Acara / Pertandingan <?php echo $i; ?></div>
                                            <div class="border border-top-0 rounded-bottom p-3 bg-white">
                                                <div class="row g-3 align-items-end">
                                                    <div class="col-12"><label class="text-secondary small fw-medium mb-1">Nama Acara</label><input type="text" class="form-control bg-white" value="<?php echo htmlspecialchars($extD['name'] ?? ''); ?>" placeholder="Tiada Penyertaan" readonly></div>
                                                    <div class="col-md-7"><label class="text-secondary small fw-medium mb-1">Peringkat Acara</label><input type="text" class="form-control bg-white" value="<?php echo htmlspecialchars($extD['level'] ?? ''); ?>" placeholder="Tiada" readonly></div>
                                                    <div class="col-md-5"><?php echo ($dObj && $dObj['file_name']) ? '<a href="uploads/'.urlencode($dObj['file_name']).'" target="_blank" class="btn btn-outline-primary w-100"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Sijil</a>' : '<input type="text" class="form-control text-muted bg-white text-center" value="Tiada Sijil" readonly>'; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h6 class="fw-bold text-primary mb-2"><i class="bi bi-star me-2"></i>(e) Lain-lain / Bonus</h6>
                                    <div class="border rounded p-3 bg-light">
                                        <div class="row g-3 align-items-center mb-3">
                                            <div class="col-md-5"><div class="form-check"><input class="form-check-input" type="checkbox" disabled <?php echo $certE_Prof ? 'checked' : ''; ?>><label class="form-check-label text-dark fw-medium" style="font-size: 14px;">Sijil Profesional (+2 Markah)</label></div></div>
                                            <div class="col-md-7"><?php echo ($certE_Prof && $certE_Prof['file_name']) ? '<a href="uploads/'.urlencode($certE_Prof['file_name']).'" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Dokumen</a>' : '<span class="text-muted small">- Tidak Diisi -</span>'; ?></div>
                                        </div>
                                        <div class="row g-3 align-items-center mb-3">
                                            <div class="col-md-5"><div class="form-check"><input class="form-check-input" type="checkbox" disabled <?php echo $certE_Luar ? 'checked' : ''; ?>><label class="form-check-label text-dark fw-medium" style="font-size: 14px;">Pengiktirafan Luar (+2 Markah)</label></div></div>
                                            <div class="col-md-7"><?php echo ($certE_Luar && $certE_Luar['file_name']) ? '<a href="uploads/'.urlencode($certE_Luar['file_name']).'" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Dokumen</a>' : '<span class="text-muted small">- Tidak Diisi -</span>'; ?></div>
                                        </div>
                                        <div class="row g-3 align-items-center">
                                            <div class="col-md-5"><div class="form-check"><input class="form-check-input" type="checkbox" disabled <?php echo $certE_Intl ? 'checked' : ''; ?>><label class="form-check-label text-dark fw-medium" style="font-size: 14px;">Penglibatan Antarabangsa (+2 Markah)</label></div></div>
                                            <div class="col-md-7"><?php echo ($certE_Intl && $certE_Intl['file_name']) ? '<a href="uploads/'.urlencode($certE_Intl['file_name']).'" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> Lihat Dokumen</a>' : '<span class="text-muted small">- Tidak Diisi -</span>'; ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white" style="border-top: 4px solid #0dcaf0 !important;">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-info mb-0 text-uppercase" style="letter-spacing: 1px; font-size: 13px;">PA Verification Data</h6>
                            <span class="text-muted small"><i class="bi bi-calendar-check me-1"></i><?php echo date('d M Y', strtotime($appData['verification_date'])); ?></span>
                        </div>
                        
                        <?php if ($appData['category_id'] != 2): ?>
                            <div id="percentageDisplayBox" class="p-3 bg-light rounded-3 mb-4 border">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-secondary text-xs fw-bold mb-1">OFFICIAL APCP RATING</div>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="fs-2 fw-bold text-primary"><?php echo number_format($baseScore, 1); ?><span class="fs-5 text-primary">%</span></div>
                                            <?php if ($cappedE > 0): ?>
                                                <div><span class="badge bg-warning text-dark fs-6 shadow-sm border border-warning rounded-pill px-3 py-2"><i class="bi bi-star-fill me-1"></i>+<?php echo $cappedE; ?> Bonus</span></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-2 d-flex gap-2">
                                            <span class="badge bg-secondary py-1 px-2" style="font-size:12px;">Markah Asas: <?php echo number_format($baseScore, 1); ?> / 100</span>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap justify-content-end" style="max-width: 200px;">
                                        <span class="score-pill bg-primary-subtle text-primary border px-2 py-1 rounded">A: <?php echo number_format($scoreA, 1); ?></span>
                                        <span class="score-pill bg-info-subtle text-info border px-2 py-1 rounded">B: <?php echo $cappedB; ?></span>
                                        <span class="score-pill bg-warning-subtle text-warning border px-2 py-1 rounded">C: <?php echo $cappedC; ?></span>
                                        <span class="score-pill bg-success-subtle text-success border px-2 py-1 rounded">D: <?php echo $cappedD; ?></span>
                                        <span class="score-pill bg-secondary-subtle text-dark border px-2 py-1 rounded">Bonus: <?php echo $cappedE; ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center bg-light rounded-3 py-2 mb-3 border border-light-subtle text-secondary fw-bold" style="font-size: 12px;"><i class="bi bi-info-circle me-1"></i> Qualitative Review (No Score Calculation)</div>
                        <?php endif; ?>

                        <div class="mb-0">
                            <span class="text-muted small d-block mb-1">Academic Advisor (PA) Name:</span>
                            <div class="fw-bold text-dark mb-3"><?php echo htmlspecialchars($appData['pa_name'] ?? 'Unknown'); ?></div>
                            <span class="text-muted small d-block mb-1">PA's Assessment Remarks:</span>
                            <div class="p-3 bg-light rounded text-dark border" style="font-size: 14px;"><?php echo nl2br(htmlspecialchars($appData['pa_remarks'] ?? 'No remarks provided.')); ?></div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 bg-white" style="border-top: 4px solid #2563eb !important;">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-primary mb-3 text-uppercase" style="letter-spacing: 1px; font-size: 13px;"><i class="bi bi-pencil-square me-2"></i>Committee Evaluation</h6>
                        <form method="POST" action="">
                            <input type="hidden" name="final_score" value="<?php echo $appData['calculated_score']; ?>">
                            <input type="hidden" name="application_id" id="hidden_app_id" value="<?php echo $application_id; ?>">
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label text-dark fw-bold mb-0">Your Official Remarks <span class="text-danger">*</span></label>
                                    <?php if (!$is_read_only): ?>
                                        <button type="button" id="btnAiMagic" class="btn btn-sm text-white fw-bold shadow-sm" style="background: linear-gradient(45deg, #8b5cf6, #d946ef); border: none;" onclick="generateAIRemark()"><i class="bi bi-stars me-1"></i> AI Generate</button>
                                    <?php endif; ?>
                                </div>
                                <textarea name="committee_remarks" id="committee_remarks" class="form-control border-primary" rows="5" placeholder="Enter your detailed justification or insights here..." required <?php echo $is_read_only ? 'disabled' : ''; ?>><?php echo htmlspecialchars($appData['committee_remarks'] ?? ''); ?></textarea>
                            </div>
                            <?php if (!$is_read_only): ?>
                                <button type="submit" name="submit_evaluation" class="btn btn-primary w-100 fw-bold py-3 rounded-3 shadow-sm"><i class="bi bi-save2-fill me-2"></i> Save & Confirm Evaluation</button>
                            <?php else: ?>
                                <div class="alert alert-warning text-center border-0 mb-0 py-3"><i class="bi bi-lock-fill me-2"></i> Application is locked (Final Winner).</div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="text-center p-4 mt-5 text-muted"><p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function generateAIRemark() {
    const btn = document.getElementById('btnAiMagic');
    const textarea = document.getElementById('committee_remarks');
    const appId = document.getElementById('hidden_app_id').value;

    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Generating...';
    btn.disabled = true; textarea.classList.add('opacity-50');

    const formData = new FormData();
    formData.append('ajax_ai_generate', '1'); formData.append('application_id', appId);

    fetch('evaluate.php?id=' + appId, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) { textarea.value = data.remark; } 
        else { alert('AI Generation Failed: \n\n' + data.error); }
    })
    .catch(error => { alert('A network error occurred while communicating with Gemini AI.'); })
    .finally(() => {
        btn.innerHTML = '<i class="bi bi-stars me-1"></i> AI Generate';
        btn.disabled = false; textarea.classList.remove('opacity-50');
        textarea.style.backgroundColor = '#fdf4ff';
        setTimeout(() => { textarea.style.backgroundColor = ''; }, 1000);
    });
}
</script>
</body>
</html>