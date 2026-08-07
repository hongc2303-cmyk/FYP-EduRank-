<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

$report_type = $_GET['report_type'] ?? 'evaluation';
$category_id = $_GET['category_id'] ?? 'all';
$should_log = $_GET['log'] ?? '1';

// Receive added date range parameters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$data = [];
$report_title = ($report_type === 'evaluation') ? "Evaluation Report" : "Application Report";
$category_name = "All Awards";

$total_records = 0;
$highest_score = 0;
$total_score_sum = 0;
$average_score = 0;

if (isset($pdo)) {
    try {
        // 1. Get category name
        if ($category_id !== 'all') {
            $cat_stmt = $pdo->prepare("SELECT category_name FROM award_categories WHERE category_id = ?");
            $cat_stmt->execute([$category_id]);
            $cat_row = $cat_stmt->fetch();
            if ($cat_row) {
                $category_name = $cat_row['category_name'];
            }
        }

        // 2. Log action to Database
        if ($should_log === '1') {
            $full_title = $report_title . ' - ' . $category_name;
            // If a date is selected, include the date in the report title as well
            if (!empty($start_date) || !empty($end_date)) {
                $date_str = " (" . (!empty($start_date) ? $start_date : 'Start') . " to " . (!empty($end_date) ? $end_date : 'Present') . ")";
                $full_title .= $date_str;
            }

            try {
                $log_stmt = $pdo->prepare("INSERT INTO report_logs (report_title, report_type, category_id) VALUES (?, ?, ?)");
                $log_stmt->execute([$full_title, $report_type, $category_id]);
            } catch (PDOException $e) {}
        }

        $params = [];
        if ($report_type === 'evaluation') {
            $sql = "
                SELECT a.application_id, u.full_name, s.matric_no, c.category_name, 
                       e.total_score, e.remarks
                FROM award_applications a
                JOIN students s ON a.student_id = s.student_id
                JOIN users u ON s.user_id = u.user_id
                JOIN award_categories c ON a.category_id = c.category_id
                JOIN evaluations e ON a.application_id = e.application_id
                WHERE a.status = 'evaluated'
            ";
        } else {
            $sql = "
                SELECT a.application_id, u.full_name, s.matric_no, c.category_name, a.status
                FROM award_applications a
                JOIN students s ON a.student_id = s.student_id
                JOIN users u ON s.user_id = u.user_id
                JOIN award_categories c ON a.category_id = c.category_id
                WHERE 1 = 1
            ";
        }

        if ($category_id !== 'all') {
            $sql .= " AND a.category_id = ?";
            $params[] = $category_id;
        }

        if (!empty($start_date)) {
            $sql .= " AND DATE(a.application_date) >= ?";
            $params[] = $start_date;
        }
        if (!empty($end_date)) {
            $sql .= " AND DATE(a.application_date) <= ?";
            $params[] = $end_date;
        }

        if ($report_type === 'evaluation') {
            $sql .= " ORDER BY e.total_score DESC";
        } else {
            $sql .= " ORDER BY c.category_name ASC, u.full_name ASC";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Calculate statistical summaries using fetched real data
        $total_records = count($data);
        if ($report_type === 'evaluation' && $total_records > 0) {
            foreach ($data as $row) {
                $score = floatval($row['total_score']);
                if ($score > $highest_score) {
                    $highest_score = $score;
                }
                $total_score_sum += $score;
            }
            $average_score = $total_score_sum / $total_records;
        }

    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $report_title; ?> - <?php echo htmlspecialchars($category_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; padding: 20px; font-family: Arial, sans-serif; }
        .report-container { background: white; max-width: 1000px; margin: 0 auto; padding: 40px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .report-header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #2c3e50; padding-bottom: 20px; }
        .report-header h2 { font-weight: bold; margin-bottom: 5px; color: #1e293b; }
        .summary-box { background-color: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: flex; justify-content: space-around; text-align: center; }
        .summary-box div span { display: block; font-size: 0.85rem; color: #64748b; text-transform: uppercase; font-weight: bold; }
        .summary-box div strong { font-size: 1.25rem; color: #0f172a; }
        .table th { background-color: #f8fafc !important; font-size: 0.85rem; text-transform: uppercase; color: #475569; }
        .table td { vertical-align: middle; }
        @media print {
            body { background-color: white; padding: 0; }
            .report-container { box-shadow: none; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="text-end mb-4 no-print">
            <button onclick="window.print()" class="btn btn-danger">
                <i class="bi bi-printer me-2"></i>Print / Save as PDF
            </button>
        </div>

        <div class="report-header">
            <h2 style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="#ffc107" viewBox="0 0 16 16">
                    <path d="M8.211 2.047a.5.5 0 0 0-.422 0l-7.5 3.5a.5.5 0 0 0 .025.917l7.5 3a.5.5 0 0 0 .372 0L14 7.14V13a1 1 0 0 0-1 1v2h3v-2a1 1 0 0 0-1-1V6.739l.686-.275a.5.5 0 0 0 .025-.917z"/>
                    <path d="M4.176 9.032a.5.5 0 0 0-.656.327l-.5 1.7a.5.5 0 0 0 .294.605l4.5 1.8a.5.5 0 0 0 .372 0l4.5-1.8a.5.5 0 0 0 .294-.605l-.5-1.7a.5.5 0 0 0-.656-.327L8 10.466z"/>
                </svg>
                EduRank AI System
            </h2>
            <h4 class="text-secondary"><?php echo htmlspecialchars($report_title); ?></h4>
            <div class="mt-3 text-muted small">
                <strong>Category Filter:</strong> <?php echo htmlspecialchars($category_name); ?> | 
                
                <?php if (!empty($start_date) || !empty($end_date)): ?>
                    <strong>Date Range:</strong> 
                    <?php echo !empty($start_date) ? date('d M Y', strtotime($start_date)) : 'Start'; ?> 
                    to 
                    <?php echo !empty($end_date) ? date('d M Y', strtotime($end_date)) : 'Present'; ?> | 
                <?php endif; ?>

                <strong>Generated On:</strong> <?php echo date('d M Y, H:i A'); ?>
            </div>
        </div>

        <div class="summary-box">
            <div><span>Total Records</span><strong><?php echo $total_records; ?></strong></div>
            <?php if ($report_type === 'evaluation'): ?>
                <div><span>Highest Score</span><strong><?php echo number_format($highest_score, 1); ?></strong></div>
                <div><span>Average Score</span><strong><?php echo number_format($average_score, 1); ?></strong></div>
            <?php endif; ?>
        </div>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th width="5%">No.</th>
                    <th width="25%">Student Name</th>
                    <th width="15%">Matric No</th>
                    <th width="20%">Award Category</th>
                    
                    <?php if ($report_type === 'evaluation'): ?>
                        <th width="10%" class="text-center">Score</th>
                        <th width="25%">Remarks</th>
                    <?php else: ?>
                        <th width="35%">Application Status</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No records found for this selection.</td></tr>
                <?php else: ?>
                    <?php $count = 1; foreach ($data as $row): ?>
                        <tr>
                            <td><?php echo $count++; ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['matric_no']); ?></td>
                            <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                            
                            <?php if ($report_type === 'evaluation'): ?>
                                <td class="text-center fw-bold"><?php echo number_format($row['total_score'], 1); ?></td>
                                <td class="small"><?php echo nl2br(htmlspecialchars($row['remarks'])); ?></td>
                            <?php else: ?>
                                <td>
                                    <?php 
                                        echo strtoupper(htmlspecialchars($row['status'])); 
                                    ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <footer class="text-center p-4 mt-5 text-muted">
    <p class="mb-0 small">&copy; 2026 EduRank AI System - Designed By JWC</p>
    </footer>
</body>
</html>