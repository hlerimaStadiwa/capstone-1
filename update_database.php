<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "Connected successfully to the database.\n";
    
    // Create grade_update_requests table
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS grade_update_requests (
            id SERIAL PRIMARY KEY,
            teacher_id INT NOT NULL REFERENCES teachers(id) ON DELETE CASCADE,
            student_id INT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
            subject_name VARCHAR(100) NOT NULL,
            term VARCHAR(50) NOT NULL,
            new_score DECIMAL(5,2) NOT NULL,
            new_comments TEXT NULL,
            reason TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL
        )
    ";
    
    $db->exec($create_table_sql);
    echo "Checked/created grade_update_requests table.\n";
    
    echo "Database upgrade completed successfully.\n";
    
} catch (Exception $e) {
    echo "ERROR during database upgrade: " . $e->getMessage() . "\n";
}
