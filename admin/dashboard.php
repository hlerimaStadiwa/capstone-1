<?php
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/init.php';
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

// Handle News Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_news'])) {
    $edit_id = intval($_POST['news_id'] ?? 0);
    $edit_title = trim($_POST['title'] ?? '');
    $edit_content = trim($_POST['content'] ?? '');
    $edit_category = $_POST['category'] ?? 'General';
    if ($edit_id && $edit_title && $edit_content) {
        try {
            $u = $db->prepare("UPDATE news SET title = ?, content = ?, category = ? WHERE id = ?");
            $u->execute([$edit_title, $edit_content, $edit_category, $edit_id]);
            $message = '<div class="message success">News updated successfully!</div>';
        } catch (PDOException $e) {
            $message = '<div class="message error">Error updating news: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $message = '<div class="message error">All fields are required to edit a news post.</div>';
    }
}

// Handle News Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_news'])) {
    $del_id = intval($_POST['news_id'] ?? 0);
    if ($del_id) {
        try {
            $d = $db->prepare("DELETE FROM news WHERE id = ?");
            $d->execute([$del_id]);
            $message = '<div class="message success">News deleted successfully.</div>';
        } catch (PDOException $e) {
            $message = '<div class="message error">Error deleting news: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $message = '<div class="message error">Invalid news id for deletion.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Danborough Student Management System</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <!-- Logout Button Section Removed -->
    <div class="container">
        <h1>Admin Dashboard</h1>
        <?php echo $message; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid" style="margin-top: 20px;">
            <div class="card">
                <h3>Total Students</h3>
                <p class="stat-number"><?php echo $students_count; ?></p>
                <a href="students.php" class="btn btn-small">Manage Students</a>
            </div>
            
            <div class="card">
                <h3>Total Teachers</h3>
                <p class="stat-number"><?php echo $teachers_count; ?></p>
                <a href="teachers.php" class="btn btn-small">Manage Teachers</a>
            </div>
            
            <div class="card">
                <h3>Total Rooms</h3>
                <p class="stat-number"><?php echo $rooms_count; ?></p>
                <a href="rooms.php" class="btn btn-small">Manage Rooms</a>
            </div>
            
            <div class="card">
                <h3>Today's Attendance</h3>
                <p class="stat-number"><?php echo $today_attendance; ?></p>
                <a href="attendance.php" class="btn btn-small">View Attendance</a>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions" style="margin-top: 20px; margin-bottom: 20px;">
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

                <div class="scrollable-panel">
                    <div id="general-news">
                        <?php
                        $news_query = "SELECT * FROM news WHERE category = 'General' AND title NOT ILIKE '%silver%' ORDER BY created_at DESC LIMIT 5";
                        $news_stmt = $db->query($news_query);
                        
                        if ($news_stmt->rowCount() > 0) {
                            while ($news = $news_stmt->fetch(PDO::FETCH_ASSOC)) {
                                $content = $news['content'];
                                if (strlen($content) > 100) {
                                    $preview = substr($content, 0, 90) . '...';
                                    echo '<div class="news-item toggleable">';
                                    echo '<h3>' . htmlspecialchars($news['title']) . '</h3>';
                                    echo '<p class="news-date">' . date('M d, Y', strtotime($news['created_at'])) . '</p>';
                                    echo '<div class="news-content-preview">' . nl2br(htmlspecialchars($preview)) . ' <span class="read-more-link">Read More ▾</span></div>';
                                    echo '<div class="news-content-full" style="display: none;">' . nl2br(htmlspecialchars($content)) . ' <span class="read-less-link">Show Less ▴</span></div>';
                                    // Admin controls: Edit / Delete
                                    echo '<div style="margin-top:8px; display:flex; gap:8px;">';
                                    echo '<button type="button" class="btn" onclick="toggleEditForm(' . intval($news['id']) . ')">Edit</button>';
                                    echo '<form method="POST" onsubmit="return confirm(\'Are you sure you want to delete this announcement?\')" style="display:inline;">';
                                    echo '<input type="hidden" name="delete_news" value="1">';
                                    echo '<input type="hidden" name="news_id" value="' . intval($news['id']) . '">';
                                    echo '<button type="submit" class="btn btn-danger">Delete</button>';
                                    echo '</form>';
                                    echo '</div>';
                                    // Hidden edit form
                                    echo '<div id="edit-form-' . intval($news['id']) . '" style="display:none; margin-top:12px;">';
                                    echo '<form method="POST">';
                                    echo '<input type="hidden" name="edit_news" value="1">';
                                    echo '<input type="hidden" name="news_id" value="' . intval($news['id']) . '">';
                                    echo '<div class="form-group"><label>Title</label><input type="text" name="title" value="' . htmlspecialchars($news['title']) . '" required></div>';
                                    echo '<div class="form-group"><label>Category</label><select name="category">';
                                    $selGeneral = $news['category'] === 'General' ? 'selected' : '';
                                    $selStaff = $news['category'] === 'Staff Announcement' ? 'selected' : '';
                                    echo '<option value="General" ' . $selGeneral . '>General News</option>';
                                    echo '<option value="Staff Announcement" ' . $selStaff . '>Staff Announcement</option>';
                                    echo '</select></div>';
                                    echo '<div class="form-group"><label>Content</label><textarea name="content" rows="3" required>' . htmlspecialchars($news['content']) . '</textarea></div>';
                                    echo '<div style="display:flex; gap:8px;"><button type="submit" class="btn btn-primary">Save</button><button type="button" class="btn" onclick="toggleEditForm(' . intval($news['id']) . ')">Cancel</button></div>';
                                    echo '</form>';
                                    echo '</div>';
                                    echo '</div>';
                                } else {
                                    echo '<div class="news-item">';
                                    echo '<h3>' . htmlspecialchars($news['title']) . '</h3>';
                                    echo '<p class="news-date">' . date('M d, Y', strtotime($news['created_at'])) . '</p>';
                                    echo '<p>' . nl2br(htmlspecialchars($content)) . '</p>';
                                    // Admin controls for short items
                                    echo '<div style="margin-top:8px; display:flex; gap:8px;">';
                                    echo '<button type="button" class="btn" onclick="toggleEditForm(' . intval($news['id']) . ')">Edit</button>';
                                    echo '<form method="POST" onsubmit="return confirm(\'Are you sure you want to delete this announcement?\')" style="display:inline;">';
                                    echo '<input type="hidden" name="delete_news" value="1">';
                                    echo '<input type="hidden" name="news_id" value="' . intval($news['id']) . '">';
                                    echo '<button type="submit" class="btn btn-danger">Delete</button>';
                                    echo '</form>';
                                    echo '</div>';
                                    // Hidden edit form
                                    echo '<div id="edit-form-' . intval($news['id']) . '" style="display:none; margin-top:12px;">';
                                    echo '<form method="POST">';
                                    echo '<input type="hidden" name="edit_news" value="1">';
                                    echo '<input type="hidden" name="news_id" value="' . intval($news['id']) . '">';
                                    echo '<div class="form-group"><label>Title</label><input type="text" name="title" value="' . htmlspecialchars($news['title']) . '" required></div>';
                                    echo '<div class="form-group"><label>Category</label><select name="category">';
                                    $selGeneral = $news['category'] === 'General' ? 'selected' : '';
                                    $selStaff = $news['category'] === 'Staff Announcement' ? 'selected' : '';
                                    echo '<option value="General" ' . $selGeneral . '>General News</option>';
                                    echo '<option value="Staff Announcement" ' . $selStaff . '>Staff Announcement</option>';
                                    echo '</select></div>';
                                    echo '<div class="form-group"><label>Content</label><textarea name="content" rows="3" required>' . htmlspecialchars($news['content']) . '</textarea></div>';
                                    echo '<div style="display:flex; gap:8px;"><button type="submit" class="btn btn-primary">Save</button><button type="button" class="btn" onclick="toggleEditForm(' . intval($news['id']) . ')">Cancel</button></div>';
                                    echo '</form>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                            }
                        } else {
                            echo '<p style="padding: 15px 0; color: #7f8c8d;">No general news announcements.</p>';
                        }
                        ?>
                    </div>

                    <div id="staff-news" style="display: none;">
                        <?php
                        $news_query = "SELECT * FROM news WHERE category = 'Staff Announcement' AND title NOT ILIKE '%silver%' ORDER BY created_at DESC LIMIT 5";
                        $news_stmt = $db->query($news_query);
                        
                        if ($news_stmt->rowCount() > 0) {
                            while ($news = $news_stmt->fetch(PDO::FETCH_ASSOC)) {
                                $content = $news['content'];
                                if (strlen($content) > 100) {
                                    $preview = substr($content, 0, 90) . '...';
                                    echo '<div class="news-item toggleable" style="border-left: 4px solid #4CAF50; padding-left: 15px;">';
                                    echo '<h3>' . htmlspecialchars($news['title']) . '</h3>';
                                    echo '<p class="news-date"><span class="badge badge-success">New Staff</span> ' . date('M d, Y', strtotime($news['created_at'])) . '</p>';
                                    echo '<div class="news-content-preview">' . nl2br(htmlspecialchars($preview)) . ' <span class="read-more-link">Read More ▾</span></div>';
                                    echo '<div class="news-content-full" style="display: none;">' . nl2br(htmlspecialchars($content)) . ' <span class="read-less-link">Show Less ▴</span></div>';
                                    // Admin controls
                                    echo '<div style="margin-top:8px; display:flex; gap:8px;">';
                                    echo '<button type="button" class="btn" onclick="toggleEditForm(' . intval($news['id']) . ')">Edit</button>';
                                    echo '<form method="POST" onsubmit="return confirm(\'Are you sure you want to delete this announcement?\')" style="display:inline;">';
                                    echo '<input type="hidden" name="delete_news" value="1">';
                                    echo '<input type="hidden" name="news_id" value="' . intval($news['id']) . '">';
                                    echo '<button type="submit" class="btn btn-danger">Delete</button>';
                                    echo '</form>';
                                    echo '</div>';
                                    // Hidden edit form
                                    echo '<div id="edit-form-' . intval($news['id']) . '" style="display:none; margin-top:12px;">';
                                    echo '<form method="POST">';
                                    echo '<input type="hidden" name="edit_news" value="1">';
                                    echo '<input type="hidden" name="news_id" value="' . intval($news['id']) . '">';
                                    echo '<div class="form-group"><label>Title</label><input type="text" name="title" value="' . htmlspecialchars($news['title']) . '" required></div>';
                                    echo '<div class="form-group"><label>Category</label><select name="category">';
                                    $selGeneral = $news['category'] === 'General' ? 'selected' : '';
                                    $selStaff = $news['category'] === 'Staff Announcement' ? 'selected' : '';
                                    echo '<option value="General" ' . $selGeneral . '>General News</option>';
                                    echo '<option value="Staff Announcement" ' . $selStaff . '>Staff Announcement</option>';
                                    echo '</select></div>';
                                    echo '<div class="form-group"><label>Content</label><textarea name="content" rows="3" required>' . htmlspecialchars($news['content']) . '</textarea></div>';
                                    echo '<div style="display:flex; gap:8px;"><button type="submit" class="btn btn-primary">Save</button><button type="button" class="btn" onclick="toggleEditForm(' . intval($news['id']) . ')">Cancel</button></div>';
                                    echo '</form>';
                                    echo '</div>';
                                    echo '</div>';
                                } else {
                                    echo '<div class="news-item" style="border-left: 4px solid #4CAF50; padding-left: 15px;">';
                                    echo '<h3>' . htmlspecialchars($news['title']) . '</h3>';
                                    echo '<p class="news-date"><span class="badge badge-success">New Staff</span> ' . date('M d, Y', strtotime($news['created_at'])) . '</p>';
                                    echo '<p>' . nl2br(htmlspecialchars($content)) . '</p>';
                                    // Admin controls for short items
                                    echo '<div style="margin-top:8px; display:flex; gap:8px;">';
                                    echo '<button type="button" class="btn" onclick="toggleEditForm(' . intval($news['id']) . ')">Edit</button>';
                                    echo '<form method="POST" onsubmit="return confirm(\'Are you sure you want to delete this announcement?\')" style="display:inline;">';
                                    echo '<input type="hidden" name="delete_news" value="1">';
                                    echo '<input type="hidden" name="news_id" value="' . intval($news['id']) . '">';
                                    echo '<button type="submit" class="btn btn-danger">Delete</button>';
                                    echo '</form>';
                                    echo '</div>';
                                    // Hidden edit form
                                    echo '<div id="edit-form-' . intval($news['id']) . '" style="display:none; margin-top:12px;">';
                                    echo '<form method="POST">';
                                    echo '<input type="hidden" name="edit_news" value="1">';
                                    echo '<input type="hidden" name="news_id" value="' . intval($news['id']) . '">';
                                    echo '<div class="form-group"><label>Title</label><input type="text" name="title" value="' . htmlspecialchars($news['title']) . '" required></div>';
                                    echo '<div class="form-group"><label>Category</label><select name="category">';
                                    $selGeneral = $news['category'] === 'General' ? 'selected' : '';
                                    $selStaff = $news['category'] === 'Staff Announcement' ? 'selected' : '';
                                    echo '<option value="General" ' . $selGeneral . '>General News</option>';
                                    echo '<option value="Staff Announcement" ' . $selStaff . '>Staff Announcement</option>';
                                    echo '</select></div>';
                                    echo '<div class="form-group"><label>Content</label><textarea name="content" rows="3" required>' . htmlspecialchars($news['content']) . '</textarea></div>';
                                    echo '<div style="display:flex; gap:8px;"><button type="submit" class="btn btn-primary">Save</button><button type="button" class="btn" onclick="toggleEditForm(' . intval($news['id']) . ')">Cancel</button></div>';
                                    echo '</form>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                            }
                        } else {
                            echo '<p style="padding: 15px 0; color: #7f8c8d;">No new staff announcements.</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Staff Contacts Section -->
            <div class="staff-section">
                <h2>Staff Contacts</h2>
                <div class="scrollable-panel" style="margin-top: 15px;">
                    <?php
                    $staff_query = "SELECT full_name, email, phone, subject_specialization FROM teachers ORDER BY full_name";
                    $staff_stmt = $db->query($staff_query);
                    
                    if ($staff_stmt->rowCount() > 0) {
                        echo '<div class="compact-staff-list">';
                        while ($staff = $staff_stmt->fetch(PDO::FETCH_ASSOC)) {
                            $initial = strtoupper(substr($staff['full_name'], 0, 1));
                            echo '<div class="compact-staff-card">';
                            echo '  <div class="compact-staff-avatar">' . htmlspecialchars($initial) . '</div>';
                            echo '  <div class="compact-staff-details">';
                            echo '      <div class="compact-staff-name">' . htmlspecialchars($staff['full_name']) . '</div>';
                            echo '      <div class="compact-staff-subtext">' . htmlspecialchars($staff['subject_specialization'] ?: 'General') . '</div>';
                            echo '      <div class="compact-staff-contact">';
                            if ($staff['phone']) {
                                echo '      <span>Phone: ' . htmlspecialchars($staff['phone']) . '</span>';
                            }
                            if ($staff['email']) {
                                echo '      <span>Email: ' . htmlspecialchars($staff['email']) . '</span>';
                            }
                            echo '      </div>';
                            echo '  </div>';
                            echo '</div>';
                        }
                        echo '</div>';
                    } else {
                        echo '<p style="color: #7f8c8d;">No staff contacts available.</p>';
                    }
                    ?>
                </div>
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

    function toggleNewsItem(element) {
        const preview = element.querySelector('.news-content-preview');
        const full = element.querySelector('.news-content-full');
        const isCollapsed = full.style.display === 'none';
        
        if (isCollapsed) {
            full.style.display = 'block';
            preview.style.display = 'none';
            element.classList.add('expanded');
        } else {
            full.style.display = 'none';
            preview.style.display = 'block';
            element.classList.remove('expanded');
        }
    }

    function toggleEditForm(id) {
        var el = document.getElementById('edit-form-' + id);
        if (!el) return;
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
        if (el.style.display === 'block') el.scrollIntoView({ behavior: 'smooth' });
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