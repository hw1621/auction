<?php
/**
 * Database Configuration File
 * This file contains the database connection settings for AWS RDS MySQL
 */

// Database connection parameters
define('DB_HOST', 'auction.c78qcak427mc.eu-north-1.rds.amazonaws.com');
define('DB_PORT', '3306');
define('DB_NAME', 'auction'); // You may need to change this to your actual database name
define('DB_USER', 'admin'); // Database username here
define('DB_PASS', 'useradmin123'); // Database password here

// Global variable to store the database connection (singleton pattern)
$GLOBALS['db_connection'] = null;

/**
 * Get database connection using singleton pattern
 * This ensures only one connection is created per request
 * You can call this function multiple times, it will reuse the same connection
 * 
 * @return mysqli Returns mysqli connection object
 */
function get_database_connection() {
    // If connection already exists and is still valid, return it
    if ($GLOBALS['db_connection'] !== null && $GLOBALS['db_connection']->ping()) {
        return $GLOBALS['db_connection'];
    }
    
    // Create new connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4 for proper character encoding
    $conn->set_charset("utf8mb4");
    
    // Store connection in global variable for reuse
    $GLOBALS['db_connection'] = $conn;
    
    return $conn;
}

/**
 * Close database connection
 * Note: In PHP, connections are automatically closed when the script ends,
 * but it's good practice to close them explicitly when done
 */
function close_database_connection() {
    if ($GLOBALS['db_connection'] !== null) {
        $GLOBALS['db_connection']->close();
        $GLOBALS['db_connection'] = null;
    }
}

?>

