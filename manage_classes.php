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

$success_msg = '';$error_msg = '';

// --- Process Add Class ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {$class_name = strtoupper(trim($_POST['class_name'] ?? ''));$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    if (isset($pdo) && !empty($class_name)) {
        try {
            // Check if class already exists
            $check =$pdo->prepare("SELECT COUNT(*) FROM classes WHERE class_name = ?");
            $check->execute([$class_name]);
            if ($check->fetchColumn() > 0) {
                $error_msg = "Class '{$class_name}' already exists in the system.";
            } else {
                $stmt =$pdo->prepare("INSERT INTO classes (class_name, is_active) VALUES (?, ?)");
                $stmt->execute([$class_name, $is_active]);$success_msg = "Success! New class '{$class_name}' has been created.";
            }
        } catch (PDOException $e) {$error_msg = "Database Error: " . $e->getMessage();
        }
    }
}

// --- Process Edit Class Name ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_class'])) {
    $class_id = (int)$_POST['class_id'];
    $class_name = strtoupper(trim($_POST['class_name'] ?? ''));

    if (isset($pdo) && !empty($class_name)) {
        try {
            $stmt =$pdo->prepare("UPDATE classes SET class_name = ? WHERE class_id = ?");
            $stmt->execute([$class_name, $class_id]);$success_msg = "Success! Class name updated to '{$class_name}'.";
        } catch (PDOException $e) {$error_msg = "Update Error: " . $e->getMessage();
        }
    }
}

// --- Process Toggle Status (Active/Inactive for single row) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $class_id = (int)$_POST['toggle_id'];
    $current_status = (int)$_POST['current_status'];
    $new_status =$current_status === 1 ? 0 : 1;

    if (isset($pdo)) {
        try {
            $stmt =$pdo->prepare("UPDATE classes SET is_active = ? WHERE class_id = ?");
            $stmt->execute([$new_status,$class_id]);
            $action_word =$new_status === 1 ? "activated" : "deactivated (hidden from students)";
            $success_msg = "Success! The class is now {$action_word}.";
        } catch (PDOException $e) {$error_msg = "Status Update Error: " . $e->getMessage();
        }
    }
}

// --- Process Delete Class (Single row) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_class'])) {
    $class_id = (int)$_POST['delete_id'];

    if (isset($pdo)) {
        try {
            $stmt =$pdo->prepare("DELETE FROM classes WHERE class_id = ?");
            $stmt->execute([$class_id]);$success_msg = "Success! The class has been permanently deleted.";
        } catch (PDOException $e) {
            // Prevent deletion if linked to existing students
            $error_msg = "Cannot delete this class because it is linked to student records. Please set its status to 'Inactive' instead.";
        }
    }
}

// --- Process Bulk Actions (Activate, Deactivate, Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action =$_POST['bulk_action']; 
    $selected_ids =$_POST['selected_ids'] ?? [];
    
    if (!empty($selected_ids)) {
        if (isset($pdo)) {
            try {
                $pdo->beginTransaction();
                $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                
                if ($action === 'delete') {
                    $stmt =$pdo->prepare("DELETE FROM classes WHERE class_id IN ($placeholders)");
                    $stmt->execute($selected_ids);
                    $count =$stmt->rowCount();
                    $success_msg = "Success! {$count} class(es) have been permanently deleted.";
                } 
                elseif ($action === 'activate') {
                    $stmt =$pdo->prepare("UPDATE classes SET is_active = 1 WHERE class_id IN ($placeholders)");
                    $stmt->execute($selected_ids);
                    $count =$stmt->rowCount();
                    $success_msg = "Success! {$count} class(es) have been successfully ACTIVATED.";
                }
                elseif ($action === 'deactivate') {
                    $stmt =$pdo->prepare("UPDATE classes SET is_active = 0 WHERE class_id IN ($placeholders)");
                    $stmt->execute($selected_ids);
                    $count =$stmt->rowCount();
                    $success_msg = "Success! {$count} class(es) have been successfully DEACTIVATED (Hidden).";
                }
                
                $pdo->commit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();$error_msg = "Bulk Action Error: Cannot delete class(es) that are linked to student records. Please set them to INACTIVE instead.";
            }
        }
    } else {
        $error_msg = "Please select at least one class.";
    }
}

