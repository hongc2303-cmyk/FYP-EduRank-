<?php
// Handle application detail view
$view_application = null;
$view_certificates = [];

if (isset($_GET['view_app']) && is_numeric($_GET['view_app'])) {
    $app_id = $_GET['view_app'];
    try {
        // Fetch main application details, CGPA, PA Verification remarks, and Evaluation Committee remarks
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   c.category_name, 
                   cert.extra_data AS student_cgpa,
                   v.remarks AS pa_remarks,
                   v.calculated_score AS pa_score,
                   v.verification_date AS pa_verified_at,
                   e.remarks AS eval_remarks,
                   e.total_score AS eval_score,
                   e.evaluation_date AS eval_at
            FROM award_applications a
            JOIN award_categories c ON a.category_id = c.category_id
            LEFT JOIN certificates cert ON a.application_id = cert.application_id AND cert.certificate_type = 'a'
            LEFT JOIN application_verifications v ON a.application_id = v.application_id
            LEFT JOIN evaluations e ON a.application_id = e.application_id
            WHERE a.application_id = ? AND a.student_id = ?
        ");
        $stmt->execute([$app_id, $student['student_id']]);
        $view_application = $stmt->fetch();
        
        if ($view_application) {
            $stmtC = $pdo->prepare("SELECT * FROM certificates WHERE application_id = ? ORDER BY certificate_type ASC");
            $stmtC->execute([$app_id]);
            $view_certificates = $stmtC->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(PDOException $e) {
        // Silently handle exception
    }
}
?>

<div class="mb-4">
    <h4 class="fw-bold text-dark mb-1">Submission History</h4>
    <p class="text-secondary text-sm">View all your submitted award applications and their current status.</p>
</div>

<?php if($view_application): ?>
    <div class="card bg-white custom-card shadow-sm p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <!-- Formatted Application ID -->
            <h5 class="fw-bold mb-0">Application <?php echo sprintf('APP-%04d', $view_application['application_id']); ?></h5>
            <a href="?page=history" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back to List</a>
        </div>
        
        <div class="row mb-4 bg-light p-3 rounded mx-0 border">
            <div class="col-md-6 mb-2 mb-md-0">
                <p class="mb-1"><strong>Category:</strong> <?php echo htmlspecialchars($view_application['category_name']); ?></p>
                <p class="mb-0"><strong>Submission Date:</strong> <?php echo date('F d, Y', strtotime($view_application['application_date'])); ?></p>
            </div>
            <div class="col-md-6">
                <p class="mb-1"><strong>GPA / CGPA:</strong> 
                    <span class="fw-bold text-primary fs-5">
                        <?php 
                            $cgpa_val = floatval($view_application['student_cgpa'] ?? $view_application['gpa'] ?? 0);
                            echo $cgpa_val > 0 ? number_format($cgpa_val, 2) : '-';
                        ?>
                    </span>
                </p>
                <p class="mb-0"><strong>Status:</strong> 
                    <?php if ($view_application['status'] === 'pending'): ?>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-pill">Pending PA Review</span>
                    <?php elseif ($view_application['status'] === 'verified'): ?>
                        <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 rounded-pill">Verified</span>
                    <?php elseif ($view_application['status'] === 'evaluated'): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Evaluated</span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">Rejected</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Academic Advisor Feedback -->
        <?php if (!empty($view_application['pa_remarks']) || $view_application['pa_score'] !== null): ?>
            <div class="card border-primary bg-primary-subtle mb-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2 border-primary-subtle">
                        <h6 class="fw-bold text-primary mb-0">
                            <i class="bi bi-chat-left-text me-2"></i>Academic Advisor (PA) Verification & Feedback
                        </h6>
                        <?php if(!empty($view_application['pa_verified_at'])): ?>
                            <small class="text-secondary">
                                Processed: <?php echo date('M d, Y', strtotime($view_application['pa_verified_at'])); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="row align-items-center">
                        <div class="col-md-8 mb-2 mb-md-0">
                            <span class="text-secondary d-block small fw-bold text-uppercase">PA Review Notes / Error Details:</span>
                            <p class="mb-0 text-dark fw-medium fs-6">
                                "<?php echo htmlspecialchars($view_application['pa_remarks'] ?? 'No specific remarks provided.'); ?>"
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end border-start border-primary-subtle ps-3">
                            <span class="text-secondary d-block small fw-bold text-uppercase">Verified Score:</span>
                            <span class="fs-4 fw-bold text-primary">
                                <?php echo $view_application['pa_score'] !== null ? number_format($view_application['pa_score'], 1) : '0.0'; ?>
                            </span> 
                            <small class="text-muted">Marks</small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Evaluation Committee Feedback -->
        <?php if (!empty($view_application['eval_remarks']) || $view_application['eval_score'] !== null): ?>
            <div class="card border-success bg-success-subtle mb-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2 border-success-subtle">
                        <h6 class="fw-bold text-success mb-0">
                            <i class="bi bi-award me-2"></i>Evaluation Committee Final Verdict
                        </h6>
                        <?php if(!empty($view_application['eval_at'])): ?>
                            <small class="text-secondary">
                                Evaluated: <?php echo date('M d, Y', strtotime($view_application['eval_at'])); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="row align-items-center">
                        <div class="col-md-8 mb-2 mb-md-0">
                            <span class="text-secondary d-block small fw-bold text-uppercase">Committee Remarks:</span>
                            <p class="mb-0 text-dark fw-medium fs-6">
                                "<?php echo htmlspecialchars($view_application['eval_remarks'] ?? 'No final comments.'); ?>"
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end border-start border-success-subtle ps-3">
                            <span class="text-secondary d-block small fw-bold text-uppercase">Final Total Score:</span>
                            <span class="fs-4 fw-bold text-success">
                                <?php echo $view_application['eval_score'] !== null ? number_format($view_application['eval_score'], 1) : '0.0'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-file-earmark-check me-2 text-primary"></i>Submitted Components & Proof Certificates</h6>
        <div class="list-group mb-3">
            <?php if (!empty($view_certificates)): ?>
                <?php foreach($view_certificates as $cert): ?>
                    <?php 
                        $type_labels = [
                            'a' => '(a) Academic CGPA Result',
                            'b' => '(b) Leadership / Organization Role',
                            'c' => '(c) Program Organizing',
                            'd' => '(d) Event Participation',
                            'e' => '(e) Bonus Merit Recognition'
                        ];
                        $badge_colors = [
                            'a' => 'bg-primary',
                            'b' => 'bg-info',
                            'c' => 'bg-warning text-dark',
                            'd' => 'bg-success',
                            'e' => 'bg-secondary'
                        ];
                        $cert_type = strtolower($cert['certificate_type'] ?? 'a');
                    ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center p-3 mb-2 border rounded">
                        <div>
                            <span class="badge <?php echo $badge_colors[$cert_type] ?? 'bg-primary'; ?> text-uppercase me-2">
                                <?php echo $type_labels[$cert_type] ?? 'Component ' . strtoupper($cert_type); ?>
                            </span>
                            <strong class="text-dark"><?php echo htmlspecialchars($cert['file_name'] ?? 'Supporting Document'); ?></strong>
                            <div class="small text-muted mt-1">
                                <?php 
                                    $data = json_decode($cert['extra_data'], true);
                                    if (is_array($data)) {
                                        $details = [];
                                        foreach ($data as $k => $v) {
                                            $details[] = ucfirst($k) . ": " . htmlspecialchars($v);
                                        }
                                        echo implode(" | ", $details);
                                    } else {
                                        echo "Value: " . htmlspecialchars($cert['extra_data']);
                                    }
                                ?>
                            </div>
                        </div>
                        <div>
                            <?php if (!empty($cert['file_name'])): ?>
                                <!-- Direct PHP Upload Folder Link -->
                                <a href="uploads/<?php echo rawurlencode($cert['file_name']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-file-earmark-pdf me-1"></i> View Document
                                </a>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border">Form Entry</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-3 text-muted text-center border rounded bg-light">No certificates found for this application.</div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card bg-white custom-card shadow-sm p-4">
        <?php if(!empty($applications) && count($applications) > 0): ?>
            <div class="table-responsive">
                <table class="table align-middle border-0">
                    <thead class="table-light text-secondary text-uppercase" style="font-size: 11px;">
                        <tr>
                            <th class="border-0">FORM NO.</th>
                            <th class="border-0">AWARD CATEGORY</th>
                            <th class="border-0">SUBMISSION DATE</th>
                            <th class="border-0">GPA / CGPA</th>
                            <th class="border-0">FEEDBACK / REMARKS</th>
                            <th class="border-0 text-end">STATUS</th>
                            <th class="border-0 text-end">ACTION</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 14px;">
                        <?php foreach($applications as $app): ?>
                            <tr class="border-bottom">
                                <!-- Formatted Application Number -->
                                <td class="fw-bold text-dark"><?php echo sprintf('APP-%04d', $app['application_id']); ?></td>
                                <td class="fw-semibold text-dark">
                                    <i class="bi bi-award me-1 text-primary"></i><?php echo htmlspecialchars($app['category_name'] ?? 'N/A'); ?>
                                </td>
                                <td class="text-secondary"><?php echo date('M d, Y', strtotime($app['application_date'])); ?></td>
                                <td class="text-secondary fw-semibold">
                                    <?php 
                                        $gpa_value = floatval($app['student_cgpa'] ?? $app['gpa'] ?? $app['extra_data'] ?? 0);
                                        echo $gpa_value > 0 ? number_format($gpa_value, 2) : '<span class="text-muted">-</span>';
                                    ?>
                                </td>
                                <!-- Review Comments / Error Details Column -->
                                <td class="text-secondary small">
                                    <?php if (!empty($app['pa_remarks'])): ?>
                                        <span class="text-truncate d-inline-block" style="max-width: 180px;" title="<?php echo htmlspecialchars($app['pa_remarks']); ?>">
                                            <i class="bi bi-chat-left-text me-1 text-primary"></i><?php echo htmlspecialchars($app['pa_remarks']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($app['status'] === 'pending'): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-pill">Pending PA Review</span>
                                    <?php elseif ($app['status'] === 'verified'): ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 rounded-pill">Verified</span>
                                    <?php elseif ($app['status'] === 'evaluated'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Evaluated</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="?page=history&view_app=<?php echo $app['application_id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-secondary">
                <i class="bi bi-inbox fs-1 mb-2 d-block text-muted"></i>
                <p class="mb-0 fw-medium">No applications submitted yet.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>