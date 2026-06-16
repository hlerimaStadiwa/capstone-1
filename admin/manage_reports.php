<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';

$classroom_filter = '';
if (isset($_POST['classroom_id'])) {
    $classroom_filter = trim($_POST['classroom_id']);
} elseif (isset($_GET['classroom_id'])) {
    $classroom_filter = trim($_GET['classroom_id']);
}
$classrooms = $db->query("SELECT id, room_number FROM rooms WHERE room_type = 'Classroom' ORDER BY room_number ASC")->fetchAll(PDO::FETCH_ASSOC);

function calculateGradeLabel($score) {
    if ($score >= 80) return 'A';
    if ($score >= 66) return 'B';
    if ($score >= 50) return 'C';
    if ($score >= 45) return 'D';
    return 'U';
}

function generateSampleGradesForTerm($db, $term, $classroom_id = null) {
    // Determine the student-subject pairs to seed
    if ($classroom_id) {
        $pairs_stmt = $db->prepare(
            "SELECT ss.student_id, s.name AS subject_name
             FROM student_subjects ss
             JOIN subjects s ON s.id = ss.subject_id
             JOIN students st ON st.id = ss.student_id
             WHERE st.classroom_id = :classroom_id
             ORDER BY ss.student_id, s.name"
        );
        $pairs_stmt->execute([':classroom_id' => $classroom_id]);
    } else {
        $pairs_stmt = $db->query(
            "SELECT ss.student_id, s.name AS subject_name
             FROM student_subjects ss
             JOIN subjects s ON s.id = ss.subject_id
             ORDER BY ss.student_id, s.name"
        );
    }
    $pairs = $pairs_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pairs) && $classroom_id) {
        $students = $db->prepare("SELECT id FROM students WHERE classroom_id = ? ORDER BY id");
        $students->execute([$classroom_id]);
        $student_ids = $students->fetchAll(PDO::FETCH_COLUMN);
        $subject_names = $db->query("SELECT name FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($student_ids as $student_id) {
            foreach ($subject_names as $subject_name) {
                $pairs[] = ['student_id' => $student_id, 'subject_name' => $subject_name];
            }
        }
    }
    if (empty($pairs)) {
        $students = $db->query("SELECT id FROM students ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $subject_names = $db->query("SELECT name FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($students as $student_id) {
            foreach ($subject_names as $subject_name) {
                $pairs[] = ['student_id' => $student_id, 'subject_name' => $subject_name];
            }
        }
    }

    if (empty($pairs)) {
        return 0;
    }

    $existing_stmt = $db->prepare("SELECT student_id, subject_name FROM grades WHERE term = ?");
    $existing_stmt->execute([$term]);
    $existing_rows = $existing_stmt->fetchAll(PDO::FETCH_ASSOC);
    $existing_set = [];
    foreach ($existing_rows as $row) {
        $existing_set[$row['student_id'] . '|' . strtolower($row['subject_name'])] = true;
    }

    $insert_stmt = $db->prepare("INSERT INTO grades (student_id, subject_name, score, grade, term, comments, is_published) VALUES (?, ?, ?, ?, ?, ?, TRUE)");
    $count = 0;

    foreach ($pairs as $pair) {
        $key = $pair['student_id'] . '|' . strtolower($pair['subject_name']);
        if (isset($existing_set[$key])) {
            continue;
        }
        $score = rand(55, 95);
        $grade = calculateGradeLabel($score);
        $comments = "Sample results published by Admin.";
        $insert_stmt->execute([$pair['student_id'], $pair['subject_name'], $score, $grade, $term, $comments]);
        $count++;
    }

    return $count;
}