// --- Fetch all classes ---
$classes = [];
if (isset($pdo)) {
    try {
        $stmt =$pdo->query("SELECT * FROM classes ORDER BY is_active DESC, class_name ASC");
        if ($stmt) {
            $classes =$stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {$error_msg = "Database query failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduRank - Manage Classes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .class-icon-wrapper {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }
    </style>
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
            <a href="manage_awards.php" class="nav-link"><i class="bi bi-award-fill me-2"></i> Award Settings</a>
            <a href="manage_classes.php" class="nav-link active"><i class="bi bi-building me-2"></i> Manage Classes</a>
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
                <div>
                    <h4 class="fw-bold text-dark mb-1">Manage Classes & Programmes</h4>
                    <p class="text-secondary small mb-0">Add new classes for the current session or hide old classes from the registration dropdown.</p>
                </div>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <!-- Toolbar: Search and Bulk Action Buttons -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <div class="input-group shadow-sm rounded bg-white" style="max-width: 350px;">
                    <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control border-0 ps-0" placeholder="Search class name...">
                </div>
                
                <div class="d-flex align-items-center gap-2">
                    <!-- Bulk Actions Group (Hidden by default) -->
                    <div id="bulkActionGroup" style="display:none;" class="p-1 bg-white rounded shadow-sm border">
                        <button type="button" class="btn btn-sm btn-success fw-bold mx-1" onclick="submitBulkAction('activate')" title="Set as Active">
                            <i class="bi bi-eye-fill me-1"></i> Active
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary fw-bold text-white mx-1" onclick="submitBulkAction('deactivate')" title="Set as Inactive (Hidden)">
                            <i class="bi bi-eye-slash-fill me-1"></i> Inactive
                        </button>
                        <button type="button" class="btn btn-sm btn-danger fw-bold mx-1" onclick="submitBulkAction('delete')" title="Permanently Delete">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </div>

                    <button class="btn btn-primary fw-bold shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addClassModal">
                        <i class="bi bi-plus-circle-fill me-2"></i> Add New Class
                    </button>
                </div>
            </div>

            <!-- Form wrapping the table for bulk submission -->
            <form id="bulkActionForm" method="POST" action="">
                <input type="hidden" name="bulk_action" id="bulkActionType" value="">

                <!-- 移除了 max-width 限制，让表格卡片充满整个容器 -->
                <div class="custom-card p-0 shadow-sm bg-white overflow-hidden border-0 rounded-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="classTable">
                            <thead class="table-light">
                                <tr>
                                    <!-- Select All Checkbox -->
                                    <th class="py-3 px-4 border-0" style="width: 5%;">
                                        <input class="form-check-input border-secondary shadow-sm" type="checkbox" id="selectAll">
                                    </th>
                                    <th class="py-3 px-2 text-secondary small fw-semibold border-0 text-center" style="width: 5%;">NO.</th>
                                    <th class="py-3 px-2 text-secondary small fw-semibold border-0" style="width: 40%;">CLASS NAME</th>
                                    <th class="py-3 px-2 text-secondary small fw-semibold border-0 text-center" style="width: 25%;">DROPDOWN STATUS</th>
                                    <th class="py-3 px-4 text-secondary small fw-semibold border-0 text-end" style="width: 25%;">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($classes)): ?>
                                    <tr><td colspan="5" class="text-center py-5 text-muted">No classes found.</td></tr>
                                <?php else: ?>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($classes as$cls): ?>
                                        <tr class="border-bottom">
                                            <!-- Individual Checkbox -->
                                            <td class="px-4">
                                                <input class="form-check-input border-secondary shadow-sm class-checkbox" type="checkbox" name="selected_ids[]" value="<?php echo $cls['class_id']; ?>">
                                            </td>
                                            
                                            <td class="px-2 text-muted fw-medium text-center class-index"><?php echo $counter++; ?></td>
                                            
                                            <td class="px-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="class-icon-wrapper bg-primary-subtle text-primary me-3 shadow-sm">
                                                        <i class="bi bi-building fw-bold"></i>
                                                    </div>
                                                    <span class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($cls['class_name']); ?></span>
                                                </div>
                                            </td>
                                            
                                            <td class="px-2 text-center">
                                                <?php if($cls['is_active'] == 1): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill shadow-sm">
                                                        <i class="bi bi-eye-fill me-1"></i> ACTIVE
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill shadow-sm">
                                                        <i class="bi bi-eye-slash-fill me-1"></i> INACTIVE
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="px-4">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold px-3 shadow-sm" title="Edit Class Name"
                                                            onclick="openEditModal(<?php echo $cls['class_id']; ?>, '<?php echo addslashes(htmlspecialchars($cls['class_name'])); ?>')">
                                                        <i class="bi bi-pencil-square me-1"></i> Edit
                                                    </button>
                                                    
                                                    <button type="button" class="btn btn-sm btn-outline-danger fw-semibold px-3 shadow-sm" title="Delete Class" onclick="deleteSingleClass(<?php echo $cls['class_id']; ?>)">
                                                        <i class="bi bi-trash-fill me-1"></i> Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>

        </div>
    </div>

    <!-- Hidden form for Single Delete to keep the logic clean -->
    <form id="singleDeleteForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="delete_class" value="1">
        <input type="hidden" name="delete_id" id="singleDeleteId" value="">
    </form>

    <!-- Create Class Modal -->
    <div class="modal fade" id="addClassModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom-0 pb-0 bg-light rounded-top-4 p-4">
                    <h5 class="modal-title fw-bold text-primary"><i class="bi bi-plus-circle-fill me-2"></i>Add New Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <form action="" method="POST" class="bg-white p-4 rounded-4 shadow-sm border">
                        <div class="mb-3">
                            <label class="form-label text-dark fw-bold" style="font-size: 13px;">Class Name</label>
                            <input type="text" class="form-control border-primary" name="class_name" placeholder="e.g. DIT5A" style="text-transform: uppercase;" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-dark fw-bold" style="font-size: 13px;">Visibility Status</label>
                            <select class="form-select border-primary" name="is_active" required>
                                <option value="1" class="text-success fw-bold">Active (Visible in dropdown)</option>
                                <option value="0" class="text-secondary fw-bold">Inactive (Hidden)</option>
                            </select>
                        </div>
                        <button type="submit" name="add_class" class="btn btn-primary w-100 fw-bold py-2 shadow-sm rounded-3">Save New Class</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Class Modal -->
    <div class="modal fade" id="editClassModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom-0 pb-0 bg-light rounded-top-4 p-4">
                    <h5 class="modal-title fw-bold text-primary"><i class="bi bi-pencil-square me-2"></i>Edit Class Name</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <form action="" method="POST" class="bg-white p-4 rounded-4 shadow-sm border">
                        <input type="hidden" name="class_id" id="edit_class_id">
                        
                        <div class="mb-4">
                            <label class="form-label text-dark fw-bold" style="font-size: 13px;">Class Name</label>
                            <input type="text" class="form-control border-primary" name="class_name" id="edit_class_name" style="text-transform: uppercase;" required>
                        </div>
                        <button type="submit" name="edit_class" class="btn btn-primary w-100 fw-bold py-2 shadow-sm rounded-3">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center p-4 mt-5 text-muted">
        <p class="mb-0 small">&copy; <?php echo date('Y'); ?> EduRank System - Designed By JWC</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 1. Table filtering script
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#classTable tbody tr');
            
            let visibleCounter = 1;
            rows.forEach(row => {
                if (row.cells.length === 1) return; // Skip "No records found" row
                let text = row.innerText.toLowerCase();
                if (text.includes(filter)) {
                    row.style.display = '';
                    let indexCell = row.querySelector('.class-index');
                    if(indexCell) indexCell.innerText = visibleCounter++;
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // 2. Open edit modal and populate data
        function openEditModal(id, name) {
            document.getElementById('edit_class_id').value = id;
            document.getElementById('edit_class_name').value = name;
            new bootstrap.Modal(document.getElementById('editClassModal')).show();
        }

        // 3. Single Delete Function
        function deleteSingleClass(id) {
            if(confirm('Are you sure you want to delete this class?')) {
                document.getElementById('singleDeleteId').value = id;
                document.getElementById('singleDeleteForm').submit();
            }
        }

        // 4. Bulk Action Logic (Select All & Buttons)
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.class-checkbox');
        const bulkActionGroup = document.getElementById('bulkActionGroup');

        function toggleBulkActions() {
            const checkedCount = document.querySelectorAll('.class-checkbox:checked').length;
            bulkActionGroup.style.display = checkedCount > 0 ? 'inline-flex' : 'none';
        }

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => {
                    // Only check visible rows (in case search is active)
                    if (cb.closest('tr').style.display !== 'none') {
                        cb.checked = this.checked;
                    }
                });
                toggleBulkActions();
            });
        }

        checkboxes.forEach(cb => cb.addEventListener('change', toggleBulkActions));

        function submitBulkAction(actionType) {
            const count = document.querySelectorAll('.class-checkbox:checked').length;
            let confirmMsg = "";
            
            if (actionType === 'delete') {
                confirmMsg = `⚠️ Warning!\n\nAre you sure you want to PERMANENTLY DELETE these ${count} class(es)?`;
            } else if (actionType === 'deactivate') {
                confirmMsg = `Hide these ${count} class(es) from the student registration dropdown?`;
            } else if (actionType === 'activate') {
                confirmMsg = `Make these ${count} class(es) ACTIVE again?`;
            }

            if (confirm(confirmMsg)) {
                document.getElementById('bulkActionType').value = actionType;
                document.getElementById('bulkActionForm').submit();
            }
        }
    </script>
</body>
</html>