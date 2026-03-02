<?php
require_once __DIR__ . '/../config/database.php';

class Logger {
    public static function log($user_id, $action, $details = '') {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $stmt->execute([$user_id, $action, $details, $ip]);
            
            return true;
        } catch (Exception $e) {
            // Silently fail logging to not disrupt main flow, or log to file
            error_log("Logging failed: " . $e->getMessage());
            return false;
        }
    }
}
?>
