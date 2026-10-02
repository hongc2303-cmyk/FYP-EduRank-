<?php
// 1. Include database connection configuration
require_once 'config.php';

// 2. Check session and enforce Evaluation Committee access
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id =$_SESSION['user_id'];
$name =$_SESSION['user_name'] ?? 'Committee Member';
$role =$_SESSION['user_role'] ?? 'Evaluation Committee';

// --- Fetch Current Session & Calculate Pending Evaluations ---
$current_session = '';$pending_evaluations = 0;
if (isset($pdo)) {
    $stmtSes =$pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'current_session'");
    $current_session =$stmtSes->fetchColumn() ?: 'Default Session';
    
    $stmtCount =$pdo->prepare("SELECT COUNT(*) FROM award_applications WHERE status = 'verified' AND session_name = ?");
    $stmtCount->execute([$current_session]);
    $pending_evaluations =$stmtCount->fetchColumn();
}
// -------------------------------------------------------------

// --- Handle AJAX Request for AI Core Advantage Summary ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_ai_discovery'])) {
    ob_clean();
    header('Content-Type: application/json');
    $gemini_api_key = 'AQ.Ab8RN6I8H05iKZ1FNIqwpr1zLgKYnqVH4l80eypbizuWjnmUEA'; 
    $appId =$_POST['application_id'];

    // Fetch Candidate Details
    $stmt =$pdo->prepare("
        SELECT u.full_name, c.category_name, v.calculated_score, v.remarks AS pa_remarks
        FROM award_applications a
        JOIN users u ON a.student_id = (SELECT student_id FROM students WHERE student_id = a.student_id)
        JOIN award_categories c ON a.category_id = c.category_id
        LEFT JOIN application_verifications v ON a.application_id = v.application_id
        WHERE a.application_id = ?
    ");
    $stmt->execute([$appId]);
    $candInfo =$stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch Certificates & Extra Data
    $stmtCerts =$pdo->prepare("SELECT certificate_type, extra_data FROM certificates WHERE application_id = ?");
    $stmtCerts->execute([$appId]);
    $certsInfo =$stmtCerts->fetchAll(PDO::FETCH_ASSOC);

    // AI Prompt Configuration (Strict constraint to avoid name hallucinations)
    $prompt = "You are a talent scout for a Malaysian Polytechnic award. Please analyze this candidate's data and write a VERY SHORT, punchy summary (MAXIMUM 30 WORDS) highlighting their core advantages. Use a professional, encouraging tone in English.\n\n";
    $prompt .= "Candidate's EXACT Name: " . $candInfo['full_name'] . "\nCategory: " . $candInfo['category_name'] . "\nScore: " . number_format($candInfo['calculated_score'], 1) . "%\n";
    $prompt .= "Certificates/Proofs:\n";
    foreach ($certsInfo as $c) {$clean_extra = str_replace(["\r", "\n", "{", "}", '"'], " ", $c['extra_data']);$prompt .= "- Type " . strtoupper($c['certificate_type']) . ": " . $clean_extra . "\n"; 
    }
    $prompt .= "\nCRITICAL INSTRUCTIONS: Output strictly ONLY ONE short summary paragraph (under 30 words). You MUST use the Candidate's EXACT Name provided above as the subject. DO NOT invent or substitute names. Do not use bullet points.";

    // Using gemini-pro for stable text generation
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $gemini_api_key;
    $data = ["contents" => [["parts" => [["text" => $prompt]]]]];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) { 
        echo json_encode(['success' => false, 'error' => 'CURL Error: ' . $err]); 
        exit; 
    }
    
    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        // Clean up text format
        $clean_text = str_replace(["\r", "\n", "*"], "", trim($result['candidates'][0]['content']['parts'][0]['text']));
        echo json_encode(['success' => true, 'summary' => $clean_text]);
    } else {
        echo json_encode(['success' => false, 'error' => "AI Response Error or High Demand."]);
    }
    exit; 
}

