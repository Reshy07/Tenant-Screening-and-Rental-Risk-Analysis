<?php
// ============================================================
// Database Configuration
// Tenant Screening and Rental Risk Analysis System
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Change to your MySQL username
define('DB_PASS', 'root');           // Change to your MySQL password
define('DB_NAME', 'rental_risk_db');

// Base path for uploads (relative to project root)
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', 'uploads/');

// Python executable path (adjust if needed for your system)
// Windows XAMPP: 'python' or 'py'
// Linux/Mac: 'python3'
define('PYTHON_PATH', 'python');
define('PYTHON_SCRIPTS_DIR', __DIR__ . '/../python/');

function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('<div style="color:red;padding:20px;font-family:Arial">
                <h3>Database Connection Failed</h3>
                <p>' . htmlspecialchars($conn->connect_error) . '</p>
                <p>Please check your database settings in <code>php/config.php</code></p>
            </div>');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
?>
