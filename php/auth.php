<?php
/**
 * Authentication System
 * ACCESS (Automated Community and Citizen E-Records Service System)
 * PWD Affair Office - LGU Malasiqui
 */

session_start();
require_once 'dbconnection.php';
require_once 'security.php';

/**
 * User login function
 */
function loginUser($username, $password) {
    try {
        // Get user with role information
        $sql = "SELECT u.*, ur.role_name, ur.permissions 
                FROM users u 
                JOIN user_roles ur ON u.role_id = ur.role_id 
                WHERE u.username = ? AND u.status = 'active'";
        
        $user = fetchOne($sql, [$username]);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Update last login
            $updateSql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
            executeQuery($updateSql, [$user['user_id']]);
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['sector'] = $user['sector'];
            $_SESSION['permissions'] = json_decode($user['permissions'], true);
            $_SESSION['login_time'] = time();
            
            // Log activity
            logActivity($user['user_id'], 'login', 'users', $user['user_id']);
            
            return [
                'success' => true,
                'user' => $user,
                'redirect' => getDashboardUrl($user['role_name'])
            ];
        } else {
            // Log failed login attempt
            logActivity(null, 'failed_login', null, null, ['username' => $username]);
            return [
                'success' => false,
                'message' => 'Invalid username or password'
            ];
        }
    } catch (Exception $e) {
        error_log("Login Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'System error. Please try again.'
        ];
    }
}

/**
 * User logout function
 */
function logoutUser() {
    if (isset($_SESSION['user_id'])) {
        // Update last logout
        $sql = "UPDATE users SET last_logout = NOW() WHERE user_id = ?";
        executeQuery($sql, [$_SESSION['user_id']]);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id']);
        
        // Clear session
        session_destroy();
    }
    
    return ['success' => true];
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user information
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role_id' => $_SESSION['role_id'],
        'role_name' => $_SESSION['role_name'],
        'first_name' => $_SESSION['first_name'],
        'last_name' => $_SESSION['last_name'],
        'sector' => $_SESSION['sector'],
        'permissions' => $_SESSION['permissions']
    ];
}

/**
 * Check user permissions
 */
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $permissions = $_SESSION['permissions'];
    
    // Super admin has all permissions
    if (in_array('all', $permissions)) {
        return true;
    }
    
    return in_array($permission, $permissions);
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /index.php');
        exit;
    }
}

/**
 * Require specific permission
 */
function requirePermission($permission) {
    requireLogin();
    
    if (!hasPermission($permission)) {
        header('HTTP/1.1 403 Forbidden');
        include '../errors/403.php';
        exit;
    }
}

/**
 * Get dashboard URL based on role
 */
function getDashboardUrl($role_name) {
    switch ($role_name) {
        case 'super_admin':
            return '/admin/dashboard.php';
        case 'sub_admin_education':
        case 'sub_admin_healthcare':
        case 'sub_admin_employment':
        case 'sub_admin_emergency':
            return '/subadmin/dashboard.php';
        case 'client':
            return '/client/dashboard.php';
        default:
            return '/index.php';
    }
}

/**
 * Register new user (client)
 */
function registerClient($data, $files = []) {
    try {
        beginTransaction();
        
        // Validate required fields
        $requiredFields = ['username', 'email', 'password', 'first_name', 'last_name', 'barangay', 'disability_type'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
        
        // Check if username or email already exists
        $checkSql = "SELECT COUNT(*) FROM users WHERE username = ? OR email = ?";
        $count = fetchCount($checkSql, [$data['username'], $data['email']]);
        
        if ($count > 0) {
            throw new Exception("Username or email already exists");
        }
        
        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $userSql = "INSERT INTO users (username, email, password_hash, role_id, status, first_name, last_name, contact_number, address) 
                    VALUES (?, ?, ?, 6, 'pending', ?, ?, ?, ?)";
        
        $userId = insertRecord($userSql, [
            $data['username'],
            $data['email'],
            $passwordHash,
            $data['first_name'],
            $data['last_name'],
            $data['contact_number'] ?? '',
            $data['address'] ?? ''
        ]);
        
        // Insert citizen record
        $citizenSql = "INSERT INTO citizen_records (user_id, first_name, middle_name, last_name, date_of_birth, gender, 
                       civil_status, barangay, contact_number, email, disability_type, disability_cause, disability_since) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $citizenId = insertRecord($citizenSql, [
            $userId,
            $data['first_name'],
            $data['middle_name'] ?? '',
            $data['last_name'],
            $data['date_of_birth'] ?? null,
            $data['gender'] ?? '',
            $data['civil_status'] ?? '',
            $data['barangay'],
            $data['contact_number'] ?? '',
            $data['email'],
            $data['disability_type'],
            $data['disability_cause'] ?? '',
            $data['disability_since'] ?? null
        ]);
        
        // Handle file uploads
        if (!empty($files)) {
            foreach ($files as $docType => $file) {
                if ($file['error'] === UPLOAD_ERR_OK) {
                    uploadDocument($userId, $citizenId, $docType, $file);
                }
            }
        }
        
        commitTransaction();
        
        // Send notification to admin
        createNotification(1, 'application_status', 'New Client Registration', 
                          "New PWD client registration from {$data['first_name']} {$data['last_name']}", 
                          $citizenId, 'citizen_records');
        
        return [
            'success' => true,
            'message' => 'Registration successful. Please wait for admin verification.',
            'user_id' => $userId
        ];
        
    } catch (Exception $e) {
        rollbackTransaction();
        error_log("Registration Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Register new sub-admin
 */
function registerSubAdmin($data, $files = []) {
    try {
        beginTransaction();
        
        // Validate required fields
        $requiredFields = ['username', 'email', 'password', 'first_name', 'last_name', 'sector', 'organization_type', 'contact_person'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
        
        // Check if username or email already exists
        $checkSql = "SELECT COUNT(*) FROM users WHERE username = ? OR email = ?";
        $count = fetchCount($checkSql, [$data['username'], $data['email']]);
        
        if ($count > 0) {
            throw new Exception("Username or email already exists");
        }
        
        // Determine role based on sector
        $roleMap = [
            'education' => 2,
            'healthcare' => 3,
            'employment' => 4,
            'emergency' => 5
        ];
        
        $roleId = $roleMap[strtolower($data['sector'])] ?? 2;
        
        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $userSql = "INSERT INTO users (username, email, password_hash, role_id, status, first_name, last_name, 
                    contact_number, address, sector, organization_type, contact_person) 
                    VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?)";
        
        $userId = insertRecord($userSql, [
            $data['username'],
            $data['email'],
            $passwordHash,
            $roleId,
            $data['first_name'],
            $data['last_name'],
            $data['contact_number'] ?? '',
            $data['address'] ?? '',
            $data['sector'],
            $data['organization_type'],
            $data['contact_person']
        ]);
        
        // Handle file uploads
        if (!empty($files)) {
            foreach ($files as $docType => $file) {
                if ($file['error'] === UPLOAD_ERR_OK) {
                    uploadDocument($userId, null, $docType, $file);
                }
            }
        }
        
        commitTransaction();
        
        // Send notification to admin
        createNotification(1, 'application_status', 'New Sub-Admin Registration', 
                          "New sub-admin registration for {$data['sector']} sector from {$data['first_name']} {$data['last_name']}", 
                          $userId, 'users');
        
        return [
            'success' => true,
            'message' => 'Registration successful. Please wait for admin verification.',
            'user_id' => $userId
        ];
        
    } catch (Exception $e) {
        rollbackTransaction();
        error_log("Sub-Admin Registration Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>