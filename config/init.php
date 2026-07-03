<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session_handler.php';
require_once __DIR__ . '/supabase_storage.php';

$database = new Database();
$db = $database->getConnection();

// Configure strict session settings
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);

session_set_save_handler(new PdoSessionHandler($db), true);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