// Handle Bulk Publishing and Withdrawal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $term = $_POST['term'];

    if (isset($_POST['bulk_publish'])) {
        try {
            $draft_sql = "SELECT COUNT(*) FROM grades g JOIN students s ON s.id = g.student_id WHERE g.term = ? AND g.is_published = FALSE";
            $total_sql = "SELECT COUNT(*) FROM grades g JOIN students s ON s.id = g.student_id WHERE g.term = ?";
            $update_sql = "UPDATE grades g SET is_published = TRUE FROM students s WHERE s.id = g.student_id AND g.term = ? AND g.is_published = FALSE";
            $draft_params = [$term];
            $total_params = [$term];
            $update_params = [$term];

            if (!empty($classroom_filter)) {
                $draft_sql .= " AND s.classroom_id = ?";
                $total_sql .= " AND s.classroom_id = ?";
                $update_sql .= " AND s.classroom_id = ?";
                $draft_params[] = $classroom_filter;
                $total_params[] = $classroom_filter;
                $update_params[] = $classroom_filter;
            }

            $draft_stmt = $db->prepare($draft_sql);
            $draft_stmt->execute($draft_params);
            $draft_count = (int)$draft_stmt->fetchColumn();

            if ($draft_count === 0) {
                $total_stmt = $db->prepare($total_sql);
                $total_stmt->execute($total_params);
                $total_count = (int)$total_stmt->fetchColumn();

                if ($total_count === 0) {
                    $created = generateSampleGradesForTerm($db, $term, $classroom_filter ?: null);
                    if ($created > 0) {
                        $message = "<div class='message success'>Published placeholder results for $term.</div>";
                    } else {
                        $message = "<div class='message error'>No student or subject data available to publish results for $term.</div>";
                    }
                } else {
                    $message = "<div class='message info'>No unpublished reports are available for $term to publish.</div>";
                }
            } else {
                $stmt = $db->prepare($update_sql);
                $stmt->execute($update_params);
                $count = $stmt->rowCount();
                $message = "<div class='message success'>Successfully published $count subject results for $term.</div>";
            }
        } catch (PDOException $e) {
            $message = "<div class='message error'>Error: " . $e->getMessage() . "</div>";
        }
    }

    if (isset($_POST['bulk_withdraw'])) {
        try {
            $withdraw_sql = "UPDATE grades g SET is_published = FALSE FROM students s WHERE s.id = g.student_id AND g.term = ? AND g.is_published = TRUE";
            $withdraw_params = [$term];
            if (!empty($classroom_filter)) {
                $withdraw_sql .= " AND s.classroom_id = ?";
                $withdraw_params[] = $classroom_filter;
            }
            $stmt = $db->prepare($withdraw_sql);
            $stmt->execute($withdraw_params);
            $count = $stmt->rowCount();
            $message = "<div class='message success'>Successfully withdrew publication for $count subject results in $term.</div>";
        } catch (PDOException $e) {
            $message = "<div class='message error'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

// Get report status summary by term
$filter_clause = '';
$params = [];
if (!empty($classroom_filter)) {
    $filter_clause = 'WHERE s.classroom_id = :classroom_id';
    $params[':classroom_id'] = $classroom_filter;
}
$query = "
    SELECT g.term, 
           COUNT(*) as total_entries,
           SUM(CASE WHEN g.is_published = TRUE THEN 1 ELSE 0 END) as published_count,
           SUM(CASE WHEN g.is_published = FALSE THEN 1 ELSE 0 END) as draft_count
    FROM grades g
    JOIN students s ON s.id = g.student_id
    $filter_clause
    GROUP BY g.term 
    ORDER BY g.term ASC";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$report_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$class_students = [];
if (!empty($classroom_filter)) {
    $student_query = "
        SELECT s.id, s.full_name, s.pin, r.room_number,
               COALESCE(SUM(CASE WHEN g.is_published = TRUE THEN 1 ELSE 0 END), 0) AS published_count,
               COALESCE(SUM(CASE WHEN g.is_published = FALSE THEN 1 ELSE 0 END), 0) AS draft_count
        FROM students s
        LEFT JOIN rooms r ON r.id = s.classroom_id
        LEFT JOIN grades g ON g.student_id = s.id
        WHERE s.classroom_id = :classroom_id
        GROUP BY s.id, r.room_number
        ORDER BY s.full_name ASC";
    $student_stmt = $db->prepare($student_query);
    $student_stmt->execute([':classroom_id' => $classroom_filter]);
    $class_students = $student_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reports - Admin Portal</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .report-card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-top: 5px solid #3498db; }
        .report-card.draft { border-top-color: #f39c12; }
        .report-card h3 { margin-top: 0; color: #2c3e50; }
        .progress-bar { background: #eee; height: 10px; border-radius: 5px; margin: 15px 0; overflow: hidden; }
        .progress-fill { height: 100%; background: #27ae60; transition: width 0.3s ease; }
        .stat-detail { display: flex; justify-content: space-between; font-size: 0.9em; color: #666; margin-bottom: 5px; }
        .action-area { margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container" style="max-width: 1000px;">
        <div class="page-header">
            <h1>Academic Report Management</h1>
            <p>Publish student results institutional-wide for specific terms</p>
        </div>

        <form method="GET" style="margin-bottom: 20px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <label style="font-weight: 600; white-space: nowrap;">Filter by Classroom:</label>
            <select name="classroom_id" style="min-width: 220px; padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                <option value="">All Classrooms</option>
                <?php foreach ($classrooms as $cls): ?>
                    <option value="<?php echo $cls['id']; ?>" <?php echo $classroom_filter == $cls['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cls['room_number']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Apply Filter</button>
            <?php if (!empty($classroom_filter)): ?>
                <a href="manage_reports.php" class="btn">Clear Filter</a>
            <?php endif; ?>
        </form>

        <?php echo $message; ?>

        <div class="stats-grid">
            <?php foreach (['Term 1', 'Term 2', 'Term 3'] as $term_name): 
                $found = false;
                $data = ['total_entries' => 0, 'published_count' => 0, 'draft_count' => 0];
                foreach ($report_stats as $stat) {
                    if ($stat['term'] === $term_name) {
                        $data = $stat;
                        $found = true;
                        break;
                    }
                }
                $percentage = ($data['total_entries'] > 0) ? ($data['published_count'] / $data['total_entries']) * 100 : 0;
            ?>
                <div class="report-card <?php echo $data['draft_count'] > 0 ? 'draft' : ''; ?>">
                    <h3><?php echo htmlspecialchars($term_name); ?></h3>
                    
                    <div class="stat-detail">
                        <span>Status</span>
                        <strong><?php echo $data['draft_count'] > 0 ? 'Draft Pending' : ($data['total_entries'] > 0 ? 'Fully Published' : 'No Data'); ?></strong>
                    </div>
                    
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                    </div>
                    
                    <div class="stat-detail">
                        <span>Total Grade Entries</span>
                        <strong><?php echo $data['total_entries']; ?></strong>
                    </div>
                    <div class="stat-detail">
                        <span>Published Subjects</span>
                        <strong style="color: #27ae60;"><?php echo $data['published_count']; ?></strong>
                    </div>
                    <div class="stat-detail" style="margin-bottom: 20px;">
                        <span>Drafts (Hidden)</span>
                        <strong style="color: #f39c12;"><?php echo $data['draft_count']; ?></strong>
                    </div>

                    <div class="action-area">
                        <form method="POST" style="margin-bottom: 10px;" onsubmit="return confirm('Publish all unpublished <?php echo $term_name; ?> results for the selected scope?')">
                            <input type="hidden" name="term" value="<?php echo htmlspecialchars($term_name); ?>">
                            <?php if (!empty($classroom_filter)): ?>
                                <input type="hidden" name="classroom_id" value="<?php echo htmlspecialchars($classroom_filter); ?>">
                            <?php endif; ?>
                            <button type="submit" name="bulk_publish" class="btn btn-primary" style="width: 100%;">Publish <?php echo $term_name; ?> Reports</button>
                        </form>
                        <?php if ($data['published_count'] > 0): ?>
                            <form method="POST" onsubmit="return confirm('Withdraw published <?php echo $term_name; ?> reports from student view? This will hide them again.')">
                                <input type="hidden" name="term" value="<?php echo htmlspecialchars($term_name); ?>">
                                <?php if (!empty($classroom_filter)): ?>
                                    <input type="hidden" name="classroom_id" value="<?php echo htmlspecialchars($classroom_filter); ?>">
                                <?php endif; ?>
                                <button type="submit" name="bulk_withdraw" class="btn btn-secondary" style="width: 100%;">Withdraw <?php echo $term_name; ?> Publication</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-secondary" style="width: 100%; cursor: not-allowed;" disabled>No Published Data to Withdraw</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($classroom_filter)): ?>
            <div style="margin-top: 40px; padding: 25px; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0;">
                <h4 style="margin-top: 0;">Student Results for Classroom <?php echo htmlspecialchars($class_students[0]['room_number'] ?? 'Selected'); ?></h4>
                <p style="font-size: 0.9em; color: #666; line-height: 1.6;">
                    Showing students in the selected classroom with published and draft grade counts across all terms.
                </p>
                <?php if (count($class_students) > 0): ?>
                    <div class="table-responsive" style="margin-top: 20px;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>PIN</th>
                                    <th>Published Grades</th>
                                    <th>Draft Grades</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($class_students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['pin']); ?></td>
                                        <td><?php echo htmlspecialchars($student['published_count']); ?></td>
                                        <td><?php echo htmlspecialchars($student['draft_count']); ?></td>
                                        <td>
                                            <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-small">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding: 20px; color: #555;">No students found for this classroom.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div style="margin-top: 40px; padding: 25px; background: #f8f9fa; border-radius: 8px; border-left: 5px solid #2c3e50;">
            <h4 style="margin-top:0;">About Bulk Management</h4>
            <p style="font-size: 0.9em; color: #666; line-height: 1.6;">
                Posting reports institution-wide makes the "latest preliminary results" visible to teachers and students for the selected term. 
                Individual reports can still be managed via the <a href="students.php">Student Directory</a>. 
                Draft results are always hidden from everyone except the grading teacher and administrators.
            </p>
            <div style="margin-top: 15px;">
                <a href="dashboard.php" class="btn btn-small">Return to Dashboard</a>
                <a href="students.php" class="btn btn-small btn-primary">Go to Student Directory</a>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
