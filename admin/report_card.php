<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Check if student ID and term are provided
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$term = isset($_GET['term']) ? $_GET['term'] : '';

if (empty($student_id) || empty($term)) {
    die("Student ID and Term are required.");
}

// Support 'Latest' term shortcut
if ($term === 'Latest') {
    $latest_stmt = $db->prepare("SELECT term FROM grades WHERE student_id = ? ORDER BY id DESC LIMIT 1");
    $latest_stmt->execute([$student_id]);
    $latest_term = $latest_stmt->fetchColumn();
    if ($latest_term) {
        $term = $latest_term;
    }
}

// Fetch student data
$st_stmt = $db->prepare("SELECT s.*, r.room_number FROM students s LEFT JOIN rooms r ON s.room_id = r.id WHERE s.id = ?");
$st_stmt->execute([$student_id]);
$student = $st_stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found.");
}

// Fetch grades for the term
$g_stmt = $db->prepare("SELECT * FROM grades WHERE student_id = ? AND term = ? ORDER BY subject_name ASC");
$g_stmt->execute([$student_id, $term]);
$grades = $g_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall performance
$total_score = 0;
$count = count($grades);
foreach ($grades as $g) {
    $total_score += $g['score'];
}
$average = $count > 0 ? $total_score / $count : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Card - <?php echo htmlspecialchars($student['full_name']); ?> - <?php echo htmlspecialchars($term); ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #fff; color: #333; line-height: 1.6; }
        .report-container { max-width: 800px; margin: 40px auto; padding: 40px; border: 2px solid #333; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .school-name { font-size: 2em; font-weight: bold; text-transform: uppercase; margin: 0; }
        .report-title { font-size: 1.5em; margin: 10px 0; color: #555; }
        .student-info { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .info-box { border: 1px solid #ddd; padding: 15px; border-radius: 4px; }
        .info-label { font-weight: bold; color: #666; font-size: 0.9em; display: block; }
        .info-value { font-size: 1.1em; font-weight: bold; }
        .grades-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .grades-table th, .grades-table td { border: 1px solid #333; padding: 12px; text-align: left; }
        .grades-table th { background: #f2f2f2; }
        .summary { border-top: 2px solid #333; padding-top: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .summary-stats { width: 40%; }
        .comments-box { width: 55%; border: 1px solid #ddd; padding: 15px; border-radius: 4px; height: 100px; }
        .print-btn { background: #333; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-bottom: 20px; }
        @media print { .print-btn { display: none; } .report-container { border: none; margin: 0; padding: 0; } }
    </style>
</head>
<body>
    <div style="text-align: center;">
        <button class="print-btn" onclick="window.print()">Print Report Card</button>
    </div>

    <div class="report-container">
        <div class="header">
            <h1 class="school-name">Danborough Student System</h1>
            <h2 class="report-title">Student Academic Report Card</h2>
            <p><strong>Academic Term:</strong> <?php echo htmlspecialchars($term); ?> | <strong>Date:</strong> <?php echo date('M d, Y'); ?></p>
        </div>

        <div class="student-info">
            <div class="info-box">
                <span class="info-label">Student Name</span>
                <span class="info-value"><?php echo htmlspecialchars($student['full_name']); ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Student PIN</span>
                <span class="info-value"><?php echo htmlspecialchars($student['pin']); ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Gender</span>
                <span class="info-value"><?php echo htmlspecialchars($student['gender']); ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Allocated Room</span>
                <span class="info-value"><?php echo htmlspecialchars($student['room_number'] ?: 'N/A'); ?></span>
            </div>
        </div>

        <table class="grades-table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Score (%)</th>
                    <th>Grade</th>
                    <th>Comments</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($count > 0): ?>
                    <?php foreach ($grades as $g): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($g['subject_name']); ?></td>
                            <td><?php echo number_format($g['score'], 2); ?></td>
                            <td><strong><?php echo htmlspecialchars($g['grade']); ?></strong></td>
                            <td><small><?php echo htmlspecialchars($g['comments']); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center;">No grades recorded for this term.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="summary">
            <div class="summary-stats">
                <div class="info-item" style="margin-bottom: 10px;">
                    <span class="info-label">Total Average Score:</span>
                    <span class="info-value" style="font-size: 1.5em; color: #2c3e50;"><?php echo number_format($average, 2); ?>%</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Overall Performance:</span>
                    <span class="info-value">
                        <?php 
                        if ($average >= 80) echo '<span style="color: green;">Excellent</span>';
                        elseif ($average >= 60) echo '<span style="color: blue;">Good</span>';
                        elseif ($average >= 40) echo '<span style="color: orange;">Satisfactory</span>';
                        else echo '<span style="color: red;">Poor</span>';
                        ?>
                    </span>
                </div>
            </div>
            <div class="comments-box">
                <span class="info-label">General Remarks:</span>
                <div style="font-style: italic; color: #777; margin-top: 10px;">
                    (Admin/Teacher Comments Area)
                </div>
            </div>
        </div>

        <div style="margin-top: 50px; display: flex; justify-content: space-between;">
            <div style="text-align: center; width: 30%;">
                <div style="border-top: 1px solid #333; padding-top: 5px;">Class Teacher</div>
            </div>
            <div style="text-align: center; width: 30%;">
                <div style="border-top: 1px solid #333; padding-top: 5px;">Headmaster</div>
            </div>
        </div>
    </div>
</body>
</html>
