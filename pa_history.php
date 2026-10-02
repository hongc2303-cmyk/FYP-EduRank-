<?php
// 1. Include database connection configuration
require_once 'config.php';

// 2. Check login session and verify Academic Advisor role
if (function_exists('requireLogin')) {
    requireLogin();
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $current_role = strtolower($_SESSION['user_role'] ?? $_SESSION['role'] ?? '');
    if (!isset($_SESSION['user_id']) || ($current_role !== 'advisor' && $current_role !== 'academic advisor')) {
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

// 4. Fetch processed application history records
if (isset($pdo) && $pa_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.application_id,
                   a.application_date AS submitted_at,
                   a.status,
                   a.category_id,
                   u.full_name AS student_name,
                   u.email AS student_email,
                   s.matric_no,
                   s.programme,
                   c.category_name,
                   v.calculated_score,
                   v.remarks AS pa_remarks,
                   v.verification_date AS verified_at,
                   p.project_title,
                   p.group_members
            FROM award_applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            JOIN award_categories c ON a.category_id = c.category_id
            LEFT JOIN application_verifications v ON a.application_id = v.application_id
            LEFT JOIN project_award_details p ON a.application_id = p.application_id
            WHERE s.advisor_id = ? AND a.status != 'pending'
            ORDER BY v.verification_date DESC
        ");
        $stmt->execute([$pa_id]);
        $history_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Fetch supporting certificates for each historical record ---
        foreach ($history_records as &$record) {
            $stmtC = $pdo->prepare("SELECT * FROM certificates WHERE application_id = ? ORDER BY certificate_type ASC");
            $stmtC->execute([$record['application_id']]);
            $record['certificates'] = $stmtC->fetchAll(PDO::FETCH_ASSOC);
        }

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
    <title>EduRank - Advisee Application History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">

<div class="d-flex">
    
    <!-- Sidebar Navigation -->
    <aside class="sidebar p-4 d-flex flex-column justify-content-between">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="fa-solid fa-graduation-cap text-warning me-2 fs-4"></i>
                <span class="fs-5 fw-bold text-white">EduRank</span>
            </div>
            <div class="text-uppercase text-secondary text-xs fw-bold mb-3" style="font-size: 11px; letter-spacing: 1px;">MENU</div>
            <ul class="nav nav-pills flex-column gap-2">
                <li class="nav-item"><a href="pa_dashboard.php" class="nav-link d-flex align-items-center gap-3"><i class="bi bi-grid-fill"></i> Dashboard Overview</a></li>
                <li class="nav-item"><a href="pa_review.php" class="nav-link d-flex align-items-center gap-3"><i class="bi bi-check-circle"></i> Review Applications</a></li>
                <li class="nav-item"><a href="pa_history.php" class="nav-link active d-flex align-items-center gap-3"><i class="bi bi-clock-history"></i> Advisee History</a></li>
                <li class="nav-item"><a href="pa_profile.php" class="nav-link d-flex align-items-center gap-3"><i class="bi bi-person"></i> Profile</a></li>
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
            <a href="logout.php" class="text-danger text-decoration-none text-sm fw-semibold d-flex align-items-center gap-2 ms-1"><i class="bi bi-box-arrow-right"></i> Logout</a>
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
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">Advisee Application History</h5>
                        <p class="text-secondary text-sm mb-0" style="font-size: 13px;">Review all past processed student nominations, calculated percentages, and submitted verification remarks.</p>
                    </div>
                    <span class="badge bg-secondary-subtle text-dark border px-3 py-2 rounded-pill shadow-sm">
                        Total Processed: <?php echo count($history_records); ?>
                    </span>
                </div>

                <!-- Search & Filter Toolbar -->
                <div class="row g-2 mb-4 bg-light p-2 rounded border">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="Search by name or matric...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="statusFilter" class="form-select form-select-sm">
                            <option value="all">All Status</option>
                            <option value="verified">Verified / Forwarded</option>
                            <option value="evaluated">Evaluated by Committee</option>
                            <option value="nominated">Final Winner (Nominated)</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle border-0" id="historyTable">
                        <thead class="table-light text-secondary text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                            <tr>
                                <th class="border-0">NO.</th>
                                <th class="border-0">STUDENT NAME</th>
                                <th class="border-0">AWARD CATEGORY</th>
                                <th class="border-0">VERIFIED AT</th>
                                <th class="border-0 text-center">RATING</th>
                                <th class="border-0">STATUS</th>
                                <th class="border-0 text-center">ACTION</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: 14px;">
                            <?php if (!empty($history_records)): ?>
                                <?php foreach ($history_records as $index => $row): ?>
                                    <tr class="border-bottom history-row" data-status="<?php echo htmlspecialchars($row['status']); ?>">
                                        <td class="fw-bold text-dark"><?php echo $index + 1; ?></td>
                                        <td class="search-target">
                                            <div class="fw-semibold text-dark"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($row['matric_no']); ?></div>
                                        </td>
                                        <td class="fw-semibold text-primary" style="max-width: 250px;">
                                            <i class="bi bi-award me-1"></i><?php echo htmlspecialchars($row['category_name']); ?>
                                        </td>
                                        <td class="text-secondary small">
                                            <?php echo !empty($row['verified_at']) ? date('d M Y, h:i A', strtotime($row['verified_at'])) : '-'; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if (isset($row['calculated_score']) && $row['calculated_score'] !== null): ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fs-6 shadow-sm">
                                                    <?php echo number_format($row['calculated_score'], 1); ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] === 'verified'): ?>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 rounded-pill">
                                                    <i class="bi bi-check-circle me-1"></i> Verified & Forwarded
                                                </span>
                                            <?php elseif ($row['status'] === 'evaluated'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">
                                                    <i class="bi bi-award me-1"></i> Evaluated (JKP)
                                                </span>
                                            <?php elseif ($row['status'] === 'nominated'): ?>
                                                <span class="badge bg-warning text-dark border px-3 py-1 rounded-pill shadow-sm">
                                                    <i class="bi bi-star-fill me-1"></i> Final Winner (Top 1)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">
                                                    <i class="bi bi-x-circle me-1"></i> Rejected
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold rounded-circle shadow-sm" data-bs-toggle="modal" data-bs-target="#historyModal<?php echo $row['application_id']; ?>" title="View Application Details">
                                                <i class="bi bi-eye-fill"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Modal Popup for Viewing Past Application Details -->
                                    <div class="modal fade" id="historyModal<?php echo $row['application_id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content border-0 shadow-lg">
                                                <div class="modal-header bg-light border-bottom-0 pb-3">
                                                    <h6 class="modal-title fw-bold text-dark"><i class="bi bi-file-earmark-text text-primary me-2"></i>Official Verification Record</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body p-4 pt-0">
                                                    
                                                    <div class="row mb-3 pt-3 pb-3 border-bottom">
                                                        <div class="col-md-6">
                                                            <div class="text-muted small mb-1">Student Name</div>
                                                            <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="text-muted small mb-1">Matric Number & Programme</div>
                                                            <div class="fw-semibold text-dark"><?php echo htmlspecialchars($row['matric_no']); ?> (<?php echo htmlspecialchars($row['programme'] ?? 'DIT'); ?>)</div>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3 pb-3 border-bottom">
                                                        <div class="col-md-6">
                                                            <div class="text-muted small mb-1">Award Category</div>
                                                            <div class="fw-semibold text-primary"><i class="bi bi-award me-1"></i><?php echo htmlspecialchars($row['category_name']); ?></div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="text-muted small mb-1">Date Submitted by Student</div>
                                                            <div class="fw-semibold text-dark"><?php echo date('d F Y, h:i A', strtotime($row['submitted_at'])); ?></div>
                                                        </div>
                                                    </div>

                                                    <!-- --- Submitted Documents Display with Smart Labels --- -->
                                                    <div class="row mb-3 pb-3 border-bottom">
                                                        <div class="col-12">
                                                            <div class="text-muted small mb-2 fw-bold"><i class="bi bi-file-earmark-check me-1"></i>Submitted Documents / Proofs</div>
                                                            <div class="list-group">
                                                                <?php 
                                                                if (!empty($row['certificates'])) {
                                                                    
                                                                    if ($row['category_id'] == 2) {
                                                                        // Category 2: Projek Terbaik Logic
                                                                        echo '<div class="list-group-item p-3 mb-3 border-primary bg-light">
                                                                                <h6 class="fw-bold text-primary mb-3"><i class="bi bi-folder-fill me-2"></i>Butiran Projek (Kategori FYP)</h6>
                                                                                <div class="mb-2"><span class="text-secondary small d-block">Tajuk Projek:</span> <strong class="text-dark fs-6">'.htmlspecialchars($row['project_title'] ?? '-').'</strong></div>
                                                                                <div><span class="text-secondary small d-block">Ahli Kumpulan:</span> <span class="text-dark" style="font-size:13px;">'.nl2br(htmlspecialchars($row['group_members'] ?? '-')).'</span></div>
                                                                              </div>';

                                                                        foreach ($row['certificates'] as $cert) {
                                                                            $typeLabel = "Dokumen Biasa";
                                                                            $badgeClass = "bg-secondary";
                                                                            $fileLink = !empty($cert['file_name']) ? '<a href="uploads/' . rawurlencode($cert['file_name']) . '" target="_blank" class="btn btn-sm btn-outline-primary px-3"><i class="bi bi-box-arrow-up-right me-1"></i>View</a>' : '<span class="badge bg-light text-muted border">Form Entry</span>';
                                                                            
                                                                            $extraData = json_decode($cert['extra_data'], true) ?? [];
                                                                            $cType = strtolower($cert['certificate_type'] ?? '');
                                                                            
                                                                            if (($extraData['type'] ?? '') === 'Bukti_Pencapaian_FYP' || $cType === 'projek_bukti') {
                                                                                $typeLabel = "Bukti Pencapaian FYP (WAJIB)";
                                                                                $badgeClass = "bg-success";
                                                                            } elseif (($extraData['type'] ?? '') === 'Sijil_Penghargaan_Industri' || $cType === 'projek_industri') {
                                                                                $typeLabel = "Sijil Penghargaan Industri (Pilihan)";
                                                                                $badgeClass = "bg-warning text-dark";
                                                                            }

                                                                            echo '<div class="list-group-item p-3 d-flex justify-content-between align-items-center mb-2 bg-light shadow-sm border rounded">
                                                                                    <div><span class="badge '.$badgeClass.' mb-1">'.$typeLabel.'</span><br><span class="text-muted small">'.htmlspecialchars($cert['file_name'] ?? '').'</span></div> 
                                                                                    '.$fileLink.'
                                                                                  </div>';
                                                                        }
                                                                    } else {
                                                                        // Category 1 & 3-7: Calculate marks and show details
                                                                        foreach ($row['certificates'] as $cert) {
                                                                            $extra = json_decode($cert['extra_data'], true) ?? [];
                                                                            if (!is_array($extra)) $extra = []; 
                                                                            
                                                                            $cType = strtolower($cert['certificate_type'] ?? '');
                                                                            $fileLink = !empty($cert['file_name']) ? '<a href="uploads/'.rawurlencode($cert['file_name']).'" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Lihat Dokumen</a>' : '<span class="badge bg-light text-muted border">Form Entry</span>';

                                                                            $typeLabel = ''; $badgeClass = ''; $itemDetails = ''; $itemScore = 0;

                                                                            if ($cType === 'a' || $cType === 'akademik') { 
                                                                                $cgpa = floatval($cert['extra_data'] ?? 0);
                                                                                $itemScore = min($cgpa * 10, 40);
                                                                                $typeLabel = 'Komp A - Akademik';
                                                                                $badgeClass = 'bg-primary';
                                                                                $itemDetails = '<div class="fw-bold text-dark">CGPA / HPNM: '.number_format($cgpa, 2).'</div>';
                                                                            } elseif ($cType === 'b' || $cType === 'organisasi_jpp' || $cType === 'organisasi_kelab') { 
                                                                                $bMap = ['JPP_YDP'=>10, 'JPP_NYDP'=>9, 'JPP_SU_BEND'=>8, 'JPP_EXCO'=>7, 'CLUB_PENGERUSI'=>6, 'CLUB_NAIB'=>5, 'CLUB_SU_BEND'=>4, 'CLUB_AJK'=>3, 'CLUB_AHLI'=>1];
                                                                                $itemScore = $bMap[$extra['position'] ?? ''] ?? 0; 
                                                                                
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
                                                                                
                                                                                $typeLabel = 'Komp C - Penganjuran Program';
                                                                                $badgeClass = 'bg-warning text-dark';
                                                                                $itemDetails = '
                                                                                    <div class="text-dark small mb-1"><strong>Nama Program:</strong> '.htmlspecialchars($extra['name'] ?? '-').'</div>
                                                                                    <div class="text-dark small"><strong>Jawatan:</strong> '.htmlspecialchars($extra['role'] ?? '-').' ('.$rScore.' pt) &nbsp;|&nbsp; <strong>Peringkat:</strong> '.htmlspecialchars($extra['level'] ?? '-').' ('.$lScore.' pt)</div>
                                                                                ';
                                                                            } elseif ($cType === 'd' || $cType === 'penyertaan_program') {
                                                                                $lMap = ['ANTARABANGSA'=>10, 'KEBANGSAAN'=>10, 'NEGERI'=>9, 'POLITEKNIK'=>8, 'JABATAN'=>7];
                                                                                $itemScore = $lMap[$extra['level'] ?? ''] ?? 0;
                                                                                
                                                                                $typeLabel = 'Komp D - Penyertaan Program';
                                                                                $badgeClass = 'bg-success';
                                                                                $itemDetails = '
                                                                                    <div class="text-dark small mb-1"><strong>Acara:</strong> '.htmlspecialchars($extra['name'] ?? '-').'</div>
                                                                                    <div class="text-dark small"><strong>Peringkat:</strong> '.htmlspecialchars($extra['level'] ?? '-').'</div>
                                                                                ';
                                                                            } elseif ($cType === 'e' || strpos($cType, 'bonus_') === 0) { 
                                                                                $itemScore = 2;
                                                                                $bonusType = strtoupper(str_replace('bonus_', '', $cType));
                                                                                if($bonusType === 'E') $bonusType = 'BONUS';
                                                                                
                                                                                $typeLabel = 'Komp E - ' . $bonusType;
                                                                                $badgeClass = 'bg-secondary';
                                                                                $itemDetails = '<div class="text-dark small"><strong>Bukti Penglibatan Tambahan Dimuat Naik</strong></div>';
                                                                            }

                                                                            if ($typeLabel !== '') {
                                                                                echo '
                                                                                <div class="list-group-item p-3 mb-2 bg-light shadow-sm border rounded">
                                                                                    <div class="d-flex justify-content-between align-items-start mb-2 border-bottom pb-2">
                                                                                        <span class="badge '.$badgeClass.' fw-bold" style="font-size:12px;">'.$typeLabel.'</span>
                                                                                        <span class="badge bg-white text-dark border px-2 py-1"><i class="bi bi-plus text-success fw-bold"></i>'.$itemScore.' Markah</span>
                                                                                    </div>
                                                                                    <div class="mb-3">'.$itemDetails.'</div>
                                                                                    <div class="d-flex justify-content-between align-items-center bg-white p-2 border rounded">
                                                                                        <span class="text-muted small text-truncate" style="max-width: 70%;"><i class="bi bi-file-earmark-pdf text-danger me-1"></i>'.htmlspecialchars($cert['file_name'] ?? '').'</span>
                                                                                        '.$fileLink.'
                                                                                    </div>
                                                                                </div>';
                                                                            }
                                                                        }
                                                                    }
                                                                } else {
                                                                    echo '<div class="list-group-item text-muted small py-2 bg-light">No documents attached.</div>';
                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- ----------------------------------------- -->

                                                    <div class="row mb-3 pb-3 border-bottom">
                                                        <div class="col-md-6">
                                                            <div class="text-muted small mb-1">Official APCP Rating</div>
                                                            <div class="fs-4 fw-bold text-primary"><?php echo number_format($row['calculated_score'] ?? 0, 1); ?>%</div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="text-muted small mb-1">Current Status</div>
                                                            <div>
                                                                <?php if ($row['status'] === 'verified'): ?>
                                                                    <span class="badge bg-info text-dark border px-3 py-1 rounded-pill">Verified & Forwarded</span>
                                                                <?php elseif ($row['status'] === 'evaluated'): ?>
                                                                    <span class="badge bg-success border px-3 py-1 rounded-pill">Evaluated by Committee</span>
                                                                <?php elseif ($row['status'] === 'nominated'): ?>
                                                                    <span class="badge bg-warning text-dark border px-3 py-1 rounded-pill"><i class="bi bi-star-fill me-1"></i> FINAL WINNER (Top 1)</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-danger border px-3 py-1 rounded-pill">Rejected</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mb-2">
                                                        <div class="text-muted small mb-2 fw-bold">Your Verification Remarks</div>
                                                        <div class="p-3 bg-light rounded text-dark border" style="font-size: 14px;">
                                                            <?php echo nl2br(htmlspecialchars($row['pa_remarks'] ?? 'No remarks recorded.')); ?>
                                                        </div>
                                                    </div>

                                                </div>
                                                <div class="modal-footer bg-light p-2 border-top-0">
                                                    <button type="button" class="btn btn-secondary btn-sm fw-semibold px-4" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- End Modal Popup -->

                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-secondary py-5"><i class="bi bi-clock-history fs-1 text-muted mb-2 d-block"></i><p class="mb-0 fw-medium">No historical records found.</p></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="text-center p-4 mt-5 text-muted">
    <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Search and Filter Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const rows = document.querySelectorAll('.history-row');

    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const statusValue = statusFilter.value.toLowerCase();

        rows.forEach(row => {
            const textContent = row.querySelector('.search-target').textContent.toLowerCase();
            const rowStatus = row.getAttribute('data-status').toLowerCase();
            
            const matchesSearch = textContent.includes(searchTerm);
            const matchesStatus = (statusValue === 'all' || rowStatus === statusValue);

            if (matchesSearch && matchesStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if(searchInput) searchInput.addEventListener('input', filterTable);
    if(statusFilter) statusFilter.addEventListener('change', filterTable);
});
</script>

</body>
</html>