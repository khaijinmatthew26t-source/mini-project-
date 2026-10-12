<?php
// config/db.php - Database connection and session setup

// Database connection settings: host, database name, MySQL username and password.
// Using 127.0.0.1 instead of localhost avoids socket path errors.
$host = '127.0.0.1';
$dbname = 'enrollment_system';
$dbuser = 'root';
$dbpass = '080603hp1559'; 

// Start the session if it is not started yet.
// The session remembers who is logged in (user_id, name, role).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connect to MySQL with PDO; if it fails, execution jumps to the catch block below.
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbuser,
        $dbpass,
        [
            // Throw an exception when an SQL error happens, so problems are easy to spot.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Return rows as associative arrays, e.g. $row['name'].
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use real prepared statements to protect against SQL injection.
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
// Connection failed: send a 500 error and stop the script.
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Check config/db.php and make sure MySQL is running.');
}
