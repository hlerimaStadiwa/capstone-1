<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Handle Add Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name']);
    $code = trim($_POST['code']);
    
    if (!empty($name) && !empty($code)) {
        try {
            $stmt = $db->prepare("INSERT INTO subjects (name, code) VALUES (?, ?)");
            $stmt->execute([$name, $code]);
            $_SESSION['success'] = "Subject added successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding subject: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Name and Code are required.";
    }
    header("Location: subjects.php");
    exit();
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $db->prepare("DELETE FROM subjects WHERE id = ?")->execute([$_GET['id']]);
        $_SESSION['success'] = "Subject deleted.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting: " . $e->getMessage();
    }
    header("Location: subjects.php");
    exit();
}

// Fetch Subjects
$subjects = $db->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Subjects - Student Management System</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <div class="page-header">
            <h1>Manage Subjects</h1>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?><div class="message success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div><?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?><div class="message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>

        <div class="action-bar">
             <!-- Simple Inline Form -->
             <form method="POST" style="display: flex; gap: 10px; align-items: center; background: #fff; padding: 10px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <input type="hidden" name="action" value="add">
                <input type="text" name="name" placeholder="Subject Name (e.g. Mathematics)" required style="padding: 8px;">
                <input type="text" name="code" placeholder="Code (e.g. MAT101)" required style="padding: 8px; width: 120px;">
                <button type="submit" class="btn btn-primary">Add Subject</button>
            </form>
            <a href="dashboard.php" class="btn">Back to Dashboard</a>
        </div>

        <div class="table-container">
            <?php if (count($subjects) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Subject Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $s): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($s['code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($s['name']); ?></td>
                        <td>
                            <a href="subjects.php?action=delete&id=<?php echo $s['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Delete this subject?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>No subjects found.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
