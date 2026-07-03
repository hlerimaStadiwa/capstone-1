<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/init.php';
$message = '';
$error = '';

function calculateGradeLabel($score) {
    if ($score >= 80) return 'A';
    if ($score >= 66) return 'B';
    if ($score >= 50) return 'C';
    if ($score >= 45) return 'D';
    return 'U';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    try {
        $db->beginTransaction();

        $select = $db->prepare("SELECT * FROM grade_update_requests WHERE id = ? AND status = 'Pending'");
        $select->execute([$request_id]);
        $request = $select->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            throw new Exception('Request not found or is already resolved.');
        }

        if ($action === 'approve') {
            $grade_label = calculateGradeLabel((float)$request['new_score']);

            $grade_check = $db->prepare("SELECT id FROM grades WHERE student_id = ? AND subject_name = ? AND term = ? LIMIT 1");
            $grade_check->execute([$request['student_id'], $request['subject_name'], $request['term']]);
            $existing_grade = $grade_check->fetch(PDO::FETCH_ASSOC);

            if ($existing_grade) {
                $update_grade = $db->prepare("UPDATE grades SET score = ?, grade = ?, comments = ? WHERE id = ?");
                $update_grade->execute([(float)$request['new_score'], $grade_label, $request['new_comments'], $existing_grade['id']]);
            } else {
                $insert_grade = $db->prepare("INSERT INTO grades (student_id, subject_name, score, grade, term, comments) VALUES (?, ?, ?, ?, ?, ?)");
                $insert_grade->execute([$request['student_id'], $request['subject_name'], (float)$request['new_score'], $grade_label, $request['term'], $request['new_comments']]);
            }

            $update_request = $db->prepare("UPDATE grade_update_requests SET status = 'Approved', resolved_at = CURRENT_TIMESTAMP WHERE id = ?");
            $update_request->execute([$request_id]);
            $message = '<div class="message success">Request approved and grade updated successfully.</div>';
        } elseif ($action === 'reject') {
            $update_request = $db->prepare("UPDATE grade_update_requests SET status = 'Rejected', resolved_at = CURRENT_TIMESTAMP WHERE id = ?");
            $update_request->execute([$request_id]);
            $message = '<div class="message success">Request rejected successfully.</div>';
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $error = '<div class="message error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Fetch all grade requests
$requests_stmt = $db->query("SELECT r.*, s.full_name AS student_name, t.full_name AS teacher_name
    FROM grade_update_requests r
    JOIN students s ON r.student_id = s.id
    JOIN teachers t ON r.teacher_id = t.id
    ORDER BY r.created_at DESC");
$requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Pending count for summary
$pending_count = $db->query("SELECT COUNT(*) FROM grade_update_requests WHERE status = 'Pending'")->fetchColumn();
$approved_count = $db->query("SELECT COUNT(*) FROM grade_update_requests WHERE status = 'Approved'")->fetchColumn();
$rejected_count = $db->query("SELECT COUNT(*) FROM grade_update_requests WHERE status = 'Rejected'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Update Requests - Admin</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .status-pill { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 0.8em; color: white; }
        .status-pending { background: #f39c12; }
        .status-approved { background: #27ae60; }
        .status-rejected { background: #c0392b; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .request-note { max-width: 800px; margin: 0 auto 20px; }
        .data-block { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .request-action-form { display: inline-flex; gap: 8px; flex-wrap: wrap; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <div class="page-header">
            <h1>Grade Update Requests</h1>
            <p>Review and resolve teacher grade change requests.</p>
        </div>

        <?php echo $message . $error; ?>

        <div class="summary-grid">
            <div class="data-block">
                <h3>Pending Requests</h3>
                <p class="stat-number"><?php echo $pending_count; ?></p>
            </div>
            <div class="data-block">
                <h3>Approved</h3>
                <p class="stat-number"><?php echo $approved_count; ?></p>
            </div>
            <div class="data-block">
                <h3>Rejected</h3>
                <p class="stat-number"><?php echo $rejected_count; ?></p>
            </div>
        </div>

        <div class="data-block">
            <table class="table">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Teacher</th>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Term</th>
                        <th>Proposed Score</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th>Resolved</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($requests) > 0): ?>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['id']); ?></td>
                                <td><?php echo htmlspecialchars($request['teacher_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['term']); ?></td>
                                <td><?php echo number_format($request['new_score'], 1); ?>%</td>
                                <td style="max-width: 220px;"><?php echo nl2br(htmlspecialchars($request['reason'])); ?></td>
                                <td>
                                    <?php $status = $request['status']; ?>
                                    <span class="status-pill status-<?php echo strtolower($status); ?>"><?php echo htmlspecialchars($status); ?></span>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?></td>
                                <td><?php echo $request['resolved_at'] ? date('M d, Y H:i', strtotime($request['resolved_at'])) : '-'; ?></td>
                                <td>
                                    <?php if ($request['status'] === 'Pending'): ?>
                                        <form method="POST" class="request-action-form">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-small btn-primary">Approve</button>
                                            <button type="submit" name="action" value="reject" class="btn btn-small">Reject</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #7f8c8d; font-size: 0.9em;">No actions</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="11" style="text-align:center; padding: 30px;">No grade update requests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
