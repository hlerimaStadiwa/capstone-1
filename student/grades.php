<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Get student data
$database = new Database();
$db = $database->getConnection();

$query = "SELECT s.* FROM students s 
          JOIN users u ON s.user_id = u.id 
          WHERE u.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "Profile not found.";
    exit();
}

// Calculate Total Paid Fees
$fee_stmt = $db->prepare("SELECT SUM(amount) FROM fees WHERE student_id = ?");
$fee_stmt->execute([$student['id']]);
$total_paid = $fee_stmt->fetchColumn() ?: 0;
$school_fee = 150.00;
$min_fee_for_reports = $school_fee / 2;
$has_access = ($total_paid >= $min_fee_for_reports);

// Fetch Published Grades
$grades = [];
if ($has_access) {
    $grades_stmt = $db->prepare("
        SELECT * FROM grades 
        WHERE student_id = ? AND is_published = TRUE 
        ORDER BY term DESC, subject_name ASC
    ");
    $grades_stmt->execute([$student['id']]);
    $all_grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by term
    foreach ($all_grades as $g) {
        $grades[$g['term']][] = $g;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades - Student Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .grades-layout { max-width: 1000px; margin: 0 auto; }
        .term-section { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; overflow: hidden; }
        .term-header { background: #2c3e50; color: white; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; }
        .term-header h2 { margin: 0; font-size: 1.2rem; }
        .grade-pill { padding: 4px 12px; border-radius: 20px; font-weight: bold; font-size: 0.9rem; }
        .grade-A { background: #e8f5e9; color: #2e7d32; }
        .grade-B { background: #e3f2fd; color: #1565c0; }
        .grade-C { background: #fffde7; color: #f9a825; }
        .grade-D { background: #fff3e0; color: #e65100; }
        .grade-F { background: #ffebee; color: #c62828; }
        .denied-card { background: white; padding: 60px 40px; border-radius: 12px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .denied-icon { font-size: 5rem; margin-bottom: 25px; filter: grayscale(1); }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">Danborough</div>
            <div class="user-info" style="padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1);">
                <div class="avatar-circle" style="width: 60px; height: 60px; background: #3498db; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; margin: 0 auto 10px; color: white;">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
                <p>Welcome,<br><strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" >Dashboard</a></li>
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="grades.php" class="active">My Grades</a></li>
                <li><a href="attendance.php">My Attendance</a></li>
                <li><a href="assignments.php">My Assignments</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1>Academic Reports</h1>
                <p>View and download your official results</p>
            </div>

            <div class="grades-layout">
                <?php if (!$has_access): ?>
                    <div class="denied-card">
                        <h2 style="color: #d32f2f; margin-bottom: 15px;">Access Restricted</h2>
                        <p style="color: #7f8c8d; font-size: 1.1rem; margin-bottom: 30px;">
                            Your academic reports are currently locked due to outstanding fees.<br>
                            Minimum <strong>50% ($75.00)</strong> payment is required for access.
                        </p>
                        <div style="background: #f8f9fa; padding: 25px; border-radius: 8px; display: inline-block; border: 1px solid #eee;">
                            <div style="font-size: 0.9rem; color: #7f8c8d; margin-bottom: 5px;">Current Payment Status</div>
                            <div style="font-size: 2rem; font-weight: bold; color: #2c3e50;">$<?php echo number_format($total_paid, 2); ?></div>
                        </div>
                    </div>
                <?php elseif (empty($grades)): ?>
                    <div class="card" style="padding: 50px; text-align: center;">
                        <h3>No Published Reports</h3>
                        <p style="color: #7f8c8d; margin-top: 10px;">Your grades have been recorded but are awaiting final approval by the administrator.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($grades as $term => $term_grades): ?>
                        <div class="term-section">
                            <div class="term-header">
                                <h2><?php echo htmlspecialchars($term); ?></h2>
                                <a href="../admin/report_card.php?student_id=<?php echo $student['id']; ?>&term=<?php echo urlencode($term); ?>" class="btn btn-small" style="background: #3498db; color: white;" target="_blank">Download Slip</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>Score</th>
                                            <th>Grade</th>
                                            <th>Teacher Comments</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($term_grades as $g): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($g['subject_name']); ?></strong></td>
                                                <td><?php echo number_format($g['score'], 1); ?>%</td>
                                                <td><span class="grade-pill grade-<?php echo $g['grade']; ?>"><?php echo $g['grade']; ?></span></td>
                                                <td><small style="color: #7f8c8d; line-height: 1.4; display: block; max-width: 300px;"><?php echo htmlspecialchars($g['comments'] ?: 'Good progress.'); ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
