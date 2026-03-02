<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Pagination Setup
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Fetch Logs
$query = "SELECT l.*, u.username, u.role 
          FROM activity_logs l 
          LEFT JOIN users u ON l.user_id = u.id 
          ORDER BY l.created_at DESC 
          LIMIT :limit OFFSET :start";
$stmt = $db->prepare($query);
$stmt->bindParam(':start', $start, PDO::PARAM_INT);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

// Get Total for Pagination
$total_stmt = $db->query("SELECT COUNT(*) FROM activity_logs");
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Student Management System</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .log-table { width: 100%; border-collapse: collapse; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .log-table th, .log-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .log-table th { background-color: #f8f9fa; font-weight: 600; color: #333; }
        .log-table tr:hover { background-color: #f5f5f5; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 0.85em; font-weight: 500; }
        .badge-admin { background: #e3f2fd; color: #0d47a1; }
        .badge-teacher { background: #e8f5e9; color: #1b5e20; }
        .badge-student { background: #fff3e0; color: #e65100; }
        .pagination { margin-top: 20px; display: flex; gap: 5px; justify-content: center; }
        .pagination a { padding: 8px 12px; border: 1px solid #ddd; text-decoration: none; color: #333; border-radius: 4px; }
        .pagination a.active { background-color: #007bff; color: white; border-color: #007bff; }
        .pagination a:hover:not(.active) { background-color: #f1f1f1; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Activity Logs</h1>
            <div class="actions">
                <a href="index.php" class="btn">Back to Dashboard</a>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="log-table">
                <thead>
                    <tr>
                        <th width="15%">Time</th>
                        <th width="15%">User</th>
                        <th width="15%">Action</th>
                        <th width="40%">Details</th>
                        <th width="15%">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td><?php echo date('M d, H:i', strtotime($row['created_at'])); ?></td>
                        <td>
                            <?php if ($row['username']): ?>
                                <span class="badge badge-<?php echo $row['role']; ?>">
                                    <?php echo htmlspecialchars($row['username']); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">Unknown/Deleted</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($row['action']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['details']); ?></td>
                        <td style="font-family: monospace; color: #666;"><?php echo htmlspecialchars($row['ip_address']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <?php if ($total_rows == 0): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">No activity logs found.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
