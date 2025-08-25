<?php
/**
 * API Endpoints
 * ACCESS (Automated Community and Citizen E-Records Service System)
 * PWD Affair Office - LGU Malasiqui
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
require_once 'dbconnection.php';
require_once 'auth.php';
require_once 'security.php';

// Get request method and endpoint
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

// Basic authentication check for API
function requireApiAuth() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Please log in']);
        exit;
    }
}

// Handle different endpoints
switch ($endpoint) {
    
    case 'dashboard-refresh':
        requireApiAuth();
        handleDashboardRefresh();
        break;
        
    case 'search':
        requireApiAuth();
        handleGlobalSearch();
        break;
        
    case 'notifications':
        requireApiAuth();
        handleNotifications();
        break;
        
    case 'applications':
        requireApiAuth();
        handleApplications();
        break;
        
    case 'citizens':
        requireApiAuth();
        handleCitizens();
        break;
        
    case 'upload':
        requireApiAuth();
        handleFileUpload();
        break;
        
    case 'renewal-check':
        requireApiAuth();
        handleRenewalCheck();
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not Found', 'message' => 'Endpoint not found']);
        break;
}

/**
 * Handle dashboard refresh
 */
function handleDashboardRefresh() {
    try {
        // Get updated dashboard metrics
        $metrics = fetchOne("SELECT * FROM dashboard_metrics");
        
        // Get current user notification count
        $currentUser = getCurrentUser();
        $notificationCount = fetchCount(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", 
            [$currentUser['user_id']]
        );
        
        // Get recent activity
        $recentActivity = fetchAll("
            SELECT 
                al.action,
                al.created_at,
                CONCAT(u.first_name, ' ', u.last_name) as user_name
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.user_id
            ORDER BY al.created_at DESC
            LIMIT 5
        ");
        
        echo json_encode([
            'success' => true,
            'metrics' => $metrics,
            'notification_count' => $notificationCount,
            'recent_activity' => $recentActivity,
            'timestamp' => time()
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server Error', 'message' => $e->getMessage()]);
    }
}

/**
 * Handle global search
 */
function handleGlobalSearch() {
    $query = sanitizeInput($_GET['q'] ?? '');
    $limit = min((int)($_GET['limit'] ?? 20), 100); // Max 100 results
    
    if (strlen($query) < 3) {
        echo json_encode(['error' => 'Query too short', 'message' => 'Search query must be at least 3 characters']);
        return;
    }
    
    try {
        $results = [];
        
        // Search citizens
        if (hasPermission('all') || hasPermission('citizen_view')) {
            $citizens = fetchAll("
                SELECT 
                    citizen_id as id,
                    'citizen' as type,
                    CONCAT(first_name, ' ', last_name) as title,
                    CONCAT('PWD ID: ', COALESCE(pwd_id_number, 'Not assigned')) as subtitle,
                    barangay,
                    verification_status as status
                FROM citizen_records 
                WHERE (first_name LIKE ? OR last_name LIKE ? OR pwd_id_number LIKE ?)
                LIMIT ?
            ", ["%$query%", "%$query%", "%$query%", $limit]);
            
            $results = array_merge($results, $citizens);
        }
        
        // Search applications
        $applications = fetchAll("
            SELECT 
                a.application_id as id,
                'application' as type,
                CONCAT('Application: ', s.service_name) as title,
                CONCAT('Ref: ', a.reference_number) as subtitle,
                CONCAT(c.first_name, ' ', c.last_name) as applicant,
                a.status
            FROM applications a
            JOIN services s ON a.service_id = s.service_id
            JOIN citizen_records c ON a.citizen_id = c.citizen_id
            WHERE (a.reference_number LIKE ? OR s.service_name LIKE ?)
            LIMIT ?
        ", ["%$query%", "%$query%", $limit]);
        
        $results = array_merge($results, $applications);
        
        // Search services
        $services = fetchAll("
            SELECT 
                s.service_id as id,
                'service' as type,
                s.service_name as title,
                sect.sector_name as subtitle,
                s.description,
                s.status
            FROM services s
            JOIN sectors sect ON s.sector_id = sect.sector_id
            WHERE (s.service_name LIKE ? OR s.description LIKE ?)
            LIMIT ?
        ", ["%$query%", "%$query%", $limit]);
        
        $results = array_merge($results, $services);
        
        echo json_encode([
            'success' => true,
            'query' => $query,
            'results' => array_slice($results, 0, $limit),
            'total' => count($results)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Search Error', 'message' => $e->getMessage()]);
    }
}

/**
 * Handle notifications
 */
function handleNotifications() {
    $currentUser = getCurrentUser();
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            try {
                $notifications = fetchAll("
                    SELECT * FROM notifications 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 50
                ", [$currentUser['user_id']]);
                
                echo json_encode([
                    'success' => true,
                    'notifications' => $notifications
                ]);
                
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database Error', 'message' => $e->getMessage()]);
            }
            break;
            
        case 'PUT':
            // Mark notification as read
            $notificationId = $_GET['id'] ?? null;
            
            if (!$notificationId) {
                http_response_code(400);
                echo json_encode(['error' => 'Bad Request', 'message' => 'Notification ID required']);
                return;
            }
            
            try {
                executeQuery("
                    UPDATE notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE notification_id = ? AND user_id = ?
                ", [$notificationId, $currentUser['user_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
                
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database Error', 'message' => $e->getMessage()]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
    }
}

/**
 * Handle applications
 */
function handleApplications() {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetApplications();
            break;
            
        case 'POST':
            handleCreateApplication();
            break;
            
        case 'PUT':
            handleUpdateApplication();
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
    }
}

function handleGetApplications() {
    $currentUser = getCurrentUser();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $filters = [];
    $params = [];
    
    // Apply filters
    if (!empty($_GET['status'])) {
        $filters[] = "a.status = ?";
        $params[] = $_GET['status'];
    }
    
    if (!empty($_GET['sector'])) {
        $filters[] = "sect.sector_name = ?";
        $params[] = $_GET['sector'];
    }
    
    if (!empty($_GET['date_from'])) {
        $filters[] = "DATE(a.submitted_at) >= ?";
        $params[] = $_GET['date_from'];
    }
    
    if (!empty($_GET['date_to'])) {
        $filters[] = "DATE(a.submitted_at) <= ?";
        $params[] = $_GET['date_to'];
    }
    
    // Role-based filtering
    if ($currentUser['role_name'] !== 'super_admin') {
        $filters[] = "sect.sector_name = ?";
        $params[] = $currentUser['sector'];
    }
    
    $whereClause = !empty($filters) ? "WHERE " . implode(" AND ", $filters) : "";
    
    try {
        // Get applications
        $sql = "
            SELECT 
                a.*,
                s.service_name,
                sect.sector_name,
                CONCAT(c.first_name, ' ', c.last_name) as citizen_name,
                c.pwd_id_number,
                CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name
            FROM applications a
            JOIN services s ON a.service_id = s.service_id
            JOIN sectors sect ON s.sector_id = sect.sector_id
            JOIN citizen_records c ON a.citizen_id = c.citizen_id
            LEFT JOIN users u ON a.assigned_to = u.user_id
            $whereClause
            ORDER BY a.submitted_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $applications = fetchAll($sql, $params);
        
        // Get total count
        $countSql = "
            SELECT COUNT(*) 
            FROM applications a
            JOIN services s ON a.service_id = s.service_id
            JOIN sectors sect ON s.sector_id = sect.sector_id
            JOIN citizen_records c ON a.citizen_id = c.citizen_id
            $whereClause
        ";
        
        $totalCount = fetchCount($countSql, array_slice($params, 0, -2));
        
        echo json_encode([
            'success' => true,
            'applications' => $applications,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $limit)
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database Error', 'message' => $e->getMessage()]);
    }
}

function handleCreateApplication() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Bad Request', 'message' => 'Invalid JSON']);
        return;
    }
    
    try {
        beginTransaction();
        
        // Generate reference number
        $refNumber = generateReferenceNumber('APP');
        
        // Calculate SLA due date (based on service processing time)
        $service = fetchOne("SELECT processing_time_days FROM services WHERE service_id = ?", [$input['service_id']]);
        $slaDueDate = date('Y-m-d', strtotime("+{$service['processing_time_days']} days"));
        
        // Insert application
        $sql = "
            INSERT INTO applications (
                reference_number, citizen_id, service_id, application_type, 
                application_data, status, priority, sla_due_date
            ) VALUES (?, ?, ?, ?, ?, 'submitted', ?, ?)
        ";
        
        $applicationId = insertRecord($sql, [
            $refNumber,
            $input['citizen_id'],
            $input['service_id'],
            $input['application_type'] ?? 'new',
            json_encode($input['application_data'] ?? []),
            $input['priority'] ?? 'normal',
            $slaDueDate
        ]);
        
        commitTransaction();
        
        // Send notification
        createNotification(
            1, // Super admin
            'application_status',
            'New Application Submitted',
            "New application $refNumber has been submitted",
            $applicationId,
            'applications'
        );
        
        echo json_encode([
            'success' => true,
            'application_id' => $applicationId,
            'reference_number' => $refNumber,
            'message' => 'Application submitted successfully'
        ]);
        
    } catch (Exception $e) {
        rollbackTransaction();
        http_response_code(500);
        echo json_encode(['error' => 'Database Error', 'message' => $e->getMessage()]);
    }
}

function handleUpdateApplication() {
    $applicationId = $_GET['id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$applicationId || !$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Bad Request', 'message' => 'Application ID and data required']);
        return;
    }
    
    try {
        $currentUser = getCurrentUser();
        
        // Get current application
        $currentApp = fetchOne("SELECT * FROM applications WHERE application_id = ?", [$applicationId]);
        
        if (!$currentApp) {
            http_response_code(404);
            echo json_encode(['error' => 'Not Found', 'message' => 'Application not found']);
            return;
        }
        
        // Update application
        $updateFields = [];
        $params = [];
        
        if (isset($input['status'])) {
            $updateFields[] = "status = ?";
            $params[] = $input['status'];
            
            if ($input['status'] === 'approved' || $input['status'] === 'rejected') {
                $updateFields[] = "reviewed_at = NOW()";
            }
            
            if ($input['status'] === 'completed') {
                $updateFields[] = "completed_at = NOW()";
            }
        }
        
        if (isset($input['assigned_to'])) {
            $updateFields[] = "assigned_to = ?";
            $params[] = $input['assigned_to'];
        }
        
        if (isset($input['reviewer_notes'])) {
            $updateFields[] = "reviewer_notes = ?";
            $params[] = $input['reviewer_notes'];
        }
        
        if (isset($input['rejection_reason'])) {
            $updateFields[] = "rejection_reason = ?";
            $params[] = $input['rejection_reason'];
        }
        
        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['error' => 'Bad Request', 'message' => 'No valid fields to update']);
            return;
        }
        
        $params[] = $applicationId;
        
        executeQuery(
            "UPDATE applications SET " . implode(", ", $updateFields) . " WHERE application_id = ?",
            $params
        );
        
        // Log activity
        logActivity($currentUser['user_id'], 'update_application', 'applications', $applicationId, 
                   ['status' => $currentApp['status']], ['status' => $input['status'] ?? null]);
        
        // Send notification to applicant
        if (isset($input['status'])) {
            $citizen = fetchOne("SELECT user_id FROM citizen_records WHERE citizen_id = ?", [$currentApp['citizen_id']]);
            
            if ($citizen && $citizen['user_id']) {
                createNotification(
                    $citizen['user_id'],
                    'application_status',
                    'Application Status Updated',
                    "Your application {$currentApp['reference_number']} has been " . $input['status'],
                    $applicationId,
                    'applications'
                );
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Application updated successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database Error', 'message' => $e->getMessage()]);
    }
}

/**
 * Handle renewal checks
 */
function handleRenewalCheck() {
    try {
        // Find PWD IDs that are due for renewal (within 1 month of expiry)
        $renewalDue = fetchAll("
            SELECT 
                cr.*,
                u.user_id,
                u.email,
                DATEDIFF(pwd_id_expiry_date, NOW()) as days_until_expiry
            FROM citizen_records cr
            LEFT JOIN users u ON cr.user_id = u.user_id
            WHERE cr.pwd_id_status = 'issued' 
            AND cr.pwd_id_expiry_date IS NOT NULL
            AND DATEDIFF(pwd_id_expiry_date, NOW()) <= 30
            AND DATEDIFF(pwd_id_expiry_date, NOW()) >= 0
        ");
        
        $notifications_sent = 0;
        
        foreach ($renewalDue as $citizen) {
            // Check if notification already sent recently
            $existingNotification = fetchOne("
                SELECT notification_id FROM notifications 
                WHERE user_id = ? AND type = 'id_renewal' 
                AND related_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ", [$citizen['user_id'], $citizen['citizen_id']]);
            
            if (!$existingNotification && $citizen['user_id']) {
                // Send renewal notification
                createNotification(
                    $citizen['user_id'],
                    'id_renewal',
                    'PWD ID Renewal Required',
                    "Your PWD ID will expire in {$citizen['days_until_expiry']} days. Please renew before expiry.",
                    $citizen['citizen_id'],
                    'citizen_records',
                    'high'
                );
                
                // Also notify admin
                createNotification(
                    1, // Super admin
                    'id_renewal',
                    'PWD ID Renewal Due',
                    "{$citizen['first_name']} {$citizen['last_name']}'s PWD ID expires in {$citizen['days_until_expiry']} days",
                    $citizen['citizen_id'],
                    'citizen_records',
                    'medium'
                );
                
                $notifications_sent++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'renewal_due_count' => count($renewalDue),
            'notifications_sent' => $notifications_sent,
            'renewal_list' => $renewalDue
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database Error', 'message' => $e->getMessage()]);
    }
}
?>