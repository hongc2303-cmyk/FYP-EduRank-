<?php
// 1. Include database configuration
require_once 'config.php';

// 2. Start session and verify authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (function_exists('requireLogin')) {
    requireLogin();
} elseif (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 3. CRITICAL SECURITY PATCH: Strict Admin Role Verification
$user_role = strtolower($_SESSION['user_role'] ?? $_SESSION['role'] ?? '');
if ($user_role !== 'admin') {
    // Kick out unauthorized users
    header("Location: login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// Process Add Award Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_award'])) {
    $category_name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_open = isset($_POST['is_open']) ? (int)$_POST['is_open'] : 1;

    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO award_categories (category_name, description, is_open) VALUES (?, ?, ?)");
            $stmt->execute([$category_name, $description, $is_open]);
            $success_msg = "Success! New award category '{$category_name}' has been created.";
        } catch (PDOException $e) {
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}

// Process Edit Award Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_award'])) {
    $category_id = (int)$_POST['category_id'];
    $category_name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_open = isset($_POST['is_open']) ? (int)$_POST['is_open'] : 1;

    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("UPDATE award_categories SET category_name = ?, description = ?, is_open = ? WHERE category_id = ?");
            $stmt->execute([$category_name, $description, $is_open, $category_id]);
            $success_msg = "Success! Award category '{$category_name}' has been updated.";
        } catch (PDOException $e) {
            $error_msg = "Update Error: " . $e->getMessage();
        }
    }
}

// Process Delete Award Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_award'])) {
    $category_id = (int)$_POST['delete_id'];

    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM award_categories WHERE category_id = ?");
            $stmt->execute([$category_id]);
            $success_msg = "Success! The award category has been permanently deleted.";
        } catch (PDOException $e) {
            // Prevent deletion if linked to existing applications
            $error_msg = "Cannot delete this award because it is currently linked to student applications. Please set its status to 'Closed' instead.";
        }
    }
}

// Process Toggle Status (Open/Close Applications)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $category_id = (int)$_POST['toggle_id'];
    $current_status = (int)$_POST['current_status'];
    $new_status = $current_status === 1 ? 0 : 1;

    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("UPDATE award_categories SET is_open = ? WHERE category_id = ?");
            $stmt->execute([$new_status, $category_id]);
            $action_word = $new_status === 1 ? "opened" : "closed";
            $success_msg = "Success! The award application is now {$action_word}.";
        } catch (PDOException $e) {
            $error_msg = "Status Update Error: " . $e->getMessage();
        }
    }
}

