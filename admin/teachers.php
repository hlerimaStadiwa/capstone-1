<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Fetch all subjects for the filter dropdown
try {
    $subj_stmt = $db->query("SELECT id, name FROM subjects ORDER BY name ASC");
    $all_subjects = $subj_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_subjects = [];
}

// --- HANDLE DELETION ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $db->beginTransaction();

        // Get user_id first
        $stmt = $db->prepare("SELECT user_id, full_name FROM teachers WHERE id = ?");
        $stmt->execute([$id]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($teacher) {
            // Delete from teachers
            $db->prepare("DELETE FROM teachers WHERE id = ?")->execute([$id]);

            // Delete from users
            if ($teacher['user_id']) {
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$teacher['user_id']]);
            }
            
            $_SESSION['success'] = "Teacher '{$teacher['full_name']}' deleted successfully.";
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Error deleting teacher: " . $e->getMessage();
    }
    header("Location: teachers.php");
    exit();
}

// Handle search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(t.full_name LIKE :search OR u.email LIKE :search OR t.subject_specialization LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($subject_id > 0) {
    $where_clauses[] = "t.id IN (SELECT teacher_id FROM teacher_subjects WHERE subject_id = :subject_id)";
    $params[':subject_id'] = $subject_id;
}

$where_clause = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Fetch teachers
$query = "
    SELECT t.*, u.email, u.username, u.created_at,
           STRING_AGG(s.name, ', ') as assigned_subjects
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN teacher_subjects ts ON t.id = ts.teacher_id
    LEFT JOIN subjects s ON ts.subject_id = s.id
    $where_clause 
    GROUP BY t.id, u.email, u.username, u.created_at
    ORDER BY t.full_name ASC
";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers - Student Management System</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Manage Teachers</h1>
            <p>View and manage teaching staff</p>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="action-bar">
            <div class="search-box">
                <form method="GET" action="" style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" name="search" placeholder="Search teachers..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select name="subject_id" onchange="this.form.submit()" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">All Subjects</option>
                        <?php foreach ($all_subjects as $subj): ?>
                            <option value="<?php echo $subj['id']; ?>" <?php echo $subject_id == $subj['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subj['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn">Filter</button>
                    <?php if (!empty($search) || $subject_id > 0): ?>
                        <a href="teachers.php" class="btn">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="action-buttons">
                <a href="subjects.php" class="btn">Manage Subjects</a>
                <a href="teacher_form.php" class="btn btn-primary">Add New Teacher</a>
            </div>
        </div>
        
        <div class="table-container">
            <?php if ($stmt->rowCount() > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Subject</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($teacher = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($teacher['full_name']); ?></strong><br>
                                <small style="color: #7f8c8d;">@<?php echo htmlspecialchars($teacher['username']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['phone']); ?></td>
                            <td>
                                <span style="background: #e1f5fe; color: #0288d1; padding: 2px 8px; border-radius: 12px; font-size: 0.9em;">
                                    <?php echo htmlspecialchars($teacher['assigned_subjects'] ?: 'None'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons-small">
                                    <a href="teacher_form.php?id=<?php echo $teacher['id']; ?>" class="btn btn-small btn-primary">Edit</a>
                                    <a href="teachers.php?action=delete&id=<?php echo $teacher['id']; ?>" 
                                       class="btn btn-small btn-danger"
                                       onclick="return confirm('Delete this teacher? This will delete their login access too.')">Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No teachers found. <a href="teacher_form.php">Add a teacher</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
