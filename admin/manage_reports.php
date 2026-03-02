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

// Handle Bulk Publishing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_publish'])) {
    $term = $_POST['term'];
    try {
        $stmt = $db->prepare("UPDATE grades SET is_published = TRUE WHERE term = ? AND is_published = FALSE");
        $stmt->execute([$term]);
        $count = $stmt->rowCount();
        
        $message = "<div class='message success'>Successfully published $count subjects/reports for $term across all students!</div>";
    } catch (PDOException $e) {
        $message = "<div class='message error'>Error: " . $e->getMessage() . "</div>";
    }
}

// Get report status summary by term
$query = "
    SELECT term, 
           COUNT(*) as total_entries,
           SUM(CASE WHEN is_published = TRUE THEN 1 ELSE 0 END) as published_count,
           SUM(CASE WHEN is_published = FALSE THEN 1 ELSE 0 END) as draft_count
    FROM grades 
    GROUP BY term 
    ORDER BY term ASC";
$stmt = $db->query($query);
$report_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                        <?php if ($data['draft_count'] > 0): ?>
                            <form method="POST" onsubmit="return confirm('CRITICAL: This will make ALL subjects for <?php echo $term_name; ?> visible to students, parents, and teachers institution-wide. Proceed?')">
                                <input type="hidden" name="term" value="<?php echo htmlspecialchars($term_name); ?>">
                                <button type="submit" name="bulk_publish" class="btn btn-primary" style="width: 100%;">Post All <?php echo $term_name; ?> Reports</button>
                            </form>
                        <?php else: ?>
                            <button class="btn" style="width: 100%; cursor: not-allowed;" disabled>Nothing to Post</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

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
