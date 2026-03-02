<?php
session_start();

// 1. Authorization
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();
require_once '../includes/Logger.php';

$error = '';
$success = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';

// 2. Handle Form Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $target_student_id = $_POST['student_id'];
    $amount = $_POST['amount'];
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
    $fee_type = $_POST['fee_type'];
    $remarks = trim($_POST['remarks']);
    $recorded_by = $_SESSION['user_id'];

    try {
        if (empty($target_student_id) || empty($amount) || empty($payment_date)) {
            throw new Exception("All required fields must be filled.");
        }

        $stmt = $db->prepare("
            INSERT INTO fees (student_id, amount, payment_date, payment_method, fee_type, remarks, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$target_student_id, $amount, $payment_date, $payment_method, $fee_type, $remarks, $recorded_by]);

        // Log the action
        $st_stmt = $db->prepare("SELECT full_name FROM students WHERE id = ?");
        $st_stmt->execute([$target_student_id]);
        $student_name = $st_stmt->fetchColumn();

        Logger::log($_SESSION['user_id'], 'Fee Payment', "Recorded payment of $amount for $student_name ($fee_type)");

        $_SESSION['success'] = "Payment recorded successfully!";
        header("Location: fees.php?student_id=" . $target_student_id);
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
        $action = 'add'; // Stay on form if error
    }
}

// 3. Search & Filtering (for List View)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(s.full_name LIKE :search OR s.pin LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($student_id) && $action === 'list') {
    $where_clauses[] = "f.student_id = :student_id";
    $params[':student_id'] = $student_id;
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// 4. Fetch Data
if ($action === 'list') {
    $query = "
        SELECT f.*, s.full_name, s.pin, u.username as recorder
        FROM fees f
        JOIN students s ON f.student_id = s.id
        LEFT JOIN users u ON f.recorded_by = u.id
        $where_sql
        ORDER BY f.payment_date DESC, f.created_at DESC
    ";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get student name if filtering by student
    $filter_student_name = '';
    if ($student_id) {
        $st_stmt = $db->prepare("SELECT full_name FROM students WHERE id = ?");
        $st_stmt->execute([$student_id]);
        $filter_student_name = $st_stmt->fetchColumn();
    }
} else {
    // For Add Form: Fetch Students for dropdown
    $students = $db->query("SELECT id, full_name, pin FROM students ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Management - Student Management System</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .form-container { max-width: 600px; margin: 20px auto; }
        .badge { background: #e1f5fe; color: #0288d1; padding: 2px 8px; border-radius: 12px; font-size: 0.85em; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        
        <?php if ($action === 'list'): ?>
            <div class="page-header">
                <h1>Fee Management</h1>
                <?php if ($filter_student_name): ?>
                    <p>Viewing records for: <strong><?php echo htmlspecialchars($filter_student_name); ?></strong></p>
                <?php endif; ?>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="message success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <div class="action-bar">
                <div class="search-box">
                    <form method="GET" style="display: flex; gap: 10px;">
                        <input type="text" name="search" placeholder="Search by student name or PIN..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn">Search</button>
                        <?php if (!empty($search) || !empty($student_id)): ?>
                            <a href="fees.php" class="btn">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="action-buttons">
                    <a href="fees.php?action=add<?php echo $student_id ? '&student_id=' . $student_id : ''; ?>" class="btn btn-primary">Record New Payment</a>
                    <a href="dashboard.php" class="btn">Back to Dashboard</a>
                </div>
            </div>

            <div class="table-container card">
                <?php if (count($fees) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Recorded By</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fees as $fee): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($fee['payment_date'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($fee['full_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($fee['pin']); ?></small>
                                    </td>
                                    <td><span class="badge"><?php echo htmlspecialchars($fee['fee_type']); ?></span></td>
                                    <td style="font-weight: bold;">$<?php echo number_format($fee['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($fee['payment_method']); ?></td>
                                    <td><small><?php echo htmlspecialchars($fee['recorder'] ?: 'System'); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($fee['remarks']); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No fee records found. <a href="fees.php?action=add">Record your first payment</a>.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'add'): ?>
            <div class="page-header">
                <h1>Record Fee Payment</h1>
                <a href="fees.php" class="btn">Back to List</a>
            </div>

            <div class="form-container card">
                <?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="record_payment" value="1">
                    <div class="form-group">
                        <label>Select Student *</label>
                        <select name="student_id" required>
                            <option value="">-- Select Student --</option>
                            <?php foreach ($students as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $student_id == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['full_name'] . ' (' . $s['pin'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Amount (USD) *</label>
                            <input type="number" step="0.01" name="amount" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>Payment Date *</label>
                            <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Payment Method</label>
                            <select name="payment_method">
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Fee Type</label>
                            <select name="fee_type">
                                <option value="Tuition">Tuition</option>
                                <option value="Uniform">Uniform</option>
                                <option value="Books">Books</option>
                                <option value="Boarding">Boarding</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" rows="3" placeholder="Additional details..."></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Record Payment</button>
                        <a href="fees.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
