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

// Get statistics
$students_count = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$teachers_count = $db->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$rooms_count = $db->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$today = date('Y-m-d');
$today_attendance = $db->query("SELECT COUNT(*) FROM attendance WHERE date = '$today' AND status = 'Present'")->fetchColumn();

// Handle News Post
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_news'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $category = $_POST['category'];
    $author_id = $_SESSION['user_id'];

    if (!empty($title) && !empty($content)) {
        try {
            $stmt = $db->prepare("INSERT INTO news (title, content, category, author_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $content, $category, $author_id]);
            $message = '<div class="message success">News posted successfully!</div>';
        } catch (PDOException $e) {
            $message = '<div class="message error">Error: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="message error">Title and Content are required.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Student Management System</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <!-- Logout Button Section Removed -->
    <div class="container">
        <h1>Admin Dashboard</h1>
        <?php echo $message; ?>
        
        <!-- Statistics Cards -->
        <div class="dashboard-cards">
            <div class="card">
                <h3>Total Students</h3>
                <p class="stat-number"><?php echo $students_count; ?></p>
                <a href="students.php" class="btn">Manage Students</a>
            </div>
            
            <div class="card">
                <h3>Total Teachers</h3>
                <p class="stat-number"><?php echo $teachers_count; ?></p>
                <a href="teachers.php" class="btn">Manage Teachers</a>
            </div>
            
            <div class="card">
                <h3>Available Rooms</h3>
                <p class="stat-number"><?php echo $rooms_count; ?></p>
                <a href="rooms.php" class="btn">Manage Rooms</a>
            </div>
            
            <div class="card">
                <h3>Today's Attendance</h3>
                <p class="stat-number"><?php echo $today_attendance; ?></p>
                <a href="attendance.php" class="btn">View Attendance</a>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2>Quick Actions</h2>
            <div class="action-buttons">
                <a href="student_form.php" class="btn btn-primary">Add New Student</a>
                <a href="attendance.php" class="btn btn-primary">View Attendance</a>
                <a href="manage_reports.php" class="btn btn-primary">Manage Reports</a>
                <a href="fees.php" class="btn btn-primary">Manage Fees</a>
                <a href="subjects.php" class="btn btn-primary">Manage Subjects</a>
            </div>
        </div>

        <!-- Dashboard Main Content Grid -->
        <div class="dashboard-grid">
            <!-- School News Section -->
            <div class="news-section">
                <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>School News & Notices</h2>
                    <button onclick="toggleNewsForm()" class="btn btn-primary btn-small">Post Update</button>
                </div>

                <!-- Add News Form -->
                <div id="newsForm" class="card" style="display: none; margin-bottom: 20px; background: #f9f9f9; padding: 20px;">
                    <form method="POST">
                        <input type="hidden" name="post_news" value="1">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" required placeholder="Announcement Title">
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category">
                                <option value="General">General News</option>
                                <option value="Staff Announcement">Staff Announcement</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Content</label>
                            <textarea name="content" rows="4" required placeholder="What's the update?"></textarea>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">Post Announcement</button>
                            <button type="button" onclick="toggleNewsForm()" class="btn">Cancel</button>
                        </div>
                    </form>
                </div>

                <div class="news-tabs" style="margin-bottom: 20px; border-bottom: 1px solid #ddd;">
                    <button class="tab-btn active" onclick="showNews('general')">General</button>
                    <button class="tab-btn" onclick="showNews('staff')">Staff Announcements</button>
                </div>

                <div id="general-news">
                    <?php
                    $news_query = "SELECT * FROM news WHERE category = 'General' ORDER BY created_at DESC LIMIT 5";
                    $news_stmt = $db->query($news_query);
                    
                    if ($news_stmt->rowCount() > 0) {
                        while ($news = $news_stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo '<div class="news-item">';
                            echo '<h3>' . htmlspecialchars($news['title']) . '</h3>';
                            echo '<p class="news-date">' . date('M d, Y', strtotime($news['created_at'])) . '</p>';
                            echo '<p>' . nl2br(htmlspecialchars($news['content'])) . '</p>';
                            echo '</div>';
                        }
                    } else {
                        echo '<p>No general news announcements.</p>';
                    }
                    ?>
                </div>

                <div id="staff-news" style="display: none;">
                    <?php
                    $news_query = "SELECT * FROM news WHERE category = 'Staff Announcement' ORDER BY created_at DESC LIMIT 5";
                    $news_stmt = $db->query($news_query);
                    
                    if ($news_stmt->rowCount() > 0) {
                        while ($news = $news_stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo '<div class="news-item" style="border-left: 4px solid #4CAF50; padding-left: 15px;">';
                            echo '<h3>' . htmlspecialchars($news['title']) . '</h3>';
                            echo '<p class="news-date"><span class="badge badge-success">New Staff</span> ' . date('M d, Y', strtotime($news['created_at'])) . '</p>';
                            echo '<p>' . nl2br(htmlspecialchars($news['content'])) . '</p>';
                            echo '</div>';
                        }
                    } else {
                        echo '<p>No new staff announcements.</p>';
                    }
                    ?>
                </div>
            </div>
            
            <!-- Staff Contacts Section -->
            <div class="staff-section">
                <h2>Staff Contacts</h2>
                <?php
                $staff_query = "SELECT full_name, role, email, phone, subject_specialization FROM teachers 
                                JOIN users ON teachers.user_id = users.id 
                                UNION 
                                SELECT 'Administrator' as full_name, role, email, 'N/A' as phone, 'Administration' as subject_specialization 
                                FROM users WHERE role='admin'";
                // Actually, let's just stick to the teachers table for now as it has phones, and maybe admin
                // Simpler query for just teachers table for now to ensure reliability if I assume admin doesn't have a profile yet
                $staff_query = "SELECT full_name, email, phone, subject_specialization FROM teachers ORDER BY full_name";
                
                $staff_stmt = $db->query($staff_query);
                
                if ($staff_stmt->rowCount() > 0) {
                    echo '<div class="table-responsive">';
                    echo '<table class="table">';
                    echo '<thead><tr><th>Name</th><th>Role/Subject</th><th>Phone</th></tr></thead>';
                    echo '<tbody>';
                    while ($staff = $staff_stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($staff['full_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($staff['subject_specialization']) . '</td>';
                        echo '<td>' . htmlspecialchars($staff['phone']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    echo '</div>';
                } else {
                    echo '<p>No staff contacts available.</p>';
                }
                ?>
            </div>
        </div>
        
        <style>
            .dashboard-grid {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 20px;
                margin-top: 30px;
            }
            @media (max-width: 768px) { .dashboard-grid { grid-template-columns: 1fr; } }
            
            .news-section, .staff-section {
                background: white;
                padding: 25px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .news-item { border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; }
            .news-item:last-child { border-bottom: none; }
            .news-item h3 { color: #2c3e50; margin-bottom: 5px; }
            .news-date { color: #7f8c8d; font-size: 0.9em; margin-bottom: 10px; }
        </style>
        
        <!-- Additional Logout Button at Bottom Removed -->
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script>
    function toggleNewsForm() {
        const form = document.getElementById('newsForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if(form.style.display === 'block') {
            form.scrollIntoView({ behavior: 'smooth' });
        }
    }

    function showNews(tab) {
        document.getElementById('general-news').style.display = tab === 'general' ? 'block' : 'none';
        document.getElementById('staff-news').style.display = tab === 'staff' ? 'block' : 'none';
        
        // Update tab buttons
        const btns = document.querySelectorAll('.tab-btn');
        btns[0].classList.toggle('active', tab === 'general');
        btns[1].classList.toggle('active', tab === 'staff');
    }

    // Add logout confirmation
    document.addEventListener('DOMContentLoaded', function() {
        const logoutLinks = document.querySelectorAll('a[href*="logout"]');
        logoutLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to logout?')) {
                    e.preventDefault();
                }
            });
        });
    });
    </script>
    <style>
        .tab-btn {
            background: none;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: bold;
            color: #7f8c8d;
            border-bottom: 2px solid transparent;
        }
        .tab-btn.active {
            color: #4CAF50;
            border-bottom: 2px solid #4CAF50;
        }
        .badge {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            color: white;
            font-weight: bold;
        }
        .badge-success { background: #4CAF50; }
    </style>
</body>
</html>