<?php
/**
 * Database Connection Configuration
 * ACCESS (Automated Community and Citizen E-Records Service System)
 * PWD Affair Office - LGU Malasiqui
 */

// Database configuration
$host = 'localhost';
$dbname = 'access_pwd_system';
$username = 'root';
$password = '';

// PDO connection options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, $options);
    
    // Set timezone
    $pdo->exec("SET time_zone = '+08:00'"); // Philippines timezone
    
} catch (PDOException $e) {
    // Log error and show user-friendly message
    error_log("Database Connection Error: " . $e->getMessage());
    
    // In production, don't show detailed error messages
    if (defined('DEBUG') && DEBUG) {
        die("Database Connection Error: " . $e->getMessage());
    } else {
        die("Database connection failed. Please contact system administrator.");
    }
}

/**
 * Function to get database connection
 */
function getDbConnection() {
    global $pdo;
    return $pdo;
}

/**
 * Function to execute prepared statements safely
 */
function executeQuery($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query Error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Function to get single row
 */
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * Function to get multiple rows
 */
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Function to get count
 */
function fetchCount($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchColumn();
}

/**
 * Function to insert and return last insert ID
 */
function insertRecord($sql, $params = []) {
    global $pdo;
    $stmt = executeQuery($sql, $params);
    return $pdo->lastInsertId();
}

/**
 * Function to start transaction
 */
function beginTransaction() {
    global $pdo;
    return $pdo->beginTransaction();
}

/**
 * Function to commit transaction
 */
function commitTransaction() {
    global $pdo;
    return $pdo->commit();
}

/**
 * Function to rollback transaction
 */
function rollbackTransaction() {
    global $pdo;
    return $pdo->rollback();
}

/**
 * Function to close connection (optional - PDO closes automatically)
 */
function closeConnection() {
    global $pdo;
    $pdo = null;
}
?>