<?php
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/init.php';
// Get filter parameters
$classroom_id = isset($_GET['classroom_id']) ? $_GET['classroom_id'] : '';
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$status = isset($_GET['status']) ? $_GET['status'] : '';

$classrooms = $db->query("SELECT id, room_number FROM rooms WHERE room_type = 'Classroom' ORDER BY room_number ASC")->fetchAll(PDO::FETCH_ASSOC);

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($classroom_id)) {
    $where_conditions[] = "s.classroom_id = :classroom_id";
    $params[':classroom_id'] = $classroom_id;
}

if (!empty($student_id)) {
    $where_conditions[] = "a.student_id = :student_id";
    $params[':student_id'] = $student_id;
}

if (!empty($status)) {
    $where_conditions[] = "a.status = :status";
    $params[':status'] = $status;
}

if (!empty($date)) {
    $where_conditions[] = "a.date = :date";
    $params[':date'] = $date;
} elseif (!empty($month)) {
    $where_conditions[] = "TO_CHAR(a.date, 'YYYY-MM') = :month";
    $params[':month'] = $month;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get attendance records
$query = "
    SELECT a.*, s.pin, s.full_name, r.room_number as classroom_name, u.username as recorded_by_name
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    LEFT JOIN rooms r ON s.classroom_id = r.id
    LEFT JOIN users u ON a.recorded_by = u.id
    $where_clause
    ORDER BY a.date DESC, s.full_name
    LIMIT 100
";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

// Get all students for filter dropdown
if (!empty($classroom_id)) {
    $students_query = "SELECT id, pin, full_name FROM students WHERE classroom_id = ? ORDER BY full_name";
    $students_stmt = $db->prepare($students_query);
    $students_stmt->execute([$classroom_id]);
} else {
    $students_query = "SELECT id, pin, full_name FROM students ORDER BY full_name";
    $students_stmt = $db->prepare($students_query);
    $students_stmt->execute();
}

// Calculate statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN status = 'Excused' THEN 1 ELSE 0 END) as excused_count
    FROM attendance a
    $where_clause
";

$stats_stmt = $db->prepare($stats_query);
foreach ($params as $key => $value) {
    $stats_stmt->bindValue($key, $value);
}
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get unique months for filter
$months_query = "SELECT DISTINCT TO_CHAR(date, 'YYYY-MM') as month FROM attendance ORDER BY month DESC";
$months_stmt = $db->query($months_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - Danborough Student Management System</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .reports-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .filters-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stat-card h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 1em;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
        }
        
        .attendance-report {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .export-options {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-present { background: #28a745; }
        .status-absent { background: #dc3545; }
        .status-late { background: #ffc107; }
        .status-excused { background: #17a2b8; }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .date-navigation {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Attendance Reports</h1>
            <p>View and analyze student attendance data</p>
        </div>
        
        <div class="reports-container">
            <!-- Filters -->
            <div class="filters-card">
                <h3>Filter Attendance Records</h3>
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="form-group">
                            <label for="classroom_id">Select Classroom</label>
                            <select id="classroom_id" name="classroom_id" class="form-control" onchange="this.form.submit()">
                                <option value="">All Classrooms</option>
                                <?php foreach ($classrooms as $classroom): ?>
                                    <option value="<?php echo $classroom['id']; ?>" <?php echo ($classroom_id == $classroom['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($classroom['room_number']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="student_id">Select Student</label>
                            <select id="student_id" name="student_id" class="form-control">
                                <option value="">All Students</option>
                                <?php while ($student = $students_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $student['id']; ?>" 
                                        <?php echo ($student_id == $student['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student['pin'] . ' - ' . $student['full_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date">Select Date</label>
                            <input id="date" type="date" name="date" class="form-control" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($date); ?>">
                        </div>
                        <div class="form-group">
                            <label for="month">Select Month</label>
                            <select id="month" name="month" class="form-control">
                                <option value="">All Months</option>
                                <?php while ($month_row = $months_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $month_row['month']; ?>" 
                                        <?php echo ($month == $month_row['month']) ? 'selected' : ''; ?>>
                                        <?php echo date('F Y', strtotime($month_row['month'] . '-01')); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status Filter</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="Present" <?php echo ($status == 'Present') ? 'selected' : ''; ?>>Present</option>
                                <option value="Absent" <?php echo ($status == 'Absent') ? 'selected' : ''; ?>>Absent</option>
                                <option value="Late" <?php echo ($status == 'Late') ? 'selected' : ''; ?>>Late</option>
                                <option value="Excused" <?php echo ($status == 'Excused') ? 'selected' : ''; ?>>Excused</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="attendance.php" class="btn">Clear Filters</a>
                    </div>
                </form>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Total Records</h4>
                    <p class="stat-number"><?php echo $stats['total_records'] ?? 0; ?></p>
                </div>
                
                <div class="stat-card">
                    <h4>Present</h4>
                    <p class="stat-number" style="color: #28a745;"><?php echo $stats['present_count'] ?? 0; ?></p>
                    <small><?php echo $stats['total_records'] ? round(($stats['present_count'] / $stats['total_records']) * 100, 1) : 0; ?>%</small>
                </div>
                
                <div class="stat-card">
                    <h4>Absent</h4>
                    <p class="stat-number" style="color: #dc3545;"><?php echo $stats['absent_count'] ?? 0; ?></p>
                    <small><?php echo $stats['total_records'] ? round(($stats['absent_count'] / $stats['total_records']) * 100, 1) : 0; ?>%</small>
                </div>
                
                <div class="stat-card">
                    <h4>Attendance Rate</h4>
                    <p class="stat-number" style="color: #3498db;">
                        <?php 
                        if ($stats['total_records']) {
                            $attendance_rate = (($stats['present_count'] + $stats['late_count']) / $stats['total_records']) * 100;
                            echo round($attendance_rate, 1) . '%';
                        } else {
                            echo '0%';
                        }
                        ?>
                    </p>
                    <small>Present + Late / Total</small>
                </div>
            </div>
            
            <!-- Attendance Report -->
            <div class="attendance-report">
                <div class="export-options">
                    <div>
                        <strong>Displaying <?php echo $stmt->rowCount(); ?> Academic records</strong>
                        <?php if (!empty($where_clause)): ?>
                            <span style="color: #666; margin-left: 10px; font-size: 0.85em; text-transform: uppercase;">(Filtered View)</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button onclick="printReport()" class="btn btn-small btn-primary">Print Official Report</button>
                        <button onclick="exportToCSV()" class="btn btn-small">Export CSV Data</button>
                    </div>
                </div>
                
                <?php if ($stmt->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table" id="attendanceTable">
                            <thead>
                                <tr>
                                    <th>Date of Record</th>
                                    <th>Student Name</th>
                                    <th>Institutional PIN</th>
                                    <th>Attendance Status</th>
                                    <th>Faculty Remarks</th>
                                    <th>Recorded By</th>
                                    <th>Verification Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($record = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('M j, Y', strtotime($record['date'])); ?></strong><br>
                                        <small style="color: #7f8c8d;"><?php echo date('l', strtotime($record['date'])); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                                    <td><code style="background: #f4f4f4; padding: 2px 5px; border-radius: 3px;"><?php echo htmlspecialchars($record['pin']); ?></code></td>
                                    <td>
                                        <span class="status-indicator status-<?php echo strtolower($record['status']); ?>"></span>
                                        <span class="status-badge status-<?php echo strtolower($record['status']); ?>" style="font-weight: bold; font-size: 0.8em; text-transform: uppercase;">
                                            <?php echo $record['status']; ?>
                                        </span>
                                    </td>
                                    <td style="font-style: italic; color: #555;"><?php echo htmlspecialchars($record['remarks'] ?: 'No formal remarks recorded'); ?></td>
                                    <td><?php echo htmlspecialchars($record['recorded_by_name'] ?: 'System System'); ?></td>
                                    <td><?php echo date('h:i A', strtotime($record['recorded_at'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <h3 style="color: #2c3e50;">Historical records not found</h3>
                        <p>Adjust your search criteria or <a href="attendance.php" style="color: #27ae60; font-weight: bold;">synchronize records</a> for the current session.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Links -->
            <div style="margin-top: 30px; text-align: center;">
                <div class="action-buttons">
                    <a href="dashboard.php" class="btn">Return to Dashboard</a>
                    <a href="students.php" class="btn">Student Registry</a>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
    function printReport() {
        const printContent = document.getElementById('attendanceTable').outerHTML;
        const originalContent = document.body.innerHTML;
        
        document.body.innerHTML = `
            <html>
            <head>
                <title>Attendance Report - <?php echo date('Y-m-d'); ?></title>
                <style>
                    body { font-family: Arial; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background: #f2f2f2; }
                    .status-badge { padding: 3px 8px; border-radius: 3px; font-size: 0.85em; }
                    .status-present { background: #d4edda; color: #155724; }
                    .status-absent { background: #f8d7da; color: #721c24; }
                    .status-late { background: #fff3cd; color: #856404; }
                    .status-excused { background: #d1ecf1; color: #0c5460; }
                </style>
            </head>
            <body>
                <h2>Attendance Report</h2>
                <p>Generated: <?php echo date('F j, Y h:i A'); ?></p>
                <p>Filters: 
                    <?php 
                    $filters = [];
                    if ($student_id) $filters[] = "Student ID: $student_id";
                    if ($month) $filters[] = "Month: " . date('F Y', strtotime($month . '-01'));
                    if ($status) $filters[] = "Status: $status";
                    echo implode(', ', $filters) ?: 'All records';
                    ?>
                </p>
                ${printContent}
            </body>
            </html>
        `;
        
        window.print();
        document.body.innerHTML = originalContent;
        location.reload();
    }
    
    function exportToCSV() {
        const table = document.getElementById('attendanceTable');
        let csv = [];
        
        // Headers
        const headers = [];
        table.querySelectorAll('thead th').forEach(th => {
            headers.push(th.textContent.trim());
        });
        csv.push(headers.join(','));
        
        // Data rows
        table.querySelectorAll('tbody tr').forEach(row => {
            const rowData = [];
            row.querySelectorAll('td').forEach(cell => {
                // Remove status badge and get clean text
                let text = cell.textContent.trim();
                text = text.replace(/[\n\r]+/g, ' ');
                text = text.replace(/\s+/g, ' ');
                text = text.replace(/"/g, '""'); // Escape quotes
                rowData.push(`"${text}"`);
            });
            csv.push(rowData.join(','));
        });
        
        // Download CSV
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `attendance_report_${new Date().toISOString().slice(0,10)}.csv`;
        link.click();
    }
    
    // Auto-refresh filters
    document.getElementById('student_id').addEventListener('change', function() {
        if (this.value) {
            document.getElementById('month').value = '';
            document.getElementById('status').value = '';
        }
    });
    
    document.getElementById('month').addEventListener('change', function() {
        if (this.value) {
            document.getElementById('student_id').value = '';
        }
    });
    </script>
</body>
</html>