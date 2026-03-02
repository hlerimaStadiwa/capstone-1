<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch Student Info
$stmt = $db->prepare("SELECT id, full_name FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "Profile not found.";
    exit();
}

// Fetch Attendance Statistics
$stats_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'Excused' THEN 1 ELSE 0 END) as excused
    FROM attendance 
    WHERE student_id = ?
");
$stats_stmt->execute([$student['id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$attendance_rate = 0;
if ($stats['total'] > 0) {
    $attendance_rate = round((($stats['present'] + $stats['late']) / $stats['total']) * 100, 1);
}

// Fetch Detailed Records
$records_stmt = $db->prepare("
    SELECT * FROM attendance 
    WHERE student_id = ? 
    ORDER BY date DESC 
    LIMIT 50
");
$records_stmt->execute([$student['id']]);
$records = $records_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - Student Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .att-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .att-stat-card { background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .att-stat-card h4 { color: #7f8c8d; font-size: 0.8em; text-transform: uppercase; margin-bottom: 5px; }
        .att-stat-card .value { font-size: 1.8em; font-weight: bold; color: #2c3e50; }
        
        .status-pill { padding: 4px 10px; border-radius: 12px; font-size: 0.85em; font-weight: bold; }
        .status-present { background: #e8f5e9; color: #2e7d32; }
        .status-absent { background: #ffebee; color: #c62828; }
        .status-late { background: #fffde7; color: #f9a825; }
        .status-excused { background: #e3f2fd; color: #1565c0; }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">Silver Academy</div>
            <div class="user-info" style="padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1);">
                <div class="avatar-circle" style="width: 60px; height: 60px; background: #3498db; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; margin: 0 auto 10px; color: white;">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
                <p>Welcome,<br><strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="grades.php">My Grades</a></li>
                <li><a href="attendance.php" class="active">My Attendance</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1>My Attendance</h1>
                <p>Track your presence and punctuality</p>
            </div>

            <div class="att-stats-grid">
                <div class="att-stat-card">
                    <h4>Attendance Rate</h4>
                    <div class="value" style="color: #3498db;"><?php echo $attendance_rate; ?>%</div>
                </div>
                <div class="att-stat-card">
                    <h4>Days Present</h4>
                    <div class="value" style="color: #2e7d32;"><?php echo $stats['present']; ?></div>
                </div>
                <div class="att-stat-card">
                    <h4>Days Absent</h4>
                    <div class="value" style="color: #c62828;"><?php echo $stats['absent']; ?></div>
                </div>
                <div class="att-stat-card">
                    <h4>Times Late</h4>
                    <div class="value" style="color: #f9a825;"><?php echo $stats['late']; ?></div>
                </div>
            </div>

            <div class="card">
                <h3>Attendance History</h3>
                <?php if (count($records) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Day</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $row): ?>
                                    <tr>
                                        <td><strong><?php echo date('M d, Y', strtotime($row['date'])); ?></strong></td>
                                        <td><?php echo date('l', strtotime($row['date'])); ?></td>
                                        <td>
                                            <span class="status-pill status-<?php echo strtolower($row['status']); ?>">
                                                <?php echo $row['status']; ?>
                                            </span>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($row['remarks'] ?: '-'); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #7f8c8d; padding: 20px;">No attendance records found yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
