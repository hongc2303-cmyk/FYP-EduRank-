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

$current_session = '';
$pending_evaluations = 0;
if (isset($pdo)) {
    $stmtSes = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'current_session'");
    $current_session = $stmtSes->fetchColumn() ?: 'Default Session';
    
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM award_applications WHERE status = 'verified' AND session_name = ?");
    $stmtCount->execute([$current_session]);
    $pending_evaluations = $stmtCount->fetchColumn();
}

$all_categories = [];
if (isset($pdo)) {
    $stmtCats = $pdo->query("SELECT category_id, category_name FROM award_categories ORDER BY category_id ASC");
    $all_categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
}

$selected_category_id = $_GET['cat_id'] ?? $_POST['category_id'] ?? ($all_categories[0]['category_id'] ?? null);
$selected_category_name = 'Unknown Category';
foreach ($all_categories as $c) {
    if ($c['category_id'] == $selected_category_id) {
        $selected_category_name = $c['category_name'];
        break;
    }
}

$top_candidates = [];
if ($selected_category_id && isset($pdo)) {
    $stmt = $pdo->prepare("
        SELECT a.application_id, u.full_name, s.matric_no, a.category_id,
               v.calculated_score, v.remarks AS pa_remarks,
               e.remarks AS committee_remarks
        FROM award_applications a
        JOIN students s ON a.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN application_verifications v ON a.application_id = v.application_id
        LEFT JOIN evaluations e ON a.application_id = e.application_id
        WHERE a.category_id = ? AND a.status IN ('verified', 'evaluated', 'nominated') AND a.session_name = ?
        ORDER BY v.calculated_score DESC
        LIMIT 3
    ");
    $stmt->execute([$selected_category_id, $current_session]);
    $top_candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($top_candidates as &$cand) {
        $stmtC = $pdo->prepare("SELECT certificate_type, extra_data FROM certificates WHERE application_id = ?");
        $stmtC->execute([$cand['application_id']]);
        $cand['raw_certificates'] = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        if ($cand['category_id'] == 2) {
            $stmtP = $pdo->prepare("SELECT project_title, group_members FROM project_award_details WHERE application_id = ?");
            $stmtP->execute([$cand['application_id']]);
            $cand['project_details'] = $stmtP->fetch(PDO::FETCH_ASSOC);
        }
    }
}

$ai_response = null;
$api_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_ai'])) {
    $gemini_api_key = 'AQ.Ab8RN6I8H05iKZ1FNIqwpr1zLgKYnqVH4l80eypbizuWjnmUEA'; 

    if (count($top_candidates) >= 2) {
        $prompt = "You are an AI decision assistant for the Malaysian Polytechnic (APCP) student award system. Your task is to act as a tie-breaker. Please deeply analyze the following top candidates based on their evaluation data, achievements, and project involvement.\n\n";
        
        foreach ($top_candidates as $index => $candidate) {
            $num = $index + 1;
            $prompt .= "Candidate {$num}: " . $candidate['full_name'] . " (Matric: " . $candidate['matric_no'] . ")\n";
            if ($selected_category_id == 2) {
                $prompt .= "- Qualitative Evaluation (Project Based)\n";
            } else {
                $prompt .= "- Total Rating: " . number_format($candidate['calculated_score'], 1) . "%\n";
            }
            $prompt .= "- Program Advisor Remarks: " . ($candidate['pa_remarks'] ?: 'None provided') . "\n";
            $prompt .= "- Committee Remarks: " . ($candidate['committee_remarks'] ?: 'None provided') . "\n";
            $prompt .= "- Achievements & Certificates:\n";
            if (!empty($candidate['raw_certificates'])) {
                foreach ($candidate['raw_certificates'] as $cert) {
                    $type = strtoupper($cert['certificate_type']);
                    $extra = $cert['extra_data'];
                    $prompt .= "  * Component {$type}: {$extra}\n"; 
                }
            } else {
                $prompt .= "  * No specific certificates recorded.\n";
            }

            if (!empty($candidate['project_details'])) {
                $prompt .= "- Project Title: " . $candidate['project_details']['project_title'] . "\n";
                $prompt .= "- Group Members: " . str_replace("\n", ", ", $candidate['project_details']['group_members']) . "\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "Please output exactly two structured sections:\n";
        $prompt .= "1. [Key Highlights]: For EACH candidate, provide 2 to 3 concise bullet points (*) highlighting their standout achievements. You MUST wrap specific achievements, roles, and competition levels in **bold** (e.g., **CGPA 3.90**, **President of JPP**, **International Level**, **Director**).\n";
        $prompt .= "2. [Final AI Recommendation]: Recommend ONE clear winner in 1 to 2 sharp sentences, giving the core decisive reason.";

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . $gemini_api_key;
        $data = ["contents" => [["parts" => [["text" => $prompt]]]]];
        
        function executeCurlRequest($url, $data) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            return [$response, $err];
        }

        list($response, $err) = executeCurlRequest($url, $data);
        
        
        if ($err || empty($response)) {
            sleep(1);
            list($response, $err) = executeCurlRequest($url, $data);
        }
        
        if ($err) {
            $api_error = 'Connection failed: ' . $err;
        } else {
            $result = json_decode($response, true);
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                $ai_response = $result['candidates'][0]['content']['parts'][0]['text'];
            } else {
                $google_error = isset($result['error']['message']) ? $result['error']['message'] : 'Raw Response: ' . $response;
                $api_error = "Google API Rejected: " . $google_error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - AI Candidate Analysis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .cand-card { border: none; border-radius: 16px; transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); background: #fff; position: relative; overflow: visible; }
        .cand-card:hover { transform: translateY(-8px); box-shadow: 0 15px 30px rgba(0,0,0,0.08) !important; }
        .rank-badge { position: absolute; top: -15px; left: 50%; transform: translateX(-50%); border-radius: 30px; padding: 5px 20px; font-weight: 900; font-size: 0.85rem; box-shadow: 0 4px 10px rgba(0,0,0,0.15); z-index: 2; border: 2px solid #fff; }
        .cand-name { font-size: 1.1rem; line-height: 1.3; height: 2.6rem; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; margin-top: 15px; }
        .score-display { background: #f8f9fa; border-radius: 10px; padding: 12px; border-left: 4px solid #0d6efd; }
        .remarks-container { font-size: 0.8rem; color: #555; background: #fdfdfd; border: 1px solid #eee; border-radius: 8px; padding: 10px; height: 80px; overflow-y: auto; position: relative; }
        .btn-ai-magic { background: linear-gradient(45deg, #4f46e5, #9333ea, #ec4899, #4f46e5); background-size: 300% 300%; color: white; border: none; transition: all 0.3s ease; animation: gradientShift 4s ease infinite; }
        .btn-ai-magic:hover { color: white; box-shadow: 0 8px 25px rgba(147, 51, 234, 0.4); transform: scale(1.02); }
        @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .ai-result-panel { background: #ffffff; border-radius: 16px; border-top: 5px solid #9333ea; box-shadow: 0 10px 40px rgba(147, 51, 234, 0.08); position: relative; }
        .ai-result-panel::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(135deg, rgba(147,51,234,0.03) 0%, rgba(255,255,255,0) 100%); border-radius: 16px; pointer-events: none; }
        
        .ai-content { font-size: 0.95rem; line-height: 1.8; color: #333; }
        .ai-content strong { color: #4c1d95; background: rgba(147, 51, 234, 0.12); padding: 1px 6px; border-radius: 4px; font-weight: 700; }
        .ai-content ul { padding-left: 20px; margin-top: 6px; margin-bottom: 16px; }
        .ai-content li { margin-bottom: 6px; }
    </style>
</head>
<body class="dashboard-body">

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
    
    <div class="main-content" id="mainContent">
        <header class="top-header shadow-sm d-flex justify-content-between align-items-center pe-4">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <?php include 'notification.php'; ?>
        </header>

        <div class="p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold text-dark mb-1"><i class="bi bi-magic text-primary me-2"></i>AI Candidate Tie-Breaker</h4>
                    <p class="text-secondary small mb-0">Powered by Google Gemini 3.6. Select a category below to perform quick and decisive analysis.</p>
                </div>
                <span class="badge bg-primary px-3 py-2 fs-6 shadow-sm"><i class="bi bi-calendar-check me-2"></i><?php echo htmlspecialchars($current_session); ?></span>
            </div>
            
            <div class="custom-card p-4 shadow-sm bg-white mb-5 border-0 rounded-4">
                <h6 class="fw-bold text-primary mb-3">Select Category For AI Analysis</h6>
                <form method="GET" action="ai_analysis.php">
                    <div class="input-group mb-0">
                        <span class="input-group-text bg-light border-primary"><i class="bi bi-funnel-fill text-primary"></i></span>
                        <select name="cat_id" class="form-select border-primary fw-semibold" onchange="this.form.submit()">
                            <?php foreach($all_categories as $c): ?>
                                <option value="<?php echo $c['category_id']; ?>" <?php echo $selected_category_id == $c['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <?php if ($selected_category_id): ?>
                <?php 
                $count_cands = count($top_candidates);
                if ($count_cands < 2): 
                ?>
                    <div class="alert alert-warning text-center border-0 shadow-sm rounded-4 py-4">
                        <i class="bi bi-exclamation-triangle-fill fs-3 text-warning d-block mb-2"></i>
                        <span class="fw-bold text-dark">Insufficient Data</span><br>
                        <span class="text-secondary small">Not enough evaluated candidates in this category to perform a comparison. Need at least 2 candidates.</span>
                    </div>
                <?php else: ?>
                    <h6 class="fw-bold text-secondary mb-4 text-center text-uppercase tracking-wider">Top Candidates Roster</h6>
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-5 g-4 justify-content-center mb-5">
                        <?php 
                        $badge_colors = ['bg-warning text-dark', 'bg-secondary text-white', 'bg-danger text-white', 'bg-info text-dark', 'bg-success text-white'];
                        $border_colors = ['#ffc107', '#6c757d', '#dc3545', '#0dcaf0', '#198754'];
                        foreach ($top_candidates as $index => $cand): ?>
                            <div class="col">
                                <div class="cand-card p-4 shadow-sm h-100 text-center" style="border-bottom: 4px solid <?php echo $border_colors[$index]; ?>;">
                                    <div class="rank-badge <?php echo $badge_colors[$index]; ?>"><i class="bi bi-trophy-fill me-1"></i> Rank #<?php echo $index + 1; ?></div>
                                    <h5 class="fw-bold text-dark cand-name" title="<?php echo htmlspecialchars($cand['full_name']); ?>"><?php echo htmlspecialchars($cand['full_name']); ?></h5>
                                    <div class="text-primary fw-bold small mb-3"><?php echo htmlspecialchars($cand['matric_no']); ?></div>
                                    <div class="score-display mb-3">
                                        <?php if ($selected_category_id == 2): ?>
                                            <div class="fw-bold text-dark small" style="line-height: 1.2;"><i class="bi bi-info-circle me-1"></i>Project based<br>Qualitative Review</div>
                                        <?php else: ?>
                                            <div class="fw-bold fs-2 text-dark" style="line-height: 1;"><?php echo number_format($cand['calculated_score'], 1); ?><span class="fs-6 text-muted fw-normal">%</span></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-start">
                                        <div class="text-muted small fw-bold mb-1"><i class="bi bi-chat-quote-fill me-1"></i>Combined Remarks</div>
                                        <div class="remarks-container">
                                            <strong>PA:</strong> <?php echo htmlspecialchars($cand['pa_remarks'] ?: '-'); ?><br>
                                            <strong>Comm:</strong> <?php echo htmlspecialchars($cand['committee_remarks'] ?: '-'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="text-center mb-5">
                        <form method="POST" action="">
                            <input type="hidden" name="category_id" value="<?php echo $selected_category_id; ?>">
                            <button type="submit" name="generate_ai" class="btn btn-ai-magic px-5 py-3 fs-5 rounded-pill shadow-lg">
                                <i class="bi bi-stars me-2 fs-4"></i> Generate Highlighted Analysis
                            </button>
                        </form>
                    </div>

                    <?php if (isset($_POST['generate_ai'])): ?>
                        <?php if ($api_error): ?>
                            <div class="alert alert-danger shadow-sm rounded-4"><i class="bi bi-x-octagon-fill me-2"></i><?php echo htmlspecialchars($api_error); ?></div>
                        <?php elseif ($ai_response): ?>
                            <div class="ai-result-panel p-4 p-md-5 mb-5">
                                <div class="d-flex align-items-center border-bottom pb-3 mb-4">
                                    <div class="bg-purple-subtle p-3 rounded-circle me-3" style="background-color: #f3e8ff;">
                                        <i class="bi bi-robot fs-2" style="color: #9333ea;"></i>
                                    </div>
                                    <div>
                                        <h3 class="fw-bold mb-0" style="color: #4c1d95;">Gemini AI Verdict</h3>
                                        <span class="text-muted small">Key Achievements Highlights & Direct Recommendation</span>
                                    </div>
                                </div>
                                <div class="ai-content">
                                    <?php 
                                        $formatted = htmlspecialchars($ai_response);
                                        
                                        $formatted = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $formatted);
                                        $formatted = preg_replace('/^\* (.*)$/m', '<li>$1</li>', $formatted);
                                        $formatted = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $formatted);
                                        $formatted = str_replace('</ul><ul>', '', $formatted);
                                        $formatted = nl2br($formatted); 
                                        $formatted = str_replace(['<br /><ul>', '<ul><br />', '<br /><li>', '</li><br />', '</ul><br />'], ['<ul>', '<ul>', '<li>', '</li>', '</ul>'], $formatted);
                                        
                                        echo $formatted; 
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <footer class="text-center p-4 mt-5 text-muted">
        <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>