// --- Fetch All Evaluated/Verified Candidates for the Grid ---
$applications = [];$unique_classes = [];
if (isset($pdo)) {
    $stmt =$pdo->prepare("
        SELECT a.application_id, a.status, a.category_id,
               u.full_name AS student_name, s.matric_no, s.programme,
               c.category_name, v.calculated_score
        FROM award_applications a
        JOIN students s ON a.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        JOIN award_categories c ON a.category_id = c.category_id
        LEFT JOIN application_verifications v ON a.application_id = v.application_id
        WHERE a.status IN ('verified', 'evaluated', 'nominated') AND a.session_name = ?
        ORDER BY s.programme ASC, v.calculated_score DESC
    ");
    $stmt->execute([$current_session]);
    $applications =$stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($applications as &$app) {
        // Collect unique classes for the top filter buttons
        if (!in_array($app['programme'],$unique_classes)) {
            $unique_classes[] =$app['programme'];
        }
        
        $stmtC =$pdo->prepare("SELECT * FROM certificates WHERE application_id = ?");
        $stmtC->execute([$app['application_id']]);
        $app['certificates'] =$stmtC->fetchAll(PDO::FETCH_ASSOC);

        $app['calculated_bonus'] = 0; // Default Bonus

        // Fetch Project details if Category is 2 (Project Based)
        if ($app['category_id'] == 2) {
            $stmtP =$pdo->prepare("SELECT project_title, group_members FROM project_award_details WHERE application_id = ?");
            $stmtP->execute([$app['application_id']]);
            $app['project_details'] =$stmtP->fetch(PDO::FETCH_ASSOC);
        } else {
            // 🌟 Strict Bonus Calculation: Only evaluate if the category is explicitly 'E' / 'Bonus'
            $bScore = 0;
            $hProf = false; $hLuar = false; $hIntl = false;
            foreach ($app['certificates'] as $c) {$t = strtolower(str_replace([' ', '_', '-'], '', $c['certificate_type']));$exStr = $c['extra_data'];$ex = json_decode($exStr, true);$exType = isset($ex['type']) ? strtolower(str_replace([' ', '_', '-'], '', $ex['type'])) : '';
                
                // Check if this certificate actually belongs to Section E
                $isBonus = ($t === 'e' || strpos($t, 'bonus') !== false || in_array($t, ['e1','e2','e3']) || strpos($exType, 'bonus') !== false);
                
                // Fallback: if they just named it "profesional" without the "E" prefix
                if (!$isBonus && (strpos($t, 'profesional') !== false || $t === 'luar' ||$t === 'antarabangsa' || strpos($exType, 'profesional') !== false)) {$isBonus = true;
                }
                
                if ($isBonus) {
                    if (strpos($t, 'profesional') !== false || $t === 'e1' || strpos($exType, 'profesional') !== false || $exType === 'e1')$hProf = true;
                    if (strpos($t, 'luar') !== false || $t === 'e2' || strpos($exType, 'luar') !== false || $exType === 'e2')$hLuar = true;
                    if (strpos($t, 'antarabangsa') !== false || $t === 'e3' || strpos($exType, 'antarabangsa') !== false || $exType === 'e3')$hIntl = true;
                }
            }
            if ($hProf)$bScore += 2;
            if ($hLuar)$bScore += 2;
            if ($hIntl)$bScore += 2;
            $app['calculated_bonus'] =$bScore;
        }
    }
    unset($app);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - Candidate Discovery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .filter-btn { border-radius: 20px; padding: 6px 20px; font-weight: 600; border: 2px solid transparent; color: #64748b; background: #f1f5f9; transition: all 0.2s; }
        .filter-btn.active, .filter-btn:hover { background: #2563eb; color: #fff; box-shadow: 0 4px 10px rgba(37,99,235,0.2); }
        .cand-card { transition: transform 0.2s, box-shadow 0.2s; border: none; border-radius: 15px; cursor: pointer; border-top: 4px solid transparent; }
        .cand-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.08) !important; border-top-color: #2563eb; }
        .avatar-circle { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; margin: 0 auto 15px; border: 2px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .modal-xl { max-width: 1100px; }
        .ai-summary-box { background: linear-gradient(135deg, #fdf4ff, #f3e8ff); border: 1px solid #e9d5ff; border-radius: 12px; padding: 20px; position: relative; min-height: 120px; }
        .ai-sparkle { position: absolute; top: -12px; right: -12px; font-size: 24px; color: #d946ef; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.2); } 100% { transform: scale(1); } }
        /* Score Breakdown Pills Styling */
        .score-pill { padding: 4px 10px; border-radius: 6px; letter-spacing: 0.5px; font-size: 0.75rem; font-weight: 700; border: 1px solid transparent; }
    </style>
</head>
<body class="dashboard-body bg-light">

<div class="d-flex">
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
                <?php if (isset($pending_evaluations) &&$pending_evaluations > 0): ?>
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

    <!-- Main Content Area -->
    <div class="main-content flex-grow-1 p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1"><i class="bi bi-compass text-primary me-2"></i>Candidate Discovery</h3>
                <p class="text-secondary small mb-0">Browse through candidates by class. Click on any card for a Quick View & AI Summary.</p>
            </div>
            <span class="badge bg-primary px-3 py-2 fs-6 shadow-sm"><i class="bi bi-calendar-check me-2"></i><?php echo htmlspecialchars($current_session); ?></span>
        </div>

        <!-- Class Filter Buttons -->
        <div class="d-flex flex-wrap gap-2 mb-4 pb-3 border-bottom">
            <button class="filter-btn active" onclick="filterClass('all')">Keseluruhan (All)</button>
            <?php foreach($unique_classes as $cls): if(!empty($cls)): ?>
                <button class="filter-btn" onclick="filterClass('<?php echo htmlspecialchars($cls); ?>')"><?php echo htmlspecialchars($cls); ?></button>
            <?php endif; endforeach; ?>
        </div>

        <!-- Candidates Grid -->
        <div class="row row-cols-1 row-cols-md-3 row-cols-xl-4 g-4" id="candidateGrid">
            <?php if (empty($applications)): ?>
                <div class="col-12 text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-3"></i> No candidates found.
                </div>
            <?php else: ?>
                <?php foreach ($applications as$app): ?>
                    <div class="col candidate-col" data-class="<?php echo htmlspecialchars($app['programme']); ?>">
                        <div class="card cand-card shadow-sm h-100 p-4 text-center bg-white" onclick='openQuickView(<?php echo json_encode($app, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'>
                            <?php if ($app['status'] === 'nominated'): ?>
                                <span class="position-absolute top-0 start-50 translate-middle badge rounded-pill bg-warning text-dark shadow-sm border border-white"><i class="bi bi-star-fill me-1"></i> Winner</span>
                            <?php endif; ?>
                            <div class="avatar-circle"><?php echo strtoupper(substr($app['student_name'], 0, 1)); ?></div>
                            <h5 class="fw-bold text-dark mb-1" style="font-size: 1.1rem;"><?php echo htmlspecialchars($app['student_name']); ?></h5>
                            
                            <div class="text-secondary small fw-medium mb-3"><?php echo htmlspecialchars($app['matric_no']); ?> (<?php echo htmlspecialchars($app['programme']); ?>)</div>
                            
                            <div class="mt-auto">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle d-block text-wrap lh-base px-2 py-1 mb-2" style="font-size: 0.75rem;"><?php echo htmlspecialchars($app['category_name']); ?></span>
                                <div class="d-flex justify-content-center align-items-center gap-2">
                                    <div class="fw-bold text-dark">Score: <?php echo number_format($app['calculated_score'], 1); ?>%</div>
                                    <?php if ($app['calculated_bonus'] > 0): ?>
                                        <span class="badge bg-warning text-dark border border-warning shadow-sm" style="font-size: 0.7rem;" title="Bonus Points Included">
                                            <i class="bi bi-star-fill"></i> +<?php echo $app['calculated_bonus']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick View Modal -->
<div class="modal fade" id="quickViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0 bg-light rounded-top-4 p-4">
                <div>
                    <h4 class="fw-bold text-dark mb-1" id="modalStudentName">Student Name</h4>
                    <div class="text-secondary small fw-medium" id="modalStudentSub">Matric No | Programme | Award Category</div>
                </div>
                <button type="button" class="btn-close fs-5" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4 bg-light">
                <div class="row g-4">
                    <!-- Left Column: Form Details -->
                    <div class="col-lg-7">
                        <div class="bg-white p-4 rounded-4 shadow-sm h-100 border">
                            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-ui-checks text-primary me-2"></i>Borang Permohonan Pelajar</h5>
                            <div id="modalFormContainer"></div>
                        </div>
                    </div>
                    
                    <!-- Right Column: AI Summary & Score -->
                    <div class="col-lg-5 d-flex flex-column gap-4">
                        
                        <!-- AI Core Advantage Panel -->
                        <div class="bg-white p-4 rounded-4 shadow-sm border border-purple-subtle" style="border-top: 4px solid #d946ef !important;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0" style="color: #a21caf;"><i class="bi bi-magic me-2"></i>AI Core Advantage</h6>
                                <button class="btn btn-sm btn-outline-dark fw-bold rounded-pill px-3" id="btnTriggerAi" onclick="fetchAiSummary()">Extract Now</button>
                            </div>
                            
                            <div class="ai-summary-box d-flex align-items-center justify-content-center text-center">
                                <i class="bi bi-stars ai-sparkle"></i>
                                <div id="aiSummaryText" class="text-muted small">
                                    Click "Extract Now" to let AI read the forms and generate a quick 30-word strength summary.
                                </div>
                            </div>
                        </div>

                        <!-- Score Display Panel -->
                        <div class="bg-white p-4 rounded-4 shadow-sm border text-center">
                            <div class="text-secondary text-xs fw-bold mb-1">OFFICIAL APCP SCORE</div>
                            <div class="d-flex justify-content-center align-items-center gap-2 mb-2">
                                <div class="display-4 fw-bold text-primary" id="modalScoreDisplay" style="line-height: 1;">0.0%</div>
                                <div id="modalBonusDisplay" style="display:none;">
                                    <span class="badge bg-warning text-dark fs-6 shadow-sm border border-warning rounded-pill px-3 py-2"><i class="bi bi-star-fill me-1"></i>+<span id="modalBonusVal">0</span> Bonus</span>
                                </div>
                            </div>
                            
                            <!-- Score Breakdown Pills Container -->
                            <div id="modalScoreBreakdown" class="mb-4">
                                <!-- Dynamically injected via JavaScript -->
                            </div>
                            
                            <a href="#" id="modalEvaluateBtn" class="btn btn-primary fw-bold w-100 rounded-pill"><i class="bi bi-pencil-square me-2"></i>Go to Full Evaluation</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer section -->
<footer class="text-center p-4 mt-5 text-muted">
    <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Filter candidates by class programme
function filterClass(className) {
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    const cols = document.querySelectorAll('.candidate-col');
    cols.forEach(col => {
        if (className === 'all' || col.getAttribute('data-class') === className) {
            col.style.display = 'block';
        } else {
            col.style.display = 'none';
        }
    });
}

let currentAppId = null;
const modal = new bootstrap.Modal(document.getElementById('quickViewModal'));

// Helper to clean up certificate types for accurate matching
const getCleanType = (c) => {
    let t = c.certificate_type || c.component_type || c.type || '';
    return t.toLowerCase().replace(/component/g, '').replace(/komponen/g, '').replace(/[_\-\s]/g, '');
};

// Helper to safely parse extra JSON data
const getExtra = (c) => {
    if (!c) return {};
    try { return typeof c.extra_data === 'string' && c.extra_data.trim().startsWith('{') ? JSON.parse(c.extra_data) : c.extra_data; } catch(e){ return {}; }
};

// Helper to render the direct PDF button 
const getFileBtn = (cert) => {
    if (cert && cert.file_name) {
        return `<a href="uploads/${encodeURIComponent(cert.file_name)}" target="_blank" class="btn btn-primary d-flex align-items-center justify-content-center" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; border-radius: 0 4px 4px 0; min-width: 65px;"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>`;
    }
    return `<button class="btn btn-secondary disabled d-flex align-items-center justify-content-center" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; border-radius: 0 4px 4px 0; min-width: 65px;">Tiada</button>`;
};

// Main function to populate the Quick View Modal
function openQuickView(appData) {
    currentAppId = appData.application_id;

    // Reset UI states
    document.getElementById('modalBonusDisplay').style.display = 'none';
    document.getElementById('modalScoreBreakdown').innerHTML = '';

    // Set core texts
    document.getElementById('modalStudentName').textContent = appData.student_name;
    document.getElementById('modalStudentSub').textContent = `${appData.matric_no} | ${appData.programme} | ${appData.category_name}`;
    document.getElementById('modalScoreDisplay').textContent = `${Number(appData.calculated_score).toFixed(1)}%`;
    document.getElementById('modalEvaluateBtn').href = `evaluate.php?id=${appData.application_id}`;

    // Reset AI Box
    document.getElementById('aiSummaryText').innerHTML = 'Click "Extract Now" to let AI read the forms and generate a quick 30-word strength summary.';
    document.getElementById('btnTriggerAi').disabled = false;
    document.getElementById('btnTriggerAi').innerHTML = 'Extract Now';

    let certsHTML = '';
    
    // Formatting logic based on Award Category
    if (appData.category_id == 2) {
        // Project Based Awards
        let projTitle = (appData.project_details && appData.project_details.project_title) ? appData.project_details.project_title : '';
        let projMembers = (appData.project_details && appData.project_details.group_members) ? appData.project_details.group_members : '';
        let formattedMembers = projMembers ? projMembers.replace(/\n/g, '<br>') : '<span class="text-muted">Tiada Rekod Ahli</span>';

        let cProj = null, cInd = null;
        appData.certificates.forEach(c => {
            let t = getCleanType(c); let ex = getExtra(c);
            if (t.includes('projekbukti') || ex.type === 'Bukti_Pencapaian_FYP') cProj = c;
            if (t.includes('projekindustri') || ex.type === 'Sijil_Penghargaan_Industri') cInd = c;
        });
        
        // Show Qualitative Pill for Project Awards
        document.getElementById('modalScoreBreakdown').innerHTML = `<span class="badge bg-secondary py-1 px-3">Project Based Evaluation</span>`;

        certsHTML = `
            <div class="mb-3">
                <label class="form-label text-dark fw-bold" style="font-size: 13px;">Tajuk Projek</label>
                <input type="text" class="form-control bg-light" value="${projTitle}" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label text-dark fw-bold" style="font-size: 13px;">Ahli Kumpulan</label>
                <div class="p-3 border rounded bg-light" style="font-size:13px;">${formattedMembers}</div>
            </div>
            <div class="row g-2 mt-3">
                <div class="col-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text fw-bold">Bukti FYP</span>
                        <input type="text" class="form-control bg-white" readonly placeholder="Wajib">
                        ${getFileBtn(cProj)}
                    </div>
                </div>
                <div class="col-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text fw-bold">Sijil Industri</span>
                        <input type="text" class="form-control bg-white" readonly placeholder="Pilihan">
                        ${getFileBtn(cInd)}
                    </div>
                </div>
            </div>
        `;
    } else {
        // Standard APCP Awards Categories
        let certA = appData.certificates.find(c => { let t = getCleanType(c); return t === 'a' || t.includes('akademik'); });
        let certBs = appData.certificates.filter(c => { let t = getCleanType(c); return t === 'b' || t.includes('organisasi'); });
        let certCs = appData.certificates.filter(c => { let t = getCleanType(c); return t === 'c' || t.includes('penganjuran'); });
        let certDs = appData.certificates.filter(c => { let t = getCleanType(c); return t === 'd' || t.includes('penyertaan'); });
        
        // 🌟 Strict JS Bonus Calculation matching PHP
        let certEs = appData.certificates.filter(c => { 
            let t = getCleanType(c); 
            let exType = (getExtra(c).type || '').toLowerCase().replace(/[_\-\s]/g, '');
            let isE = t === 'e' || t.includes('bonus') || ['e1','e2','e3'].includes(t) || exType.includes('bonus'); 
            if (!isE && (t.includes('profesional') || t === 'luar' || t === 'antarabangsa' || exType.includes('profesional'))) {
                isE = true;
            }
            return isE;
        });
        
        let certE_Prof = certEs.find(c => { let t = getCleanType(c); let exType = (getExtra(c).type || '').toLowerCase().replace(/[_\-\s]/g, ''); return t.includes('profesional') || t.includes('e1') || exType.includes('profesional') || exType === 'e1'; });
        let certE_Luar = certEs.find(c => { let t = getCleanType(c); let exType = (getExtra(c).type || '').toLowerCase().replace(/[_\-\s]/g, ''); return t.includes('luar') || t.includes('e2') || exType.includes('luar') || exType === 'e2'; });
        let certE_Intl = certEs.find(c => { let t = getCleanType(c); let exType = (getExtra(c).type || '').toLowerCase().replace(/[_\-\s]/g, ''); return t.includes('antarabangsa') || t.includes('e3') || exType.includes('antarabangsa') || exType === 'e3'; });

        let certB_JPP = certBs.find(c => getCleanType(c).includes('jpp') || getExtra(c).type === 'JPP' || (getExtra(c).position || '').includes('JPP')) || certBs[0];
        let certB_Kelab = certBs.find(c => c !== certB_JPP) || certBs[1];

        let extJPP = getExtra(certB_JPP);
        let extKelab = getExtra(certB_Kelab);
        let extC1 = getExtra(certCs[0]||null), extC2 = getExtra(certCs[1]||null);
        let extD1 = getExtra(certDs[0]||null), extD2 = getExtra(certDs[1]||null), extD3 = getExtra(certDs[2]||null);
        let cgpaVal = certA && certA.extra_data ? parseFloat(certA.extra_data) : 0;

        // Auto-calculate A, B, C, D to render the breakdown pills
        let scoreA = Math.min((cgpaVal * 10) || 0, 40);
        
        let bMap = {'JPP_YDP':10, 'JPP_NYDP':9, 'JPP_SU_BEND':8, 'JPP_EXCO':7, 'CLUB_PENGERUSI':6, 'CLUB_NAIB':5, 'CLUB_SU_BEND':4, 'CLUB_AJK':3, 'CLUB_AHLI':1};
        let scoreB = Math.min((bMap[extJPP.position] || 0) + (bMap[extKelab.position] || 0), 10);
        
        let rMap = {'PENGARAH':5, 'TIMBALAN':4, 'SU_BEND':3, 'AJK':2, 'AHLI':1};
        let lMapC = {'ANTARABANGSA':5, 'KEBANGSAAN':5, 'NEGERI':4, 'POLITEKNIK':3, 'JABATAN':2};
        let c1 = (extC1.role ? (rMap[extC1.role]||0) : 0) + (extC1.level ? (lMapC[extC1.level]||0) : 0);
        let c2 = (extC2.role ? (rMap[extC2.role]||0) : 0) + (extC2.level ? (lMapC[extC2.level]||0) : 0);
        let scoreC = Math.min(c1 + c2, 20);

        let lMapD = {'ANTARABANGSA':10, 'KEBANGSAAN':10, 'NEGERI':9, 'POLITEKNIK':8, 'JABATAN':7};
        let d1 = lMapD[extD1.level] || 0;
        let d2 = lMapD[extD2.level] || 0;
        let d3 = lMapD[extD3.level] || 0;
        let scoreD = Math.min(d1 + d2 + d3, 30);

        let bonusScore = 0;
        if (certE_Prof) bonusScore += 2;
        if (certE_Luar) bonusScore += 2;
        if (certE_Intl) bonusScore += 2;

        if (bonusScore > 0) {
            document.getElementById('modalBonusDisplay').style.display = 'block';
            document.getElementById('modalBonusVal').textContent = bonusScore;
        }

        // 🌟 Hide the 'Bonus' pill entirely if the bonusScore is 0
        let bonusPillHtml = bonusScore > 0 ? `<span class="score-pill bg-secondary-subtle text-dark border border-secondary-subtle">Bonus: +${bonusScore}</span>` : '';

        // Inject the A B C D breakdown pills under the main score
        document.getElementById('modalScoreBreakdown').innerHTML = `
            <div class="d-flex gap-2 flex-wrap justify-content-center mt-2">
                <span class="score-pill bg-primary-subtle text-primary border border-primary-subtle">A: ${scoreA.toFixed(1)}</span>
                <span class="score-pill bg-info-subtle text-info border border-info-subtle">B: ${scoreB}</span>
                <span class="score-pill bg-warning-subtle text-warning border border-warning-subtle">C: ${scoreC}</span>
                <span class="score-pill bg-success-subtle text-success border border-success-subtle">D: ${scoreD}</span>
                ${bonusPillHtml}
            </div>
        `;

        // Render the forms
        certsHTML = `
            <div class="mb-3"><h6 class="fw-bold text-primary mb-2" style="font-size: 13px;">(a) Akademik</h6>
                <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text fw-bold" style="width:65px;">CGPA</span>
                    <input type="text" class="form-control bg-light" value="${cgpaVal > 0 ? cgpaVal.toFixed(2) : '-'}" readonly>
                    ${getFileBtn(certA)}
                </div>
            </div>
            
            <div class="mb-3"><h6 class="fw-bold text-primary mb-2" style="font-size: 13px;">(b) Pengurusan Organisasi</h6>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" class="form-control bg-light" value="${extJPP.org_name || ''}" placeholder="Tiada JPP" readonly>
                    <input type="text" class="form-control bg-light" value="${extJPP.position || ''}" placeholder="Jawatan" readonly>
                    ${getFileBtn(certB_JPP)}
                </div>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control bg-light" value="${extKelab.org_name || ''}" placeholder="Tiada Kelab" readonly>
                    <input type="text" class="form-control bg-light" value="${extKelab.position || ''}" placeholder="Jawatan" readonly>
                    ${getFileBtn(certB_Kelab)}
                </div>
            </div>
            
            <div class="mb-3"><h6 class="fw-bold text-primary mb-2" style="font-size: 13px;">(c) Penganjuran</h6>
                <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text" style="width:65px;">Prog 1</span>
                    <input type="text" class="form-control bg-light" value="${extC1.name || 'Tiada'}" readonly>
                    <input type="text" class="form-control bg-light" value="${extC1.role || '-'}" readonly>
                    ${getFileBtn(certCs[0])}
                </div>
                <div class="input-group input-group-sm">
                    <span class="input-group-text" style="width:65px;">Prog 2</span>
                    <input type="text" class="form-control bg-light" value="${extC2.name || 'Tiada'}" readonly>
                    <input type="text" class="form-control bg-light" value="${extC2.role || '-'}" readonly>
                    ${getFileBtn(certCs[1])}
                </div>
            </div>
            
            <div class="mb-3"><h6 class="fw-bold text-primary mb-2" style="font-size: 13px;">(d) Penyertaan</h6>
                <div class="input-group input-group-sm mb-1">
                    <span class="input-group-text" style="width:65px;">Acara 1</span>
                    <input type="text" class="form-control bg-light" value="${extD1.name || 'Tiada'}" readonly>
                    <input type="text" class="form-control bg-light" value="${extD1.level || '-'}" readonly>
                    ${getFileBtn(certDs[0])}
                </div>
                <div class="input-group input-group-sm mb-1">
                    <span class="input-group-text" style="width:65px;">Acara 2</span>
                    <input type="text" class="form-control bg-light" value="${extD2.name || 'Tiada'}" readonly>
                    <input type="text" class="form-control bg-light" value="${extD2.level || '-'}" readonly>
                    ${getFileBtn(certDs[1])}
                </div>
                <div class="input-group input-group-sm">
                    <span class="input-group-text" style="width:65px;">Acara 3</span>
                    <input type="text" class="form-control bg-light" value="${extD3.name || 'Tiada'}" readonly>
                    <input type="text" class="form-control bg-light" value="${extD3.level || '-'}" readonly>
                    ${getFileBtn(certDs[2])}
                </div>
            </div>
            
            <div class="mb-3"><h6 class="fw-bold text-primary mb-2" style="font-size: 13px;">(e) Lain-lain / Bonus</h6>
                <div class="input-group input-group-sm mb-1">
                    <div class="input-group-text bg-white flex-grow-1 text-start">
                        <input class="form-check-input mt-0 me-2" type="checkbox" disabled ${certE_Prof ? 'checked' : ''}>
                        Sijil Profesional (+2)
                    </div>
                    ${getFileBtn(certE_Prof)}
                </div>
                <div class="input-group input-group-sm mb-1">
                    <div class="input-group-text bg-white flex-grow-1 text-start">
                        <input class="form-check-input mt-0 me-2" type="checkbox" disabled ${certE_Luar ? 'checked' : ''}>
                        Pengiktirafan Luar (+2)
                    </div>
                    ${getFileBtn(certE_Luar)}
                </div>
                <div class="input-group input-group-sm">
                    <div class="input-group-text bg-white flex-grow-1 text-start">
                        <input class="form-check-input mt-0 me-2" type="checkbox" disabled ${certE_Intl ? 'checked' : ''}>
                        Penglibatan Antarabangsa (+2)
                    </div>
                    ${getFileBtn(certE_Intl)}
                </div>
            </div>
        `;
    }

    document.getElementById('modalFormContainer').innerHTML = certsHTML;
    modal.show();
}

// Perform AJAX call to Google Gemini API
function fetchAiSummary() {
    if (!currentAppId) return;
    const btn = document.getElementById('btnTriggerAi');
    const box = document.getElementById('aiSummaryText');

    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Extracting...';
    btn.disabled = true;
    box.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i> AI is analyzing records...</span>';

    const formData = new FormData();
    formData.append('ajax_ai_discovery', '1');
    formData.append('application_id', currentAppId);

    fetch('candidate_discovery.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            box.innerHTML = `<span class="fw-bold" style="color: #4c1d95; font-size: 1.1rem; line-height: 1.6;">"${data.summary}"</span>`;
            btn.innerHTML = '<i class="bi bi-check2-all"></i> Done';
        } else {
            box.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle"></i> Failed: ${data.error}</span>`;
            btn.innerHTML = 'Try Again';
            btn.disabled = false;
        }
    })
    .catch(error => {
        box.innerHTML = '<span class="text-danger">Network error occurred.</span>';
        btn.innerHTML = 'Try Again';
        btn.disabled = false;
    });
}
</script>
</body>
</html>