// Fetch all awards
$awards = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT * FROM award_categories ORDER BY is_open DESC, category_id ASC");
        if ($stmt) {
            $awards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error_msg = "Database query failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - Award Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">

    <div class="sidebar d-flex flex-column justify-content-between p-4" id="sidebar">
        <div>
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-mortarboard-fill text-warning fs-3"></i>
                <span class="fs-4 fw-bold text-white">EduRank</span>
            </div>
            <div class="text-uppercase text-secondary mb-3" style="font-size: 0.75rem; font-weight: 600; padding-left: 12px;">Admin Menu</div>
            <a href="admin_dashboard.php" class="nav-link"><i class="bi bi-grid-fill me-2"></i> Dashboard</a>
            <a href="manage_users.php" class="nav-link"><i class="bi bi-people-fill me-2"></i> Manage Users</a>
            <a href="manage_awards.php" class="nav-link active"><i class="bi bi-award-fill me-2"></i> Award Settings</a>
            <a href="manage_classes.php" class="nav-link"><i class="bi bi-building me-2"></i> Manage Classes</a>
            <a href="global_reports.php" class="nav-link"><i class="bi bi-bar-chart-fill me-2"></i> Global Reports</a>
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
        <header class="top-header shadow-sm d-flex justify-content-between align-items-center pe-4">
            <span class="fw-medium text-white" style="font-size: 14px;">INTELLIGENT STUDENT AWARD MANAGEMENT SYSTEM</span>
            <div class="text-white small fw-bold">
                <i class="bi bi-person-circle me-1"></i> Admin Portal
            </div>
        </header>

        <div class="p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-dark mb-0">Award Categories & Rules</h4>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="input-group shadow-sm rounded w-50">
                    <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control border-0 ps-0" placeholder="Search awards or descriptions...">
                </div>
                
                <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addAwardModal">
                    <i class="bi bi-plus-circle-fill me-1"></i> Create New Award
                </button>
            </div>

            <div class="custom-card p-0 shadow-sm bg-white overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="awardTable">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 px-4 text-secondary small fw-semibold" style="width: 50px;">NO.</th>
                                <th class="py-3 px-2 text-secondary small fw-semibold w-25">AWARD NAME</th>
                                <th class="py-3 px-2 text-secondary small fw-semibold">SYARAT / DESCRIPTION</th>
                                <th class="py-3 px-2 text-secondary small fw-semibold text-center">APPLICATION STATUS</th>
                                <th class="py-3 px-4 text-secondary small fw-semibold text-end">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($awards)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No award categories found.</td></tr>
                            <?php else: ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($awards as $award): ?>
                                    <tr>
                                        <td class="px-4 text-muted fw-medium"><?php echo $counter++; ?></td>
                                        
                                        <td class="px-2 fw-bold text-dark">
                                            <i class="bi bi-trophy-fill text-warning me-2"></i>
                                            <?php echo htmlspecialchars($award['category_name']); ?>
                                        </td>
                                        
                                        <td class="px-2 text-secondary" style="font-size: 0.85rem; max-width: 400px;">
                                            <?php echo nl2br(htmlspecialchars($award['description'])); ?>
                                        </td>
                                        
                                        <td class="px-2 text-center">
                                            <form method="POST" action="" style="display:inline-block;">
                                                <input type="hidden" name="toggle_status" value="1">
                                                <input type="hidden" name="toggle_id" value="<?php echo $award['category_id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $award['is_open'] ?? 1; ?>">
                                                
                                                <?php if(isset($award['is_open']) && $award['is_open'] == 1): ?>
                                                    <button type="submit" class="btn badge bg-success text-white border-0 py-2 px-3" title="Click to Close Applications">
                                                        <i class="bi bi-door-open-fill me-1"></i> OPEN
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn badge bg-danger text-white border-0 py-2 px-3" title="Click to Open Applications">
                                                        <i class="bi bi-door-closed-fill me-1"></i> CLOSED
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                        
                                        <td class="px-4 text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1" title="Edit Award"
                                                    onclick="openEditModal(<?php echo $award['category_id']; ?>, '<?php echo addslashes(htmlspecialchars($award['category_name'])); ?>', '<?php echo addslashes(htmlspecialchars($award['description'])); ?>', <?php echo $award['is_open'] ?? 1; ?>)">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>
                                            
                                            <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this award category?');">
                                                <input type="hidden" name="delete_award" value="1">
                                                <input type="hidden" name="delete_id" value="<?php echo $award['category_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Award">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Award Modal -->
    <div class="modal fade" id="addAwardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-primary"><i class="bi bi-plus-circle-fill me-2"></i>Create New Award</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-semibold">Award Name</label>
                            <input type="text" class="form-control" name="category_name" placeholder="e.g. Anugerah Khas Pengarah" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-semibold">Requirements / Description</label>
                            <textarea class="form-control" name="description" rows="4" placeholder="Enter the official criteria based on APCP rules..." required></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-semibold">Initial Application Status</label>
                            <select class="form-select border-primary" name="is_open" required>
                                <option value="1" class="text-success fw-bold">Open (Students can apply)</option>
                                <option value="0" class="text-danger fw-bold">Closed (Hidden from students)</option>
                            </select>
                        </div>
                        <button type="submit" name="add_award" class="btn btn-primary w-100 fw-bold">Save New Award</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Award Modal -->
    <div class="modal fade" id="editAwardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-primary"><i class="bi bi-pencil-square me-2"></i>Edit Award Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <form action="" method="POST">
                        <input type="hidden" name="category_id" id="edit_category_id">
                        
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-semibold">Award Name</label>
                            <input type="text" class="form-control" name="category_name" id="edit_category_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-semibold">Requirements / Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="4" required></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-semibold">Application Status</label>
                            <select class="form-select border-primary" name="is_open" id="edit_is_open" required>
                                <option value="1" class="text-success fw-bold">Open (Active Season)</option>
                                <option value="0" class="text-danger fw-bold">Closed (Season Ended)</option>
                            </select>
                        </div>
                        <button type="submit" name="edit_award" class="btn btn-primary w-100 fw-bold">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center p-4 mt-5 text-muted">
    <p class="mb-0 small">&copy; 2026 EduRank System - Designed By JWC</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Table filtering script
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#awardTable tbody tr');
            
            rows.forEach(row => {
                if (row.cells.length === 1) return;
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });

        // Open edit modal and populate data
        function openEditModal(id, name, desc, isOpen) {
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_category_name').value = name;
            document.getElementById('edit_description').value = desc.replace(/<br\s*[\/]?>/gi, "\n");
            document.getElementById('edit_is_open').value = isOpen;
            
            new bootstrap.Modal(document.getElementById('editAwardModal')).show();
        }
    </script>
</body>
</html>