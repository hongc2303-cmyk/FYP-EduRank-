<?php
require_once 'config.php';

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

$error_msg = '';
$results = [];

$categories = [];
$sessions = [];

if (isset($pdo)) {
    try {
        $stmt_cat = $pdo->query("SELECT category_id, category_name FROM award_categories ORDER BY category_id ASC");
        $categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

        $stmt_ses = $pdo->query("SELECT DISTINCT session_name FROM award_applications WHERE session_name IS NOT NULL AND session_name != '' ORDER BY session_name DESC");
        $sessions = $stmt_ses->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($sessions)) {
            $stmt_def = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'current_session'");
            $def_ses = $stmt_def->fetchColumn();
            if ($def_ses) $sessions[] = ['session_name' => $def_ses];
        }
    } catch (PDOException $e) {
        $error_msg = "Failed to load filter data: " . $e->getMessage();
    }
}


$filter_session = $_POST['filter_session'] ?? ($sessions[0]['session_name'] ?? '');
$filter_category = $_POST['filter_category'] ?? 'all';
$filter_status = $_POST['filter_status'] ?? 'evaluated'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    if (isset($pdo)) {
        try {
            
            $query = "
                SELECT 
                    u.full_name, 
                    s.matric_no, 
                    s.programme, 
                    ac.category_name, 
                    aa.application_id,
                    aa.session_name,
                    aa.status,
                    e.total_score,
                    c.extra_data AS hpnm
                FROM award_applications aa
                JOIN students s ON aa.student_id = s.student_id
                JOIN users u ON s.user_id = u.user_id
                JOIN award_categories ac ON aa.category_id = ac.category_id
                LEFT JOIN evaluations e ON aa.application_id = e.application_id
                LEFT JOIN certificates c ON aa.application_id = c.application_id AND c.certificate_type = 'a'
                WHERE aa.session_name = :session
            ";
            
            $params = [':session' => $filter_session];

            if ($filter_category !== 'all') {
                $query .= " AND aa.category_id = :category";
                $params[':category'] = $filter_category;
            }

            if ($filter_status !== 'all') {
                $query .= " AND aa.status = :status";
                $params[':status'] = $filter_status;
            }

            $query .= " ORDER BY e.total_score DESC, u.full_name ASC";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

          
            $report_title = "Evaluation Report - " . ($filter_category === 'all' ? "All Awards" : "Category ID: $filter_category") . " ($filter_session)";
            $log_stmt = $pdo->prepare("INSERT INTO report_logs (report_title, report_type, category_id) VALUES (?, 'evaluation', ?)");
            $log_stmt->execute([$report_title, $filter_category]);

        } catch (PDOException $e) {
            $error_msg = "Report Generation Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - Global Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Base styles for print headers */
        .print-header, .print-signature { display: none; }

        @media print {
            /* 1. Force A4 Portrait and minimize page margins */
            @page {
                size: A4 portrait;
                margin: 10mm; 
            }

            /* 2. Reset backgrounds and adjust colors */
            body { background-color: #fff !important; margin: 0; padding: 0; }
            * { 
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important; 
            }

            /* 3. Hide all unnecessary web UI elements including footer */
            .no-print, .sidebar, .top-header, .filter-section, footer { 
                display: none !important; 
            }

            /* 4. Remove container margins for full width */
            .main-content { margin: 0 !important; width: 100% !important; padding: 0 !important; }
            .custom-card { border: none !important; box-shadow: none !important; margin-bottom: 0 !important; }

            /* 5. Header adjustments for print */
            .print-header { display: block !important; text-align: center; margin-bottom: 15px; }
            .print-header h3 { font-size: 16px !important; margin-bottom: 5px; } 
            .print-header h5 { font-size: 14px !important; }
            .print-header hr { border-top: 2px solid #000 !important; opacity: 1; margin: 10px 0; }

            /* 6. Core table optimization for portrait mode */
            .table { 
                border-color: #000 !important; 
                margin-bottom: 10px !important;
                table-layout: fixed !important; /* Force fixed layout to prevent overflow */
                width: 100% !important;
                font-size: 10px !important; /* Reduce font size to fit portrait */
            }

            /* 7. Compress table cells and force word wrap */
            .table th, .table td { 
                border-color: #000 !important; 
                padding: 4px !important; /* Minimal padding */
                word-wrap: break-word !important; /* Allow words to break into new lines */
                white-space: normal !important; /* Force text to wrap */
                vertical-align: middle;
            }

            .table th { background-color: #f0f0f0 !important; color: #000 !important; font-size: 11px !important; }

            /* 8. Allocate specific width percentages for each column */
            .table th:nth-child(1), .table td:nth-child(1) { width: 5%; }  /* NO. */
            .table th:nth-child(2), .table td:nth-child(2) { width: 22%; } /* FULL NAME */
            .table th:nth-child(3), .table td:nth-child(3) { width: 15%; } /* MATRIC NO. */
            .table th:nth-child(4), .table td:nth-child(4) { width: 8%; }  /* PROG. */
            .table th:nth-child(5), .table td:nth-child(5) { width: 8%; }  /* HPNM */
            .table th:nth-child(6), .table td:nth-child(6) { width: 24%; } /* AWARD CATEGORY */
            .table th:nth-child(7), .table td:nth-child(7) { width: 10%; } /* STATUS */
            .table th:nth-child(8), .table td:nth-child(8) { 
                width: 8%; 
                white-space: nowrap !important; /* Force single line for score */
                font-size: 11px !important; /* Slightly smaller to fit safely */
            }  /* SCORE */
            
            /* The 9th column (EVIDENCE) is hidden via .no-print class */

            /* 9. Prevent rows from splitting across pages */
            tr { page-break-inside: avoid; }
            
            /* 10. Signature block styling */
            .print-signature { display: block !important; margin-top: 20px !important; page-break-inside: avoid; font-size: 12px; }
        }
    </style>
</head>
<body class="dashboard-body">

    <div class="sidebar d-flex flex-column justify-content-between p-4 no-print" id="sidebar">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-mortarboard-fill text-warning fs-3"></i>
                <span class="fs-4 fw-bold text-white">EduRank</span>
            </div>
            <div class="text-uppercase text-secondary mb-3" style="font-size: 0.75rem; font-weight: 600; padding-left: 12px;">Admin Menu</div>
            <a href="admin_dashboard.php" class="nav-link"><i class="bi bi-grid-fill me-2"></i> Dashboard</a>
            <a href="manage_users.php" class="nav-link"><i class="bi bi-people-fill me-2"></i> Manage Users</a>
            <a href="manage_awards.php" class="nav-link"><i class="bi bi-award-fill me-2"></i> Award Settings</a>
            <a href="manage_classes.php" class="nav-link"><i class="bi bi-building me-2"></i> Manage Classes</a>
            <a href="global_reports.php" class="nav-link active"><i class="bi bi-bar-chart-fill me-2"></i> Global Reports</a>
            <a href="audit_logs.php" class="nav-link"><i class="bi bi-journal-text me-2"></i> Audit Logs</a>
            <a href="system_setting.php" class="nav-link"><i class="bi bi-gear-fill me-2"></i> System Settings</a>
        </div>
        <div class="border-top border-secondary pt-3 mt-auto">
            <form method="POST" action="logout.php">
                <button type="submit" class="btn text-danger p-0 border-0 text-sm fw-semibold d-flex align-items-center gap-2 ms-1 bg-transparent">
                    <i class="bi bi-box-arrow-right fs-5"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <header class="top-header shadow-sm d-flex justify-content-between align-items-center pe-4 no-print">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <div class="text-white small fw-bold">
                <i class="bi bi-person-circle me-1"></i> Admin Portal
            </div>
        </header>

        <div class="p-4">
            
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger filter-section"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_msg; ?></div>
            <?php endif; ?>

           
            <div class="print-header">
                <h3 class="fw-bold text-uppercase">SENARAI PENERIMA ANUGERAH PELAJAR CEMERLANG POLITEKNIK</h3>
                <h5 class="text-secondary"><?php echo htmlspecialchars($filter_session); ?></h5>
                <hr style="border: 2px solid #000; opacity: 1;">
            </div>

            
            <div class="custom-card p-4 shadow-sm bg-white mb-4 filter-section">
                <h5 class="fw-bold text-primary mb-3"><i class="bi bi-funnel-fill me-2"></i>Report Generator Filters</h5>
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-secondary">Academic Session</label>
                            <select class="form-select border-primary" name="filter_session" required>
                                <?php foreach($sessions as $ses): ?>
                                    <option value="<?php echo htmlspecialchars($ses['session_name']); ?>" <?php if($filter_session === $ses['session_name']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($ses['session_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Award Category</label>
                            <select class="form-select" name="filter_category">
                                <option value="all" <?php if($filter_category === 'all') echo 'selected'; ?>>-- All Awards --</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>" <?php if($filter_category == $cat['category_id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-secondary">Application Status</label>
                            <select class="form-select" name="filter_status">
                                <option value="all" <?php if($filter_status === 'all') echo 'selected'; ?>>All Status</option>
                                <option value="evaluated" <?php if($filter_status === 'evaluated') echo 'selected'; ?>>Evaluated (Scored)</option>
                                <option value="verified" <?php if($filter_status === 'verified') echo 'selected'; ?>>Verified by P.A.</option>
                                <option value="pending" <?php if($filter_status === 'pending') echo 'selected'; ?>>Pending</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" name="generate_report" class="btn btn-primary w-100 fw-bold">
                                <i class="bi bi-file-earmark-bar-graph me-1"></i> Generate
                            </button>
                        </div>
                    </div>
                </form>
            </div>

          
            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])): ?>
                <div class="d-flex justify-content-between align-items-center mb-3 filter-section">
                    <h5 class="fw-bold text-dark mb-0">Report Results <span class="badge bg-secondary ms-2"><?php echo count($results); ?> Record(s)</span></h5>
                    <div>
                       
                        <button class="btn btn-success fw-bold shadow-sm me-2" onclick="exportTableToCSV('EduRank_Report_<?php echo date('Ymd'); ?>.csv')">
                            <i class="bi bi-file-earmark-excel-fill me-1"></i> Export Excel
                        </button>
                        
                        <button class="btn btn-dark fw-bold shadow-sm btn-print" onclick="window.print()">
                            <i class="bi bi-printer-fill me-1"></i> Print / PDF
                        </button>
                    </div>
                </div>

                <div class="custom-card p-0 shadow-sm bg-white overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle mb-0" id="reportTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-3 px-3 text-secondary small fw-semibold text-center" style="width: 50px;">NO.</th>
                                    <th class="py-3 px-3 text-secondary small fw-semibold">FULL NAME</th>
                                    <th class="py-3 px-3 text-secondary small fw-semibold text-center">MATRIC NO.</th>
                                    <th class="py-3 px-3 text-secondary small fw-semibold text-center">PROG.</th>
                                    <th class="py-3 px-3 text-secondary small fw-semibold text-center">HPNM</th>
                                    <th class="py-3 px-3 text-secondary small fw-semibold">AWARD CATEGORY</th>
                                    <th class="py-3 px-3 text-secondary small fw-semibold text-center">STATUS</th>
                                    <th class="py-3 px-3 text-secondary small fw-semibold text-center">SCORE</th>
                                    <th class="py-3 px-3 text-secondary small fw-semibold text-center no-print no-export">EVIDENCE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($results)): ?>
                                    <tr><td colspan="9" class="text-center py-5 text-muted">No records found for the selected filters.</td></tr>
                                <?php else: ?>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($results as $row): ?>
                                        <tr>
                                            <td class="px-3 text-center text-muted"><?php echo $counter++; ?></td>
                                            <td class="px-3 fw-bold text-dark"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                            <td class="px-3 text-center text-primary fw-semibold"><?php echo htmlspecialchars($row['matric_no']); ?></td>
                                            <td class="px-3 text-center text-secondary"><?php echo htmlspecialchars($row['programme']); ?></td>
                                            
                                        
                                            <td class="px-3 text-center fw-bold text-dark">
                                                <?php echo !empty($row['hpnm']) ? number_format((float)$row['hpnm'], 2) : '-'; ?>
                                            </td>

                                            <td class="px-3 text-muted small fw-medium"><?php echo htmlspecialchars($row['category_name']); ?></td>
                                            <td class="px-3 text-center">
                                                <?php 
                                                    $status = $row['status'];
                                                    if ($status === 'evaluated') echo '<span class="badge bg-success">Evaluated</span>';
                                                    elseif ($status === 'verified') echo '<span class="badge bg-info text-dark">Verified</span>';
                                                    elseif ($status === 'rejected') echo '<span class="badge bg-danger">Rejected</span>';
                                                    else echo '<span class="badge bg-secondary">Pending</span>';
                                                ?>
                                            </td>
                                            
                                            <!-- REMOVED 'fs-5' FROM SCORE TD HERE -->
                                            <td class="px-3 text-center fw-bold <?php echo (isset($row['total_score']) && $row['total_score'] >= 85) ? 'text-success' : 'text-dark'; ?>">
                                                <?php echo isset($row['total_score']) ? htmlspecialchars($row['total_score']) : '-'; ?>
                                            </td>
                                            
                                          
                                            <td class="px-3 text-center no-print no-export">
                                                <a href="view_application.php?id=<?php echo $row['application_id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="View Student Certificates">
                                                    <i class="bi bi-folder2-open"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

             
                <div class="print-signature mt-5">
                    <div class="row">
                        <div class="col-6">
                            <p class="mb-5">Disediakan oleh Urusetia JIP:</p>
                            <p>______________________________________<br>
                            Nama:<br>
                            Tarikh:</p>
                        </div>
                        
                        <div class="col-6">
                            <p class="mb-5">Disahkan oleh Pengerusi JIP:</p>
                            <p>______________________________________<br>
                            Tandatangan & Cop Rasmi<br>
                            Tarikh:</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Added 'no-print' class here -->
    <footer class="text-center p-4 mt-5 text-muted no-print">
    <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
     
        function exportTableToCSV(filename) {
            var csv = [];
            var rows = document.querySelectorAll("#reportTable tr");
            
            for (var i = 0; i < rows.length; i++) {
                var row = [];
                
                var cols = rows[i].querySelectorAll("td:not(.no-export), th:not(.no-export)");
                
                for (var j = 0; j < cols.length; j++) {
                    
                    var data = cols[j].innerText.replace(/"/g, '""');
                    row.push('"' + data + '"');
                }
                csv.push(row.join(","));
            }

            downloadCSV(csv.join("\n"), filename);
        }

        function downloadCSV(csv, filename) {
            var csvFile = new Blob(["\uFEFF" + csv], {type: "text/csv;charset=utf-8;"}); 
            var downloadLink = document.createElement("a");
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
    </script>
</body>
</html>