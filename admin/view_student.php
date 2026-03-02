<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Check if student ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: students.php");
    exit();
}

$student_id = $_GET['id'];

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Fetch student data with user info
$query = "
    SELECT s.*, u.username, u.created_at as account_created,
           r.room_number, r.room_type 
    FROM students s 
    LEFT JOIN users u ON s.user_id = u.id 
    LEFT JOIN rooms r ON s.room_id = r.id
    WHERE s.id = :id
";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $student_id);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    header("Location: students.php");
    exit();
}

$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate age from date of birth
$age = '';
if ($student['date_of_birth']) {
    $birthDate = new DateTime($student['date_of_birth']);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
}

// Handle Publishing Report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_term'])) {
    $term_to_publish = $_POST['term_to_publish'];
    try {
        $publish_stmt = $db->prepare("UPDATE grades SET is_published = TRUE WHERE student_id = ? AND term = ?");
        $publish_stmt->execute([$student_id, $term_to_publish]);
        $_SESSION['success'] = "Report card for $term_to_publish has been PUBLISHED successfully!";
        header("Location: view_student.php?id=" . $student_id);
        exit();
    } catch (PDOException $e) {
        $error = "Error publishing report: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student - Student Management System</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .student-profile {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-top: 20px;
        }
        @media (max-width: 768px) {
            .student-profile {
                grid-template-columns: 1fr;
            }
        }
        .profile-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: #3498db;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3em;
            margin: 0 auto 20px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .info-item {
            margin-bottom: 15px;
        }
        .info-label {
            font-weight: bold;
            color: #2c3e50;
            display: block;
            margin-bottom: 5px;
        }
        .info-value {
            color: #555;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Student Profile</h1>
            <p>View complete student information</p>
        </div>
        
        <div class="student-profile">
            <!-- Left Column: Profile Summary -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                    </div>
                    <h2><?php echo htmlspecialchars($student['full_name']); ?></h2>
                    <p><strong>PIN:</strong> <?php echo htmlspecialchars($student['pin']); ?></p>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Gender</span>
                        <div class="info-value"><?php echo htmlspecialchars($student['gender']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Age</span>
                        <div class="info-value"><?php echo $age ? $age . ' years' : 'Not specified'; ?></div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Enrollment Date</span>
                        <div class="info-value"><?php echo date('F j, Y', strtotime($student['enrollment_date'])); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Account Created</span>
                        <div class="info-value"><?php echo date('F j, Y', strtotime($student['account_created'])); ?></div>
                    </div>
                </div>
                
                <div style="margin-top: 30px; text-align: center;">
                    <a href="student_form.php?id=<?php echo $student_id; ?>" class="btn btn-primary">Edit Profile</a>
                    <a href="students.php" class="btn">Back to List</a>
                </div>
            </div>
            
            <!-- Right Column: Detailed Information -->
            <div class="profile-card">
                <h3>Contact Information</h3>
                
                <div class="info-item">
                    <span class="info-label">Email Address</span>
                    <div class="info-value"><?php echo htmlspecialchars($student['email']); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Phone Number</span>
                    <div class="info-value"><?php echo htmlspecialchars($student['phone'] ?: 'Not provided'); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Address</span>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($student['address'] ?: 'Not provided')); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Date of Birth</span>
                    <div class="info-value">
                        <?php 
                        if ($student['date_of_birth']) {
                            echo date('F j, Y', strtotime($student['date_of_birth'])) . 
                                 ' (' . $age . ' years old)';
                        } else {
                            echo 'Not specified';
                        }
                        ?>
                    </div>
                </div>

                <h3 style="margin-top: 30px;">Room Allocation</h3>
                 <div class="info-item">
                    <span class="info-label">Allocated Room</span>
                    <div class="info-value">
                        <?php 
                        if ($student['room_number']) {
                            echo "<strong>" . htmlspecialchars($student['room_number']) . "</strong> (" . htmlspecialchars($student['room_type']) . ")";
                        } else {
                            echo '<span style="color: #666;">Not a Boarder / No Room Allocated</span>';
                        }
                        ?>
                    </div>
                </div>
                
                <h3 style="margin-top: 30px;">Academic Performance</h3>
                <?php
                // Fetch grade history summary
                $history_stmt = $db->prepare("
                    SELECT term, AVG(score) as avg_score, COUNT(*) as subjects, BOOL_OR(is_published) as published
                    FROM grades 
                    WHERE student_id = ? 
                    GROUP BY term 
                    ORDER BY term DESC
                ");
                $history_stmt->execute([$student_id]);
                $history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($history) > 0): ?>
                    <div class="table-responsive" style="margin-bottom: 20px;">
                        <table class="table" style="font-size: 0.9em;">
                            <thead>
                                <tr>
                                    <th>Term</th>
                                    <th>Avg Score</th>
                                    <th>Status</th>
                                    <th>Report</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $h): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($h['term']); ?></td>
                                    <td><strong><?php echo number_format($h['avg_score'], 1); ?>%</strong></td>
                                    <td>
                                        <?php if ($h['published']): ?>
                                            <span class="badge" style="background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9;">Published</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2;">Draft</span>
                                            <form method="POST" style="display:inline; margin-left: 5px;">
                                                <input type="hidden" name="term_to_publish" value="<?php echo htmlspecialchars($h['term']); ?>">
                                                <button type="submit" name="publish_term" class="btn btn-small btn-success" style="padding: 2px 8px; font-size: 0.8em;" onclick="return confirm('Post this report to the student and teacher?')">Post</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td><a href="report_card.php?student_id=<?php echo $student_id; ?>&term=<?php echo urlencode($h['term']); ?>" class="btn btn-small btn-primary" target="_blank">View</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: #666; font-style: italic; margin-bottom: 20px;">No academic records found for this student.</p>
                <?php endif; ?>

                <h3>Quick Actions</h3>
                
                <div style="display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap;">
                    <a href="attendance.php?student_id=<?php echo $student_id; ?>" class="btn btn-small btn-success">Attendance</a>
                    <a href="fees.php?student_id=<?php echo $student_id; ?>" class="btn btn-small btn-warning">Fees</a>
                    <a href="report_card.php?student_id=<?php echo $student_id; ?>&term=Latest" class="btn btn-small btn-primary" title="Generate report for the most recent term">Latest Report</a>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>